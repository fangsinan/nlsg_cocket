<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/1/19
 * Time: 上午12:43
 */

namespace App\Lib;


use App\Lib\Message\Status;
use EasySwoole\Http\Response;
use EasySwoole\Utility\SnowFlake;

class Common
{

    /**
     * @param $phone
     * @return string
     * 手机号隐藏
     */
    function setPhone($phone){
        return substr($phone, 0, 3) . "****" . substr($phone, strlen($phone) - 4, 4);
    }

    //过滤非法字符
    public static function filterStr($str){

        //https://www.cnblogs.com/siqi/p/4117421.html
        /*$UserInfo['content'] = $UserObj->db->escape ($content);
        $UserInfo['content'] = htmlentities ($content);
        $UserInfo['content'] = htmlspecialchars ($content);
        $UserInfo['content'] = mysql_real_escape_string ($content);
        $UserInfo['content'] = mysql_escape_string ($content);
        $UserInfo['content'] ="'" . mysql_real_escape_string(stripslashes($content)) . "'";*/

        $reg = '/([a-zA-Z4-5]|7|8|9|0|壹|贰|叁|肆|伍|陆|柒|捌|玖|拾|一|二|三|四|五|六|七|八|九|十)/';
        $replace = Common::textDecode('\ud83c\udf39');  // 替换成此字符串
        $str = preg_replace($reg, $replace, $str);  // 进行替换

        //定义敏感词数组
        $list = ['结婚','传销','习大大','美国','武汉','华为','最','第一','首','性','色情','疫情','赌博','退款','李文亮','骗','投诉','垃圾','新冠状肺炎','操','亚洲超级演说家','傻','卖课',
            '拉人','交税','智商税','大师','报名','废话','假','淫荡', '退',' 退款','退钱','套路','禁言','广告','录播','重播','没用','没效果','没改变','不想去','退费','退款','孩子没改变',
            '下课','传销','洗脑','逼我','不去','抓鸭子','不要脸','王八蛋','死','牛逼','你妈','滚', '嘎' ,'抓','录播', '回放', '本人', '不是','看过','视频',
            '骗子', '骗', '球', '退', '退了', '卖课', '卖', '什么也不是', '废话', '狗', '屁', '洗脑', '推销', 'SB', '傻逼', '鸡汤', '毒', '华子', '死', '屎', '垃圾',
            '胡说', '瞎扯', '乱说', '下课', '贵', '非法', '集资', '疯', '鸡'];
        $str = self::sensitive($list, $str,$replace);

        return $str;

    }

    /**
     * @todo 敏感词过滤，返回结果
     * @param array $list  定义敏感词一维数组
     * @param string $string 要过滤的内容
     * @return string $log 处理结果
     */
    public static function sensitive($list, $string,$replace){
//        $count = 0; //违规词的个数
//        $sensitiveWord = '';  //违规词
        $stringAfter = $string;  //替换后的内容
        $pattern = "/".implode("|",$list)."/i"; //定义正则表达式
        if(preg_match_all($pattern, $string, $matches)){ //匹配到了结果
            $patternList = $matches[0];  //匹配到的数组
//            $count = count($patternList);  //匹配到需过滤数量
//            $sensitiveWord = implode(',', $patternList); //敏感词数组转字符串
            $replaceArray = array_combine($patternList,array_fill(0,count($patternList),$replace)); //把匹配到的数组进行合并，替换使用
            $stringAfter = strtr($string, $replaceArray); //结果替换
        }

        return $stringAfter;
    }

    //返回格式  转换结果替换null
    public static function ReturnJson($status,$message,$data=[]){
        return (\str_replace(':null', ':""', json_encode(['status'=>$status,'message'=>$message,'data'=>$data])));
    }

    /**
    把用户输入的文本转义（主要针对特殊符号和emoji表情）
     */
    public static function textEncode($str){
        if(!is_string($str))return $str;
        if(!$str || $str=='undefined')return '';

        $text = json_encode($str); //暴露出unicode
        $content = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){

            return addslashes($str[0]);//加两次转义  插入数据库的时候会被过滤掉一个\
        },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。

        return json_decode($content);
    }
    /**
    解码上面的转义
     */
    public static function textDecode($str){
        $text = json_encode($str); //暴露出unicode
        $content = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($content);
    }
}