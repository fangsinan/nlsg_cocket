<?php
/**
 * Created by PhpStorm.
 * User: zxg
 * Date: 2019-01-01
 * Time: 20:06
 */

return [
    'SERVER_NAME'=>"live_swoole_v4",
    'MAIN_SERVER'=>[
        'LISTEN_ADDRESS'=>'0.0.0.0',
        'PORT'=>9581,
        'SERVER_TYPE'=>EASYSWOOLE_WEB_SOCKET_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER
        'SOCK_TYPE'=>SWOOLE_TCP, //该配置项当为SERVER_TYPE值为TYPE_SERVER时有效
        'RUN_MODEL'=>SWOOLE_PROCESS,//默认Server的运行模式
        'SETTING'=>[ //https://wiki.swoole.com/wiki/page/274.html
            'worker_num'=>32,//运行的  worker进程数量
            'max_request'=>5000,// worker 完成该数量的请求后将退出，防止内存溢出
            'task_worker_num'=>32,//运行的 task_worker 进程数量
            'task_max_request'=>1000,// task_worker 完成该数量的请求后将退出，防止内存溢出
            'task_enable_coroutine' => true, //开启后自动在onTask回调中创建协程
            'reload_async' => true,//设置异步重启开关。设置为true时，将启用异步安全重启特性，Worker进程会等待异步事件完成后再退出。
            'package_max_length' =>4*1024*1024, //处理大文件上线
            'buffer_output_size' => 4 * 1024 * 1024,//4M
            //使用nginx反向代理(nginx处理加解密) nginx->swoole 明文
            /*'ssl_cert_file' => EASYSWOOLE_ROOT .'/App/Config/ssl/online/ssl.crt',
            'ssl_key_file' => EASYSWOOLE_ROOT.'/App/Config/ssl/online/ssl.key',
            'ssl_ciphers' => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE:ECDH:AES:HIGH:!NULL:!aNULL:!MD5:!ADH:!RC4',//底层默认使用EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH
            'ssl_method' => SWOOLE_TLSv1_METHOD,//默认算法为SWOOLE_SSLv23_METHOD*/
        ]
    ],
    'TEMP_DIR'=>null,
    'LOG_DIR'=>null,
    'CONSOLE'=>[
        'ENABLE'=>false, //未开启 https://www.easyswoole.com/Manual/3.x/Cn/_book/SystemComponent/Console/Introduction.html
        'LISTEN_ADDRESS'=>'127.0.0.1',
        'HOST'=>'127.0.0.1',
        'PORT'=>9500,
        'EXPIRE'=>'120',
        'AUTH'=>null,
        'PUSH_LOG'=>true
    ],
    'DISPLAY_ERROR'=>true,//是否开启错误显示
    //easyswoole 缓存
    'FAST_CACHE'    => [//fastCache组件
        'PROCESS_NUM' => 0,//进程数,大于0才开启
        'BACKLOG'     => 256,//数据队列缓冲区大小
    ],

//    'MYSQL'=>[
//        'host'          => 'pc-2ze52g145e143cs50.rwlb.rds.aliyuncs.com',
//        #'host'          => 'eoy62ktxmkua664nxx9c-rw4rm.rwlb.rds.aliyuncs.com',
//        'port'          => '3306',
//        'user'          => 'bj_nlsg_v4',
//        'timeout'       => '5',
//        'charset'       => 'utf8mb4',//utf8mb4
//        'password'      => 'Rds_&0331$NLSG^v3@_V4',
//        'database'      => 'nlsg_v4',
//        'POOL_MAX_NUM'  => '20',
//        'POOL_TIME_OUT' => '0.1',
//    ],


'MYSQL'=>[
    'host'          => '39.105.214.152',
    'port'          => '3306',
    'user'          => 'bj_root',
    'timeout'       => '5',
    'charset'       => 'utf8',
    'password'      => 'NLSG_2019cs*beijin.0410BJ',
    'database'      => 'nlsg_v4',
    'POOL_MAX_NUM'  => '20',
    'POOL_TIME_OUT' => '0.1',
],

    'REDIS'=>[
        'host'          => '39.105.214.152',
        'port'          => '6379',
        'auth'          => 'HYQC2021*beijin.1209BJ',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.1',
    ],
];
