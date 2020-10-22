<?php
namespace App\Lib\Upload;
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/17
 * Time: 下午8:21
 * 上传阿里云oss
 */
 use EasySwoole\EasySwoole\Config;
 use App\Lib\Message\Status;
 use OSS\OssClient;
 use OSS\Core\OssException;

 require_once EASYSWOOLE_ROOT . '/vendor/aliyuncs/oss-sdk-php/autoload.php';

// https://github.com/aliyun/aliyun-oss-php-sdk
//https://help.aliyun.com/document_detail/88473.html?spm=a2c4g.11186623.2.20.5a926f09yfNCHd#concept-88473-zh

 class AliOss{

     /**
      * @var array
      */
     protected static $MIME_TYPE_TO_TYPE = [
         'image/jpeg' => 'jpg', 'image/png' => 'png'
     ];

     //处理上传图片
     public static function UploadAliOss($file_base64,$user_id){

         //二进制流文件上传
         if(empty($file_base64)){
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'请传入图片'];
         }
         $imgInfo = getimagesize($file_base64);  //获取长宽比例
         //图片类型
         $mimeTypes = array_keys(self::$MIME_TYPE_TO_TYPE);
         if (!in_array($imgInfo['mime'], $mimeTypes)) {
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'图片类型不对'];
         }

         //图片上传
         if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $file_base64, $match)) {

             // 阿里云主账号AccessKey拥有所有API的访问权限，风险很高。强烈建议您创建并使用RAM账号进行API访问或日常运维，请登录 https://ram.console.aliyun.com 创建RAM账号。
             $accessKeyId = Config::getInstance()->getConf('web.ACCESS_KEY_ALI');
             $accessKeySecret = Config::getInstance()->getConf('web.SECRET_KEY_ALI');
             $endpoint = "oss-cn-beijing.aliyuncs.com";
             try {
                $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
             } catch (OssException $e) {
                 return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
             }

             try {
                 // 存储空间名称
                 $bucket= Config::getInstance()->getConf('web.BUCKET_ALI');
                 $ext = self::$MIME_TYPE_TO_TYPE["image/" . $match[2]]; //扩展名
                 $content=base64_decode(str_replace($match[1], '', $file_base64));
                 // 文件名称
                 $object=$user_id.'/'.date('Y').'/'.date('YmdHis').rand(1000,9999).'.'.$ext;
                 // 文件内容
                 $doesres = $ossClient->doesObjectExist($bucket, $object); //获取是否存在
                 if($doesres){
                     return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'文件已存在'];
                 }
                 $ossClient->putObject($bucket, $object, $content);

                 return $object;
             } catch (OssException $e) {
                 return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
             }


         }else{
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'上传失败'];
         }

     }

     //删除图片
     public static function DelAli($key){

         $accessKeyId = Config::getInstance()->getConf('web.ACCESS_KEY_ALI');
         $accessKeySecret = Config::getInstance()->getConf('web.SECRET_KEY_ALI');
         $endpoint = "oss-cn-beijing.aliyuncs.com";
         try {
             $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
         } catch (OssException $e) {
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
         }

         try {
             // 存储空间名称
             $bucket= Config::getInstance()->getConf('web.BUCKET_ALI');
             // 文件名称
             $object=$key;

             $doesres = $ossClient->doesObjectExist($bucket, $object); //获取是否存在
             if($doesres){
                 // 文件内容
                 $ossClient->deleteObject($bucket, $object);
                 return true;
             }else{
                 return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'文件不存在'];
             }

         } catch (OssException $e) {
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
         }
     }

     /**
      * 抓取远程图片
      * 如果已存在相同名称则不覆盖
      * Qiniu::GetUrl('https://share.nlsgapp.com/wechat/works/video/161627/2017111314220953986.jpg','wechat/head/bbb.jpg');
      */
     public static function GetUrl($url,$keyname){

         try {
             $filePath = EASYSWOOLE_ROOT . "/Temp/".md5($url).'.jpg';
             //下载图片
             $ch = curl_init ();
             curl_setopt ($ch, CURLOPT_URL, $url);
             curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
             curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 30);
             $file = curl_exec ($ch);
             curl_close ($ch);
             $resource = fopen ($filePath, 'a');
             fwrite ($resource, $file);
             fclose ($resource);

             $accessKeyId = Config::getInstance ()->getConf ('web.ACCESS_KEY_ALI');
             $accessKeySecret = Config::getInstance ()->getConf ('web.SECRET_KEY_ALI');
             $endpoint = "oss-cn-beijing.aliyuncs.com";

             $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
         } catch (OssException $e) {
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
         }

         try {
             // 存储空间名称
             $bucket= Config::getInstance()->getConf('web.BUCKET_ALI');
             // 文件名称
             $object=$keyname;
             //本地文件路径

             $doesres = $ossClient->doesObjectExist($bucket, $object);
             if($doesres){ //true 已经存在
                 return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>'文件已存在'];
             }

             $ossClient->uploadFile($bucket, $object, $filePath);

         } catch (OssException $e) {
             return ['code'=>Status::CODE_BAD_REQUEST,'msg'=>$e->getMessage()];
         }
         unlink ($filePath); //删除文件
         return ['code'=>Status::CODE_OK,'msg'=>'上传成功'];

     }


 }