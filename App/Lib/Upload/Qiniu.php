<?php
namespace App\Lib\Upload;
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/17
 * Time: 下午8:21
 * 上传七牛云
 */
 use EasySwoole\EasySwoole\Config;


 use Qiniu\Auth;
 use Qiniu\Storage\UploadManager;
 use Qiniu\Storage\BucketManager;
 use App\Lib\Message\Status;

 require_once(EASYSWOOLE_ROOT . '/vendor/qiniu/php-sdk/autoload.php');

 class Qiniu{

     /*$file_base64=$this->params['file_base64'];
     $file=Qiniu::UploadQiniu($file_base64,$user_id);

     if(is_array($file)){
     return $this->writeJson ($file['code'], $file['msg']);
     }else{
         return $this->writeJson (Status::CODE_OK, '上传成功', [
             'url'=>Config::getInstance()->getConf('web.IMAGES_QINIU_URL'),
             'name' => $file
         ]);
     }*/
     /**
      * @var array
      */
     protected static $MIME_TYPE_TO_TYPE = [
         'image/jpeg' => 'jpg', 'image/png' => 'png'
     ];

     //处理七牛上传图片
     //   各种图片生成  https://developer.qiniu.com/dora/manual/1270/the-advanced-treatment-of-images-imagemogr2
     public static function UploadQiniu($file_base64,$user_id){
         /*<form method="post" action="/upload/push" enctype="multipart/form-data">
             <input name="file" type="file" />
             <input type="submit" value="上传"/>
         </form>*/
//        file 文件方式上传
         /*if(empty($_FILES['file']['tmp_name'])){ //要上传的缓存文件
             ApiOutPut::show(0,'图片不合法');
         }
         //扩展名
         $ext=explode('.',$_FILES['file']['name']);
         $ext=$ext[1];
 //        $pathinfo=pathinfo($_FILES['file']['name']);
 //        $ext=$pathinfo['extension'];
         $file=$_FILES['file']['tmp_name']; //要上传的缓存文件*/

         //二进制流文件上传
         if(empty($file_base64)){
             return ['code'=>Status::CODE_FAIL,'msg'=>'请传入图片'];
         }
         $imgInfo = getimagesize($file_base64);  //获取长宽比例
         //图片类型
         $mimeTypes = array_keys(self::$MIME_TYPE_TO_TYPE);
         if (!in_array($imgInfo['mime'], $mimeTypes)) {
             return ['code'=>Status::CODE_FAIL,'msg'=>'图片类型不对'];
         }

         //图片上传
         if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $file_base64, $match)) {

             $ext = self::$MIME_TYPE_TO_TYPE["image/" . $match[2]]; //扩展名
             $file=base64_decode(str_replace($match[1], '', $file_base64));

             // 用于签名的公钥和私钥
             $accessKey = Config::getInstance()->getConf('web.ACCESS_KEY_QN');
             $secretKey = Config::getInstance()->getConf('web.SECRET_KEY_QN');
             // 初始化签权对象
             $auth = new Auth($accessKey, $secretKey);

             $bucket = Config::getInstance()->getConf('web.BUCKET');
             // 生成上传Token
             $token = $auth->uploadToken($bucket);
             //上传文件名
             $name=$user_id.'/'.date('Y').'/'.date('YmdHis').rand(1000,9999).'.'.$ext;
             // 构建 UploadManager 对象 上传类
             $uploadMgr = new UploadManager();
             //file文件上传
//        list($rst,$err)=$uploadMgr->putFile($token,$name,$file);
             // 上传字符串到七牛
             list($ret, $err) = $uploadMgr->put($token, $name, $file);

             if($err!==null){
                 return ['code'=>Status::CODE_FAIL,'msg'=>'上传失败'];
             }else{
                 return $name;
             }

         }else{
             return ['code'=>Status::CODE_FAIL,'msg'=>'上传失败'];
         }

     }

     //删除图片
     public static function DelQiniu($key){
         //初始化Auth状态
         // 用于签名的公钥和私钥
         $accessKey = Config::getInstance()->getConf('web.ACCESS_KEY_QN');
         $secretKey = Config::getInstance()->getConf('web.SECRET_KEY_QN');
         $auth = new Auth($accessKey, $secretKey);
         //初始化BucketManager
         $bucketManager = new BucketManager($auth);
         $bucket = Config::getInstance()->getConf('web.BUCKET');
         $err = $bucketManager->delete($bucket, $key);
         if($err){
             return $err;
         }else{
             return true;
         }
     }

     /**
      * 抓取远程图片
      * 如果已存在相同名称则不覆盖
      * Qiniu::GetUrl('https://share.nlsgapp.com/wechat/works/video/161627/2017111314220953986.jpg','wechat/head/bbb.jpg');
      */
     public static function GetUrl($url,$keyname){

         $accessKey = Config::getInstance()->getConf('web.ACCESS_KEY_QN');
         $secretKey = Config::getInstance()->getConf('web.SECRET_KEY_QN');
         $bucket = Config::getInstance()->getConf('web.BUCKET');
         $auth = new Auth($accessKey, $secretKey);
         //初始化BucketManager
         $bucketManager = new BucketManager($auth);

        // 指定抓取的文件保存名称
         list($ret, $err) = $bucketManager->fetch($url, $bucket, $keyname);
         if ($err !== null) {
             return ['url'=>$url,'name'=>$keyname,'status'=>'fail'];
         } else {
//             print_r($ret);
             return ['url'=>$url,'name'=>$keyname,'status'=>'OK'];
         }

     }


 }