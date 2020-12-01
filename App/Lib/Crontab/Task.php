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
use App\Model\V1\LiveCommentModel;
use App\Model\V1\LiveForbiddenWordsModel;
use App\Model\V1\LiveInfo;
use App\Model\V1\LiveNotice;
//use App\Model\User;
use App\Model\V1\LiveNumberModel;
use App\Model\V1\LivePush;
use App\Model\V1\User;
use App\Model\V1\Order;
use App\Model\V1\Works;
use App\Model\V1\WorksInfo;
use App\Services\V1\PushService;
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

    //生成在线人数
    public function onlineNumber($taskId, $fromWorkerId,$data,$path){

        try {
            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $live_id_num=Config::getInstance()->getConf('web.live_redis_number');
            $Redis = new Redis();

            //获取所有在线直播id
//            keys 11_live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(!empty($listRst)){
                $LiveModel=new LiveNumberModel();
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    $num=$Redis->scard($live_id_key.$live_id); //获取成员数据
                    $Redis->set($live_id_num.$live_id,$num,3600); //设置在线人数
                    if($num>100) {
                        //实时数据入库
                        $LiveModel->add(LiveNumberModel::$table,['live_id'=>$live_id,'count'=>$num,'time'=>time()]);
                    }
                }
            }
            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //加入直播间
    public function Joinlive($taskId, $fromWorkerId,$data,$path){

        try {

            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $live_join=Config::getInstance()->getConf('web.live_join');
            $Redis = new Redis();

            $ListPort = swoole_get_local_ip (); //获取监听ip
            $PushServiceObj=new PushService();

            //获取所有在线直播id
//            keys 11_live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(!empty($listRst)){ //获取直播间
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    $list=$Redis->lrange($live_join.$live_id,0,-1);// 获取所有数据
                    if(!empty($list)){
                        $start=0;
                        $arr=[];
                        foreach ($list as $key=>$val){
                            $start=$key;
//                            $arr[]=$val;
                            $arr[]=json_decode($val,true);
                        }
                        $Redis->ltrim($live_join.$live_id,$start+1,-1);//删除已取出数据
                        $list=$data = Common::ReturnJson(Status::CODE_OK,'进入直播间',['type' => 5, 'content_arr' => $arr,]);;
                        $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$list);
                    }
                }
            }

            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //广播评论
    public function Comment($taskId, $fromWorkerId,$data,$path){
        try {

            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $live_comment=Config::getInstance()->getConf('web.live_comment');
            $Redis = new Redis();

            $ListPort = swoole_get_local_ip (); //获取监听ip
            $PushServiceObj=new PushService();

            //获取所有在线直播id
//            keys 11_live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(!empty($listRst)){ //获取直播间
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    $list=$Redis->lrange($live_comment.$live_id,0,-1);// 获取所有数据
                    if(!empty($list)){
                        $arr=[];
                        $start=0;
                        foreach ($list as $key=>$val){
                            $start=$key;
                            //$arr[]=$val;
                            $arr[]=json_decode($val,true);
                        }
                        $list=Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 2, 'content_arr' => $arr,]);
                        $Redis->ltrim($live_comment.$live_id,$start+1,-1);//删除已取出数据
                        $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$list);
                    }
                }
            }

            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    /**
     * 礼物推送  12
     */
    public static function getLiveGiftOrder($taskId, $fromWorkerId,$data,$path){

        try {

            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $live_gift=Config::getInstance()->getConf('web.live_gift');
            $Redis = new Redis();

            $ListPort = swoole_get_local_ip (); //获取监听ip
            $PushServiceObj=new PushService();

            //获取所有在线直播id
//            keys 11_live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(!empty($listRst)){ //获取直播间
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    $list=$Redis->lrange($live_gift.$live_id,0,-1);// 获取所有数据
                    if(!empty($list)){
                        $arr=[];
                        $start=0;
                        foreach ($list as $key=>$val){
                            $start=$key;
//                            $arr[]=$val;
                            $arr[]=json_decode($val,true);
                        }
                        $list=Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 12, 'content_arr' => $arr,]);
                        $Redis->ltrim($live_gift.$live_id,$start+1,-1);//删除已取出数据
                        $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$list);
                    }
                }
            }

            return [
                'data' => 1,
                'path' => $path
            ];

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //发送公告 7
    public function pushNotice($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $noticeObj  = new LiveNotice();
            $PushServiceObj=new PushService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $idArr=[];
            $ListPort = swoole_get_local_ip (); //获取监听ip
            foreach($listRst as $key => $val){
                $arr = explode ('_', $val);
                $live_id=$arr[2];
                $noticeList = $noticeObj->get($noticeObj->tableName,['live_id'=>$live_id,'is_send'=>1,'is_del'=>0,'is_done'=>0],'id,live_id,live_info_id,content,length,created_at,type');
                if(!empty($noticeList)){
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 7,'ios_content' =>$noticeList[0], 'content_obj' =>$noticeList[0] ]);

                    if(!empty($noticeList)){
                        //修改标记
                        $idArr=array_column($noticeList, 'id');
                        $noticeObj->update($noticeObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$idArr]);
                    }
                    //推送消息
                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);

                }
            }
            return [
                'data' => $idArr,
                'path' => $path
            ];
        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    /**
     * 产品推送  6
     */
    public static function PushProduct($taskId, $fromWorkerId,$data,$path){

        try {
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $Redis = new Redis();
            $pushObj = new LivePush();
            $PushServiceObj=new PushService();

            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            $now = time();
            $ListPort = swoole_get_local_ip (); //获取监听ip
            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];
                $where = [
                    'live_info_id' => $live_id,
                    '(push_at < ?)'=>[date('Y-m-d H:i:s',$now)],
                    'is_push' => 1,
                    'is_done' => 0,
                    'is_del' => 0,
                ];
                $push_info = $pushObj->get($pushObj->tableName,$where,'*');
                if(!empty($push_info)){
                    //多个
                    $res = self::getLivePushDetail($push_info);
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 6, 'content' => $res,'ios_content' =>$res ]);
                    //推送消息
                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);

                }
            }
            return [
                'data' => 1,
                'path' => $path
            ];

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //产品推送扩展
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
            if($val['push_type'] == 1 && !empty($val['push_gid']) ){
                $fields = 'id,name,price,subtitle,cover_pic img,user_id';
                $Info = $colObj->getOne($colObj->tableName,['id'=>$val['push_gid'],'status'=>2],$fields);
            }elseif($val['push_type'] == 2 && !empty($val['push_gid']) ){
                $fields = 'id,title name,type,price,cover_img img';
                $Info = $workObj->getOne($workObj->tableName,['id'=>$val['push_gid'],'status'=>4],$fields);
                $WorkInfoData=$WorkInfoObj->getOne($WorkInfoObj->tableName,['pid'=>$val['push_gid'],'status'=>4],'id',['`order`'=>0]);
                $Info['workinfo_id']=$WorkInfoData['id'];
            }else if($val['push_type'] == 3 && !empty($val['push_gid'])){
                $fields = 'id,name,price,subtitle,picture img';
                $Info = $goodsObj->getOne($goodsObj->tableName,['id'=>$val['push_gid'],'status'=>2],$fields);
            }else if($val['push_type'] == 4){ //线下门票
                $fields = 'id,title name,price,subtitle,cover_img img,image';
                $Info = $goodsObj->getOne('nlsg_offline_products',['id'=>$val['push_gid']],$fields);
            }else if($val['push_type'] == 6){
                $Info=[
                    'name'=>'幸福360会员',
                    'price'=>360,
                    'subtitle'=>'',
                    'image'=>'/nlsg/works/20201124144228445466.png', //大图
                    'img'=>'/nlsg/works/20201124144228445465.png'
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
            $PushServiceObj=new PushService();
            $OrderObj =new Order();
            $UserObj = new User();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $ListPort = swoole_get_local_ip (); //获取监听ip
            foreach($listRst as $k => $v) {
                $arr = explode('_', $v);
                $live_id = $arr[2];

                $OrderInfo=$OrderObj->db
                    ->join($UserObj->tableName . ' u', 'o.user_id=u.id', 'left')
                    ->where('o.live_id',$live_id)->where('o.type', [14,16],'in')->where('o.status',1)
                    ->where('is_live_order_send',0) //->where('o.pay_price',1,'>')
                    ->orderBy('o.id','ASC')
                    ->get($OrderObj->tableName .' o',null,'o.id,u.nick_name,o.product_id,o.live_num,o.pay_price');

                if(!empty($OrderInfo)){
                    $res=[];
                    foreach($OrderInfo as $key=>$val){
                        $val['nick_name']=Common::textDecode($val['nick_name']);
                        switch ($val['product_id']){
                            case 0://360
                                $res[]=$val['nick_name'].':您已成功购买幸福360会员';
                                break;
                            case 1: //经营能量
                                $res[]=$val['nick_name'].':您已成功购买'.$val['live_num'].'张经营能量门票';
                                break;
                            case 2: //一代天骄
                                $res[]=$val['nick_name'].':您已支付成功一代天骄定金';
                                break;
                            case 3: //演说能量
                                $res[]=$val['nick_name'].':您已支付成功演说能量定金';
                                break;
                            case 4: //幸福套餐
                                $res[]=$val['nick_name'].':您已支付成功幸福套餐';
                                break;
                        }
                    }
                    if(!empty($OrderInfo)){
                        //修改标记
                        $idArr=array_column($OrderInfo, 'id');
                        $OrderObj->update($OrderObj->tableName,['is_live_order_send'=>1],['id'=>$idArr]);
                    }

                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 10, 'content' =>$res,'content_one_array' =>$res]);

                    //推送消息
                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);

                }

            }
            return [
                'data' => 1,
                'path' => $path
            ];

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //直播开始|结束
    public function pushEnd($taskId, $fromWorkerId,$data,$path){

        try {
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $liveObj=new LiveInfo();
            $PushServiceObj = new PushService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $ListPort = swoole_get_local_ip(); //获取监听ip
            $time=time();
            foreach($listRst as $key => $val){
                $arr = explode ('_', $val);
                $live_id=$arr[2];
                //$liveInfo=$liveObj->getOne($liveObj->tableName,['id'=>$live_id],'id,status,end_time,is_begin,is_begin_time,is_end_time');
                $liveInfo=$liveObj->getOne($liveObj->tableName,['id'=>$live_id],'id,status,end_at,is_begin,begin_at');
                //echo $liveObj->getLastQuery();
                if(!empty($liveInfo)){
                    $is_push=0;
                    if($liveInfo['is_begin']==1 && empty($liveInfo['begin_at'])){ //开始直播
                        $is_push=1;
                        $liveObj->update($liveObj->tableName,['begin_at'=>date("Y-m-d H:i:s",$time)],['id'=>$live_id]);
                    }else if($liveInfo['is_begin']==0 && $liveInfo['status']==2 && empty($liveInfo['end_at'])){
                        $is_push=1;
                        $liveObj->update($liveObj->tableName,['end_at'=>date("Y-m-d H:i:s",$time)],['id'=>$live_id]);
                    }
                    if($is_push) {
                        $live_info = [
                            'id' => $live_id,
                            'is_begin' => $liveInfo['is_begin'], //is_begin=0  status=2  直播结束      is_begin=1
                            'status' => $liveInfo['status'],
                        ];
                        //推送记录
                        $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 8, 'content_obj' => $live_info,'ios_content' => $live_info ]);
                        $PushServiceObj->pushMessage($ListPort['eth0'], $live_id, $data);
                    }

                }
            }
            return [
                'data' => 0,
                'path' => $path
            ];

        }catch (\Exception $e){
            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
            //短信通知
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }

    //禁言  9
    public function pushForbiddenWords($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $forbiddenObj = new LiveForbiddenWordsModel();
            $PushServiceObj = new PushService();
            $Redis = new Redis();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');

            if(empty($listRst)) return '';
            $ListPort = swoole_get_local_ip(); //获取监听ip
            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];
                $time=time();

                //是否全场禁言  有且仅有一条
                $forbidden = $forbiddenObj->getOne(LiveForbiddenWordsModel::$table,['live_id'=>$live_id,'user_id'=>0],'id,user_id,is_forbid,forbid_ctime,forbid_time');
                if(!empty($forbidden)){ //操作的是一条记录
                    if($forbidden['is_forbid']==2){//user_id  0时 为直播间全员控制 is_forbid 1 禁言 2解禁 forbid_ctime 开始禁言时间 forbid_time 禁言时长
                        $forbiddenObj->getDb()->where('id',$forbidden['id'])->delete($forbiddenObj::$table); //防止一直推送
                        $all_res = [
                            'user_id' => 0,
                            'is_forbid' => 2,
                            'forbid_ctime' => 0,
                            'forbid_time' => 0
                        ];
                    }else {
                        //推送禁言状态
                        $all_res = [
                            'user_id' => 0,
                            'is_forbid' => $forbidden['is_forbid'],
                            'forbid_ctime' => $forbidden['forbid_ctime'],
                            'forbid_time' => $forbidden['forbid_time']
                        ];
                    }
                    $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 9, 'content_obj' =>$all_res,'ios_content' => $all_res ]);
                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);
                    continue;

                }

                //个人禁言
                $forbidden = $forbiddenObj->get(LiveForbiddenWordsModel::$table,['live_id'=>$live_id,'is_forbid'=>1],'id,user_id,is_forbid,forbid_ctime,forbid_time');
                $idArr=[];
                if(!empty($forbidden) ) {
                    foreach ($forbidden as $k=>$v) {
                        //  禁言时间 + 禁言时长 - 当前时间  大于0(禁言中)  否则0
                        $forbid_time = ($v['forbid_ctime'] + $v['forbid_time']) - $time;
                        if ($forbid_time <=0) {
                            //记录已解除禁言用户
                            $forbiddenObj->update($forbiddenObj::$table,['is_forbid'=>2,'forbid_ctime'=>0,'forbid_time'=>0],['id'=>$v['id']]);
                            $res = [
                                'user_id' => $v['user_id'],
                                'is_forbid' => 2,   //禁言
                                'forbid_ctime' => 0, //开始时间
                                'forbid_time' => 0, //时长
                            ];
                        }else {
                            $res = [
                                'user_id' => $v['user_id'],
                                'is_forbid' => $v['is_forbid'],   //禁言
                                'forbid_ctime' => $v['forbid_ctime'], //开始时间
                                'forbid_time' => $v['forbid_time'], //时长
                            ];
                        }
                        //推送记录
                        $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 9, 'content_obj' =>$res,'ios_content' => $res ]);
                        //推送消息
                        $PushServiceObj->PushForbid($live_id,$v['user_id'],$data);

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
            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);
        }

    }



}
