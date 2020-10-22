<?php

namespace App\Lib\Cache;

use App\Lib\Redis\Redis;
use EasySwoole\EasySwoole\Config;
use EasySwoole\FastCache\Cache as MemoryCache;
use App\Utility\Tools\Io;
class Cache
{

    //缓存
    public static function Cache($CACHE_TYPE=0,$filename,$data=[],$expire=0){

        try {
            if($CACHE_TYPE==4){
                /*$cache = MemoryCache::getInstance();
                $cache->set('name', '仙士可');//设置
                $cache->get('name');//获取
                $cache->keys();//获取所有key
                $str = "现在存储的数据有:";
                foreach ($keys as $key) {
                    $value = $cache->get($key);//获取
                    $str .= "$key:$value\n";
                }
                $cache->unset('name');//删除key
                $cache->flush();//清空所有key
                ($cache->enQueue('listA', '1'));//增加一个队列数据
                ($cache->enQueue('listA', '2'));//增加一个队列数据
                ($cache->enQueue('listA', '3'));//增加一个队列数据
                var_dump($cache->queueSize('listA'));//队列大小
                var_dump(  $cache->unsetQueue('listA'));//删除队列
                var_dump($cache->queueList('listA'));//队列列表
                var_dump($cache->flushQueue());//清空队列
                var_dump($cache->deQueue('listA'));//出列
                var_dump($cache->deQueue('listA'));//出列*/

                MemoryCache::getInstance()->unset($filename);//删除easyswoole缓存
            }else {
                if ( $CACHE_TYPE == 0 ) {
                    $CACHE_TYPE = Config::getInstance ()->getConf ('web.CACHE_TYPE');  //  1 easyswoole 缓存 2 redis 3 文件缓存  4删除easyswoole缓存
                }
                $IS_FRONTEND_FLAG = Config::getInstance ()->getConf ('web.IS_FRONTEND_FLAG');  //是否启用缓存  ===true
                if ( empty($expire) ) {
                    $expire = Config::getInstance ()->getConf ('web.IS_FRONTEND_TIME');
                } //缓存时间

                $dir = EASYSWOOLE_ROOT . Config::getInstance ()->getConf ('web.FileCache_Log');
                if ( empty($data) ) { //get获取值
                    if ( $IS_FRONTEND_FLAG === false ) {//没开缓存
                        return [];
                    } else {
                        if ( $CACHE_TYPE == 1 ) { //  数组简单情况 json_encode() 比 serialize  但解码：unserialize 比 json_decode() 快
                            $resultData = MemoryCache::getInstance()->get($filename);
                        } else if ( $CACHE_TYPE == 2 ) {
                            $RedisObj=new Redis();
                            $resultData = $RedisObj->get($filename);
                        } else if ( $CACHE_TYPE == 3 ) {
                            $filename = $dir . '/' . $filename;
                            $resultData = is_file ($filename) ? file_get_contents ($filename) : [];
                        }

                        $resultData = !empty($resultData) ? $resultData : '';

                        return $resultData;

                    }
                } else { //set设置值
                    if ( !empty($data) || $IS_FRONTEND_FLAG === false ) { //关闭缓存刷新数据

                        if ( is_array ($data) ) {
                            $data = json_encode ($data);
//                            $data=serialize($data);
                        }
                        if ( $CACHE_TYPE == 1 ) {
                            MemoryCache::getInstance ()->set($filename, $data);
                        } else if ( $CACHE_TYPE == 2 ) {
                            $RedisObj=new Redis();
                            return $RedisObj->set ($filename, $data, $expire);
                        } else if ( $CACHE_TYPE == 3 ) {
                            if ( !is_dir ($dir) ) {
                                mkdir ($dir, 0777, true);
                            }
                            file_put_contents ($dir . '/' . $filename, $data);
                        }
                    }
                }
            }

        }catch (\Exception $e){
            //发送报警信息
            Io::WriteFile ('', 'cache_error','catch：'.$e->getMessage());
        }

    }

}
