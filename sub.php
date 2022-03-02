<?php
ini_set('default_socket_timeout', -1);

$ipArr=swoole_get_local_ip();
$ip=$ipArr['eth0'];

if($ip=='172.17.212.212'){
    $config=@include('dev.php');
}

//$config=@include('produce.php');

$redis = new \Redis();
$redis->pconnect($config['REDIS']['host'], $config['REDIS']['port']);
$redis->auth($config['REDIS']['auth']);
$redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);

function callback ($redis, $chan, $msg){

    $ipArr=swoole_get_local_ip();

    $ip=$ipArr['eth0'];


    if($ip=='172.17.212.212'){
        $config=@include('dev.php');
    }

    switch ($chan){

        case 'pushOrder':

            $redisObj= new \Redis();
            $redisObj->connect($config['REDIS']['host'], $config['REDIS']['port']);
            $redisObj->auth($config['REDIS']['auth']);

            $msg_arr=json_decode($msg,true);

            $live_id=$msg_arr['live_id']??'';

            if($live_id){
                $key='push_order_list:'.$ip.':'.$live_id;
                $res=$redisObj->rpush($key,$msg);
                var_dump($key,$res);
            }

            break;
    }
}


$redis->subscribe(['pushOrder'], 'callback');

