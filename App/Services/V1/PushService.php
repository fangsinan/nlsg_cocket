<?php
/**
 * User: zxg
 * Date: 2019/1/14
 * Time: 1:59 PM
 */

namespace App\Services\V1;

use App\Lib\Common;
use App\Lib\Message\Status;
use App\Utility\Tools\Io;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use App\Lib\Redis\Redis;

class PushService
{

    /**
     * 单独推送
     * 主要针对禁言
     */
    public function PushForbid($live_id,$user_id,$data)
    {

        $Redis = new Redis();
        $clients = $Redis->sMembers ($live_redis_key=Config::getInstance ()->getConf ('web.live_redis_key').$live_id); //获取集合 ip,user_id,fd
        $str=','.$user_id.',';
        $server_ip='';
        foreach($clients as $key => $val ){ //删除数据
            if(strpos($val,$str) !== false){
                $arr=explode(',',$val);
                $server_ip=$arr[0]; $fd=$arr[2];
                break;
            }
        }
        if(!empty($server_ip)) {
            $url = "http://$server_ip:9581/index/forbid";
            $info = self::CurlPost($url, ['fd'=>$fd, 'data' => $data]);
            Io::WriteFile('', 'forbid_send', $server_ip . '#' . $info, 2);
        }

    }

    public function forbidMessage($data){

            $server = ServerManager::getInstance()->getSwooleServer();
            $info = $server->getClientInfo($data['fd']);
            //判断此fd 是否是一个有效的 websocket 连接
            if ($info && $info['websocket_status'] == WEBSOCKET_STATUS_FRAME) {
                  $server->push($data['fd'], $data['data']);
            } else {
//                  $Redis->srem($data['live_id'].':'.$ip,$fd); //删除遍历直播间
//                  $delkey_flag=$live_id_list.':'.$ip.'_'.$fd;
//                  $Redis->del($delkey_flag);
            }

            return 1;

    }

    /**
     * @param $ip
     * @param $live_id
     * @param $data
     * @throws \Exception
     * 推送直播间消息
     */
    public function pushMessage($ip,$live_id,$data){

        if(is_array($data)){
            //安卓返回格式必须统一
            $data_str=json_encode($data);
        }else{
            $data_str=$data;
        }

        //当前服务器发送，多直播间时容易导致定时任务拥堵 全部采用分发
        $IpLoadArr=Config::getInstance ()->getConf ('web.load_ip_arr');

        $Redis = new Redis();
        $resultData = $Redis->get('live_serverload_iplist'); //服务器ip列表
        $IpLoadArr=explode(',',$resultData);

        $sendArr=[];
        foreach ($IpLoadArr as $key=>$val){
            $url = "http://$val:9581/index/broadcast";
            //print_r(['live_id'=>$live_id,'data'=>$data_str]);
            $info = self::CurlPost($url,['live_id'=>$live_id,'data'=>$data_str]);
//
            /*$res = json_decode($info,true);
            var_dump($res['msg'] == 1);
            if($res['msg'] == 1){ //发送成功

            }*/
//            var_dump($live_id);
//            var_dump($data_str);
            $sendArr[]=$val.'#'.$info;
        }
        Io::WriteFile('','load_send',$sendArr,2);

    }

    /**
     *广播推送直播间
     */
    public static function Broadcast($ip,$data){
        $server = ServerManager::getInstance()->getSwooleServer();
        $Redis = new Redis();
        $live_id_list=Config::getInstance ()->getConf ('web.live_id_list');
        $clients = $Redis->sMembers ($data['live_id'].':'.$ip); //获取有序集合

        if(!empty($clients)) {
            foreach ($clients as $key => $fd) {
                $info = $server->getClientInfo($fd);
                //判断此fd 是否是一个有效的 websocket 连接
                if ($info && $info['websocket_status'] == WEBSOCKET_STATUS_FRAME) {
                    $server->push($fd, $data['data']);
                } else {
//                    $Redis->srem($data['live_id'].':'.$ip,$fd); //删除遍历直播间
//                    $delkey_flag=$live_id_list.':'.$ip.'_'.$fd;
//                    $Redis->del($delkey_flag);
                }
            }
        }

        return 0;

    }

    //模拟请求
    public static function CurlPost($url , $data = []){

        $ch =   curl_init();
        curl_setopt($ch , CURLOPT_URL , $url);//用PHP取回的URL地址（值将被作为字符串）

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);//设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($ch,CURLOPT_TIMEOUT,30);//30秒超时限制
        curl_setopt($ch, CURLOPT_HEADER, 0); //忽略header头信息
        if($data){
            curl_setopt($ch,CURLOPT_POST,1);//设置post方式提交
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//post操作的所有数据的字符串。
        }

        $output = curl_exec($ch);//抓取URL并把他传递给浏览器
        curl_close($ch); // 关闭cURL资源，并且释放系统资源

        return $output;

    }

}