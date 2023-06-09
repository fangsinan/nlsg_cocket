<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/1/19
 * Time: 上午12:43
 */

namespace App\Lib;


use App\Lib\Message\Status;
use App\Lib\Redis\Redis;
use App\Model\V1\Config;
use App\Model\V1\ShieldKey;
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
    public static function filterStr($str,$auth_user_id=0){

        //https://www.cnblogs.com/siqi/p/4117421.html
        /*$UserInfo['content'] = $UserObj->db->escape ($content);
        $UserInfo['content'] = htmlentities ($content);
        $UserInfo['content'] = htmlspecialchars ($content);
        $UserInfo['content'] = mysql_real_escape_string ($content);
        $UserInfo['content'] = mysql_escape_string ($content);
        $UserInfo['content'] ="'" . mysql_real_escape_string(stripslashes($content)) . "'";*/

        $content_json=json_encode($str);
        $content_json = str_replace("\ud83d\udc4f",'#1#',$content_json); //鼓掌
        $content_json = str_replace("\ud83d\udc4d",'#2#',$content_json); //点赞
        $content_json = str_replace("\ud83c\udf39",'#3#',$content_json); //小红花
        $str=json_decode($content_json);

        $str = preg_replace_callback('/./u', function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        }, $str);

//        $regex='/[\xf0-\xf7].{3}/'; // 全局匹配
//        $str = preg_replace($regex,'',$str);

        if(empty($str)){
            $replace_content=self::textDecode('\ud83d\udc4f');  //   \ud83d\udc4f  鼓掌
            $str=$replace_content.$replace_content.$replace_content;
        }
        $replace_gz=self::textDecode('\ud83d\udc4f');  // \ud83d\udc4f  鼓掌
        $replace_dz=self::textDecode('\ud83d\udc4d');  // \ud83d\udc4d  点赞
        $replace_xhh=self::textDecode('\ud83c\udf39');  // \ud83c\udf39  小红花
        $str = str_replace("#1#",$replace_gz,$str); //鼓掌
        $str = str_replace("#2#",$replace_dz,$str); //点赞
        $str = str_replace("#3#",$replace_xhh,$str); //小红花

        $Redis = new Redis();

        //处理直播间数字是否屏蔽
        $redis_Live_number_key='111_Live_number_key';
        $Live_number_str = $Redis->get($redis_Live_number_key);
        if(empty($Live_number_str)) {
            $ConfigObj = new Config();
            $ConfigInfo = $ConfigObj->getOne($ConfigObj->tableName, ['id' => 84], 'value');
            if (!empty($ConfigInfo) && isset($ConfigInfo['value'])) {
                $Live_number_str=$ConfigInfo['value'];
            }else{
                $Live_number_str='[169209,214932,187586]';
            }
            $Redis->set($redis_Live_number_key, $ConfigInfo['value'], 60*2);
        }
        $Live_number_arr=json_decode($Live_number_str,true);

        if(!in_array($auth_user_id,$Live_number_arr)) { //不过滤 169209 李婷   214932 姬广亮  187586 孟祥玲
//        if(!in_array($auth_user_id,[169209,214932,187586])) { //不过滤 169209 李婷   214932 姬广亮  187586 孟祥玲
            $reg = '/([a-zA-Z4-5]|7|8|9|0|壹|贰|叁|肆|伍|陆|柒|捌|玖|拾|四|五|六|七|八|九|十)/';
//            $reg = '/([a-zA-Z]|7|8|9|0|肆|伍|陆|柒|捌|玖|拾|四|五|六|七|八|九|十)/';
            $replace = Common::textDecode('\ud83c\udf39');  // 替换成此字符串
            $str = preg_replace($reg, $replace, $str);  // 进行替换
        }

        //读取缓存
        $redis_shield_key='111_ShieldKey';
        $ShieldingWords = $Redis->get($redis_shield_key);
        if(empty($ShieldingWords)) {
            $ShieldKeyObj = new ShieldKey();
            $list = $ShieldKeyObj->db->where('status', 1)->get($ShieldKeyObj->tableName,null, 'name');
            $listArr= array_column($list, 'name');
//            $ShieldingWords=serialize($listArr);
            $ShieldingWords = implode("|", $listArr); //字符串分隔
            $Redis->set($redis_shield_key, $ShieldingWords, 60*2); //设置对应屏蔽词库
        }
        $flag = 0; //违规词的个数
        //serialize（）explode（）json_deocde()   经测算serialize（）更优
        //第一种
        $RegExp="/".$ShieldingWords."/i";
        if(preg_match_all($RegExp, $str, $matches)){ //匹配到了结果
            $patternList = $matches[0];  //匹配到的数组
            $count = count($patternList);  //匹配到需过滤数量
            if($count>0){
                $flag=1;
            }
        }
        //第二种
//        $dataArr=unserialize($ShieldingWords);
        /*$dataArr=explode('|',$ShieldingWords);
        foreach ($dataArr as $key=>$val){
            $val=trim($val);//去掉两端空格
            $rst=strpos($str,$val);//函数对大小写敏感。  找到返回位置(int)  找不到返回false
            if(!empty($rst) && $rst!==false){
                $flag=1;
                break;
            }
        }*/

        return [
            'content'=>$str,
            'flag'=>$flag
        ];

        //定义敏感词数组
        $list = ['结婚','传销','习大大','美国','武汉','华为','最','第一','首','性','色情','疫情','赌博','退款','李文亮','骗','投诉','垃圾','新冠状肺炎','操','亚洲超级演说家','傻','卖课',
            '拉人','交税','智商税','大师','报名','废话','假','淫荡', '退',' 退款','退钱','套路','禁言','广告','录播','重播','没用','没效果','没改变','不想去','退费','孩子没改变',
            '下课','洗脑','逼我','不去','抓鸭子','不要脸','王八蛋','死','牛逼','你妈','滚','嘎','抓','录播','回放','本人','不是','看过','视频',
            '骗子','球','退','退了','卖','什么也不是','废话','狗','屁','洗脑','推销','SB','傻逼','鸡汤','毒','华子','死','屎','垃圾', '胡说','瞎扯','乱说','下课','贵','非法','集资','疯','鸡',
            '牛马','fuck','一群','小学生','傻逼','快点','结束','SB','好痒','屮','鄙视','别发了','色狼','二逼','我艹','快点','滚','不认同','胡比比','胡逼','S','B','牛马','弟弟',
            '王者荣耀','放屁','QQ农场','狗','有病','给这','逼','劳资','粑粑','聋','你妈','你长的','妈来个逼','给我咗住','二蛋','八嘎','巴嘎','别瞎说','话痨','niuma',
            '别逼逼了','别说话了','晦气','胡连八扯','背后的鬼','我TM的','啥时候结束','你有大屁','虾扯','你长得像粑粑','给我磕个头','沙B','傻子','艹泥马','你有什么本事','你吃屎了',
            '瞎BB','你不行','你好像有大病','浪荡','抠鼻屎，让你吃','屎','给我憋住','你个大','老师傻子','我操','他妈的','露娜连招公式','屁股','打钱','sb','主播','你妈','健在',
            '我是你爸爸','快点好么？','无聊','你妈卖批','别说了','脑子有问题','卧槽泥马','按到','床上','艹','你有病吧','傻逼玩意','炸鱼','媳妇儿在干嘛','吃屎法','认同你妈','屁股','尼吗',
            '你的无耻打动了我','老婆晚安','八嘎呀路','你个傻逼','我操你妈','听懂掌声','cnm','日你妈','考你妈','我不掏钱','你个骗子','收费是你的目的','推销','骗钱','在线坑钱','一群傻逼','就是玩',
            '讲这有屁用','打王者','妈蛋','敏感肌能用吗？','傻逼骗子李婷','创业天下能量','吹牛逼了','有毛病','假的','吃席了','不买','不要','没下单','带货','农村','乡下','老子','爬','丑','死','衮','滚'];
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