<?php
/**
 * 用户
 * User: zxg
 * Date: 2019/1/15
 * Time: 11:56 AM
 */

namespace App\HttpController\V1;


use App\HttpController\Base;
use App\Services\V1\UserService;

class User extends Base
{

    public function saveServiceAuthInfo(){

        $rst=$this->IsLogin(); //验证登录
        if($rst===false){
            return false;
        }
        $UserService=new UserService();
        $res = $UserService->getUserServiceInfo();
        $this->writeJson($res);

    }


}