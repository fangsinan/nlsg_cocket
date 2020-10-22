<?php
namespace App\Lib\Redis;

/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/16
 * Time: 下午2:37
 */
use EasySwoole\EasySwoole\Config;
use App\Utility\Pool\RedisPool;
use App\Utility\Pool\RedisObject;
use EasySwoole\Component\Pool\PoolManager;

class Redis{

    public $redis;

    public function __construct(){

        $timeout=Config::getInstance()->getConf('REDIS.POOL_TIME_OUT');
        $redisObject = PoolManager::getInstance()->getPool(RedisPool::class)->getObj($timeout);
        if ($redisObject instanceof RedisObject) {
            $this->redis = $redisObject;
        } else {
            throw new \Exception('redis pool is empty');
        }
    }

    function __destruct()
    {
        // TODO: Implement __destruct() method.
        if ($this->redis instanceof RedisObject) {
            PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($this->redis);
            $this->redis=null;

        }
    }

    /**
     * @param $key
     * @return bool|string
     * 获取redis
     */
    public function get($key){

        if(empty($key)){
            return '';
        }

        $data= $this->redis->get($key);

//        return unserialize($data);
        return $data;

    }

    /**
     * @param $key
     * @param $value
     * @param int $expire
     * @return bool
     * 设置redis
     */
    public function set($key,$value,$expire=300){

        if(empty($key)||empty($value)){
            return false;
        }

        return $this->redis->set($key,$value,$expire);

    }

    //出队列  先进先出
    public function lPop($key){

        if(empty($key)){
            return '';
        }

        return  $this->redis->lPop($key);
    }

    //推入数据信息在队列尾部
    public function rpush($key,$value){

        if(empty($key)){
            return '';
        }

        return  $this->redis->rpush($key,$value);

    }

    //获取队列数据 0,0第一条 0，-1 全部
    public function lrange($key,$start,$end){
        if(empty($key)){
            return '';
        }

        return  $this->redis->lrange($key,$start,$end);
    }

    //有序集合
    public function sAdd($key,$value){
        return $this->redis->sAdd($key,$value);
    }
    //删除有序集合
    public function srem($key,$value){
        return $this->redis->srem($key,$value);
    }
    //获取有序集合
    public function sMembers($key){
        return $this->redis->sMembers($key);
    }

    //获取集合成员数
    public function SCARD($key){
        return $this->redis->scard($key);
    }

    //处理类不存在的方法
    //sAdd('key','val')
    public function __call($name,$arguments){

        $num=count($arguments);
        switch($num){
            case 1: $redisObj=$this->redis->$name($arguments[0]);break;
            case 2: $redisObj=$this->redis->$name($arguments[0],$arguments[1]);break;
            default:$redisObj='';
        }

        return $redisObj;

    }

}