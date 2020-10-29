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




    protected function __call($name,$arguments){

        print_r($name);
        print_r($arguments);

        if( empty($arguments['live_id']) ){
            return $this->writeJson(Status::CODE_FAIL,[],'error');
        }
        $TaskObj=new Task([
            'method'    => $name,
            'path'      =>[
                'dir'   => '/Crontab',
                'name'  => 'pro_',
            ],
            'data'      => $arguments
        ]);
        TaskManager::async ($TaskObj);


        return $this->writeJson(Status::CODE_OK,[],'success');

    }

}
