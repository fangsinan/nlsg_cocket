<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-11-1
 * Time: 下午1:51
 */

namespace App\Model;


use App\Lib\Auth\Time;
use App\Lib\Auth\Des;
use App\Lib\Auth\IAuth;
use App\Utility\Tools\Tool;
use EasySwoole\EasySwoole\Config;

class UserModel extends Base
{
    static $table = 'nlsg_user';

    const STATUS_UNCERTIFIED = 0; // 未认证
    const STATUS_INAUDIT = 1; // 审核中
    const STATUS_HAVE = 2; // 已通过
    const STATUS_NOT = 3; // 未通过
    const STATUS_PROHIBIT = 4; // 禁用


    //登录界面 手机号登录
    public function PhoneLogin($user_id,$token,$time_out,$time){
        $rst=$this->update(self::$table,['token'=>$token,'time_out' => $time_out, 'last_login_time' => $time],['uid'=>$user_id]);
//        echo $this->getLastQuery().PHP_EOL;
        return $rst;
    }

    //微信登录 wechat控制器验证微信是否绑定手机号
    public  function GetUnionId($unionid){
        $where=[
            'WxUnionId'=>$unionid,
            '(phone !=? )'=>[''],
        ];
        $UserArr=$this->getOne(self::$table,$where,'uid,phone,role,status,avatar,realname');
        return $UserArr;
    }

    //微信登录 wechat控制器已绑定手机号直接登录
    public function WechatLogin($UserArr){
        $time=time();
        $token=IAuth::setAppLoginToken($UserArr['phone'].$time);
        $time_out=strtotime('+'.Config::getInstance()->getConf('web.login_time_out_days').' days');

        $this->update(self::$table,['token'=>$token,'time_out' => $time_out, 'last_login_time' => $time],['uid'=>$UserArr['uid']]);
        //获取登录信息
        $UserInfo=$this->LoginSuccess($token,$UserArr);
        return $UserInfo;
    }

    //登录成功返回
    public function LoginSuccess($token,$UserArr){

        $DesObj=new Des();
        $time=Time::get13TimeStamp();
        $token=$DesObj->encrypt($token.'||'.$time);
        $uid=$DesObj->encrypt($UserArr['uid'].'||'.$time);

        return [
            'token'=>$token,
            'uid'=>$uid,
            'role'=>$UserArr['role'],//1 用户 2服务 3弟子 4伙伴 5讲师
            'status'=>$UserArr['status'],//0未认证 1审核中 2已通过 3未通过 4禁用
            'avatar'=>Tool::getHttpFlag($UserArr['avatar']),
            'realname'=>$UserArr['realname'],
//            'phone'=>substr_replace($UserArr['phone'], '****', 3, 4)
            'phone'=>$UserArr['phone']
        ];
    }

    //微信注册用户 绑定用户
    public  function WechatBinding($data,$token,$time_out,$time,$flag=0){

        if(empty($flag)){ //注册用户

//            $headimg=$data['headimg']; //默认为空，异步上传完阿里云直接更新
            $openid=$data['openid'];

            $lastId=$this->add(self::$table,[
                'WxUnionId'=>$data['unionid'],
                'WxOpenId'=>$openid,
                'phone'=>$data['phone'],
                'token'=>$token,
                'time_out'=>$time_out,
                'ctime'=>$time,
                'last_login_time'=>$time
            ]);
            if($lastId){
                return $lastId;
            }else{
                return 0;
            }

        }else{ //绑定用户
            $rst=$this->update(self::$table,[
                'WxUnionId'=>$data['unionid'],
                'WxOpenId'=>$data['openid'],
                'token'=>$token,
                'time_out' => $time_out,
                'last_login_time' => $time
            ],['uid'=>$data['uid']]);
//            echo $this->getLastQuery().PHP_EOL;
            return $rst;

        }


    }

    //获取用户信息
    public function GetUserInfo($user_id,$keys='*'){

        $UserInfo = $this->getOne ($this->tableName,['id'=>$user_id],$keys);

        return $UserInfo;
    }

}