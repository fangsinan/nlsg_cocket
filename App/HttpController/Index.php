<?php
namespace App\HttpController;

use App\Lib\Auth\Aes;
use App\Lib\Cache\Cache;
use App\Lib\Redis\Redis;
use App\Lib\Message\Status;
use App\Services\V1\UserService;
use App\WebSocket\Common;
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
     *1=>心跳    同时返回在线人数5s
      2=>评论
      //3=>弹幕
      4=>礼物
      5=>进入直播间
      6=>商品推送
      7=>公告
      8=>直播结束
      9=>禁言
      10=>线下课成交订单
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

    //https://live.api.nlsgapp.com/Index/GetRedis?clear=0&set=0&type=0&num=0
    //http://wechat.test.nlsgapp.com/websocket.html
    /**
     * 0 真实
     * 1 0->30
     * 2 100->300
     * 3 400->600
     * 4 基数+redis链接
     */
    public function GetRedis(){

        $params             = $this->request()->getRequestParam();
        $Redis = new Redis();
        $live_redis_key=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_redis_key');
        if(isset($params['clear']) && $params['clear']==1){
            $Redis->DEL($live_redis_key); //使用魔术方法处理
        }

        $expire=$Redis->ttl($live_redis_key); //有效期 -2键不存在 -1没设置有效期

        if(isset($params['set']) && $params['set']==1){//设置实时人数
            $Redis->set ('live_redis_number', json_encode(['num'=>$params['num'],'type'=>$params['type']]), 300);
            if($params['type']==4){
                $Redis->set ('live_redis_number_base',$params['num'] , 10800);
            }
        }

        $live_redis_number=\EasySwoole\EasySwoole\Config::getInstance()->getConf('web.live_redis_number');
        $resultData = $Redis->get($live_redis_number);

        $simulation_count=0;
        if(!empty($resultData)){
            $resultData=json_decode($resultData,true);
            $simulation_count=$resultData['num'];
        }

        $UserServiceObj=new UserService();
        $count=$UserServiceObj->linkNum();
        //获取有序集合
        $clients=$Redis->sMembers($live_redis_key);
        $data=[
            'expire'=>$expire,
            'count'=>$count,
            'simulation_count'=>$simulation_count,
            'intro'=>'type 1 (0-30) 2 (100-300) 3 (400-600) 4 基数+redis数量 0 (真实) clear =1 清空redis  set=1 设置数量',
            'redis'=>$clients,
        ];

        return $this->writeJson(Status::CODE_OK,$data,'success');

    }


}
