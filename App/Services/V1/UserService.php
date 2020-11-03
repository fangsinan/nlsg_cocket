<?php
/**
 * User: zxg
 * Date: 2019/1/14
 * Time: 1:59 PM
 */

namespace App\Services\V1;


use App\Lib\Auth\Aes;
use App\Lib\Common;
use App\Lib\Message\Status;
use App\Model\UserModel;
use App\Model\V1\LiveForbiddenWordsModel;
use App\Model\V1\LiveUserPrivilege;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Validate\Validate;
use EasySwoole\EasySwoole\ServerManager;
use App\Lib\Redis\Redis;

class UserService
{

    //获取用户数据
    public function GetUserInfo($live_id,$user_id,$content='',$access_user_token=''){
        if(empty($live_id)){
            return Status::Error (Status::CODE_NOT_FOUND, '直播live_id为空');
        }
        if(empty($user_id)){
            //根据后端返回的token前端解密$token.'||'.13位时间戳
            //再加密传给后端$token.'||'.13位时间戳
            $access_user_token = (new Aes())->decrypt ($access_user_token);
            if ( empty($access_user_token) ) {
                return Status::Error (Status::CODE_FORBIDDEN, '您token信息有误');

            }
            if ( !preg_match ('/||/', $access_user_token) ) {
                return Status::Error (Status::CODE_FORBIDDEN, '您token信息格式有误');

            }
            try {
                list($token, $token_time) = explode ('||', $access_user_token);
            } catch ( \Exception $e ) {
                return Status::Error (Status::CODE_FORBIDDEN, '用户错误');
            }
            if ( empty($token) ) {
                return Status::Error (Status::CODE_FORBIDDEN, '您token信息为空');
            }

            $UserObj = new UserModel();
            $UserInfo = $UserObj->getOne (UserModel::$table, ['token' => $token], 'id,level,expire_time,phone username,nickname');
            if (empty($UserInfo) ) { //不是有效用户
                return Status::Error (Status::CODE_FORBIDDEN, '用户信息不存在');

            }
            $LiveForbiddenWords=new LiveForbiddenWordsModel();
            $is_flag=$LiveForbiddenWords->whereArr(['user_id'=>$UserInfo['id'],'live_id'=>$live_id,'is_forbid'=>1])->getOne(LiveForbiddenWordsModel::$table,'id');
            if(!empty($is_flag)){ //已被禁言
                return Status::Error (Status::CODE_FORBIDDEN, '被禁言');
            }
        }else{

            $LiveForbiddenWords=new LiveForbiddenWordsModel();
            $is_flag=$LiveForbiddenWords->whereArr(['user_id'=>$user_id,'live_id'=>$live_id,'is_forbid'=>1])->getOne(LiveForbiddenWordsModel::$table,'id');
            if(!empty($is_flag)){ //已被禁言
                return Status::Error (Status::CODE_FORBIDDEN, '被禁言');
            }
            $UserObj = new UserModel();
            $UserInfo = $UserObj->getOne (UserModel::$table, ['id' => $user_id], 'id,level,expire_time,phone username,nickname,headimg');
            if ( empty($UserInfo) ) { //不是有效用户
                return Status::Error (Status::CODE_NOT_FOUND, '用户信息不存在');

            }
        }

//        echo $UserObj->db->getLastQuery().PHP_EOL;

        if ( empty($UserInfo) ) { //不是有效用户
            return Status::Error (Status::CODE_NOT_FOUND, '用户信息不存在');

        }
        if(!empty($content)) {
            $LUPObj=new LiveUserPrivilege();
            $lupInfo=$LUPObj->getOne($LUPObj->tableName,['user_id'=>$UserInfo['id']],'id');
            if(!empty($lupInfo)){ //管理员不过滤
                $UserInfo['content']=$content;
            }else {
                $UserInfo['content'] = Common::filterStr($content);
            }
        }

        return Status::Success('获取成功',$UserInfo);


    }

    //获取禁止弹幕用户
    public function getProhibitBarrage($live_id){
        $LiveForbiddenWords=new LiveForbiddenWordsModel();
        $UserInfo=$LiveForbiddenWords->whereArr(['live_id'=>$live_id,'is_barrage'=>2])->get(LiveForbiddenWordsModel::$table,null,'user_id');

        $UserInfo= array_column($UserInfo, 'user_id');

        return $UserInfo;
    }

    //获取人数  获取每个直播间
    public function linkNum($live_id=0){

        if(empty($live_id)){
            $live_id=Config::getInstance()->getConf('web.live_id_now');   //涉及有定时任务执行
        }

        //需要开启  onOpen onClose
        $live_redis_key=\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_redis_key');
        $Redis = new Redis();
        //获取数量
        $realcount=$Redis->SCARD($live_redis_key);

        /*//获取本机链接数
        $server = ServerManager::getInstance()->getSwooleServer();
        $realcount=0;
        foreach($server->ports[0]->connections as $fd)
        {
            $realcount+=1;
        }*/

        return $realcount;

    }

    //推送消息 发送每个直播间
    public function pushMessage($getfd,$data,$ListPort,$live_id=1,$UserArr=[],$is_forbid_user_id=0){

        $server = ServerManager::getInstance()->getSwooleServer();

        //负载均衡
        $Redis = new Redis();
        //获取有序集合
        $clients = $Redis->sMembers (\EasySwoole\EasySwoole\Config::getInstance ()->getConf ('web.live_redis_key').$live_id);

        foreach ( $clients as $key => $val ) { //$ip,$fd,$live_id,$user_id
            $arr = explode (',', $val);
            if ( $arr[0]==$ListPort['eth0']) { //当前服务器

                if(!empty($is_forbid_user_id)) { //禁言推送 被禁言用户id
                    if ($arr[3] == $is_forbid_user_id) {
                        $server->push($arr[1], $data);
                        break;
                    }
                }else if ( $arr[1] != $getfd ) { //排除当前链接
                    if($arr[2]==$live_id){ //当前直播间
                        if(!empty($UserArr)){ //弹幕禁止推送
                            if(!in_array($arr[3],$UserArr)) {
                                $server->push ($arr[1], $data);
                            }
                        }else{
                            $server->push ($arr[1], $data);
                        }
                    }
                }
            }else{ //其他服务器
            }
        }

        //防止多端口监听导致数据混乱  默认9530  本服务器
        /*foreach($server->ports[0]->connections as $fd)
        {
            if($fd!=$getfd) { //排除当前链接
                $server->push ($fd, $data);
            }
        }*/


    }

    /**
     * 服务中心认证
     * @param $data
     */
    public function saveServiceAuthInfo($data){
        $validate = new Validate();

        // 验证基本信息
        $validate->addColumn('realname')->required('真实姓名必须填写')->notEmpty('真实姓名不能为空');
        $validate->addColumn('birthday')->required('出生年月日必须选择')->notEmpty('出生年月日不能为空')->regex('/^(\d{4}-\d{2}-\d{2})?$/' , '无效的出生年月日');

        if(!$validate->validate($data)) {
            return Status::Error(Status::CODE_BAD_REQUEST , $validate->getError()->__toString(), 'fail');
        }
        //验证身份证格式
        $res = Common::authCard($data['identity_card']);
        if(Status::isError($res)){
            return $res;
        }

        $UserModel = new UserModel();

        $UserModel->getDb()->startTransaction();

        $res1 = $this->setBaseInfo($data , UserModel::ROLE_SERVICE);

        if($res1 ){
            $UserModel->getDb()->commit();
            return Status::Success('提交成功' , 'success');
        }else{
            $UserModel->getDb()->rollback();
            return Status::Error(Status::CODE_BAD_REQUEST , '提交失败' , 'fail');
        }
    }



    // 获取服务信息
    public function getUserServiceInfo(){
        $field = [
            'ServiceUser.realname',
            'User.service_uid',
            'UserServiceInfo.service_advisor',
            'UserServiceInfo.energy_advisor',
            'UserServiceInfo.cooperative_partner',
            'UserServiceInfo.remarks',
            'FROM_UNIXTIME(User.ctime , "%Y-%m-%d %H:%i:%s") as ctime',
        ];

        $leftjoin = [
            UserModel::$table.' User'=>'User.uid = UserServiceInfo.uid',
            UserModel::$table.' ServiceUser'=>'ServiceUser.uid = User.service_uid',
        ];

        return (new UserModel())->getOne(UserModel::$table.' UserServiceInfo',['UserServiceInfo.uid'=>$this->uid] ,$field , [] , [] , $leftjoin);

    }

}