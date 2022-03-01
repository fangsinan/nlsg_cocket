<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-03-05
 * Time: 20:08
 */

namespace App\Process;

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
            $redis = new \Redis();
            $conf = Config::getInstance()->getConf('REDIS');
            $redis->connect($conf['host']);
            $redis->auth($conf['auth']);
            $redis->subscribe(['pushOrder'],function (){
                $data=func_get_args();
                if(isset($data[1])){
                    switch ($data[1]){
                        case 'pushOrder':
                            if(isset($data[2])){
                                $redis = new \Redis();
                                $conf = Config::getInstance()->getConf('REDIS');
                                $redis->connect($conf['host']);
                                $redis->auth($conf['auth']);
                                $redis->rpush('push_order_list',$data[2]);
                            }
                            break;
                    }
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