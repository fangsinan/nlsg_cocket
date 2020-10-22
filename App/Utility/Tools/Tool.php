<?php
/**
 * Created by PhpStorm.
 * User: xuguang
 * Date: 2016/8/10
 * Time: 15:05
 */

namespace App\Utility\Tools;

use App\Crontab\Task;
use App\Model\User\UserCodeModel;
use EasySwoole\EasySwoole\Config;
use App\Lib\Cache\Cache;

require_once(EASYSWOOLE_ROOT . '/vendor/SendSms/TopSdk.php');
require_once(EASYSWOOLE_ROOT . '/vendor/Sms253/ChuanglanSmsHelper/ChuanglanSmsApi.php');

class Tool
{

    private static $_instance = null; //推送信息
    private static $_topclient = null; //短信
    private static $_alibabasend = null; //短信
    private static $_sendsms253 = null; //253短信

    //访问入口 单例模式防止多次实例化
    static public function getInstance($type, &$_this)
    {
        if ($_this == null) {
            switch ($type) {
                case 2:
                    $_this = new \TopClient;
                    break;
                case 3:
                    $_this = new \AlibabaAliqinFcSmsNumSendRequest;
                    break;
                case 4:
                    $_this = new \ChuanglanSmsApi;
                    break;
            }
        }
        return $_this;
    }

    //253营销
    public static function SendSms253($type = 1, $params, $msg, $sendtime = 0)
    {

        $clapi = self::getInstance(4, static::$_sendsms253);
        if ($type == 1) {
            //设置您要发送的内容：其中“【】”中括号为运营商签名符号，多签名内容前置添加提交
            $result = $clapi->sendSMS($params, '【能量时光】' . $msg . '退订回T', $sendtime);
        } else {
            //设置您要发送的内容：其中“【】”中括号为运营商签名符号，多签名内容前置添加提交
//        $msg = '【能量时光】您好,您的注册已经审核通过,您的登录帐号为：{$var},登录密码为：{$var}';
//        $params = '15311111111,李先生,2017-04-12';  //15800000000,1234;13800000000,4321
            $result = $clapi->sendVariableSMS($params, '【能量时光】' . $msg . '退订回T', $sendtime);
        }

        if (!is_null(json_decode($result))) {

            $output = json_decode($result, true);

            if (isset($output['code']) && $output['code'] == '0') {
                $data = ['code' => 200, 'msg' => '发送成功'];
            } else {
                $data = ['code' => 0, 'msg' => $output['errorMsg']];
            }
        } else {
            $data = ['code' => 0, 'msg' => $result];
        }

        return $data;

    }


    //短信发送方法  阿里
    //$rst=\common\helps\tools::SendSms("{\"code\":\"999999\"}",'18810355387','SMS_13041003');   //各控制器调用方法
    /*
    验证码        SMS_70300075 您好，您的验证码是：${code}   SendSms("{\"code\":\"$code\"}",'18810355387','SMS_13041003')
    发货通知     SMS_14265625    SendSms("{\"ordernum\":\"$danhao\",\"date\":\"$time\"}",18810355387, SMS_12987360);
    */

    //短信发送方法
    //var_dump(tools::SendSms (["code"=>888888],18810355387,'SMS_70300075'));
    public static function SendSms($Param, $phone, $tpl)
    {
        $response = SmsDemo::sendSms($phone, $tpl, $Param);
        if (isset($response->Code) && $response->Code == 'OK') {
            return ['status' => true];
        } else {
            return ['status' => false, 'message' => $response->Message];
        }
    }
    //https://dysms.console.aliyun.com/dysms.htm?spm=5176.2020520115.aliyun_sidebar.147.4c4079d6VtRXr4#/dayu/application/list
    /*public static function SendSms($Param,$recNum,$tpl)
    {

        $appkey = "23935528";
        $secret = "088643d60a196193a4247e1d78f52490";
        $c = self::getInstance(2,static::$_topclient);
        $c->appkey = $appkey;
        $c->secretKey = $secret;
        $c->format = "json";
        $req = self::getInstance(3,static::$_alibabasend);
        $req->setSmsFreeSignName("能量时光");  //签名
        $req->setSmsType("normal");  //短信
        $req->setExtend("");   //渗透参数回传

        $req ->setSmsParam( "$Param" );  //替换参数 json
        $req ->setRecNum( "$recNum" );   //手机号  13764196108
        $req ->setSmsTemplateCode( "$tpl" );   //模板id
        $resp = $c ->execute( $req );
//        if($recNum=='18134986566'){
//            echo "<pre>";
//            var_dump($resp);
//            echo "<pre>";
//            exit;
//        }
        if(isset($resp->result->success)){
            return true;
        }else{
            return $resp->sub_msg;
        }

    }*/

    //发送验证码  每个ip每天限制发送10次  一分钟之内不能连续发送
    public static function Sendcode($phone, $ip)
    {

        if (in_array($phone, [18810355389,18810355388,13330202231,15823278387,18810355389,13520624800,18602383058,18810037036])) { //居(15823278387  13330202231)
            $rand = "0000";
            Cache::Cache(1, $phone, $rand, 3600); //设置redis缓存
            return array('status' => 200, 'message' => '发送成功，请在2分钟内完成验证');
        } else {
            $rand     = rand(1000, 9999);
            $time     = time();
            $get_time = Cache::Cache(1, $phone . "_time");
            if (!empty($get_time) && $time - $get_time < 60) {
                return array('status' => 1, 'message' => '请一分钟后再重试！');
            } else {
                Cache::Cache(1, $phone . "_time", $time, 60); //60秒
            }

            $day     = strtotime(date('Y-m-d', $time));
            $CodeObj = new UserCodeModel();
            $whereArr=[
                'mobile'=>$phone,
                'ctime > ? '=>[$day],
                'ip'=>$ip,
            ];
            $num = $CodeObj->whereArr($whereArr)->count(UserCodeModel::$table,'id');
            if (!empty($num) && $num > Config::getInstance()->getConf('web.SendCode')) {
                return array('status' => 1, 'message' => '发送限制!'); //ip发送限制
            }

            //投递异步任务 发送阿里短信
            $taskClass = new Task([
                'class'  => 0,
                'method' => 'sendSms',
                'path'   => [
                    'dir'  => '/Sms',
                    'name' => 'sms_',
                ],
                'data'   => [
                    'param' => ["code" => $rand],
                    'phone' => $phone,
                    'tpl'   => 'SMS_160040116',
                    'ip'    => $ip,
                ]
            ]);
            \EasySwoole\EasySwoole\Swoole\Task\TaskManager::async($taskClass);

            return array('status' => 200, 'message' => '发送成功，请在2分钟内完成验证');


        }

    }

    /**
     * @param $url
     * @param int $flag 1头像默认图 2其他默认图片 3默认空
     * @return string
     */
    public static function getHttpFlag($url,$flag=1)
    {
        $IMAGES_URL = Config::getInstance()->getConf('web.IMAGES_URL');
        if (empty($url)) {
            if($flag==1){
                return $IMAGES_URL .Config::getInstance()->getConf('web.user_default_img');//头像默认图片
            }else if($flag==2){
                return $IMAGES_URL .Config::getInstance()->getConf('web.default_img');//其他默认图片
            }else{
                return '';//默认返回空
            }
        }
        if (stristr($url, 'http://') || stristr($url, 'https://')) {
            return $url;
        } else {
            return $IMAGES_URL . $url;
        }

    }

    /**
     * @param $url
     * @return string
     * 获取课程默认图片
     */
    public static function getCourseImage($url){
        return self::getHttpFlag($url,2);
    }


    //中英文截取
    public static function Substr($str, $num)
    {

        return mb_substr($str, 0, $num, 'utf-8');

    }

    //处理html标签问题  去除标签
    public static function GetHtmlText($content)
    {

        return htmlspecialchars(strip_tags(trim($content)));

    }

    //保留两位小数 价格  折扣
    public static function RetainDecimal($price, $Discount, $flag = 0)
    {
        $price = $price * $Discount;

        if (empty($flag)) {
            //四舍五入保留两位
            return round($price, 2);  //针对商品折扣
        } else {
            //不四舍五入保留两位
            return floor($price * 100) / 100; //针对收益
        }
    }

    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    public static function Randstr($length = 32)
    {
        $charts   = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz0123456789";
        $max      = strlen($charts) - 1;
        $noncestr = "";
        for ($i = 0; $i < $length; $i++) {
            $noncestr .= $charts[mt_rand(0, $max)];
        }


        return $noncestr;

    }

    /**
     * 把用户输入的文本转义（主要针对特殊符号和emoji表情）
     */
    public static function textEncode($str)
    {
        if (!is_string($str)) return $str;
        if (!$str || $str == 'undefined') return '';

        $text    = json_encode($str); //暴露出unicode
        $content = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function ($str) {

            return addslashes($str[0]);//加两次转义  插入数据库的时候会被过滤掉一个\
        }, $text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。

        return json_decode($content);
    }

    /**
     * 解码上面的转义
     */
    public static function textDecode($str)
    {
        $text    = json_encode($str); //暴露出unicode
        $content = preg_replace_callback('/\\\\\\\\/i', function ($str) {
            return '\\';
        }, $text); //将两条斜杠变成一条，其他不动
        return json_decode($content);
    }

    /**
     * @curl抓取页面数据
     * @param $url 访问地址
     * @param null $isPost 是否post
     * @param null $data post数据
     * @return array
     */
    public static function curlPost($url, $data = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        //显示获取的头部信息必须为true否则无法看到cookie
        //curl_setopt($curl, CURLOPT_HEADER, true);
//        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);// 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);// 使用自动跳转
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 获取的信息以文件流的形式返回
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);// 发送一个常规的Post请求
            if (is_array($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));// Post提交的数据包
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);// Post提交的数据包 可以是json数据
            }
        }
        curl_setopt($curl, CURLOPT_COOKIESESSION, true); // 读取上面所储存的Cookie信息
        curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        //curl_setopt($curl, CURLOPT_TIMEOUT, 30);// 设置超时限制防止死循环
        //curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        //curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        $tmpInfo = curl_exec($curl);
        curl_close($curl);
        if (empty($tmpInfo)) {
            return false;
        }
        return $tmpInfo;
    }

    /**
     * post方法发送xml数据
     * @param $url
     * @param $vars
     * @param int $second
     * @param array $aHeader
     * @return bool|mixed
     */
    public static function postXmlCurl($url, $vars, $second = 30, $aHeader = [])
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //以下两种方式需选择一种
        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, EASYSWOOLE_ROOT . '/vendor/api_pay/WxPay_v3.0.9/cert/apiclient_cert.pem'); //提现退款需要
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, EASYSWOOLE_ROOT . '/vendor/api_pay/WxPay_v3.0.9/cert/apiclient_key.pem');


        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, http_build_query($aHeader));
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "curl出错:$error\n<br>";
            curl_close($ch);
            return false;
        }
    }

    /**
     * @param $url
     * @return string
     * 获取相对地址
     */
    public static function getRelativeUrl($url){
        $arr=parse_url($url);
        if(isset($arr['path']) &&$arr['path']){
            return trim($arr['path'],'/');
        }
        return $url;
    }

}
