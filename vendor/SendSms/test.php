<?php
    include "TopSdk.php";
    date_default_timezone_set('Asia/Shanghai'); 

    $appkey="23429520";
    $secret="1792db20dc108373c93f52f2a5923004";
    $c = new TopClient;
    $c ->appkey = $appkey ;
    $c ->secretKey = $secret ;
    $c ->format = "json" ;
    $req = new AlibabaAliqinFcSmsNumSendRequest;
    $req ->setSmsFreeSignName( "寻拍" );  //签名
    $req ->setSmsType( "normal" );

    $req ->setExtend( "aaaa" );   //渗透参数回传
    $code=rand(100000,999999);
    $req ->setSmsParam( "{\"code\":\"$code\"}" );  //替换参数 json
    $req ->setRecNum( "18810355387" );   //手机号  13764196108
    $req ->setSmsTemplateCode( "SMS_13041003" );   //模板id
    $resp = $c ->execute( $req );
     if($resp->result->err_code==0){
         echo "1";
     }else{
         echo "0";
     }

    /*$req ->setExtend( "测试" );   //渗透参数回传
    $req ->setSmsParam( "" );  //替换参数 json
    $req ->setRecNum( "18810355387" );   //手机号
    $req ->setSmsTemplateCode( "SMS_12996087" );   //模板id
    $resp = $c ->execute( $req );
    echo "<pre>";
    var_dump($resp);
    echo "<pre>";*/


/*
SMS_13041003
您好，您的验证码是：${code}
SMS_12996087
尊敬的寻拍用户您好！您的拍摄已完成，请您于3天内支付尾款，支付尾款后我们会在7-10天为您上传调好颜色后的底片，感谢您对寻拍的支持！
底片上传
SMS_13021166
精修上传
SMS_13001031
排版通知
SMS_12971110
*/

?>