<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/11
 * Time: 下午10:46
 */
namespace App\Utility\Pool;
use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\EasySwoole\Config;
class RedisPool extends AbstractPool
{
    protected function createObject()
    {
        // TODO: Implement createObject() method.
        $redis = new RedisObject();
        $conf = Config::getInstance()->getConf('REDIS');
        if( $redis->connect($conf['host'],$conf['port'])){
            if(!empty($conf['auth'])){
                $redis->auth($conf['auth']);
            }
            $redis->select(0);
            return $redis;
        }else{
            return null;
        }
    }
}