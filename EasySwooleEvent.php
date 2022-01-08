<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;

use App\Lib\Crontab\ServerLoad;
use App\WebSocket\WebSocketEvent;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
//配置外部文件
use EasySwoole\Utility\File;
//状态码
use App\Lib\Message\Status;
//连接池
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\RedisPool;
//定时器
use EasySwoole\Component\Timer;
//异步任务
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
//毫秒任务
use App\Lib\Crontab\Task;
//定时任务
use EasySwoole\EasySwoole\Crontab\Crontab;
//热重载代码更新
use App\Lib\Process\HotReload;
//队列消费
use EasySwoole\EasySwoole\ServerManager;
use App\Lib\Process\Consumer;
//socket
use EasySwoole\Socket\Dispatcher;
use App\WebSocket\WebSocketParser;


class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
        // 注册mysql数据库连接池
        PoolManager::getInstance()->register(MysqlPool::class, Config::getInstance()->getConf('MYSQL.POOL_MAX_NUM'));
        PoolManager::getInstance()->register(RedisPool::class, Config::getInstance()->getConf('REDIS.POOL_MAX_NUM'));
        self::loadConf(); //加载外部文件
    }

    /**
     * 加载配置文件
     */
    public static function loadConf()
    {
        $files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Config');
        if (is_array($files)) {
            foreach ($files['files'] as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);
                if ($fileSuffix == 'php') {
                    Config::getInstance()->loadFile($file);//引入之后,文件名自动转为小写,成为配置的key
                }
            }
        }
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // TODO: Implement mainServerCreate() method.

        /*Di::getInstance()->set(SysConst::ERROR_HANDLER,function (){});//配置错误处理回调
        Di::getInstance()->set(SysConst::SHUTDOWN_FUNCTION,function (){});//配置脚本结束回调
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_NAMESPACE,'App\\HttpController\\');//配置控制器命名空间
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_MAX_DEPTH,5);//配置http控制器最大解析层级
        Di::getInstance()->set(SysConst::HTTP_EXCEPTION_HANDLER,function (){});//配置http控制器异常回调
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_POOL_MAX_NUM,15);//http控制器对象池最大数量*/

        //创建队列消费进程
        $allNum = 3;
        for ($i = 0; $i < $allNum; $i++) {
            ServerManager::getInstance()->getSwooleServer()->addProcess((new Consumer("consumer_{$i}"))->getProcess());
        }

        //预创建链接
        $register->add($register::onWorkerStart, function (\swoole_server $server, int $workerId) {
            if ($server->taskworker == false) {
                PoolManager::getInstance()->getPool(MysqlPool::class)->preLoad(5);
                PoolManager::getInstance()->getPool(RedisPool::class)->preLoad(5);
            }
        });

        /**
         * **************** websocket控制器 **********************
         */
        // 创建一个 Dispatcher 配置
        $conf = new \EasySwoole\Socket\Config();
        // 设置 Dispatcher 为 WebSocket 模式
        $conf->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        // 设置解析器对象
        $conf->setParser(new WebSocketParser());
        // 创建 Dispatcher 对象 并注入 config 对象
        $dispatch = new Dispatcher($conf);
        // 给server 注册相关事件 在 WebSocket 模式下  on message 事件必须注册 并且交给 Dispatcher 对象处理
        $register->set(EventRegister::onMessage, function (\swoole_websocket_server $server, \swoole_websocket_frame $frame) use ($dispatch) {
            $dispatch->dispatch($server, $frame->data, $frame);
        });

        $websocketEvent = new WebSocketEvent();
        //自定义握手事件
        /*$register->set(EventRegister::onHandShake, function (\swoole_http_request $request, \swoole_http_response $response) use ($websocketEvent) {
            $websocketEvent->onHandShake($request, $response);
        });*/

        //打开事件
        $register->set(EventRegister::onOpen, function (\swoole_server $server, \swoole_http_request $request) use ($websocketEvent) {
            $websocketEvent->onOpen($server, $request);
        });

        //自定义关闭事件
        $register->set(EventRegister::onClose, function (\swoole_server $server, int $fd, int $reactorId) use ($websocketEvent) {
            $websocketEvent->onClose($server, $fd, $reactorId);
        });


        /**
         * **************** websocket控制器 **********************
         */

        $ListPort = swoole_get_local_ip(); //获取监听ip
        #172.17.213.52,172.17.213.53,172.17.213.54,172.17.213.55,172.17.212.213

        //扫描评论
        $TaskObj = new Task([
            'method' => 'CommentRedis',
            'path' => [
                'dir' => '/Crontab',
                'name' => 'comment_redis_',
            ],
            'data' => [
            ]
        ]);
        $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
            if ($workerId == 0) {
                Timer::getInstance()->loop(1 * 1000, function () use ($TaskObj) {  //1s 扫描评论
                    //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                    TaskManager::async($TaskObj);
                });
            }
        });

        //购物车商品推送
        $TaskObj = new Task([
            'method' => 'PushProduct',
            'path' => [
                'dir' => '/Crontab',
                'name' => 'pro_',
            ],
            'data' => [
            ]
        ]);
        $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
            if ($workerId == 1) {
                Timer::getInstance()->loop(2 * 1000, function () use ($TaskObj) {  //2s 更新在线人数
                    //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                    TaskManager::async($TaskObj);
                });
            }
        });

        //扫描加入直播
        $TaskObj = new Task([
            'method' => 'JoinRedis',
            'path' => [
                'dir' => '/Crontab',
                'name' => 'join_redis_',
            ],
            'data' => [
            ]
        ]);
        $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
            if ($workerId == 2) {
                Timer::getInstance()->loop(2 * 1000, function () use ($TaskObj) {  //2s 扫描加入直播
                    //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                    TaskManager::async($TaskObj);
                });
            }
        });

        if ($ListPort['eth0'] == '172.17.213.52' || $ListPort['eth0'] == '172.17.212.212') {//172.17.213.52

            //https://www.easyswoole.com/Manual/3.x/Cn/_book/SystemComponent/crontab.html?h=crontab

            //公告推送
            $TaskObj = new Task([
                'method' => 'pushNotice',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'notice_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 3) {
                    Timer::getInstance()->loop(2 * 1000, function () use ($TaskObj) {  //2s 发送公告
                        //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                        TaskManager::sync($TaskObj);
                    });
                }
            });

        }

        if($ListPort['eth0']=='172.17.213.53' || $ListPort['eth0']=='172.17.212.212' ) { //172.17.213.53

            //笔记推送
            $TaskObj = new Task([
                'method' => 'pushNoticeType',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'notice_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 3) {
                    Timer::getInstance()->loop(2 * 1000, function () use ($TaskObj) {  //2s 发送公告
                        //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                        TaskManager::sync($TaskObj);
                    });
                }
            });


        }

        if ($ListPort['eth0'] == '172.17.213.54' || $ListPort['eth0'] == '172.17.212.212') {//172.17.213.54

            //订单推送  扫描redis记录
            $TaskObj = new Task([
                'method' => 'getLivePushOrder',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'order_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 3) {
                    Timer::getInstance()->loop(2 * 1000, function () use ($TaskObj) {
                        TaskManager::async($TaskObj);
                    });
                }
            });

            //更新在线人数
            $TaskObj = new Task([
                'method' => 'onlineNumber',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'onlineNum_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 4) {
                    Timer::getInstance()->loop(15 * 1000, function () use ($TaskObj) {  //15s 更新在线人数
                        //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                        TaskManager::async($TaskObj);
                    });
                }
            });

            //开始|结束直播
            $TaskObj = new Task([
                'method' => 'pushEnd',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'startEnd_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 3) {
                    Timer::getInstance()->loop(5 * 1000, function () use ($TaskObj) {
                        TaskManager::async($TaskObj);
                    });
                }
            });

        }
        if($ListPort['eth0']=='172.17.212.131' || $ListPort['eth0']=='172.17.212.212' ){ //8.140.167.113
            //linux定时任务 分 此方式使用异步进程异步执行，crontab工作机制->异步进程异步执行
            Crontab::getInstance()->addTask(ServerLoad::class); //1 分钟执行一次  更新服务器负载ip

            //推送打赏礼物  扫描redis记录
            $TaskObj = new Task([
                'method' => 'getLiveGiftOrder',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'GiftOrder_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 3) {
                    Timer::getInstance()->loop(5 * 1000, function () use ($TaskObj) {
                        //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                        TaskManager::async($TaskObj);
                    });
                }
            });

            //禁言
            $TaskObj = new Task([
                'method' => 'pushForbiddenWords',
                'path' => [
                    'dir' => '/Crontab',
                    'name' => 'forbid_',
                ],
                'data' => [
                ]
            ]);
            $register->add(EventRegister::onWorkerStart, function (\swoole_server $server, $workerId) use ($TaskObj) {
                if ($workerId == 4) {
                    Timer::getInstance()->loop(10 * 1000, function () use ($TaskObj) {  //30s
                        //为了防止因为任务阻塞，引起定时器不准确，把任务给异步进程处理
                        TaskManager::async($TaskObj);
                    });
                }
            });
            
        }

        //热重载代码更新  关闭防止正式环境重启代理业务问题
//        $swooleServer = ServerManager::getInstance()->getSwooleServer();
//        $swooleServer->addProcess((new HotReload('HotReload', ['disableInotify' => false]))->getProcess());

    }
    //跨域处理
    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        $response->withHeader('Access-Control-Allow-Origin', '*');
        $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->withHeader('Access-Control-Allow-Credentials', 'true');
        $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With,sign,version,apptype,model,os,did,accessusertoken');
        if ($request->getMethod() === 'OPTIONS') {
            $response->withStatus(Status::CODE_OK);
            $response->end();
        }
        return true;
    }
    //日志记录
    public static function afterRequest(Request $request, Response $response): void
    {
        /*// TODO: Implement afterAction() method.
        //从请求里获取之前增加的时间戳
        $reqTime = $request->getAttribute('requestTime');
        $userId_log = $request->getAttribute('userId_log');
        //计算一下运行时间
        $runTime = round(microtime(true) - $reqTime, 3);
        //获取用户IP地址
        $server=$request->getServerParams();
        //拼接一个简单的日志
        $getUri= $request->getUri();
        $logStr = ' | '. $runTime . ' | '.$userId_log.' | '.$server['remote_addr'] .'|' . $getUri .' | '. $request->getHeader('user-agent')[0];
        //判断一下当执行时间大于1秒记录到 slowlog 文件中，否则记录到 access 文件
        if($runTime > 1){
            Logger::getInstance()->log($logStr, 'accessslow');
        }else{
            logger::getInstance()->log($logStr,'access');
        }*/
    }
}