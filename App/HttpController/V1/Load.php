<?php
namespace App\HttpController\V1;

use App\Lib\Redis\Redis;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

/**
 * Class Load
 *负载均衡推送
 */
class Load extends Controller
{

    //负载均衡
    public function Loadpush()
    {

        $redis = new Redis();

        go(function () use ($redis){

            $ListPort = swoole_get_local_ip(); //获取监听ip
            $ip=$ListPort['eth0'];
//            echo $ListPort['eth0'].PHP_EOL;

            echo "订阅频道为：" . $this->channel . '_' . $ip . "\n";

            //订阅完成设置已订阅
            $redis->set($this->channel . '_' . $ip, 1);

            //订阅
            $redis->subscribe(function ($redis, $pattern, $str) {
                echo '订阅频道已收到消息' . "\n";
                $data = json_decode($str, true);
                echo $str . "\n";

                $fd_list = isset($data['fd_list']) ? $data['fd_list'] : '';

                $ListPort = swoole_get_local_ip(); //获取监听ip
                $server_ip=$ListPort['eth0'];

                //判断服务器ip与绑定设备的ip是否一致
                if ($server_ip == $fd_list['server_ip']) {
                    echo 'ip is ok' . "\n";
                    $push_arr = [
                        'receive_data' => $data['receive_data'],
                        'fd_list' => $data['fd_list'],
                        'current_fd' => $data['current_fd'],
                        'type' => $data['type'],
                        'msg' => $data['msg'],
                        'status' => isset($data['status']) ? $data['status'] : 100200,
                        'flag' => $data['flag']
                    ];
                    TaskManager::getInstance()->async(new BroadCastTask($push_arr));
                }

            }, $this->channel . '_' . $ip);
        });
    }

    /**
     * 发布消息
     * @param $channel
     * @param $message
     * @return bool
     * @author:joniding
     * @date:2019/12/20 10:47
     */
    public function lPublish($channel, $message)
    {
        $redis = new Redis();
        go(function () use ($redis, $channel, $message) {
            $redis->publish($channel, $message);
        });
        return true;
    }


    public function test($channel, $message)
    {
        //调用示例
        $subcribe = new Subscribe();
        $result = $subcribe->lPublish(self::$channel.'_'.$fd_server_ip, json_encode($push_arr));
    }


}
