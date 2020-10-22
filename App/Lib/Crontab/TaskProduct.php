<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/12
 * Time: 下午9:42
 */
namespace App\Lib\Crontab;

use App\Lib\Common;
use App\Lib\Message\Status;
use App\Model\V1\Column;
use App\Model\V1\Goods;
use App\Model\V1\LiveInfo;
use App\Model\V1\LivePush;
use App\Model\V1\Works;
use App\Model\V1\WorksInfo;
use App\Services\V1\UserService;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use App\Utility\Tools\Io;
use EasySwoole\EasySwoole\Config;
use App\Lib\Cache\Cache;
use App\Utility\Tools\Tool;
use EasySwoole\EasySwoole\ServerManager;

//linux crontab 定时任务
/**
 * Class TaskOne
 * @package App\Crontab
 *
 * @yearly(0 0 1 1 *)                     每年一次
   @annually(0 0 1 1 *)                   每年一次
   @monthly(0 0 1 * *)                    每月一次
   @weekly(0 0 * * 0)                     每周一次
   @daily(0 0 * * *)                      每日一次
   @hourly(0 * * * *)                     每小时一次

     * *    *    *    *    *
    -    -    -    -    -
    |    |    |    |    |
    |    |    |    |    |
    |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    +---------- month (1 - 12)
    |    |    +--------------- day of month (1 - 31)
    |    +-------------------- hour (0 - 23)
    +------------------------- min (0 - 59)
 *
 */
class TaskProduct extends AbstractCronTask
{

    public static function getRule(): string
    {
        // TODO: Implement getRule() method.
        // 定时周期 （每小时）
//        return '@hourly';

        // 定时周期 （每一分钟一次）
        return '*/1 * * * *';

    }

    public static function getTaskName(): string
    {
        // TODO: Implement getTaskName() method.
        // 定时任务名称
        return 'taskProduct';
    }

    static function run(\swoole_server $server, int $taskId, int $fromWorkerId,$flags = null)
    {
        // TODO: Implement run() method.
        // 定时任务处理逻辑
        self::PushProduct();
    }


    public static $CrontabError='crontab_error_';

    /**
     * 产品推送  6
     */
    public static function PushProduct(){

        try {

            $live_id = Config::getInstance()->getConf('web.live_id_now');
            $now = time();
//            $now_date = strtotime(date('Y-m-d H:i',$now));

            $pushObj = new LivePush();
            $where = [
                'live_id' => $live_id,
//                'push_time' => $now_date,
                '(push_time < ?)'=>[$now],
                'is_push' => 0,
                'is_del' => 0,
            ];
            $push_info = $pushObj->get($pushObj->tableName,$where,'*');
            if(!empty($push_info)){
                //多个
                $res = self::getLivePushDetail($push_info);

                $data = Common::ReturnJson (Status::CODE_OK,'发送成功',['type' => 6, 'content' =>$res]);

                $ListPort = swoole_get_local_ip (); //获取监听ip
                //推送消息
                $UserServiceObj=new UserService();
                $UserServiceObj->pushMessage(0,$data,$ListPort,$live_id);

                //写入redis
                Io::WriteFile ('/Crontab', 'pro_', 1);
            }
        }catch (\Exception $e){
            Io::WriteFile ('', self::$CrontabError,'taskProduct：'.$e->getMessage());
        }


    }

    public static function getLivePushDetail($push_info){

        //获取产品信息
        $res=[];
        $colObj   = new Column();
        $workObj  = new Works();
        $WorkInfoObj=new WorksInfo();
        $goodsObj = new Goods();
        foreach($push_info as $key=>$val){
            //push_type 产品type  1专栏 2精品课 3商品
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
            $LivePushObj->update($LivePushObj->tableName,['is_push'=>1],['id'=>$idArr]);
        }

        return $res;
    }



}