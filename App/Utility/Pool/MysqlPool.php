<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 19/1/11
 * Time: 下午10:44
 */
namespace App\Utility\Pool;
use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\EasySwoole\Config;
class MysqlPool extends AbstractPool
{
    /**
     * 请在此处返回一个数据库链接实例
     * @return MysqlObject
     */
    protected function createObject()
    {
        $conf = Config::getInstance()->getConf("MYSQL");
        $dbConf = new \EasySwoole\Mysqli\Config($conf);
        return new MysqlObject($dbConf);
    }
}