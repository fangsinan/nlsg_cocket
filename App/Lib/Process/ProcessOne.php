<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-03-05
 * Time: 20:08
 */

namespace App\Lib\Process;

use App\Lib\Redis\Redis;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

class ProcessOne extends AbstractProcess
{
    public function run($arg)
    {

        Logger::getInstance()->console($this->getProcessName() . " start");
        go(function ()use ($arg) {

//            $redis = new Redis();
//            while (true){
//                $res=$redis->rpush('push_order_list','111');
//                var_dump($res);
//                $redis->subscribe(['pushOrder']);
//                $res=$redis->rpush('push_order_list','111');
//                var_dump($res);
//            }

            ini_set('default_socket_timeout', -1);

            $redis = new \Redis();
            $conf = Config::getInstance()->getConf('REDIS');
            $redis->pconnect($conf['host']);
            $redis->auth($conf['auth']);
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
            $redis->subscribe(['pushOrder'],function ($redis, $chan, $msg)use ($arg){

                switch ($chan){

                    case 'pushOrder':
                        $redisObj= new Redis();
                        $msg_arr=json_decode($msg,true);
                        $live_id=$msg_arr['live_id']??'';
                        if($live_id){
                            $key='push_order_list:'.$arg['ip'].':'.$live_id;
                            $res=$redisObj->rpush($key,$msg);
//                            var_dump($key,$res);
                        }
                        break;
                }
            });
        });
    }

    public function onShutDown()
    {
        var_dump('onShutDown');

        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        var_dump($str);
        // TODO: Implement onReceive() method.
    }
}