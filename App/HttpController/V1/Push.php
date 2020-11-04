<?php
namespace App\HttpController\V1;

use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\LiveCommentModel;
use App\Model\V1\LiveForbiddenWordsModel;
use App\Model\V1\LiveLoginModel;
use App\Model\V1\LiveUserPrivilege;
use App\Services\V1\UserService;
use App\Lib\Common;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;

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
        if ( $UserInfo['statusCode'] == 200 or $UserInfo['statusCode'] == Status::CODE_FORBIDDEN ) { //获取成功
            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);
            $IMAGES_URL = \EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.IMAGES_URL');
            $headimg = $UserInfo['result']['headimg'] ? $IMAGES_URL.$UserInfo['result']['headimg'] : '';
            $data = Common::ReturnJson(Status::CODE_OK,'进入直播间',['type' => 5,'content_text' => '进入直播间',
                'userinfo' => ['level' => $UserInfo['result']['level'], 'nickname' => $UserInfo['result']['nickname'],'headimg'=> $headimg]]);

            $ListPort = swoole_get_local_ip (); //获取监听ip
            //print_r($data);
            // 异步推送
            TaskManager::async (function () use ($client, $data, $ListPort,$user_id,$live_id) {

                //当前连接
                $getfd = $client->getFd ();
                $UserServiceObj=new UserService();

                $UserServiceObj->pushMessage($getfd,$data,$ListPort,$live_id);

                $LiveLogin=new LiveLoginModel();
                $LiveLogin->add(LiveLoginModel::$table,['user_id'=>$user_id,'ctime'=>time()]);

            });
        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>5]);
            print_r($data);

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

        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $live_id=$message['live_id'];
            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);

            $content = Common::textEncode($UserInfo['result']['content']); //入库内容信息 处理表情

            $data = Common::ReturnJson (Status::CODE_OK,'发送成功',
                ['type' => 2, 'content_text'=>Common::textDecode($content), 'userinfo' => ['user_id'=>$message['user_id'],
//                ['type' => 2, 'content' =>Common::textDecode($content),'content_text'=>Common::textDecode($content), 'userinfo' => ['user_id'=>$message['user_id'],
                    'level' => $UserInfo['result']['level'],'nickname' => $UserInfo['result']['nickname']]]);

            $ListPort = swoole_get_local_ip (); //获取监听ip
            $user_id=$UserInfo['result']['id'];

            // 异步推送
            TaskManager::async (function () use ($client, $data, $ListPort,$user_id,$content,$live_id) {

                //当前连接
                $getfd = $client->getFd ();
                $UserServiceObj=new UserService();
                $UserServiceObj->pushMessage($getfd,$data,$ListPort,$live_id);

                $LiveComment=new LiveCommentModel();
                $LiveComment->add(LiveCommentModel::$table,['live_id'=>$live_id,'user_id'=>$user_id,'content'=>$content,'created_at'=>date('Y-m-d H:i:s',time())]);
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

        if(empty($message['user_id'])){$message['user_id']=0;};
        $UserServiceObj = new UserService();
        $UserInfo = $UserServiceObj->GetUserInfo ($live_id,$user_id);
        if ( $UserInfo['statusCode'] == 200 ) { //获取成功

            $UserInfo['result']['nickname']=Common::textDecode($UserInfo['result']['nickname']);
            $content=json_encode(['giftChoose'=>$gift_class,'giftNumber'=>$gift_num,'nickname' => $UserInfo['result']['nickname']]);

            $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 4, 'content' => $content,'content_gift' => $content,
                'userinfo' => ['level' => $UserInfo['result']['level'], 'nickname' => $UserInfo['result']['nickname']]]);

            $ListPort = swoole_get_local_ip (); //获取监听ip

            // 异步推送
            TaskManager::async (function () use ($client, $data, $ListPort,$live_id,$user_id,$content) {

                //当前连接
                $getfd = $client->getFd ();
                $UserServiceObj=new UserService();
                $UserServiceObj->pushMessage($getfd,$data,$ListPort,$live_id);

                //送礼物
                $LiveCommentObj=new LiveCommentModel();
                $LiveCommentObj->add(LiveCommentModel::$table,['type'=>1,'live_id'=>$live_id,'user_id'=>$user_id,'content'=>$content,'ctime'=>time()]);

            });
        }else{
            $server = ServerManager::getInstance()->getSwooleServer();
            $getfd = $client->getFd ();
            $data = Common::ReturnJson (Status::CODE_FAIL,$UserInfo['msg'],['type'=>4]);
            $server->push ($getfd, $data);
        }

    }

}
