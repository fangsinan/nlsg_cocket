<?php

namespace App\Lib\Auth;

use App\Lib\Redis\Redis;
use EasySwoole\EasySwoole\Config;
/**
 * sign 加密
 * Class IAuth
 */
class IAuth
{

	/**
	 * 生成每次请求的sign
	 * @param array $data
	 * @return HexString|string
	 */
	public static function setSign($data=[]){ //处理多参数 和 字符串问题
		if(is_array($data)){
			//1 按字段升序排序
			ksort($data);
			//2 拼接字符串 &
			$string=http_build_query($data);
		}else{
			$string=$data;
		}
		//3 通过aes加密
		$string=(new Des())->encrypt($string);
		//4 所有字符转换大写
		//$string=strtoupper($string);

		return $string;
	}

	/**
	 * 检查sign是否正常
	 * @param array $data
	 * @return boolen
	 */
	public static function checkSignPass($data){

		$string=(new Des())->decrypt($data['sign']);
		if(empty($string)){ //解密不成功
//			echo '解密不成功'.PHP_EOL;
			return false;
		}
		//did=xx&app_type=xx
		//将字符串转为数组并复制给$arr
		parse_str($string,$arr);
		/*
		* version 版本号
		* app_type  安卓 ios 微信 请求端
		* did  设备唯一识别号
		* model 手机型号 三星 iphone 8
		*/
		if(!is_array($arr) || empty($arr['did']) || $arr['did']!=$data['did'] || empty($arr['time'])){
//			echo '唯一标记和时间有误'.PHP_EOL;
			return false;
		}
		if(!Config::getInstance()->getConf('web.app_debug')) {
			//限制时间请求   对应前端请求获取服务器时间再发送请求
			if ( (time() - ceil ($arr['time'] / 1000)) > Config::getInstance()->getConf('web.app_sign_time')) { //请求大于10秒验证失败
//				echo '请求超时10s'.PHP_EOL;
				return false;
			}

			//接口一次性请求
			$sign_data='Des_'.md5($data['sign']);
			$RedisObj=new Redis();
			$sign_flag = $RedisObj->get($sign_data);
			if ( $sign_flag ) {
//				echo '触碰一次性请求'.PHP_EOL;
				return false;
			}
		}
		return true;
	}

	/**
	 * 设置密码
	 */
	public static function setPassword($data) {
		return  md5($data.Config::getInstance()->getConf('web.app_password_halt'));
	}

	/**
	 * 设置登录的token  - 唯一性的
	 * @param string $phone
	 * @return string
	 */
	public  static function setAppLoginToken($phone = '') {
		$str = md5(uniqid(md5(microtime(true)), true));
		$str = sha1($str.$phone);
		return $str;
	}

}
