<?php
/**
 * Created by PhpStorm.
 * User: xuguang
 * Date: 2016/10/22
 * Time: 13:52
 */

namespace App\Utility\Tools;

use App\Lib\Cache\Cache;
use EasySwoole\EasySwoole\Config;
/**
 * Class share
 * @package common\helps
 * 分享
 */
class Share {

    //获取分享所需数据
    public static function GetWechatShare($type,$url,$coupon_title,$coupon_content,$shareimg,$share_url){

        $jsapi_ticket=Cache::Cache(2,'swoole_wechat_ticket_qc');
        if(empty($jsapi_ticket)){
            $jsapi_ticket=self::get_ticket();
        }
        // 签名
        $TIMESTAMP=time();
        $noncestr=Tool::Randstr(16);
        $signature = 'jsapi_ticket=' . $jsapi_ticket . '&noncestr=' .$noncestr. '&timestamp=' . $TIMESTAMP . '&url=' . $url;
//        echo $signature.PHP_EOL;
        $signature = sha1($signature);

        $wechatinfo=array(
            'APP_ID'=>Config::getInstance()->getConf('web.wechat.appid'),
//            'APP_SECRET'=>Config::getInstance()->getConf('web.wechat.appsecret'),
            'TIMESTAMP'=>$TIMESTAMP,
            'NONCESTR'=>$noncestr,
            'signature'=>$signature,
        );

        if($type==1){ //分享
            $wechatinfo['title']=$coupon_title;
            $wechatinfo['content']=$coupon_content;
            $wechatinfo['img_url']=$shareimg;
            $wechatinfo['share_url']=$share_url;
        }


        return $wechatinfo;

    }

    /**
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141115
     * @param $file
     * @return mixed
     * 每天限制 500万请求  可缓存
     * jsapi_ticket是公众号用于调用微信JS接口的临时票据。正常情况下，jsapi_ticket的有效期为7200秒，通过全局access_token来获取
     * {
    "errcode":0,
    "errmsg":"ok",
    "ticket":"bxLdikRXVbTPdHSM05e5u5sUoXNKd8-41ZO3MhKoyN5OfkWITDGgnr2fwJ0m9E8NYzWKVZvdVtaUgWvsdshFKA",
    "expires_in":7200
    }
     */
    //暂时由wechat项目生成  对应2.1版本model WxShare文件
    public static function get_ticket()
    {

        $Access_Token=Cache::Cache(2,'swoole_wechat_access_token_qc');  //获取redis保存的基础token

        // 获取jsapi_ticket
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $Access_Token . '&type=jsapi';
        $content = Tool::curlPost($url);
        $obj = json_decode($content);
        $jsapi_ticket = $obj->ticket;

        Cache::Cache(2,'swoole_wechat_ticket_qc',$jsapi_ticket,3600); //设置redis缓存

        return $jsapi_ticket;
    }

}