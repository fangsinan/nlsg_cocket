<?php
namespace App\HttpController\V1;

use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\LiveNumberModel;
use App\Lib\Common;
use App\Services\V1\UserService;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

/**
 * Class Index
 *
 * 此类是默认的 websocket 消息解析后访问的 控制器
 *
 */
class Index extends Controller
{

    //连接心跳检测
    public function Ping(){

        $client = $this->caller()->getClient();

        $message = $this->caller ()->getArgs ();//获取所有参数
        if(empty($message['live_id']))$message['live_id']=0;
        if(empty($message['user_id']))$message['user_id']=0;
        $live_id = $message['live_id']+0;

        $server = ServerManager::getInstance()->getSwooleServer();
        $getfd=$client->getFd();

        $count=0;
        /*------*/
        $Redis=new Redis();
        //获取人数
        $live_redis_number=\EasySwoole\EasySwoole\Config::getInstance()->getConf('web.live_redis_number');
        $resultData = $Redis->get($live_redis_number.$live_id);
        if(!empty($resultData)){
            $count=$resultData;
        }
        /*------*/

        $data=Common::ReturnJson(Status::CODE_OK,'获取成功',['type'=>1,'fd'=>$getfd,'num'=>$count]);

        $server->push($getfd,$data);

    }

}
