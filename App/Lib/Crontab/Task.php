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
use App\Model\V1\Live;
use App\Model\V1\LiveCommentModel;
use App\Model\V1\LiveForbiddenWordsModel;
use App\Model\V1\LiveInfo;
use App\Model\V1\LiveLoginModel;
use App\Model\V1\LiveNotice;
//use App\Model\User;
use App\Model\V1\LiveNumberModel;
use App\Model\V1\LiveOnlineUser;
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
//            $listRst=$Redis->keys($live_id_key.'*');

            //查询直播中
            $LiveInfoObj=new LiveInfo();
            $listRst = $LiveInfoObj->db->where('is_begin',1)->get($LiveInfoObj->tableName, [0,10],'id,live_pid');

            if(!empty($listRst)){
//                $LiveModel=new LiveNumberModel();
                $LiveObj=new Live();
                $LiveLoginObj=new LiveLoginModel();
                foreach ($listRst as $val){
//                    $arr = explode ('_', $val);
//                    $live_id=intval($arr[2]);
                    $live_id=$val['live_pid'];

                    //人气值改版
                    $NumInfo=$LiveLoginObj->db->where('live_id',$live_id)->get($LiveLoginObj::$table,[0,1],'count(id) counts');
                    if(!empty($NumInfo[0])){
                        $num=($NumInfo[0]['counts']);
                        $Liveinfo = $LiveObj->db->where('id',$live_id)->getOne($LiveObj->tableName, 'virtual_online_num');
                        $num=$num+$Liveinfo['virtual_online_num'];
                        $Redis->set($live_id_num.$live_id,$num,3600*4); //设置在线人数
                    }

                    //实时在线人数socket->fd
                    /*$num=$Redis->scard($live_id_key.$live_id); //获取成员数据
                    $Liveinfo = $LiveObj->db->where('id',$live_id)->getOne($LiveObj->tableName, 'virtual_online_num');
                    $num=$num+$Liveinfo['virtual_online_num'];
                    $Redis->set($live_id_num.$live_id,$num,3600); //设置在线人数
                    
                    $Liveinfo = $LiveInfoObj->db->where('id',$live_id)->getOne($LiveInfoObj->tableName, 'is_begin');
                    if(!empty($Liveinfo['is_begin'])) { //直播中
                        //数据入库
                        $LiveModel->add(LiveNumberModel::$table, ['live_id' => $live_id, 'count' => $num, 'time' => time()]);
                    }*/

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
            $listRst=$Redis->keys($live_join.'*');
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
                            if($key<2) { //防止高并发加入直播间人数较多，丢弃一部分最多返回5条减轻压力
                                $arr[] = json_decode($val, true);
                            }
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

    //广播加入直播redis
    public function JoinRedis($taskId, $fromWorkerId,$data,$path){
        try {

            //获取redis
            $ListPort = swoole_get_local_ip (); //获取监听ip
            $ip=$ListPort['eth0'];
            $ip_str=str_replace(".","_",$ip);
            $key_name="1111livejoin:".$ip_str.":"; //1111livejoin:172.17.212.131:19

            $Redis = new Redis();
            $listRst=$Redis->keys($key_name.'*');
            if(!empty($listRst)) { //获取直播间
                foreach ($listRst as $val) {
                    $arr = explode(':', $val);
                    $live_id = $arr[2];

                    $list=$Redis->lrange($key_name.$live_id,0,-1);// 获取所有数据
                    if(!empty($list)){
                        $arr=[];
                        $count=count($list);
                        foreach ($list as $key=>$val){
                            if($key<5) { //防止高并发加入过度，丢弃一部分最多返回5条减轻压力
                                $arr[] = json_decode($val, true);
                            }else{
                                break;
                            }
                        }
//                        if($live_id!=19) {
                            $Redis->ltrim($key_name . $live_id, $count, -1);//删除已取出数据   保留指定区间内的元素，不在指定区间之内的元素都将被删除
//                        }
                        $list = Common::ReturnJson(Status::CODE_OK,'进入直播间',['type' => 5, 'content_arr' => $arr,]);;
                        $data=[
                            'live_id'=>$live_id,
                            'data'=>$list
                        ];
                        PushService::Broadcast($ListPort['eth0'],$data);
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

    //广播评论redis
    public function CommentRedis($taskId, $fromWorkerId,$data,$path){
        try {

            //获取redis
            $ListPort = swoole_get_local_ip (); //获取监听ip
            $ip=$ListPort['eth0'];
            $ip_str=str_replace(".","_",$ip);
            $key_name="1111livecomment:".$ip_str.":"; //1111livecomment:172_17_212_131:19

            $Redis = new Redis();
            $listRst=$Redis->keys($key_name.'*');
            if(!empty($listRst)) { //获取直播间
                foreach ($listRst as $val) {
                    $arr = explode(':', $val);
                    $live_id = $arr[2];

                    $list=$Redis->lrange($key_name.$live_id,0,-1);// 获取所有数据
                    if(!empty($list)){
                        $arr=[];
                        $start=0;
                        foreach ($list as $key=>$val){
                            $start=$key;
                            if($key<10) { //防止高并发评论过度，丢弃一部分评论最多返回5条减轻压力
                                $arr[] = json_decode($val, true);
                            }
                        }
                        $list=Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 2, 'content_arr' => $arr,]);
//                        if($live_id!=19) {
                            $Redis->ltrim($key_name . $live_id, $start + 1, -1);//删除已取出数据   保留指定区间内的元素，不在指定区间之内的元素都将被删除
//                        }
                        $data=[
                            'live_id'=>$live_id,
                            'data'=>$list
                        ];
                        PushService::Broadcast($ListPort['eth0'],$data);
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
            $listRst=$Redis->keys($live_comment.'*');
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
                            if($key<10) { //防止高并发评论过度，丢弃一部分评论最多返回5条减轻压力
                                $arr[] = json_decode($val, true);
                            }
                        }
                        $list=Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 2, 'content_arr' => $arr,]);

                        $Redis->ltrim($live_comment.$live_id,$start+1,-1);//删除已取出数据   保留指定区间内的元素，不在指定区间之内的元素都将被删除
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
                            $arr=[];
                            $arr[]=json_decode($val,true);//只返回一条
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
                $noticeList = $noticeObj->get($noticeObj->tableName,['live_info_id'=>$live_id,'type'=>1, 'is_send'=>1,'is_del'=>0,'is_done'=>0],'id,live_id,live_info_id,content,length,created_at,type,content_type');
                if(!empty($noticeList)){
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 7,'ios_content' =>$noticeList[0], 'content_obj' =>$noticeList[0]]);
                    //修改标记
                    $idArr=array_column($noticeList, 'id');
                    $noticeObj->update($noticeObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$idArr]);

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



    //发送笔记 13
    public function pushNoticeType($taskId, $fromWorkerId,$data,$path){

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
                $noticeList = $noticeObj->get($noticeObj->tableName,['live_info_id'=>$live_id,'type'=>2, 'is_send'=>1,'is_done'=>0],'id,live_id,live_info_id,content,length,created_at,type,content_type,is_del');
                if(!empty($noticeList)){
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 13,'ios_content' =>$noticeList, 'content' =>$noticeList, ]);
                    //修改标记
                    $idArr=array_column($noticeList, 'id');
                    $noticeObj->update($noticeObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$idArr]);

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
            $Redis = new Redis();
            $pushObj = new LivePush();

            //获取所有在线直播id
            $ListPort = swoole_get_local_ip (); //获取监听ip
            $ip_str=str_replace(".","_",$ListPort['eth0']);
            $push_key_name='111Productlivepush:'.$ip_str . ':';
            $listRst=$Redis->keys($push_key_name.'*');

            $time=date('Y-m-d H:i:s');
            foreach($listRst as $key => $val) {

                $PushFlagInfo=$Redis->get($val);
                if(!empty($PushFlagInfo)){
                    $PushFlagArr=json_decode($PushFlagInfo,true);
                    if($PushFlagArr['status']==0){ //标记已扫描
                        $data=json_encode(['status'=>1,'time'=>$time]);
                        $Redis->setex($val,3600*5,$data);
                    }else{
                        continue;
                    }
                }

                $arr = explode(':', $val);
                $push_id = $arr[2];

                $where = [
                    'id' => $push_id,
                ];
                $field = 'id,live_id,live_info_id,push_type,push_gid,user_id,click_num,close_num,is_push,is_done,length';
                $push_info = $pushObj->getOne($pushObj->tableName,$where,$field);
                if(!empty($push_info)){
                    $res = self::getLivePushDetail($push_info);
                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 6, 'content' => $res,'ios_content' =>$res ]);
                    //推送消息
//                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);

                    $data=[
                        'live_id'=>$push_info['live_info_id'],
                        'data'=>$data
                    ];
                    PushService::Broadcast($ListPort['eth0'],$data);
                    $Redis->del($val); //清空执行成功标记
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
    public static function getLivePushDetail($val){
        //获取产品信息
        $res=[];
        //push_type 产品type  1专栏 2精品课 3商品 4 经营能量 5 一代天骄 6 演说能量 7:讲座 8:听书  9直播   10 直播外链  11 训练营
        //push_gid 推送产品id，专栏id  精品课id  商品id
        $show_address = 0;
        if(($val['push_type'] == 1 or $val['push_type'] == 7 or $val['push_type'] == 11) && !empty($val['push_gid']) ){
            $fields = 'id,name,price,subtitle,details_pic img,user_id,details_pic as image';
            $colObj   = new Column();
            $Info = $colObj->getOne($colObj->tableName,['id'=>$val['push_gid'],'status'=>1],$fields);
        }elseif(($val['push_type'] == 2 or $val['push_type'] == 8) && !empty($val['push_gid']) ){
            $fields = 'id,title name,type,price,detail_img img,detail_img as image';
            $workObj  = new Works();
            $WorkInfoObj=new WorksInfo();
            $Info = $workObj->getOne($workObj->tableName,['id'=>$val['push_gid'],'status'=>4],$fields);
            $WorkInfoData=$WorkInfoObj->getOne($WorkInfoObj->tableName,['pid'=>$val['push_gid'],'status'=>4],'id',['`rank`'=>0]);
            $Info['workinfo_id']=$WorkInfoData['id'];
        }else if($val['push_type'] == 3 && !empty($val['push_gid'])){
            $fields = 'id,name,price,subtitle,picture img,picture image';
            $goodsObj = new Goods();
            $Info = $goodsObj->getOne($goodsObj->tableName,['id'=>$val['push_gid'],'status'=>2],$fields);
        }else if($val['push_type'] == 4){ //线下门票
            // $fields = 'id,title name,price,subtitle,image img,cover_img image,show_address';
            $fields = 'id,title name,price,subtitle,image img,image,show_address';
            $goodsObj = new Goods();
            $Info = $goodsObj->getOne('nlsg_offline_products',['id'=>$val['push_gid']],$fields);
        }else if($val['push_type'] == 6){
            $Info=[
                'name'=>'幸福360会员',
                'price'=>360,
                'subtitle'=>'',
                'image'=>'/nlsg/works/20201124144228445465.png', //方图
                'img'=>'/nlsg/works/20201124144228445466.png'  //长图
            ];
        }else if($val['push_type'] == 9){
            $fields = 'id, title name, `describe` subtitle, cover_img img,cover_img image,begin_at, end_at, user_id, price, is_free';
            $liveObj = new Live();
            $Info = $liveObj->getOne($liveObj->tableName,['id'=>$val['push_gid'],'status'=>4,'is_del'=>0],$fields); //,'is_test'=>0
            $live_Info = $liveObj->getOne("nlsg_live_info",['live_pid'=>$val['push_gid']],"id");
            $Info['live_info_id'] = $live_Info['id'];
            $Info['show_address']=1;//配合渠道推购物车出现填地址入口
        }else if($val['push_type'] == 10){ //外链
            $fields = 'id, name, `describe`, `url`,image,img';
            $liveObj = new Live();
            $Info = $liveObj->getOne('nlsg_live_url',['id'=>$val['push_gid']],$fields);
        }else if($val['push_type'] == 12){ //上传二维码弹窗
            $fields = 'id, qr_url';
            $liveObj = new Live();
            $qr_code = $liveObj->getOne('nlsg_live_push_qrcode',['id'=>$val['push_gid']],$fields);

            $Info=[
                'name'      =>  '二维码弹窗',
                'price'     =>  0,
                'subtitle'  =>  '',
                'image'     =>  $qr_code['qr_url'],
                'img'       =>  $qr_code['qr_url'],
            ];

        }else if($val['push_type'] == 13){ //幸福学社
            $liveObj = new Live();
            $message_info = $liveObj->getOne('nlsg_config',['id'=>90,],"value");
            // $Info = json_decode($message_info,true);
			$Info = json_decode($message_info['value'],true);
        }
        if(!empty($Info)){
            // 线下产品显示填写地址按钮
            if(empty($Info['show_address'])){
                $Info['show_address'] = $show_address;
            }

            $res[]= [
                'push_info' => $val,
                'son_info' => $Info,
            ];
        }

        //修改标记
        $LivePushObj=new LivePush();
        $LivePushObj->update($LivePushObj->tableName,['is_done'=>1,'done_at'=>date('Y-m-d H:i:s',time())],['id'=>$val['id']]);

        return $res;
    }

    /**
     * 订单推送  10
     */
    public static function getLivePushOrder($taskId, $fromWorkerId,$data,$path){

        try {

            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $PushServiceObj=new PushService();
            $Redis = new Redis();
            $infoObj = new LiveInfo();
            //获取所有在线直播id
//            keys live_key_*
            $listRst=$Redis->keys($live_id_key.'*');
            if(empty($listRst)) return '';
            $ListPort = swoole_get_local_ip (); //获取监听ip
            foreach($listRst as $k => $v) {
                $arr = explode('_', $v);
                $live_id = $arr[2];
                $info = $infoObj->getOne($infoObj->tableName,['id'=>$live_id],'live_pid');
                $key_name = 'laravel_database_live_PushOrder_'.$info['live_pid'];

                $list=$Redis->lrange($key_name,0,-1);// 获取所有数据
                if(!empty($list)){

                    $start=0;
                    $arr=[];
                    foreach ($list as $key=>$val){
                        $start=$key;
                        if($key<10) { //防止高并发，丢弃一部分最多返回5条减轻压力
                            $arr[] = $val;
                        }
                    }

                    $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 10, 'content_one_array' =>$arr]);
                    $Redis->ltrim($key_name,$start+1,-1);//删除已取出数据

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
                $liveInfo=$liveObj->getOne($liveObj->tableName,['id'=>$live_id,'status'=>1],'id,end_at,is_begin,begin_at,begin_status,is_finish');
                if(!empty($liveInfo)){
                    $is_push=0;
                    if($liveInfo['is_begin']==1 && $liveInfo['begin_status'] != $liveInfo['is_begin'] ){ //开始直播

                        /*$IsBeginList=$Redis->get('111live_isbegin_idlist');
                        if(!empty($IsBeginList)){
                            $LiveIDList=json_decode($IsBeginList,true);
                        }
                        if(!isset($LiveIDList[$live_id])){
                            $LiveIDList[$live_id]=0;
                        }
                        $Redis->set('111live_isbegin_idlist',json_encode($LiveIDList),3600*5); //直播间直播中id*/

                        $is_push=1;
                        $liveObj->update($liveObj->tableName,['begin_status'=>$liveInfo['is_begin']],['id'=>$live_id]);
                    }else if($liveInfo['is_begin']==0 && $liveInfo['is_finish']==1 && $liveInfo['begin_status'] != $liveInfo['is_begin'] ){

                        /*$IsBeginList=$Redis->get('111live_isbegin_idlist');
                        if(!empty($IsBeginList)){
                            $LiveIDList=json_decode($IsBeginList,true);
                        }
                        if(isset($LiveIDList[$live_id])){
                            unset($LiveIDList[$live_id]);
                        }
                        $Redis->set('111live_isbegin_idlist',json_encode($LiveIDList),3600*5); //直播间直播中id*/

                        $is_push=1;
                        $liveObj->update($liveObj->tableName,['begin_status'=>$liveInfo['is_begin']],['id'=>$live_id]);
                    }
                    if($is_push) {
                        $live_info = [
                            'id' => $live_id,
                            'is_begin' => $liveInfo['is_begin'], //is_begin=0  is_finish=1  直播结束      is_begin=1
                            'is_finish' => $liveInfo['is_finish'],
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
            $idArr=[];
            foreach($listRst as $key => $val) {
                $arr = explode('_', $val);
                $live_id = $arr[2];
                $time=time();
                /*//是否全场禁言  有且仅有一条
                $forbidden = $forbiddenObj->getOne(LiveForbiddenWordsModel::$table,['live_info_id'=>$live_id,'user_id'=>0],'id,user_id,is_forbid,forbid_at,length');
                if(!empty($forbidden)){ //操作的是一条记录
                    if($forbidden['is_forbid']==2){//user_id  0时 为直播间全员控制 is_forbid 1 禁言 2解禁 forbid_ctime 开始禁言时间 forbid_time 禁言时长
                        $forbiddenObj->getDb()->where('id',$forbidden['id'])->delete($forbiddenObj::$table); //防止一直推送
                        $all_res = [
                            'user_id' => 0,
                            'is_forbid' => 2,
                            'forbid_at' => '',
                            'length' => 0
                        ];
                    }else {
                        //推送禁言状态
                        $all_res = [
                            'user_id' => 0,
                            'is_forbid' => $forbidden['is_forbid'],
                            'forbid_at' => $forbidden['forbid_at'],
                            'length' => $forbidden['length']
                        ];
                    }
                    $data = Common::ReturnJson(Status::CODE_OK, '发送成功', ['type' => 9, 'content_obj' =>$all_res,'ios_content' => $all_res ]);
                    $PushServiceObj->pushMessage($ListPort['eth0'],$live_id,$data);
                    continue;

                }*/
                //个人禁言
                $forbidden = $forbiddenObj->get(LiveForbiddenWordsModel::$table,['live_info_id'=>$live_id,'is_forbid'=>1],'id,user_id,is_forbid,forbid_at,length');
                if(!empty($forbidden) ) {
                    foreach ($forbidden as $k=>$v) {
                        //  禁言时间 + 禁言时长 - 当前时间  大于0(禁言中)  否则0
                        $forbid_time = (strtotime($v['forbid_at']) + $v['length']) - $time;
                        if ($forbid_time <=0) {
                            //记录已解除禁言用户
                            $forbiddenObj->update($forbiddenObj::$table,['is_forbid'=>2,'forbid_at'=>null,'length'=>0],['id'=>$v['id']]);
                            $res = [
                                'user_id' => $v['user_id'],
                                'is_forbid' => 2,   //禁言
                                'forbid_at' => '', //开始时间
                                'length' => 0, //时长
                            ];
                        }else {
                            $res = [
                                'user_id' => $v['user_id'],
                                'is_forbid' => $v['is_forbid'],   //禁言
                                'forbid_at' => $v['forbid_at'], //开始时间
                                'length' => $v['length'], //时长
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


    /**-----------------暂时废弃-------------------**/
    //抓取实时在线人数用户明细  暂时废弃，使用larvael定时任务，方便调试
    public function onlineUser($taskId, $fromWorkerId,$data,$path){

        try {
            //获取redis
            $live_id_key=Config::getInstance()->getConf('web.live_redis_key');
            $Redis = new Redis();

            //获取所有在线直播id
//            keys  live_key_*
            $listRst=$Redis->keys($live_id_key.'*'); //获取多个直播间
            if(!empty($listRst)){
                $key_name='111online_user_list_'.date('YmdHi');
                $now_time=date('Y-m-d H:i:s');
                $online_time_str=substr($now_time,0,16);
                $flag=0;
                $LiveInfoObj=new LiveInfo();
                foreach ($listRst as $val){
                    $arr = explode ('_', $val);
                    $live_id=$arr[2];
                    //获取直播间信息
                    $Liveinfo = $LiveInfoObj->db->where('id',$live_id)->getOne($LiveInfoObj->tableName, 'is_begin');
                    if(!empty($Liveinfo['is_begin'])) { //直播中
                        $clients = $Redis->sMembers($live_id_key . $live_id); //获取直播间有序集合
                        if (!empty($clients)) {
                            $flag=1;
                            foreach ($clients as $k => $v) {
                                $user_arr = explode (',', $v); //ip,user_id,fd,live_son_flag
                                $OnlineUserArr=['live_id' => $live_id, 'user_id' => $user_arr[1], 'live_son_flag'=>$user_arr[3],'online_time_str'=>$online_time_str]; //'online_time' => $now_time,
//                                $Redis->rpush ('online_user_list', json_encode($OnlineUserArr)); //从队尾插入  先进先出   全写入一个队列
                                $Redis->sAdd ($key_name, json_encode($OnlineUserArr));
                            }
                        }
                    }
                }
                if($flag==1) {
                    //可执行入库列表   没直播间开播也会插入
                    $Redis->rpush('111online_user_list_in', $key_name); //从队尾插入  先进先出
                }
            }
            Io::WriteFile ('/onlineuser', 'online_user_pull_', 1,2);
            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            Io::WriteFile ('/onlineuser', 'online_user_pull_error',$e->getMessage(),2);
//            $SysArr=Config::getInstance()->getConf('web.SYS_ERROR');
//            Tool::SendSms (["system"=>'live4.0版','content'=>$e->getMessage()], $SysArr['phone'], $SysArr['tpl']);//短信通知
        }

    }

    //入库在线用户数据  暂时废弃，使用larvael定时任务，方便调试
    public function PushLiveUser($taskId, $fromWorkerId,$data,$path){

        try {
            $key_name='111online_user_list_in';
            $Redis = new Redis();
            $num=$Redis->llen($key_name);
            if($num>0) {
                $LiveOnlineUserObj = new LiveOnlineUser();
                $key=$Redis->lPop($key_name); //获取可执行key
                $list = $Redis->sMembers($key);// 获取有序集合
                if (!empty($list)) {
                    $map = [];
                    foreach ($list as $k => $val) {
//                        if(($k+1)%10000==0){
//                            $Redis->srem($key,$val); //删除元素
//                        }
                        $map[] = json_decode($val, true);
                    }
                    if (!empty($map)) {
                        $rst = $LiveOnlineUserObj->add($LiveOnlineUserObj->tableName, $map, 0);
                        if (!$rst) {//执行失败，加入执行队列
                            $Redis->rpush ($key_name, $key); //可执行队列
                            Io::WriteFile ('/onlineuser', 'online_user_', $rst,2);
                        }else{
                            $Redis->del($key); //执行成功删除
                            Io::WriteFile ('/onlineuser', 'online_user_', 1,2);
                        }
                    }
                }
            }

//            $num=$Redis->llen('online_user_list');
//            if (!empty($num) && $num>0) {
//                $map=[];
//                $length=($num>=10000)?10000:$num;
//                for ($n=0;$n<$length;$n++){ //遍历10000条
//                    $val=$Redis->lPop('online_user_list');
//                    $map[]=json_decode($val,true);
//                }
//
//                if(!empty($map)) {
//                    $rst = $LiveOnlineUserObj->add($LiveOnlineUserObj->tableName, $map, 0);
//                    if (!$rst) {//执行失败，回写数据
//                        foreach ($map as $v) {
//                            $Redis->rpush('online_user_list', json_encode($v)); //从队尾插入  先进先出   全写入一个队列
//                        }
//                    }
//                }

            /*for ($n=0;$n<$length;$n++){ //遍历10000条
                $val=$Redis->lPop('online_user_list'); //取出数据
                $data=json_decode($val, true);
                $flag=$LiveOnlineUserObj->db->where('online_time_str',$data['online_time_str'])->where('user_id',$data['user_id'])->where('live_id',$data['live_id'])
                    ->where('live_son_flag',$data['live_son_flag'])->getOne($LiveOnlineUserObj->tableName, 'id');
                if(empty($flag)) { //不存在
                    $rst=$LiveOnlineUserObj->db->insert($LiveOnlineUserObj->tableName,$data);
                    if(!$rst){//执行失败，回写数据
                        $Redis->rpush('online_user_list', $val); //从队尾插入  先进先出   全写入一个队列
                    }
                }
            }*/

//            }
            return [
                'data' => 1,
                'path' => $path
            ];
        }catch (\Exception $e){
            Io::WriteFile ('/onlineuser', 'online_user_error',$e->getMessage(),2);
        }

    }

    /**-----------------暂时废弃-------------------**/

}
