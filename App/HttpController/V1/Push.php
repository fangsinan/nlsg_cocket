<?php
namespace App\HttpController\V1;

use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\LiveCommentModel;
use App\Model\V1\LiveInfo;
use App\Model\V1\LiveLoginModel;
use App\Model\V1\ShieldUser;
use App\Services\V1\UserService;
use App\Lib\Common;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Config;

/**
 * 推送消息
 */
class Push extends Controller
{

    /**
     *
     * 进入直播间
    {
    "action": "Joinlive",
    "controller": "Push",
    "data": {
    "live_id": "16",
    "type": 0,
    "user_id": "228740"
    }
    }
    //打赏
    {
    controller: 'Push',
    action: 'Gift',
    data: {
    type: 4,
    user_id: user_id,
    live_id: live_id,
    gift_num: 数量,
    gift_class: 打赏type         /[礼物] 1  鲜花 1   2爱心 5.21   3书籍  18.88   4咖啡  36  5送花  6.66  6比心 8.88   7独角兽 18.88   8跑车  66.66   9飞机 88.88   10火箭 188.88      类型对应的价格

    }
    }

    //评论
    {
    controller: 'Push',
    action: 'Comment',
    data: {
    type: 2,
    content: '内容',
    user_id: user_id,
    live_id: live_id
    }
    }
     * */

    //进入直播间
    public function Joinlive()
    {
        $client = $this->caller ()->getClient ();
        $message = $this->caller ()->getArgs ();//获取所有参数

        $user_id = intval($message['user_id']);
        $live_id = intval($message['live_id']);
        $live_son_flag = $message['live_son_flag']?intval($message['live_son_flag']):0;

        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo ($live_id,$user_id);
        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $live_son_flag_num=0;
            if(!empty($live_son_flag)) {
                $redis_flag_key = Config::getInstance()->getConf('web.live_son_flag') . $live_id . '_' . $live_son_flag;
                $RedisObj = new Redis();
                $live_son_flag_num = intval($RedisObj->get($redis_flag_key));
                if(empty($live_son_flag_num)){
                    $LiveLogin = new LiveLoginModel();
                    $NumInfo=$LiveLogin->db->where('live_id',$live_id)->where('live_son_flag',$live_son_flag)->get(LiveLoginModel::$table,[0,1],'count(id) counts');
                    if(!empty($NumInfo[0]['counts'])) {
                        $live_son_flag_num = intval($NumInfo[0]['counts']);
                        $RedisObj->set($redis_flag_key,$live_son_flag_num+1,3600*5);
                    }
                }else {
                    $RedisObj->incr($redis_flag_key);
                    $RedisObj->expire($redis_flag_key, 3600 * 5); //有效期5天
                }
                $live_son_flag_num++;
            }
            
            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);
            $IMAGES_URL =Config::getInstance ()->getConf ('web.IMAGES_URL');
            $headimg = $UserInfo['result']['headimg'] ? $IMAGES_URL.$UserInfo['result']['headimg'] : 'wechat/head.png';
            $data = json_encode([
                'type' => 5,
                'content_text' => '进入直播间',
                'live_son_flag' => $live_son_flag,
                'live_son_flag_num' => $live_son_flag_num,
                'userinfo' => ['user_id' => $UserInfo['result']['id'],'level' => $UserInfo['result']['level'], 'nickname' => $UserInfo['result']['nickname'],'headimg'=> $headimg]]);

            $live_join=Config::getInstance()->getConf('web.live_join');
            $infoObj = new LiveInfo();
            $infoPid = $infoObj->db->where('id',$message['live_id'])->getOne($infoObj->tableName, 'live_pid,is_begin');
            $Info = $infoObj->db->where('id',$infoPid['live_pid'])->getOne('nlsg_live', 'is_join');

            // 异步推送
            TaskManager::async (function () use ($client, $data,$user_id,$live_id,$live_join,$Info,$live_son_flag) {

                if( $Info['is_join'] == 0) { //屏蔽加入直播间信息
                    $RedisObj = new Redis();
                    $RedisObj->rpush($live_join . $live_id, $data);
                }
                $time=time();
                $created_at=date('Y-m-d H:i:s',$time);
                $LiveLogin = new LiveLoginModel();
                $LiveLogin->add(LiveLoginModel::$table, ['user_id' => $user_id, 'live_id' => $live_id, 'ctime' => $time,'live_son_flag'=>$live_son_flag,'created_at'=>$created_at]);
            });
        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>5]);
            $server->push ($getfd, $data);
        }
    }

    //评论消息
    public function Comment()
    {

        $client = $this->caller()->getClient();
        $message=$this->caller()->getArgs();//获取所有参数

        if(empty($message['accessUserToken'])){$message['accessUserToken']=0;};
        if(empty($message['user_id'])){$message['user_id']=0;};
        if(empty($message['live_id'])){$message['live_id']=0;};

        $message['live_id']=intval($message['live_id']);
        $message['user_id']=intval($message['user_id']);

        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo($message['live_id'],$message['user_id']+0,$message['content'],$message['accessUserToken']);

        $infoObj = new LiveInfo();
        $infoPid = $infoObj->db->where('id',$message['live_id'])->getOne($infoObj->tableName, 'live_pid');
        $lupInfo = $infoObj->db->where('id',$infoPid['live_pid'])->getOne('nlsg_live', 'is_forb,helper');
        $admin_arr=explode('-',$lupInfo['helper']);
        if( $lupInfo['is_forb'] == 1 && !in_array($UserInfo['result']['username'],$admin_arr)){ //仅管理员评论
            return ;
        }
        //处理屏蔽一次，则相同直播间不推送评论
        $ShieldUserObj=new ShieldUser();
        $ShieldUserInfo=$ShieldUserObj->db->where('live_id',$message['live_id'])->where('user_id',$message['user_id']+0)->getOne($ShieldUserObj->tableName, 'id');
        if(!empty($ShieldUserInfo)){
            //已屏蔽过一次，直接终止
            return ;
        }

        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $live_id=$message['live_id'];
            $live_pid=$infoPid['live_pid'];
            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);
            $live_son_flag=$message['live_son_flag'];

            $content = $UserInfo['result']['content']; //入库内容信息 处理表情
            $data = json_encode(['type' => 2, 'content_text'=>$content,'live_son_flag' => $live_son_flag, 'userinfo' => ['user_id'=>$message['user_id'],
                'level' => $UserInfo['result']['level'],'nickname' => $UserInfo['result']['nickname']]]);

            $user_id=$UserInfo['result']['id'];

            $live_comment=Config::getInstance()->getConf('web.live_comment');
            $rk_comment=$message['content']; //入库信息不转义

            $ShieldKeyFlag=0;
            if(isset($UserInfo['result']['ShieldKeyFlag'])){
                $ShieldKeyFlag=$UserInfo['result']['ShieldKeyFlag'];
                if($ShieldKeyFlag==1){
                    //添加屏蔽记录
                    $ShieldUserObj->add($ShieldUserObj->tableName,[
                        'live_id'=>$message['live_id']+0,
                        'user_id'=>$message['user_id']+0,
                        'content'=>$rk_comment,
                        'created_at'=>date('Y-m-d H:i:s')
                    ]);
                    return ;
                }
            }

            // 异步推送
            TaskManager::async (function () use ($client, $data,$user_id,$content,$live_id,$live_comment,$live_pid,$rk_comment,$live_son_flag,$ShieldKeyFlag) {

                if($ShieldKeyFlag==0) { //1是有敏感词不发送
                    $RedisObj = new Redis();
                    $RedisObj->rpush($live_comment . $live_id, $data);

                    $LiveComment = new LiveCommentModel();
                    //此时的live_Id 用的是直播间id
                    $LiveComment->add(LiveCommentModel::$table,
                        ['live_id' => $live_pid, 'live_info_id' => $live_id, 'user_id' => $user_id, 'content' => $rk_comment, 'live_son_flag' => $live_son_flag, 'created_at' => date('Y-m-d H:i:s', time())]
                    );
                }
            });

        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>2]);
            $server->push ($getfd, $data);
        }
    }

    //送礼物
    public function Gift()
    {
        $client = $this->caller()->getClient();
        $message=$this->caller()->getArgs();//获取所有参数

        $user_id=$message['user_id']+0;
        $live_id=$message['live_id']+0;
        $gift_num=$message['gift_num']+0;
        $gift_class=$message['gift_class']+0;
        $gift_price=$message['gift_price']+0;

        if(empty($message['user_id'])){$message['user_id']=0;};
        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo ($live_id,$user_id);
        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);
            //$content=json_encode(['giftChoose'=>$gift_class,'giftNumber'=>$gift_num,'gift_price'=>$gift_price,'nickname' => $UserInfo['result']['nickname']]);
            $content=['giftChoose'=>$gift_class,'giftNumber'=>$gift_num,'gift_price'=>$gift_price,'nickname' => $UserInfo['result']['nickname']];

            $data = json_encode(['type' => 12, 'ios_content' => $content,'content_obj' => $content,
                'userinfo' => ['level' => $UserInfo['result']['level'], 'nickname' => $UserInfo['result']['nickname'],'user_id'=>$user_id]]);

            $live_gift=Config::getInstance()->getConf('web.live_gift');
            // 异步推送
            TaskManager::async (function () use ($client, $data,$live_id,$user_id,$content,$live_gift) {

                $RedisObj=new Redis();
                $RedisObj->rpush($live_gift.$live_id,$data);
                //送礼物
                $LiveCommentObj=new LiveCommentModel();
                $LiveCommentObj->add(LiveCommentModel::$table,['type'=>1,'live_id'=>$live_id,'user_id'=>$user_id,'content'=>json_encode($content),'created_at'=>date('Y-m-d H:i:s',time())]);

            });

        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>4]);
            $server->push ($getfd, $data);
        }

    }

}
