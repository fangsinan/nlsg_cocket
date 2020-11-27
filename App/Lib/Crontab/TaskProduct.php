<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/12
 * Time: 下午9:42
 */
namespace App\Lib\Crontab;

use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use App\Utility\Tools\Io;

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

            //写入redis
            Io::WriteFile ('/Crontab', 'pro_', 1);

        }catch (\Exception $e){
            Io::WriteFile ('', self::$CrontabError,'taskProduct：'.$e->getMessage());
        }


    }

}