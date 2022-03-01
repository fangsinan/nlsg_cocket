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
            $redis->subscribe(['channel','channel1','channel2','channel3'],function (){
                var_dump(func_get_args());
            });
        });
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}