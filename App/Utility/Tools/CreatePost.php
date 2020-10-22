<?php
/**
 * Created by PhpStorm.
 * Date: 2018/12/24
 * Time: 15:59
 */

namespace App\Utility\Tools;

/**
 * Class CreatePoster
 * @package backend21\commands
 *
 * 初始化:
 *      path:图片的保存路径
 *      source:修改的源文件 底片
 *      如果没有底片 需要创建底片 则需要传画图的长高
 *      w:底片宽度
 *      h:底片高度
 *      bg:创造底片可以选择图片 用户上半部分显示 创建出的底片的宽度等于bg宽度 高度等于bg高度加上add_height
 *      bg_color:创建的底片颜色(r,g,b)
 *      add_height:基于bg高度多出的高度部分,颜色由bg_color决定
 *
 * 生成图片:
 *      type:类型  文字->text  图片->image
 *      文字:
 *          size:文字大小
 *          x:坐标
 *          y:坐标
 *          font:字体
 *          text:内容
 *          rgb:字体颜色
 *
 *      图片:
 *          path:图片路径
 *          dst_x:坐标
 *          dst_y:坐标
 *          src_w:图片画入宽度(图片x轴从0计算,如果图片宽度像素大于该值,则多出部分被丢弃.可配合scaling使用)
 *          src_h:图片画图高度(图片y周从0计算)
 *          corners:圆角半径(如图片宽度100,cornesr=50,则为圆形)
 *          scalling:数组 w=缩放后的宽度  h=缩放后的高度
 */
class CreatePost
{
    private static $source;
    private static $path;
    private static $del_source;
    private static $basic_y;

    function __construct(array $array)
    {
        self::$path = $array['path'];

        if (empty($array['source'])) {
            if (empty($array['bg'])) {
                $temp_source_width  = $array['w'];
                $temp_source_height = $array['h'];
                $add_height         = 0;
            } else {
                $temp_source        = self::getFile($array['bg']);
                $temp_source_width  = imagesx($temp_source);
                $temp_source_height = imagesy($temp_source);
                $add_height         = empty($array['add_height']) ? 0 : $array['add_height'];
                $temp_source_height += $add_height;
            }

            //创建画布
            $temp_source = imagecreatetruecolor($temp_source_width, $temp_source_height);

            //背景色
            if (empty($array['bg_color'])) {
                $bg_r = 255;
                $bg_g = 255;
                $bg_b = 255;
            } else {
                $temp_bg_color = explode(',', $array['bg_color']);
                $bg_r          = empty($temp_bg_color[0]) ? 0 : $temp_bg_color[0];
                $bg_g          = empty($temp_bg_color[1]) ? 0 : $temp_bg_color[1];
                $bg_b          = empty($temp_bg_color[2]) ? 0 : $temp_bg_color[2];
            }
            $temp_source_color = imagecolorallocate($temp_source, $bg_r, $bg_g, $bg_b);
            imagefill($temp_source, 0, 0, $temp_source_color);

            //合成背景图
            if (!empty($array['bg'])) {
                self::imagecopymerge_alpha(
                    $temp_source, self::getFile($array['bg']),
                    0, 0, 0, 0,
                    $temp_source_width, $temp_source_height - $add_height,
                    0
                );
            }


            $temp_source_save_name = self::$path . 'temp_' . time() . rand(1, 99999) . '.jpg';
            imagejpeg($temp_source, $temp_source_save_name, 100);
            imagedestroy($temp_source);
            self::$del_source = true;
            self::$source     = $temp_source_save_name;
            self::$basic_y    = $temp_source_height - $add_height;

        } else {
            self::$source     = $array['source'];
            self::$del_source = false;
            self::$basic_y    = 0;
        }
    }

    public static function draw($array,$save_name)
    {
        $source = self::getFile(self::$source);
        foreach ($array as $v) {
            $type = $v['type'];
            if ($type == 'image') {
                $temp_dst_im  = $source;
                $temp_src_im  = self::getFile($v['path']);
                $temp_dst_x   = empty($v['dst_x']) ? 0 : $v['dst_x'];
                $temp_dst_y   = empty($v['dst_y']) ? 0 : $v['dst_y'];
                $temp_src_x   = empty($v['src_x']) ? 0 : $v['src_x'];
                $temp_src_y   = empty($v['src_y']) ? 0 : $v['src_y'];
                $temp_src_w   = empty($v['src_w']) ? 100 : $v['src_w'];
                $temp_src_h   = empty($v['src_h']) ? 100 : $v['src_h'];
                $temp_pct     = empty($v['pct']) ? 0 : $v['pct'];
                $temp_scaling = empty($v['scaling']) ? null : $v['scaling'];
                $temp_corners = empty($v['corners']) ? 0 : $v['corners'];

                self::imagecopymerge_alpha(
                    $temp_dst_im, $temp_src_im, $temp_dst_x, $temp_dst_y, $temp_src_x, $temp_src_y,
                    $temp_src_w, $temp_src_h, $temp_pct, $temp_scaling, $temp_corners
                );
            } elseif ($type == 'text') {
                $temp_source = $source;
                $temp_size   = empty($v['size']) ? 20 : $v['size'];
                $temp_angle  = empty($v['angle']) ? 0 : $v['angle'];
                $temp_x      = empty($v['x']) ? 0 : $v['x'];
                $temp_y      = empty($v['y']) ? 0 : $v['y'];
                $font        = $v['font'];
                $temp_text   = $v['text'];

                $color = empty($v['rgb']) ? '255,255,255' : $v['rgb'];
                $color = explode(',', $color);

                $c_r = (int)$color[0];
                $c_g = (int)$color[1];
                $c_b = (int)$color[2];

                $temp_y += self::$basic_y;

                imagefttext(
                    $temp_source, $temp_size, $temp_angle, $temp_x, $temp_y,
//                    imagecolorallocate($temp_source, $c_r, $c_g, $c_b),
                    imagecolorexact($temp_source, $c_r, $c_g, $c_b),
                    $font, $temp_text
                );

            } else {
                return false;
            }
        }

        imagejpeg($source, self::$path . $save_name, 100);
        $res = imagedestroy($source);
        if ($res) {
            if (self::$del_source == true) {
                unlink(self::$source);
            }
            return $save_name;
        }

    }


    //判断图片格式,选择对应方法
    private static function getFile($file)
    {
        $imginfo  = getimagesize($file);
        $img_type = end($imginfo);
        switch (strtolower($img_type)) {
            case 'image/jpeg':
                $method = 'imagecreatefromjpeg';
                break;
            case 'image/png':
                $method = 'imagecreatefrompng';
                break;
            case 'image/gif':
                $method = 'imagecreatefromgif';
                break;
            default:
                return false;
        }
        return $method($file);
    }

    /**
     * 合成图片
     * @param $dst_im   底片
     * @param $src_im   水印图片
     * @param $dst_x    底片坐标x(画的位置)
     * @param $dst_y    底片坐标y
     * @param $src_x    水印开始坐标x(0)
     * @param $src_y    水印开始坐标y(0)
     * @param $src_w    水印画布的宽度
     * @param $src_h    水印画布的高度
     * @param $pct      透明度  0为不透明  124为完全透明
     * @param array|null $scaling 是否缩放,数组['w'=>'缩放后宽度','h'=>'缩放后高度']
     * @param null $corners 圆角,null为直角,其他数字代表半径
     */
    private static function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h,
                                                 $pct, array $scaling = null, $corners = null)
    {
        $dst_y   = self::$basic_y + $dst_y;
        $opacity = $pct;
        //处理缩放
        if ($scaling) {
            // getting the watermark width获取水印宽度
            $w = imagesx($src_im);
            // getting the watermark height获取水印高度
            $h = imagesy($src_im);
            //创建缩放后大小的画布
            $new_src_im = imagecreatetruecolor($scaling['w'], $scaling['h']);
            //缩放原图   新宽度,新高度,原宽度,原高度
            imagecopyresampled($new_src_im, $src_im, 0, 0, 0, 0, $scaling['w'], $scaling['h'], $w, $h);
            $src_im = $new_src_im;
        }


        //处理圆角
        if ($corners) {
            $src_im = self::radius_img($src_im, $corners);
        }

        // creating a cut resource创建一个切割资源
        $cut = imagecreatetruecolor($src_w, $src_h);
        // copying that section of the background to the cut将该部分的背景复制到剪切
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        // inverting the opacity反相the混浊
        $opacity = 100 - $opacity;
        // placing the watermark now现在放置水印
        // 将 src_im 图像中坐标从 src_x，src_y 开始，宽度为 src_w，高度为 src_h 的一部分拷贝到 dst_im 图像中坐标为 dst_x 和 dst_y 的位置上。
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        // 将 src_im 图像中坐标从 src_x，src_y 开始，宽度为 src_w，高度为 src_h 的一部分拷贝到 dst_im 图像中坐标为 dst_x 和 dst_y
        // 的位置上。两图像将根据 pct 来决定合并程度，其值范围从 0 到 100
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
    }


    /**
     * 处理圆角图片
     * @param  string $imgpath 源图片路径
     * @param  integer $radius 圆角半径长度默认为15,处理成圆型
     * @return [type]           [description]
     */
    private static function radius_img($imgData, $radius = 50)
    {
        $w = imagesx($imgData);
        $h = imagesy($imgData);

        // $radius = $radius == 0 ? (min($w, $h) / 2) : $radius;
        $img = imagecreatetruecolor($w, $h);
        //这一句一定要有
        imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);
        $r = $radius; //圆 角半径
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($imgData, $x, $y);
                if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
                    //不在四角的范围内,直接画
                    imagesetpixel($img, $x, $y, $rgbColor);
                } else {
                    //在四角的范围内选择画
                    //上左
                    $y_x = $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //上右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下左
                    $y_x = $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                }
            }
        }
        return $img;
    }
}