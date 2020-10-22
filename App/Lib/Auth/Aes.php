<?php

namespace App\Lib\Auth;

use EasySwoole\EasySwoole\Config;
/**
 * aes 加密 解密类库
 * Class Aes
 */
class Aes
{

	private $key = null;
	private $method='AES-256-CBC';
	private $iv=null;
	/**
	 * @param $key 		密钥
	 * @return String
	 */

	public function __construct() {
		// 需要配置文件app.php中定义aeskey
		$this->key = Config::getInstance()->getConf('web.app_aeskey');
		$this->iv = Config::getInstance()->getConf('web.app_iv');
	}

	/**
	 * 加密
	 * @param String input 加密的字符串
	 * @param String key   解密的key
	 * @return HexString
	 */
	public function encrypt($input = '') {

		//全是后端可以用内部提供
//		$ivLength = openssl_cipher_iv_length($this->method); //获取该加密算法iv应该具有的长度
//		$iv=openssl_random_pseudo_bytes($ivLength);
		$data=openssl_encrypt($input, $this->method, $this->key, 0, $this->iv);
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
		$return=openssl_decrypt($input, $this->method, $this->key, 0, $this->iv);
		return $return;

	}

}
