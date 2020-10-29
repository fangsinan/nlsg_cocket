<?php
namespace App\HttpController;



use App\Lib\Crontab\Task;
use App\Lib\Message\Status;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;

/**
 * live controller
 */
class live extends  Controller
{

    public function index()
    {
        return $this->writeJson(Status::CODE_OK,[],'success');
    }

    //push
    public function push(){

        $params = $this->request()->getRequestParam();

        $TaskObj=new Task([
            'method'=>'getLiveOrderRanking',
            'path'=>[
                'dir'=>'/Crontab',
                'name'=>'Ranking_',
            ],
            'data'=>[
                'live_id' =>1,
            ]
        ]);
        TaskManager::async ($TaskObj);
        return $this->writeJson(Status::CODE_OK,[],'success  ok');
    }




    public function LiveTask(){
        $params = $this->request()->getRequestParam();

        if( empty($params['live_id']) ){
            return $this->writeJson(Status::CODE_FAIL,[],'error:live_id');
        }

        $method_list = [
            'PushProduct',//产品推送6
            'getLivePushOrder',//订单推送10
            'getLiveOrderRanking',//排行榜11
            'getLiveGiftOrder',//礼物订单12
        ];
        if( empty($params['method']) || in_array($params['method'],$method_list) ){
            return $this->writeJson(Status::CODE_FAIL,[],'error:method');
        }
        $TaskObj=new Task([
            'method'    => $params['method'],
            'path'      =>[
                'dir'   => '/Crontab',
                'name'  => 'pro_',
            ],
            'data'      => $params
        ]);
        TaskManager::async ($TaskObj);


        return $this->writeJson(Status::CODE_OK,[],'success');

    }

}
