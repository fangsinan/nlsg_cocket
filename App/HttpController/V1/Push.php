<?php
namespace App\HttpController\V1;

use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\LiveCommentModel;
use App\Model\V1\LiveInfo;
use App\Model\V1\LiveLoginModel;
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

        $user_id = $message['user_id'];
        $live_id = $message['live_id'];

        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo ($live_id,$user_id);
        if ( $UserInfo['statusCode'] == 200 ) { //获取成功
            $UserInfo['result']['nick_name']=Common::textDecode($UserInfo['result']['nick_name']);
            $IMAGES_URL =Config::getInstance ()->getConf ('web.IMAGES_URL');
            $headimg = $UserInfo['result']['headimg'] ? $IMAGES_URL.$UserInfo['result']['headimg'] : 'wechat/head.png';
            //$data = json_encode(['type' => 5, 'content' => '进入直播间','content_text' => '进入直播间',
            //    'userinfo' => ['level' => $UserInfo['result']['level'], 'nick_name' => $UserInfo['result']['nick_name'],'headimg'=> $headimg]]);
            $data = json_encode([
                'type' => 5,
                'content_text' => '进入直播间',
                'userinfo' => ['user_id' => $UserInfo['result']['id'],'level' => $UserInfo['result']['level'], 'nickname' => $UserInfo['result']['nickname'],'headimg'=> $headimg]]);

            $live_join=Config::getInstance()->getConf('web.live_join');
            $infoObj = new LiveInfo();
            $infoPid = $infoObj->db->where('id',$message['live_id'])->getOne($infoObj->tableName, 'live_pid');
            $Info = $infoObj->db->where('id',$infoPid['live_pid'])->getOne('nlsg_live', 'is_join');
//            $Info = $infoObj->db->where('id',$live_id)->getOne($infoObj->tableName, 'is_join');

            // 异步推送
            TaskManager::async (function () use ($client, $data,$user_id,$live_id,$live_join,$Info) {

                if( $Info['is_join'] == 0) { //屏蔽加入直播间信息
                    $RedisObj = new Redis();
                    $RedisObj->rpush($live_join . $live_id, $data);
                }
                $LiveLogin=new LiveLoginModel();
                $LiveLogin->add(LiveLoginModel::$table,['user_id'=>$user_id,'ctime'=>time()]);

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

        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo($message['live_id'],$message['user_id']+0,$message['content'],$message['accessUserToken']);

        $infoObj = new LiveInfo();
        $infoPid = $infoObj->db->where('id',$message['live_id'])->getOne($infoObj->tableName, 'live_pid');
        $lupInfo = $infoObj->db->where('id',$infoPid['live_pid'])->getOne('nlsg_live', 'is_forb,helper');
        $admin_arr=explode('-',$lupInfo['helper']);
        if( $lupInfo['is_forb'] == 1 && !in_array($UserInfo['result']['username'],$admin_arr)){ //仅管理员评论
            return ;
        }

        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $live_id=$message['live_id'];
            $UserInfo['result']['nick_name']=Common::textDecode($UserInfo['result']['nickname']);

            $content = Common::textEncode($UserInfo['result']['content']); //入库内容信息 处理表情

//            $data = json_encode(['type' => 2, 'content' =>Common::textDecode($content),'content_text'=>Common::textDecode($content), 'userinfo' => ['user_id'=>$message['user_id'],
//                    'level' => $UserInfo['result']['level'],'nick_name' => $UserInfo['result']['nick_name']]]);
            $data = json_encode(['type' => 2, 'content_text'=>Common::textDecode($content), 'userinfo' => ['user_id'=>$message['user_id'],
                'level' => $UserInfo['result']['level'],'nickname' => $UserInfo['result']['nickname']]]);

            $user_id=$UserInfo['result']['id'];

            $live_comment=Config::getInstance()->getConf('web.live_comment');
            // 异步推送
            TaskManager::async (function () use ($client, $data,$user_id,$content,$live_id,$live_comment) {

                $RedisObj=new Redis();
                $RedisObj->rpush($live_comment.$live_id,$data);

                $LiveComment=new LiveCommentModel();
                $LiveComment->add(LiveCommentModel::$table,
                    ['live_id'=>$live_id,'user_id'=>$user_id,'content'=>$content,'created_at'=>date('Y-m-d H:i:s',time())]
                );
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

            $UserInfo['result']['nick_name']=Common::textDecode($UserInfo['result']['nickname']);
            $content=json_encode(['giftChoose'=>$gift_class,'giftNumber'=>$gift_num,'gift_price'=>$gift_price,'nick_name' => $UserInfo['result']['nick_name']]);

            $data = json_encode(['type' => 12, 'content' => $content,'content_gift' => $content,
                'userinfo' => ['level' => $UserInfo['result']['level'], 'nick_name' => $UserInfo['result']['nick_name'],'user_id'=>$user_id]]);

            $live_gift=Config::getInstance()->getConf('web.live_gift');
            // 异步推送
            TaskManager::async (function () use ($client, $data,$live_id,$user_id,$content,$live_gift) {

                $RedisObj=new Redis();
                $RedisObj->rpush($live_gift.$live_id,$data);
                //送礼物
                $LiveCommentObj=new LiveCommentModel();
                $LiveCommentObj->add(LiveCommentModel::$table,['type'=>1,'live_id'=>$live_id,'user_id'=>$user_id,'content'=>$content,'created_at'=>date('Y-m-d H:i:s',time())]);

            });

        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>4]);
            $server->push ($getfd, $data);
        }

    }

}
