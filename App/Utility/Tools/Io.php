<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/28
 * Time: 下午5:38
 */

namespace App\Utility\Tools;

use \EasySwoole\EasySwoole\Config;
use App\Lib\Auth\Time;

class Io
{
    /**
     * @param $name
     * 日志异步写入
     */
    public static function WriteFile($dir,$name,$content,$flag=''){ //1 微信支付 2 task任务

        if($flag==2){
            // 任务执行完的处理
            $dir = EASYSWOOLE_ROOT . Config::getInstance ()->getConf ('web.Task_Log') . $dir;
        }else{
            $dir = EASYSWOOLE_ROOT . Config::getInstance ()->getConf ('web.msg_Log') . $dir;
        }
        //创建目录
        if ( !is_dir ($dir) ) {
            mkdir ($dir, 0777, true);
        }
        if(is_array($content)){
            $content=json_encode($content);
        }
//        $time=Time::get13TimeStamp();
//        $content = Time::microtime_format('Y-m-d H:i:s x',$time) . '  ' . $content. PHP_EOL;
        $time=time();
        $content = date('Y-m-d H:i:s',$time) . '  ' . $content. PHP_EOL;
        $name = $name.date('ymd');
        file_put_contents($dir . "/$name.log",$content,FILE_APPEND|LOCK_EX);
        //异步IO写文件
        /*swoole_async_writefile ($dir . "/$name.log", $content, function () {

        }, FILE_APPEND);*/

    }
}