<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-03-05
 * Time: 20:08
 */

namespace App\Process;

use App\Lib\Redis\Redis;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

class ProcessOne extends AbstractProcess
{
    public function run($arg)
    {
        ini_set('default_socket_timeout', -1);

        Logger::getInstance()->console($this->getProcessName() . " start");

        go(function () {

//            $redis = new Redis();
//            while (true){
//                $res=$redis->rpush('push_order_list','111');
//                var_dump($res);
//                $redis->subscribe(['pushOrder']);
//                $res=$redis->rpush('push_order_list','111');
//                var_dump($res);
//            }

            $redis = new \Redis();
            $conf = Config::getInstance()->getConf('REDIS');
            $redis->connect($conf['host']);
            $redis->auth($conf['auth']);
            $redis->subscribe(['pushOrder'],function ($redis, $chan, $msg){
                switch ($chan){
                    case 'pushOrder':
                        $redisObj= new Redis();
                        $redisObj->rpush('push_order_list',$msg);
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