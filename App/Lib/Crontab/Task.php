<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/12
 * Time: 下午9:54
 */

namespace App\Lib\Crontab;

use App\Lib\Common;
use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\Column;
use App\Model\V1\Goods;
use App\Model\V1\LiveForbiddenWordsModel;
use App\Model\V1\LiveInfo;
use App\Model\V1\LiveNotice;
//use App\Model\User;
use App\Model\V1\LiveNumberModel;
use App\Model\V1\LivePush;
use App\Model\V1\Order;
use App\Model\V1\User;
use App\Model\V1\Works;
use App\Model\V1\WorksInfo;
use App\Services\V1\UserService;
use EasySwoole\EasySwoole\ServerManager;
use App\Utility\Tools\Io;
use App\Utility\Tools\Tool;
use App\Lib\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

/**
 * Class MillisecondTask
 * @package App\Crontab
 * 毫秒任务
 */
//直接投递
// 在控制器中投递的例子
/*\EasySwoole\EasySwoole\Swoole\Task\TaskManager::async(function () {
    echo "执行异步任务...\n";
    return true;
}, function () {
    echo "异步任务执行完毕...\n";
});
// 在定时器中投递的例子
\EasySwoole\Component\Timer::getInstance()->loop(1000, function () {
    \EasySwoole\EasySwoole\Swoole\Task\TaskManager::async(function () {
        echo "执行异步任务...\n";
    });
});
//模板投递
// 在控制器中投递的例子
function index()
{
    // 实例化任务模板类 并将数据带进去 可以在任务类$taskData参数拿到数据
    $taskClass = new Task('taskData');
    \EasySwoole\EasySwoole\Swoole\Task\TaskManager::async($taskClass);
}
// 在定时器中投递的例子
\EasySwoole\Component\Timer::getInstance()->loop(1000, function () {
    \EasySwoole\EasySwoole\Swoole\Task\TaskManager::async($taskClass);
});*/


class Task extends \EasySwoole\EasySwoole\Swoole\Task\AbstractAsyncTask
{

    /**
     * 执行任务的内容
     * @param mixed $taskData     任务数据
     * @param int   $taskId       执行任务的task编号
     * @param int   $fromWorkerId 派发任务的worker进程号
     * @author : evalor <master@evalor.cn>
     */
    function run($taskData, $taskId, $fromWorkerId,$flags = null)
    {
        // 需要注意的是task编号并不是绝对唯一
        // 每个worker进程的编号都是从0开始
        // 所以 $fromWorkerId + $taskId 才是绝度唯一的编号
        // !!! 任务完成需要 return 结果

        //分发task任务机制  不同任务不同逻辑
        $method=$taskData['method'];
        $rst=$this->$method($taskId, $fromWorkerId,$taskData['data'],$taskData['path']);

        return ['method'=>$method,'data'=>$rst];
    }


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
    11=>排行榜
    12=> 礼物
     *
     */



    /**
     * 任务执行完的回调
     * @param mixed $result  任务执行完成返回的结果
     * @param int   $task_id 执行任务的task编号
     * @author : evalor <master@evalor.cn>
     */
    function finish($result, $task_id)
    {
        if(isset($result['data']['data']) && !empty($result['data']['data'])) {
            //$result['data']['data']['method']=='WxPaySuccess'
            //文件处理
            Io::WriteFile($result['data']['path']['dir'],$result['data']['path']['name'],$result['data']['data'],2);
        }

    }

    //生成在线人数
    public function onlineNumber($taskId, $fromWorkerId,$data,$path){

        try {

            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $live_id_num=Config::getInstance()->getConf('web.live_redis_number');
            $Redis = new Redis();

            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(!empty($listRst)){
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    $num=$Redis->scard($live_id_key.$live_id); //获取成员数据
                    $Redis->set($live_id_num.$live_id,$num,86400); //设置在线人数
                    //TaskManager::async(function ()use($live_id,$num){  });
                    if($num>0) {
                        //实时数据入库
                        $LiveModel=new LiveNumberModel();
                        $LiveModel->add(LiveNumberModel::$table,['live_id'=>$live_id,'count'=>$num,'time'=>time()]);
                    }
                }
            }

            /*$live_redis_number_base=Config::getInstance()->getConf('web.live_redis_number_base');
            $live_redis_number_base=Cache::Cache(2,$live_redis_number_base);
            if(empty(($live_redis_number_base))){
                $live_redis_number_base=0;
            }
            $count = $realcount + $live_redis_number_base;*/

            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //发送公告 7
    public function pushNotice($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $noticeObj  = new LiveNotice();
            $UserServiceObj=new UserService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $idArr=[];
            foreach($listRst as $key => $val){
                $arr = explode ('_', $val);
                $live_id=$arr[2];
                $noticeList = $noticeObj->get($noticeObj->tableName,['live_info_id'=>$live_id,'is_done'=>0,'is_del'=>0],'id,live_id,content,type,created_at,length');
                if(!empty($noticeList)){
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 7,'ios_content' =>$noticeList[0], 'content_obj' =>$noticeList[0]  ]);
                    $ListPort = swoole_get_local_ip (); //获取监听ip
                    //推送消息
                    $idArr=[];
                    $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id);

                    if(!empty($noticeList)){
                        //修改标记
                        $idArr=array_column($noticeList, 'id');
                        $noticeObj->update($noticeObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$idArr]);
                        $noticeObj->getLastQuery();
                    }
                }
            }

            return [
                'data' => $idArr,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //直播结束  8
    public function pushEnd($taskId, $fromWorkerId,$data,$path){

        try {
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $liveObj=new LiveInfo();
            $UserServiceObj = new UserService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $is_end=0;
            foreach($listRst as $key => $val){
                $arr = explode ('_', $val);
                $live_id=$arr[2];

                $liveInfo=$liveObj->getOne($liveObj->tableName,['id'=>$live_id],'id,end_time,is_begin');

                //echo $liveObj->getLastQuery();
                $is_end=0;
                $time=time();
                $live_info =[];
                if(!empty($liveInfo)){
                    if($liveInfo['is_begin'] == 0){
                        $is_end = 1;    //前端判断有误   结束判断的是1
                    }
                    $live_info=[
                        'id'  => $live_id,
                        'is_begin'=>$liveInfo['is_begin'],
                        'is_end'  =>$is_end,
                    ];
                }
                //if($is_end==1 && ($time-$liveInfo['end_time'])<600) { //10分钟内推送
                if(!empty($liveInfo) && $liveInfo['is_begin'] == 0 ) { //10分钟内推送
                    //推送记录
                    $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 8, 'content' => $live_info ]);
                    $ListPort = swoole_get_local_ip(); //获取监听ip
                    //推送消息
                    $UserServiceObj->pushMessage(0, $data, $ListPort, $live_id);

                    $Redis->del($live_id_key.$live_id); //结束后删除直播间记录

                }
            }
            return [
                'data' => $is_end,
                'path' => $path
            ];

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //禁言  9
    public function pushForbiddenWords($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $forbiddenObj = new LiveForbiddenWordsModel();
            $UserServiceObj = new UserService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');

            if(empty($listRst)) return '';

            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];
                $time=time();

                //是否全场禁言
                //发送两次公告   一个是全员的禁言推送   另一个是个人发送情况
//                $forbidden = $forbiddenObj->getOne(LiveForbiddenWordsModel::$table,['live_id'=>$live_id,'is_forbid'=>1,'user_id'=>0],'*');
                $forbidden = $forbiddenObj->getOne(LiveForbiddenWordsModel::$table,['live_id'=>$live_id,'user_id'=>0],'*');
                if(!empty($forbidden)){
                    //推送禁言状态
                    $all_res= [
                        'user_id'       => 0,
                        'is_forbid'     => $forbidden['is_forbid'],
                        'forbid_ctime'  => $forbidden['forbid_ctime'],
                        'forbid_time'   => $forbidden['forbid_time'],
                    ];
                    $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 9, 'content' =>[$all_res] ]);
                    $ListPort = swoole_get_local_ip(); //获取监听ip
                    //推送消息
                    $UserServiceObj->pushMessage(0, $data, $ListPort, $live_id);

                    //如果为全员禁言  直接返回
                    $forbid_time = ($val['forbid_ctime'] + $val['forbid_time']) - $time;
                    if ($forbid_time > 0  && $forbidden['is_forbid'] == 1) {
                        return [
                            'data' => [],
                            'path' => $path
                        ];
                    }
                }
                //如果是解禁的情况下 返回个人的禁言状态

                //个人禁言
                $forbidden = $forbiddenObj->get(LiveForbiddenWordsModel::$table,['live_id'=>$live_id,'is_forbid'=>1],'*');

                $idArr=[];
                if(!empty($forbidden) ) {

                    foreach ($forbidden as $key=>$val) {
                        //  禁言时间 + 禁言时长 - 当前时间  大于0(禁言中)  否则0
                        $forbid_time = ($val['forbid_ctime'] + $val['forbid_time']) - $time;
                        if ($forbid_time <=0) {
                            $idArr[]=$val['id']; //记录已解除禁言用户
                        }else{
                            $forbid_ctime=$val['forbid_ctime']; //开始时间
                            $is_forbid = 1;    //禁言
                            $forbid_time=$val['forbid_time']; //时长

                            $res= [
                                'user_id' => $val['user_id'],
                                'is_forbid' => $is_forbid,
                                'forbid_ctime' => $forbid_ctime,
                                'forbid_time' => $forbid_time,
                            ];
                            //推送记录
                            $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 9, 'content' =>[$res],'ios_content' => $res ]);
                            $ListPort = swoole_get_local_ip(); //获取监听ip
                            //推送消息
                            $UserServiceObj->pushMessage(0, $data, $ListPort, $live_id,[],$val['user_id']);

                        }

                    }
                    //修改标记
                    if(!empty($idArr)){
                        $forbiddenObj->update($forbiddenObj::$table,['is_forbid'=>2,'forbid_ctime'=>0,'forbid_time'=>0],['id'=>$idArr]);
                    }
                }

            }
            return [
                'data' => $idArr,
                'path' => $path
            ];
//            $live_id=Config::getInstance()->getConf('web.live_id_now');


        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    /**
     * 产品推送  6
     */
    public static function PushProduct($taskId, $fromWorkerId,$data,$path){

        try {
            $live_id = $data['live_id'];

            $pushObj = new LivePush();
            $UserServiceObj=new UserService();
            $now = date('Y-m-d H:i:s',time());
            $where = [
                'live_info_id' => $live_id,
                '(push_at < ?)'=>[$now],
                'is_push' => 0,
                'is_del' => 0,
                'is_done' => 0,
            ];
            $push_info = $pushObj->get($pushObj->tableName,$where,'id,push_type,push_gid');
            echo $pushObj->getLastQuery();
            if(!empty($push_info)){
                //多个
                $res = self::getLivePushDetail($push_info);

                $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 6, 'content' =>$res]);

                $ListPort = swoole_get_local_ip (); //获取监听ip
                //推送消息
                $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id);
            }
            return [
                'data' => 1,
                'path' => $path
            ];


        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }


    //产品推送
    public static function getLivePushDetail($push_info){

        //获取产品信息
        $res=[];
        $colObj   = new Column();
        $workObj  = new Works();
        $WorkInfoObj=new WorksInfo();
        $goodsObj = new Goods();
        foreach($push_info as $key=>$val){
            //push_type 产品type  1专栏 2精品课 3商品 4 经营能量 5 一代天骄 6 演说能量
            //push_gid 推送产品id，专栏id  精品课id  商品id
            if(($val['push_type'] == 1 || $val['push_type'] == 7) && !empty($val['push_gid']) ){
                $fields = 'id,name,price,subtitle,cover_pic img,user_id';
                $Info = $colObj->getOne($colObj->tableName,['id'=>$val['push_gid'],'status'=>2],$fields);
            }elseif( ($val['push_type'] == 2 || $val['push_type'] == 8) && !empty($val['push_gid']) ){
                $fields = 'id,title name,type,price,cover_img img';
                $Info = $workObj->getOne($workObj->tableName,['id'=>$val['push_gid'],'status'=>4],$fields);
                $WorkInfoData=$WorkInfoObj->getOne($WorkInfoObj->tableName,['pid'=>$val['push_gid'],'status'=>4],'id',['`order`'=>0]);
                $Info['workinfo_id']=$WorkInfoData['id'];
            }else if($val['push_type'] == 3 && !empty($val['push_gid'])){
                $fields = 'id,name,price,subtitle,picture img';
                $Info = $goodsObj->getOne($goodsObj->tableName,['id'=>$val['push_gid'],'status'=>2],$fields);
            }else if($val['push_type'] == 4){

                $fields = 'id,title name,price,subtitle,cover_img img';
                $Info = $goodsObj->getOne('nlsg_offline_products',['id'=>$val['push_gid']],$fields);
            }else if($val['push_type'] == 6){
                $Info=[
                        'name'=>'幸福360会员',
                        'price'=>360,
                        'subtitle'=>'',
                        'img'=>'/nlsg/poster_img/1581599882211_.pic.jpg'
                    ];
            }

            $res[]= [
                'push_info' => $val,
                'son_info' => $Info,
            ];
        }
        if(!empty($push_info)){
            //修改标记
            $idArr=array_column($push_info, 'id');
            $LivePushObj=new LivePush();
            $LivePushObj->update($LivePushObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$idArr]);
        }

        return $res;
    }

    /**
     * 订单推送  10
     */
    public static function getLivePushOrder($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $UserServiceObj=new UserService();
            $OrderObj = new Order();
            $UserObj = new User();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';

            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];

                $OrderInfo=$OrderObj->db
                    ->join($UserObj->tableName . ' u', 'o.user_id=u.id', 'left')
                    ->where('o.type', 14)->where('o.live_id',$live_id)->where('o.status',1)
                    ->where('o.product_id',0,'>')->where('is_live_order_send',0) //->where('o.pay_price',1,'>')
                    ->orderBy('o.id','ASC')
                    ->get($OrderObj->tableName .' o',null,'o.id,u.nickname,o.product_id,o.live_num,o.pay_price');

                if(!empty($OrderInfo)){
                    $res=[];
                    foreach($OrderInfo as $key=>$val){
                        $val['nickname']=Common::textDecode($val['nickname']);
                        switch ($val['product_id']){
                            case 1: //经营能量
                                $res[]=$val['nickname'].':您已成功购买'.$val['live_num'].'张经营能量门票';
                                break;
                            case 2: //一代天骄
                                $res[]=$val['nickname'].':您已支付成功一代天骄定金';
                                break;
                            case 3: //演说能量
                                $res[]=$val['nickname'].':您已支付成功演说能量定金';
                                break;
                        }
                    }
                    if(!empty($OrderInfo)){
                        //修改标记
                        $idArr=array_column($OrderInfo, 'id');
                        $OrderObj->update($OrderObj->tableName,['is_live_order_send'=>1],['id'=>$idArr]);
                    }

                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 10, 'content' =>$res,'content_one_array' =>$res]);

                    $ListPort = swoole_get_local_ip (); //获取监听ip
                    //推送消息
                    $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id);



                }

            }
            return [
                'data' => 1,
                'path' => $path
            ];
//            $live_id = Config::getInstance()->getConf('web.live_id_now');





        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }


    /**
     * 排行榜  11
     */
    public static function getLiveOrderRanking($taskId, $fromWorkerId,$data,$path){

        try {
            print_r($data);
            echo 1111;
            return;
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $UserServiceObj=new UserService();
            $OrderObj = new Order();
            $UserObj = new User();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');

            if(empty($listRst)) return '';

            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];

                $OrderInfo=$OrderObj->db
                    ->join($UserObj->tableName . ' u', 'o.twitter_id=u.id', 'left')
                    ->where('o.type', 10)->where('o.live_id',$live_id)->where('o.status',1)->where('o.twitter_id != ?',[0])
                    ->groupBy('twitter_id')
                    ->orderBy('u_num','DESC')
                    ->get($OrderObj->tableName .' o',[0,10],'count(*) u_num,u.id user_id,u.nickname,u.phone username,u.headimg');

                foreach($OrderInfo as $key=>$val){
                    $OrderInfo[$key]['nickname']=Common::textDecode($val['nickname']);
                }

                //根据用户不同 获取邀请名次
                $listUser=$Redis->sMembers($live_id_key.$live_id);
                $num = 0;
                $user_ranking = 0;
                foreach ($listUser as $key=>$item){
                    $arr = explode(',', $item);
                    $uid = $arr[3];
                    //获取当前用户的邀请人数
                    $user_num=$OrderObj->db
                        ->where('o.type', 10)->where('o.live_id',$live_id)->where('o.status',1)->where('o.twitter_id ',$uid)
                        ->getOne($OrderObj->tableName .' o','count(*) u_num');
                    $num = $user_num['u_num']?$user_num['u_num']:0;
                    if( $num > 0 ){
                        $sql = "select count(*) c from 
                            (SELECT  count(*) u_num FROM nlsg_order o 
                             WHERE  o.type = '10'  AND o.live_id = ?  AND o.status = '1'  AND o.twitter_id != '0'  
                            GROUP BY twitter_id ) d 
                            where u_num >= ?";
                        $UserInfo = $OrderObj->query($sql,[$live_id,$num]);
                        $user_ranking = $UserInfo[0]['c'] ? $UserInfo[0]['c']  : 0;
                    }
                    $content = ['ranking_data'=>$OrderInfo,
                        'ranking' => [
                            "user_ranking" => $user_ranking,
                            "user_num" => $num,
                            "user_id" => $uid,
                            "live_id" => $live_id,
                        ]
                    ];
                    if(!empty($content)){
                        $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 11, 'content' =>[$content] ]);
                        $ListPort = swoole_get_local_ip (); //获取监听ip
                        //推送消息
                        $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id,'',$uid);
                    }
                }


            }
            return [
                'data' => 1,
                'path' => $path
            ];
//            $live_id = Config::getInstance()->getConf('web.live_id_now');

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }



    /**
     * 礼物订单推送  12
     */
    public static function getLiveGiftOrder($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $UserServiceObj=new UserService();
            $OrderObj = new Order();
            $UserObj = new User();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            //print_r($listRst);
            if(empty($listRst)) return '';

            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];

                $OrderInfo=$OrderObj->db
                    ->join($UserObj->tableName . ' u', 'o.user_id=u.id', 'left')
                    ->where('o.type', 5)->where('o.live_id',$live_id)->where('o.status',1)
                    ->where('o.reward_num',0,'>')->where('is_live_order_send',0) //->where('o.pay_price',1,'>')
                    ->where('o.pay_time',(time()-600),'>')    //查询前面十分钟的，避免历史数据推送
                    ->orderBy('o.id','ASC')
                    ->get($OrderObj->tableName .' o',null,'o.id,u.nickname,o.product_id,o.live_num,o.pay_price,reward,reward_num');
                //echo $OrderObj->getLastQuery();
                if(!empty($OrderInfo)){
                    $res=[];
                    foreach($OrderInfo as &$v){
                        $v['nickname']=Common::textDecode($v['nickname']);
//                        switch ($val['reward']){
//                            case 1: //经营能量
//                                $res[]=$val['nickname'].':您已成功购买'.$val['live_num'].'张经营能量门票';
//                                break;
//                            case 2: //一代天骄
//                                $res[]=$val['nickname'].':您已支付成功一代天骄定金';
//                                break;
//                            case 3: //演说能量
//                                $res[]=$val['nickname'].':您已支付成功演说能量定金';
//                                break;
//                        }
                    }
                    if(!empty($OrderInfo)){
                        //修改标记
                        $idArr=array_column($OrderInfo, 'id');
                        $OrderObj->update($OrderObj->tableName,['is_live_order_send'=>1],['id'=>$idArr]);
                    }

                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 12, 'content' =>$OrderInfo]);

                    $ListPort = swoole_get_local_ip (); //获取监听ip
                    //推送消息
                    $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id);
                }

            }
            return [
                'data' => 1,
                'path' => $path
            ];
//            $live_id = Config::getInstance()->getConf('web.live_id_now');





        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live-V4','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

}
