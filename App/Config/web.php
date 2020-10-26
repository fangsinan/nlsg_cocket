<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/20
 * Time: 下午9:23
 */
//Config::getInstance()->getConf('web.INDEX_URL')
return [

    'IS_FRONTEND_FLAG'=>true,
    'IS_FRONTEND_TIME'=>300,
    'CACHE_TYPE'=>1,//1 easyswool内存缓存 cache  2 redis     3 filecache 文件缓存

    //app加密校验串
    'app_password_halt'=>'#_hyqc2020', // 密码加盐
    'app_aeskey'=>'nlsghuiyujiaoyujt2020hyqc_xitong', //32位16位
    'app_iv'=>'hyqc#swoole_2020',//aes加密
    'app_deskey'=>'nlsghuiyujiaoyujt2020hyqc_xitong', //32位16位
    'apptypes'=>[ //app类型
        'ios',
        'android',
        'wechat',
        'live'
    ],
    'not_verifying_apptypes'=>[ //不验证微信和后台获取不到操作系统和机型
        'live',
        'admin'
    ],
    'app_sign_time'=>10, //sign 设置请求验证失效时间10秒
    'app_sign_cache_time'=>20, //sign 缓存失效时间20秒
    'app_debug'=>true, //是否为调试模式
    'login_time_out_days'=>7, //用户登录失效时间
    'adminlogin_time_out'=>3600, //后台登录失效时间 1小时

    'Api_Url' => 'https://live.api.nlsgapp.com/',

    'SYS_ERROR'=>['phone'=>18810355387,'tpl'=>'SMS_152505672'],//系统报错错误通知

    //easyswoole 文件缓存   报错日志 Log/cache_error
    'FileCache_Log'=>'/Temp/FileCache',
    //异步任务日志
    'Task_Log'=>'/Log/task',
    'Pay_Log'=>'/Log/pay',
    'msg_Log'=>'/Log/msg',

    //直播key
    'live_redis_key'=>'livev4_key_', //主要用于存储负载时各个服务器的fd链接
    //直播人数
    'live_redis_number'=>'livev4_number_',
    //直播关闭列表
    'live_colse_list'=>'livev4_colse_list', //用于关闭fd链接
    //在线人数基数
    'live_redis_number_base'=>'livev4_number_base',
    'IMAGES_URL' => 'https://image.nlsgapp.com/'
];
