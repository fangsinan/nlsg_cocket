<?php
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/17
 * Time: 下午8:26
 */

namespace App\Lib\Upload;


class Base
{
    /**
     * @var string
     * 上传文件的 file ->key
     */

    public function __construct($request){

        $this->request=$request;
    }

    public function upload($type){

        $videos=$this->request->getUploadedFile($type); //上传key video
        $this->size=$videos->getSize(); //获取大小
        $size_flag=$this->checkSize(); //验证大小
        if(!$size_flag){
            return false;
        }

        $fileName=$videos->getClientFileName();
        //获取类型
        $this->clientMediaType=$videos->getClientMediaType();
        //验证类型
        $this->clientMediaType($type);
        $basename=$this->getFile($fileName,$type);
        //保存
        $flag=$videos->moveTo($basename);
        if(!empty($flag)){
             return $this->file;
        }

        return false;
    }

    public function getFile($fileName,$type){
        $pathinfo=pathinfo($fileName);
        $extension=$pathinfo['extension']; //扩展名
        $dirname=$type.'/'.date('Y').'/'.date('m'); //存放目录
        $dir=EASYSWOOLE_ROOT.'/webroot/'.$dirname;
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $basename='/'.md5($fileName).rand(10000,99999).'.'.$extension;

        $this->file=$dirname.$basename;

        return $dir.$basename;

    }

    /**
     * @throws \Exception
     * 验证上传类型
     */
    public function clientMediaType($type){
        $clientMediaType=explode('/',$this->clientMediaType);
        $clientMediaType=$clientMediaType[1] ?? "";
        if(empty($clientMediaType)){
            throw new \Exception("上传{$type}文件不合法");
        }
        if(!in_array($clientMediaType,$this->fileExtTypes)){
            throw new \Exception("上传{$type}文件不合法"); //此信息可抛至控制层
        }

        return true;
    }

    //验证上传大小
    public function checkSize(){
        if(empty($this->size)){
            return false;
        }

        if($this->size>$this->maxSize){
            return false;
        }

        return true;
    }

}