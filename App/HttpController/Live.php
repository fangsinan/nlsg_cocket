<?php
namespace App\HttpController;

use App\Lib\Auth\Aes;
use App\Lib\Cache\Cache;
use App\Lib\Crontab\Task;
use App\Lib\Redis\Redis;
use App\Lib\Message\Status;
use App\Model\V1\LiveCommentModel;
use App\Services\V1\UserService;
use App\WebSocket\Common;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use App\Lib\Auth\Des;
use App\Lib\Auth\IAuth;
use App\Lib\Auth\Time;
use EasySwoole\EasySwoole\ServerManager;

/**
 * 调试页面暂时保留
 * Index controller
 */
class live extends  Controller
{
    public function index()
    {
        return $this->writeJson(Status::CODE_OK,[],'success');
    }

    function demo(){

        $params = $this->request()->getRequestParam();
        print_r($params);

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


}
