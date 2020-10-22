<?php

namespace App\Lib\Auth;

use EasySwoole\EasySwoole\Config;
use \EasySwoole\Component\Openssl;
/**
 * des 加密 解密类库
 * Class des
 */
class Des
{

	private $method='DES-EDE3';
	private $key = null;
	private $openssl=null;
	/**
	 * @param $key 		密钥
	 * @return String
	 */

	public function __construct() {
		// 需要配置文件app.php中定义deskey
		$this->key = Config::getInstance()->getConf('web.app_deskey');
		$this->openssl = new Openssl($this->key,$this->method);
	}

	/**
	 * 加密
	 * @param String input 加密的字符串
	 * @param String key   解密的key
	 * @return HexString
	 */
	public function encrypt($input = '') {

		$data=  $this->openssl->encrypt($input);
		return base64_encode($data);
	}

	/**
	 * 解密
	 * @param String input 解密的字符串
	 * @param String key   解密的key
	 * @return String
	 */
	public function decrypt($input) {

		$input=base64_decode($input);
		return $this->openssl->decrypt($input);

	}

}
