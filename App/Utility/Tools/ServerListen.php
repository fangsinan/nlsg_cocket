<?php
namespace App\Utility\Tools;
/**
 *监控服务 http 9581 swoole
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/10
 * Time: 下午5:30
 */
class Server
{

    const PORT=9581;

    public function port(){

//        netstat -anp 2>/dev/null | grep 9581 | grep LISTEN | wc -l

        $shell='netstat -anp 2>/dev/null | grep '.self::PORT.' | grep LISTEN | wc -l';
//        $shell = "netstat -anp | grep " . self::port . " | grep LISTEN | grep tcp | wc -l";

        $result=shell_exec($shell);
        if($result!=1){
            //发送报警服务
            echo self::PORT.' 端口：'.date('Y-m-d H:i:s').' error'.PHP_EOL;
        }else{
            echo self::PORT.'success'.PHP_EOL;
        }

    }

}

//2s 执行一次
swoole_timer_tick(2000,function($timer_id){
    (new Server())->port();
    echo 'time-start'.PHP_EOL;
});

//nohup php Shell.php &