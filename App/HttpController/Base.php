<?php

namespace App\HttpController;

use App\Lib\Auth\IAuth;
use App\Lib\Auth\Des;
use App\Lib\Redis\Redis;
use App\Model\Exception;
use App\Model\UserModel;
use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Config;
use App\Lib\Message\Status;

/**
 * Class Base
 * @package App\HttpController
 */
class Base extends Controller
{

    public $headers = [];
    public $servers = [];
    public $UserInfo = [];
    public $params = [];//请求参数数据

    /**
     * 检查每次app请求的数据是否合法
     */
    public function checkRequestAuth()
    {
        /**
         * headers  头部信息
         * sign 加密串
         * version 版本号
         * app_type  安卓 ios 微信
         * did  设备号唯一识别号
         * model 手机型号 三星 iphone 8
         * os 客户端操作系统
         */

        if ($this->params['api_test'] == 1) {//忽略头部参数
            $headers = [
                'did'             => 'huiyuqiancheng_uniqid',
                'version'         => '2.0',
                'apptype'         => 'live',
                'sign'            => 'aUxCZlp3TXpmMnpjS0xiK2dPRUhZay9TVmppbHNrYjR1V2JnVmdKalRaekpuV2c0R3Zkb0dIL3pEU05ua2YvMw==',
                'accessUserToken' => 'NGxWaGtERGl1c0RsYXZiUGdrVFR0T3plUWpxQkttRjFiYXJ0TmpvUENBSzNWRWtJZTJyR2V3dXlDWXRIK2VuVlRnMko3blc0bEo4PQ==',
            ];
        } else {
            //首先需要获取headers
            $header_arr = $this->request()->getHeaders();
            //排除多余信息
            $headers['did']             = (isset($header_arr['did'][0]) && !empty($header_arr['did'][0])) ? $header_arr['did'][0] : '';
            $headers['version']         = (isset($header_arr['version'][0]) && !empty($header_arr['version'][0])) ? $header_arr['version'][0] : '2.0';
            $headers['apptype']         = (isset($header_arr['apptype'][0]) && !empty($header_arr['apptype'][0])) ? $header_arr['apptype'][0] : 'live';
            $headers['sign']            = (isset($header_arr['sign'][0]) && !empty($header_arr['sign'][0])) ? $header_arr['sign'][0] : '';
            $headers['accessUserToken'] = (isset($header_arr['accessusertoken'][0]) && !empty($header_arr['accessusertoken'][0])) ? $header_arr['accessusertoken'][0] : '';
        }

        // todo
        //sign 加密需要  404 找不到参数
        if (empty($headers['sign'])) {
            return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'sign不存在'];
        }
        if (empty($headers['version'])) {
            return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'version不存在'];
        }
        if (!in_array($headers['apptype'], Config::getInstance()->getConf('web.apptypes'))) {
            return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'app_type不合法'];
        }
        if (empty($headers['did'])) {
            return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'did不存在'];
        }
        if (!in_array($headers['apptype'], Config::getInstance()->getConf('web.not_verifying_apptypes'))) {
            if (empty($headers['model'])) {
                return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'model不存在'];
            }
            if (empty($headers['os'])) {
                return ['code' => Status::CODE_NOT_FOUND, 'msg' => 'os不存在'];
            }
        }
        //校验sign  401
        if (!IAuth::checkSignPass($headers)) {
            return ['code' => Status::CODE_UNAUTHORIZED, 'msg' => '授权码sign失败']; //未授权
        }

        if (!Config::getInstance()->getConf('web.app_debug')) {
            //支持一次性校验
            //1 文件 2 mysql 3 redis  key->value限制为512M
            $sign_cache = 'Des_' .md5($headers['sign']);
            $RedisObj=new Redis();
            $RedisObj->set($sign_cache, 1, Config::getInstance()->getConf('web.app_sign_cache_time')); //20秒过期
        }
        $this->headers = $headers;

        $this->servers = $this->request()->getServerParams();

        return true;

    }

    /**
     * 判断是否登录  403 登录有误
     */
    public function IsLogin($upload = 0, $must = 0)
    {
        $res=$this->getLoginInfo($upload, $must);
        if(Status::isError($res)){
            $this->writeJson($res);
            return false;
        }
        return true;
    }

    /**
     * @param int $upload
     * @param int $must
     */
    public function getLoginInfo($upload = 0, $must = 0){

        $access_user_token = $this->headers['accessUserToken'];
        if (empty($access_user_token)) {
            if ($must === 1) {
                return 0;
            } else {
                return Status::Error(Status::CODE_FORBIDDEN, '您没有登录');
            }
        }
        //根据后端返回的token前端解密$token.'||'.13位时间戳
        //再加密传给后端$token.'||'.13位时间戳
        $access_user_token = (new Des())->decrypt($access_user_token);
        if (empty($access_user_token)) {
            return Status::Error(Status::CODE_FORBIDDEN, '您token信息有误');

        }
        if (!preg_match('/||/', $access_user_token)) {
            return Status::Error(Status::CODE_FORBIDDEN, '您token信息格式有误');

        }
        try {
            list($token, $token_time) = explode('||', $access_user_token);
        } catch (\Exception $e) {
            return Status::Error(Status::CODE_FORBIDDEN, '用户错误');
        }
        if(empty($token)){
            return Status::Error(Status::CODE_FORBIDDEN, '您token信息为空');
        }
        $UserObj  = new UserModel();
        $UserInfo=$UserObj->getOne(UserModel::$table,['token'=>$token],'uid,time_out,role,status,last_login_time,role role_level,avatar,realname,phone');
//        echo $UserObj->db->getLastQuery().PHP_EOL;
        $data['params']=$token;

        if (empty($UserInfo)) { //不是有效用户
            return Status::Error(Status::CODE_FORBIDDEN, '您token信息不存在');

        }
//        echo $token;
        if ($upload === 0) { //防止未登录有上传情况  默认验证用户  0未认证 1审核中 2已通过 3未通过 4禁用
            if ($UserInfo['status']==4) { //in_array($UserInfo['status'], [0,1,3,4])
                return Status::Error(Status::CODE_METHOD_NOT_ALLOWED, '用户未启用');
            }
        }
        $time = time();
        if ($token != 'd30e848a9597d546f968af8ea808fcf76dfd2373') { //18810355387 测试账号
            if ($time > $UserInfo['time_out']) { //时间已过期
                return Status::Error(Status::CODE_FORBIDDEN, '登录状态已过期');
            }
        }

        if ($time - $UserInfo['last_login_time'] > 86400) { //每天刷新
            $time_out = strtotime('+' . Config::getInstance()->getConf('web.login_time_out_days') . 'days');//更新过期时间
            $UserObj->update(UserModel::$table,['time_out' => $time_out, 'last_login_time' => $time],['token'=> $token]);
//            echo $UserObj->db->getLastQuery().PHP_EOL;
        }

        if (!Config::getInstance()->getConf('web.app_debug')) {
            //防止拿一个token一直请求
            if ((time() - ceil($token_time / 1000)) > Config::getInstance()->getConf('web.app_sign_time')) { //请求大于10秒验证失败
                return Status::Error(Status::CODE_FORBIDDEN, '请求时限已过期');
            }
        }
//        $UserInfo['role']; //角色 （默认1 用户 2服务 3弟子 4伙伴 5讲师 ）

        switch($UserInfo['role']){
            case 1:$UserInfo['role']='user';break;
            case 2:$UserInfo['role']='service';break;
            case 3:$UserInfo['role']='disciple';break;
            case 4:$UserInfo['role']='agent';break;
            case 5:$UserInfo['role']='teacher';break;
        }

        $this->UserInfo = $UserInfo;

        $this->request()->withAttribute('userId_log', $this->UserInfo['uid']); //用于获取日志统计用户

        return Status::Success();

    }

    /**
     * 处理获取参数
     */
    public function getParams()
    {

        $params             = $this->request()->getRequestParam();
        $params['api_test'] = (isset($params['api_test']) && !empty($params['api_test'])) ? $params['api_test'] : 0;
        //page size cat_id
        $params['page'] = !empty($params['page']) ? intval($params['page']) : 1;
        $params['size'] = !empty($params['size']) ? intval($params['size']) : 5;

        //用于缓存分页
        $params['from'] = ($params['page'] - 1) * $params['size'];

        $this->params = $params;
    }

    /**
     * @param $count
     * @param $data
     * json缓存分页
     */
    public function getPagingDatas($count, $data)
    {
        $totalPage = ceil($count / $this->params['size']);
        $data      = $data ?? [];
        $data      = array_splice($data, $this->params['from'], $this->params['size']);
        return [
            'total_page' => $totalPage,
            'page'       => $this->params['page'],
            'count'      => intval($count),
            'lists'      => $data,
        ];

    }

    /**
     * @param int $statusCode
     * @param string $msg
     * @param array $result
     * @return bool
     * 防止composer更新框架版本覆盖掉controller 此处重写
     */
    protected function writeJson($statusCode = 200, $msg = '', $result = [])
    {

        if(is_array($statusCode)){
            $msgData=array_values($statusCode);
            list($statusCode,$msg,$result)=$msgData;
        }

        if (!$this->response()->isEndResponse()) {
            $data = Array(
                "code"   => $statusCode,
                "msg"    => $msg,
                "result" => $result
            );
            $this->response()->write(\str_replace(':null', ':""', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            if (!in_array($statusCode, [403, 404, 500,400])) { //防止浏览器和程序访问冲突
                $this->response()->withStatus($statusCode);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param \Throwable $throwable
     * 异常处理  上线开启  如有其他业务逻辑未处理异常此处可避免用户看到敏感信息，如控制器有异常处理则以控制器为准
     */
    public function onException(\Throwable $throwable): void
    {
        $ExceptionObj = new Exception();
        $message      = $throwable->getMessage();
        $data         = [
            'user_id'     => $this->request()->getAttribute('userId_log'),
            'server'      => json_encode($this->servers),
            'headers'     => json_encode($this->headers),
            'request_uri' => $this->servers['request_uri'],
            'message'     => $message,
            'params'      => json_encode($this->params),
        ];
        $ExceptionObj->add(Exception::$table , $data);
        $this->writeJson(Status::CODE_INTERNAL_SERVER_ERROR, '请求抛出异常', $throwable->getMessage());
    }

    /**
     * 必须要实现方法，子类可覆盖
     */
    public function index()
    {

    }

    /**
     * @param $action
     * 拦截器   false 不往下执行  true 继续执行
     * 解密数据(支持一次性请求 10秒验证成功)    查询用户 修改过期时间
     */
    protected function onRequest(?string $action): ?bool
    {
        $this->request()->withAttribute('requestTime', microtime(true)); //用于计算单次请求开始时间
        $this->request()->withAttribute('userId_log', 0); //用于获取日志统计用户
        $this->getParams(); //获取参数
        $check_data = $this->checkRequestAuth(); //验证请求
        if ($check_data === true) {
            return true;
        } else {
            $this->writeJson($check_data['code'], $check_data['msg']);
            return false;
        }
    }

    public function gc(){

        $this->headers = [];
        $this->servers = [];
        $this->UserInfo = [];
        $this->params = [];//请求参数数据

        //回收协程
        if (\App\Model\Base::$_db_instance instanceof MysqlObject) {
            PoolManager::getInstance()->getPool(MysqlPool::class)->recycleObj(\App\Model\Base::$_db_instance);
            \App\Model\Base::$_db_instance=null;
        }

    }

}