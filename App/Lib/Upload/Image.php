<?php
namespace App\Lib\Upload;
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/17
 * Time: 下午8:21
 */
 use App\Lib\Upload\Base;

 class Image extends Base{

     public $maxSize=1024000; //1M

     public $fileExtTypes=[
         'jpeg',
         'png',
         'jpg'
     ];

 }