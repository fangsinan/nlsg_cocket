<?php

namespace App\WebSocket;

/**
 * Class WebSocketEvent
 *
 * 此类是 WebSocet 中一些非强制的自定义事件处理
 *
 * @package App\WebSocket
 */
use App\Lib\Redis\Redis;
use App\Model\V1\LiveOnlineDuration;
use App\Utility\Tools\Io;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

class WebSocketEvent
{
    /**
     * 握手事件
     *
     * @param \swoole_http_request  $request
     * @param \swoole_http_response $response
     * @return bool
     */
    public function onHandShake(\swoole_http_request $request, \swoole_http_response $response)
    {
        /** 此处自定义握手规则 返回 false 时中止握手 */
        if (!$this->customHandShake($request, $response)) {
            $response->end();
            return false;
        }

        /** 此处是  RFC规范中的WebSocket握手验证过程 必须执行 否则无法正确握手 */
        if ($this->secWebsocketAccept($request, $response)) {
            $response->end();
            return true;
        }

        $response->end();
        return false;
    }

    /**
     * 自定义握手事件
     *
     * @param \swoole_http_request  $request
     * @param \swoole_http_response $response
     * @return bool
     */
    protected function customHandShake(\swoole_http_request $request, \swoole_http_response $response): bool
    {
        /**
         * 这里可以通过 http request 获取到相应的数据
         * 进行自定义验证后即可
         * (注) 浏览器中 JavaScript 并不支持自定义握手请求头 只能选择别的方式 如get参数
         */
        $headers = $request->header;
        $cookie = $request->cookie;

        // if (如果不满足我某些自定义的需求条件，返回false，握手失败) {
        //    return false;
        // }
        return true;
    }

    /**
     * RFC规范中的WebSocket握手验证过程
     * 以下内容必须强制使用
     *
     * @param \swoole_http_request  $request
     * @param \swoole_http_response $response
     * @return bool
     */
    protected function secWebsocketAccept(\swoole_http_request $request, \swoole_http_response $response): bool
    {
        // ws rfc 规范中约定的验证过程
        if (!isset($request->header['sec-websocket-key'])) {
            // 需要 Sec-WebSocket-Key 如果没有拒绝握手
//            var_dump('shake fai1 3');
            return false;
        }
        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $request->header['sec-websocket-key'])
            || 16 !== strlen(base64_decode($request->header['sec-websocket-key']))
        ) {
            //不接受握手
//            var_dump('shake fai1 4');
            return false;
        }

        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $headers = array(
            'Upgrade'               => 'websocket',
            'Connection'            => 'Upgrade',
            'Sec-WebSocket-Accept'  => $key,
            'Sec-WebSocket-Version' => '13',
            'KeepAlive'             => 'off',
        );

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        // 发送验证后的header
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        // 接受握手 还需要101状态码以切换状态
        $response->status(101);
//        var_dump('shake success at fd :' . $request->fd);
        return true;
    }

    /**
     * 监听ws连接事件
     * @param $ws
     * @param $request
     */
    public function onOpen(\swoole_server $server, \swoole_http_request $request) {
        // 服务器ip 链接fd 直播间id
        $params=$request->get;
        if(empty($params['live_id'])){$params['live_id']=0;}
        if(empty($params['user_id'])){$params['user_id']=0;}
        $live_id=$params['live_id']+0;
        $user_id=$params['user_id']+0;

        $ListPort = swoole_get_local_ip(); //获取监听ip

        //echo $ListPort['eth0'].PHP_EOL;
        //处理负载均衡
        $Redis = new Redis();
        $live_redis_key=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_redis_key');
        $Redis->sAdd ($live_redis_key.$live_id, $ListPort['eth0'].','.$request->fd.','.$live_id.','.$user_id); //ip,fd,live_id,user_id
        //记录关闭列表  因为关闭只有一个fd值此队列用于方便关闭对应直播间记录
        $live_colse_list=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_colse_list');
//        $Redis->sAdd($live_colse_list.$live_id,$ListPort['en0'].','.$request->fd.','.$live_id.','.$user_id); // ip,fd,live_id,user_id
        $Redis->sAdd($live_colse_list,$ListPort['eth0'].','.$request->fd.','.$live_id.','.$user_id); // ip,fd,live_id,user_id

    }
    /**
     * 关闭事件
     * @param \swoole_server $server
     * @param int            $fd
     * @param int            $reactorId
     */
    public function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
        /** @var array $info */
        $info = $server->getClientInfo($fd); //获取链接信息
        /**
         * 判断此fd 是否是一个有效的 websocket 连接
         * 参见 https://wiki.swoole.com/wiki/page/490.html
         */
        if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME) { //已握手成功等待浏览器发送数据帧
            /**
             * 判断连接是否是 server 主动关闭
             * 参见 https://wiki.swoole.com/wiki/page/p-event/onClose.html
             */
            if ($reactorId < 0) {  //服务端关闭
                echo "server close ".PHP_EOL;
            }
        }

        $ListPort = swoole_get_local_ip(); //获取监听ip
        $live_colse_list=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_colse_list');
        $live_redis_key=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_redis_key');

        // 异步推送
        TaskManager::async (function () use ($ListPort,$fd,$live_colse_list,$live_redis_key) {
            $Redis = new Redis();
            $clients = $Redis->sMembers ($live_colse_list); //获取集合
            //ip,fd,live_id,user_id
            $str=$ListPort['eth0'].','.$fd.',';
            foreach($clients as $key => $val ){ //删除数据
                if(strpos($val,$str) !== false){
                    $arr=explode(',',$val);
                    $Redis->srem($live_colse_list,$val); //删除记录表
                    $Redis->srem($live_redis_key.$arr[2],$val); //删除直播间记录
                    break;
                }
            }

        });


    }

}

