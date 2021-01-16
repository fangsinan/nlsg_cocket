<?php
namespace App\HttpController;

use App\Lib\Auth\Aes;
use App\Lib\Cache\Cache;
use App\Lib\Redis\Redis;
use App\Lib\Message\Status;
use App\Services\V1\PushService;
use App\Services\V1\UserService;
use App\Utility\Tools\Io;
use EasySwoole\Http\AbstractInterface\Controller;
use App\Lib\Auth\Des;
use App\Lib\Auth\IAuth;
use App\Lib\Auth\Time;
use EasySwoole\EasySwoole\ServerManager;

/**
 * 调试页面暂时保留
 * Index controller
 */
class Index extends  Controller
{

    /**
     * 直播返回标记
    1=>心跳    保持连接同时返回在线人数5s     1
    2=>评论
    4=>礼物
    5=>进入直播间
    6=>小黄车商品推送             1
    7=>公告                1
    8=>直播结束             1
    9=>禁言                   1
    10=>线下课成交订单      1
    12=>打赏推送到评论区                   1
     */

    /**
     * @api {get} index/time 获取时间戳
     * @apiVersion 1.0.0
     * @apiName time
     * @apiGroup index
     *
     * @apiSuccess {string[]} [msg] 返回数据
     * @apiSuccess {string} [msg.time] 返回时间戳
     */
    public function time(){

        return $this->writeJson(Status::CODE_OK,[
            'time'=>Time::get13TimeStamp(),
        ],'success');

    }

    /**
     * 默认的 websocket 测试页
     * /V1/WebSocket/index
     */
    /*public function index()
    {
        $content = file_get_contents(EASYSWOOLE_ROOT. '/webroot/websocket.html');
        $this->response()->write($content);
        $this->response()->end();
    }*/

    public function index()
    {
//        echo (new Aes())->encrypt('bbab2ae18acc51354c1f438d70a3636933d7b368||1222233232');
        //注意此处如果继承了基类base 数据和提示消息互换
        return $this->writeJson(Status::CODE_OK,[],'success');

    }

    /**
     * 负载  直播间广播
     */
    public function broadcast(){

        $params  = $this->request()->getRequestParam();
        $ListPort = swoole_get_local_ip(); //获取监听ip
        var_dump(111111);
        var_dump($ListPort);
        $PushService=new PushService();
        $rst=$PushService->Broadcast($ListPort['eth0'],$params);

        Io::WriteFile('','load_receive',$rst,2);

    }



    public function test(){

        $url = "http://182.92.56.200:9581/index/broadcast";
        $data_str = '{"controller":"Push","action":"Comment","data":{"content":"快看，11111~","user_id":254378,"live_id":645}}';
        $info = PushService::CurlPost($url,['live_id'=>3,'data'=>$data_str]);
        var_dump($info);



    }

    /**
     * 负载  禁言
     */
    public function forbid(){

        $params  = $this->request()->getRequestParam();
        $PushService=new PushService();
        $rst=$PushService->forbidMessage($params);

        Io::WriteFile('','forbid_receive',$rst,2);

    }

    /*public function LiveStartEnd(){

        $params  = $this->request()->getRequestParam();
        if(!isset($params['live_id'])){
            return $this->writeJson(Status::CODE_FAIL,[],'直播间id有误');
        }
        if(!isset($params['user_id'])){
            return $this->writeJson(Status::CODE_FAIL,[],'主播id有误');
        }
        $live_id=$params['live_id']+0;
        $user_id=$params['user_id']+0;

        //获取全员禁言中直播间
        $forbidden=Live::create()->field('id')->get(['id'=>$live_id,'user_id'=>$user_id])->toArray();  //status  2 开始  1结束
        if(!empty($forbidden) ) {
            //推送记录
            $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 7, 'content' => '', 'is_end' => $params['status']]);
            TaskManager::getInstance()->async(function () use ($data, $live_id) {
                //推送消息
                $UserServiceObj = new PushService();
                $UserServiceObj->pushMessage(0, $data, $live_id);
            });
            return $this->writeJson(Status::CODE_OK,[],'发送成功');
        }else{
            return $this->writeJson(Status::CODE_FAIL,[],'直播间有误');
        }

    }*/

}
