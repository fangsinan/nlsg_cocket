<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/11
 * Time: 下午10:45
 */
namespace App\Utility\Pool;
use EasySwoole\Component\Pool\PoolObjectInterface;
use Swoole\Coroutine\Redis;
class RedisObject extends Redis implements PoolObjectInterface
{
    function gc()
    {
        // TODO: Implement gc() method.
        $this->close();
    }
    function objectRestore()
    {
        // TODO: Implement objectRestore() method.
    }
    function beforeUse(): bool
    {
        // TODO: Implement beforeUse() method.
        return true;
    }
}