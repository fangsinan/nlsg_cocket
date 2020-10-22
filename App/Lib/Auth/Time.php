<?php
namespace App\Lib\Auth;

/**
 * 时间
 * Class Time
 */
class Time {

    /**
     * 获取13位时间戳
     * @return int
     */
    public static function get13TimeStamp() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    public static function microtime_format($tag,$time)
    {
        $time=$time/1000;
        list($usec, $sec) = explode(".", $time);
        $date = date($tag,$usec);
        return str_replace('x', $sec, $date);
    }

}