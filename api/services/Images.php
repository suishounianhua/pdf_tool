<?php
use Phalcon\Di;
use Phalcon\Image\Adapter\Imagick as ImagickClass;
use Phalcon\Image\Adapter\Gd as gdClass;
class Images{
	protected $userPath;//用户临时文件存放路径
	protected $userKey;//用户唯一字符
	protected $qrcodeFilename;//二维码文件名
	protected $headimgFilename;//头像文件名
	protected $headimgRoundFilename;//头像文件名(圆形)
	protected $backgroundFilename;//背景文件名
	protected $posterFilename;//海报文件名
	protected $fontfile;//字体文件路径
    protected $themeFilename;
	protected $extendImage;//扩展个人业务参数

	/**
	* @title 构造
	* @author luodiao
	* @param userKey 用户唯一标识
	* @param image_path 临时文件路径
	*/
	public function __construct($userKey = '',$image_path = ''){
		$this->userPath = $image_path;
		$this->userKey = $userKey;
		if($image_path == ''){
			$this->userPath = APP_PATH.'/public/images/';
		}
		if($userKey == ''){
			$this->userKey = md5(time().rand(0,9999));
		}
		$this->extendImage = array();
		//微软雅黑
		$this->fontfile = APP_PATH.'/public/fonts/msyh.ttf';
        $this->msyhBold = APP_PATH.'/public/fonts/msyhBold.ttf';
	}

	/**
	* @title 清理文件
	*/
	public function __destruct(){
		if(file_exists($this->qrcodeFilename)) unlink($this->qrcodeFilename);
		if(file_exists($this->headimgFilename)) unlink($this->headimgFilename);
		if(file_exists($this->backgroundFilename)) unlink($this->backgroundFilename);
		if(file_exists($this->posterFilename)) unlink($this->posterFilename);
		if(file_exists($this->headimgRoundFilename)) unlink($this->headimgRoundFilename);
        if(file_exists($this->themeFilename)) unlink($this->themeFilename);
		$extendImage = $this->extendImage;
		foreach ($extendImage as $key => $value) {
			 unlink($value);
		}
	}
	public function __clone(){
		exit('当前clone 不被允许！');
	}

	/**
	* @title 准备文件名
	* @author luodiao
	* @param 
	*/
	protected function check(){
		$this->qrcodeFilename = $this->userPath . $this->userKey .'_qecode.png';
		$this->headimgFilename = $this->userPath . $this->userKey .'_headimg.png';
		$this->backgroundFilename = $this->userPath . $this->userKey .'_background.jpg';
		$this->posterFilename = $this->userPath . $this->userKey .'_poster.png';
		$this->headimgRoundFilename = $this->userPath . $this->userKey .'_headimg_round.png';
        $this->themeFilename = $this->userPath . $this->userKey .'_theme.png';
	}

	/**
	* @title 生成海报
	* @author luodiao
	* @param poster_rule 规则
	* @param qrcodeimg 二维码地址
	* @param extend 需要扩展的写入的参数
	*/
	public function mergeImg($poster_rule,$qrcodeimg = '',$extend = array()){

        $this->check();
		if(isset($poster_rule['background']) == false){
			//当背景图不存在的时候不做任何操作
			exit('需要上传背景图');
		}

        if(is_array($poster_rule['background'])){
            $background_total = count($poster_rule['background']);
            $back_key = 0;
            if($background_total > 1){
                $back_key = rand(0,$background_total-1);
            }
            $backgroundFilename = CloudStorage::getImagesTmpFile($poster_rule['background'][$back_key],'long');
        }else{
            $backgroundFilename = CloudStorage::getImagesTmpFile($poster_rule['background'],'long');
        }
        
		//下载背景
        copy($backgroundFilename,$this->backgroundFilename);
		$postage = new gdClass($this->backgroundFilename);
        // $postage->resize(640,1008);
		if(isset($poster_rule['qrcode'])){
			$qrcode = new gdClass($this->qrcodeFilename);
			$qrcode->resize($poster_rule['qrcode']['width'],$poster_rule['qrcode']['height']);
			$postage->watermark($qrcode,$poster_rule['qrcode']['x'],$poster_rule['qrcode']['y']);
		}
        if(isset($poster_rule['theme'])){
            $qrcode = new gdClass($this->qrcodeFilename);
            $qrcode->resize($poster_rule['theme']['width'],$poster_rule['theme']['height'])->crop($poster_rule['theme']['width'],$poster_rule['theme']['height']);
            $postage->watermark($qrcode,$poster_rule['theme']['x'],$poster_rule['theme']['y']);
        }
        if(!empty($extend)){
			if(isset($extend['text'])){
				//循环写入文字
				foreach ($extend['text'] as $key => $value) {
					$postage->text( mb_convert_encoding($value['text'],'html-entities','UTF-8'),$value['x'],$value['y'],100,$value['color'],$value['size'],$this->fontfile);
				}
			}

			if(isset($extend['images'])){
				//循环写入图片
				foreach ($extend['text'] as $key => $value) {
					//存入图片地址
					$this->extendImage[] = $this->userPath . $this->userKey.'extendImage'.$key.'png';

					$extend_img[$key] = new gdClass($value['url']);
					$extend_img[$key]->resize($value['width'],$extend_img['height']);
					$postage->watermark($extend_img[$key],$value['x'],$value['y']);
				}
			}
		}
		//判断头像是否存在
		if(isset($poster_rule['picture'])){
			//判断是否需要圆角图片
			$pictureFilename = CloudStorage::getImagesTmpFile($poster_rule['picture']['url'],'picture');
        	copy($pictureFilename,$this->headimgFilename);
            if(!file_exists($this->headimgFilename)){
                Di::getDefault()->get('logger')->debug('=====luodiao dump picture empty:'.$this->userKey.' url:'.$poster_rule['picture']['url']);
            }else{
                //先确定大小
                $headimg = new gdClass($this->headimgFilename);
                if($poster_rule['picture']['status'] == 'Y'){
                    //进行圆角处理
                    $headimg->resize(406,406);
                    $headimg->save();
                    $this->imagickRound($this->headimgFilename,$this->headimgRoundFilename);
                    if(!file_exists($this->headimgRoundFilename)){
                        $headimg = new ImagickClass($this->headimgFilename);
                        $headimg->resize($poster_rule['picture']['width'],$poster_rule['picture']['height']);
                    }else{
                        try {
                            $headimg = new ImagickClass($this->headimgRoundFilename);
                            $headimg->resize($poster_rule['picture']['width'],$poster_rule['picture']['height']);
                            $headimg->save($this->headimgRoundFilename);
                            $headimg = new gdClass($this->headimgRoundFilename);
                        } catch(Throwable $e) {
                                $headimg = new gdClass($this->headimgFilename);
                                $headimg->resize($poster_rule['picture']['width'],$poster_rule['picture']['height']);
                                $headimg->save();
                                $this->roundCorners($this->headimgFilename,$poster_rule['picture']['width']);
                                $headimg = new gdClass($this->headimgRoundFilename);
                        }
                    }
                }else{
                    $headimg->resize($poster_rule['picture']['width'],$poster_rule['picture']['height']);
                }
                
                $postage->watermark($headimg,$poster_rule['picture']['x'],$poster_rule['picture']['y']);
            }
			
		}
		$postage->save($this->backgroundFilename);
		if(isset($poster_rule['name'])){
			$text = $poster_rule['name'];
			$this->write_font([$text]);
		}
		return $this->backgroundFilename;
	}

	public function tncodeBackground($title="test",$rand_num = 4,$rand_bnum=3,$x=300,$y=200){

		$background = APP_PATH.'/public/tncode/system/background'.$rand_bnum.'.jpg';
		$backgroundObj = new gdClass($background);
		$tncode = APP_PATH.'/public/tncode/system/back'.$rand_num.'.png';
		$qrcode = new gdClass($tncode);
		$backgroundObj->watermark($qrcode,$x,$y);
		$backgroundObj->save(APP_PATH.'/public/tncode/users/'.$title."_background.jpg");
		chmod(APP_PATH.'/public/tncode/users/'.$title."_background.jpg",0777);
		return APP_PATH.'/public/tncode/users/'.$title."_background.jpg";
	}
	//缺失板块图
	public function tncode($title="test",$rand_num = 4,$rand_bnum=3,$x=300,$y=200){
		$img = "./split/mark.png";
		$background = APP_PATH.'/public/tncode/system/background'.$rand_bnum.'.jpg';
		$headimg = new gdClass($background);
		$headimg->crop(60, 60,$x,$y);
		$chushi = APP_PATH."/public/tncode/users/".$title.'tncode.png';
		$headimg->save($chushi);
		$new_path = APP_PATH."/public/tncode/users/".$title.'.png';
		$path = APP_PATH.'/public/tncode/system/'.$rand_num.'.png';
		exec("convert $chushi -alpha set -gravity center -extent 60 $path -compose DstIn -composite $new_path");
    	chmod($new_path,0777);
        unlink($chushi);

    	return $new_path;
	}


	/**
	* @title 写入文字 (由于框架本身gd文字不是太友好)
	* @author luodiao
	* @param $string 需要写入的文字
	* @param $left 左边距
	* @param $top 顶部间距
	* @param $font_size 文字大小
	* @param $color 文字颜色 hex
	*/
	public function write_font($data){
	    $ext = pathinfo($this->backgroundFilename, PATHINFO_EXTENSION);
	    if(strcasecmp($ext,'png') == 0) {
            $img = imagecreatefrompng($this->backgroundFilename);//创建
        }elseif(strcasecmp($ext,'jpg') == 0){
	        $img = imagecreatefromjpeg($this->backgroundFilename);
        }
		list($bigWidth, $bigHight, $bigType) = getimagesize($this->backgroundFilename);//获取图片详情
		$backgroundInfo = array('width'=>$bigWidth,'height'=>$bigHight);
		foreach ($data as $key => $value) {
			if(isset($value['fontfile'])){
				$this->textW($img,$backgroundInfo,$value,$value['fontfile']);
			}else{
				$this->textW($img,$backgroundInfo,$value);
			}
		}
        if(strcasecmp($ext,'png') == 0) {
            imagepng($img,$this->backgroundFilename);
        }elseif(strcasecmp($ext,'jpg') == 0){
            imagejpeg($img,$this->backgroundFilename);
        }
		chmod($this->backgroundFilename, 0777);
	}




	public function textW(&$img,$backgroundInfo,$data,$fontfile = ''){
		if($fontfile == '') $fontfile = $this->fontfile;
		$data['text'] = filterEmoji($data['text']);
		if($data['text'] != ''){
			$space = imagettfbbox($data['size'], 0, $fontfile, $data['text']);
			if(isset($space[0])){
				$s0 = (int) $space[0];
				$s1 = (int) $space[1];
				$s4 = (int) $space[4];
				$s5 = (int) $space[5];
			}
			// if (!$s0 || !$s1 || !$s4 || !$s5) {
			// 	throw new Exception("Call to imagettfbbox() failed");
			// }

			$width  = abs($s4 - $s0) + 10;
			$height = abs($s5 - $s1) + 10;
			if ($data['x']< 0 ){
				$data['x'] = $backgroundInfo['width'] - $width + $offsetX;
			}
			if ($data['y'] < 0 ){
				$data['y'] = $backgroundInfo['height'] - $height + $offsetY;
			}
			$colors = $this->hex2rgb($data['color']);

			$color = imagecolorallocatealpha($img, $colors['r'], $colors['g'], $colors['b'],0);
			imagettftext($img , $data['size'] , 0 , $data['x'] , $data['y'] , $color , $fontfile , $data['text']);
		}
	}

	/**
	* @title hex 颜色转 RGB
	* @author luodiao
	* @param $hexColor 颜色色值
	*/
	public function hex2rgb($hexColor) {
		if($hexColor == 'fff' || $hexColor == '#fff' || $hexColor == 'FFF' || $hexColor == '#FFF'){
			$hexColor = '#ffffff';
		}
		$hexColor = strtolower($hexColor);
        $color = str_replace('#', '', $hexColor);
        if (strlen($color) > 3) {
            $rgb = array(
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2))
            );
        } else {
            $color = $hexColor;
            $r = substr($color, 0, 1) . substr($color, 0, 1);
            $g = substr($color, 1, 1) . substr($color, 1, 1);
            $b = substr($color, 2, 1) . substr($color, 2, 1);
            $rgb = array(
                'r' => hexdec($r),
                'g' => hexdec($g),
                'b' => hexdec($b)
            );
        }
        return $rgb;
    }

    public function imagickRound($path,$new_path){
    	exec("convert $path -alpha set -gravity center -extent 406 ". APP_PATH."/public/split/Round.png -compose DstIn -composite $new_path");
    	chmod($new_path,0777);
    }
	/**
	* @title 生成圆角图片
	* @author luodiao
	* @param src_img 图片地址 
	* @param size 大小宽度 
	*/
	public function roundCorners($src_img,$size) {
		$src_img = @imagecreatefromstring(file_get_contents($src_img));
		$w = $h = $size;
		$img = imagecreatetruecolor($w, $h);
		//这一句一定要有
		imagesavealpha($img, true);
		//拾取一个完全透明的颜色,最后一个参数127为全透明
		$bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
		imagefill($img, 0, 0, $bg);
		$r   = $w / 2; //圆半径
		$y_x = $r; //圆心X坐标
		$y_y = $r; //圆心Y坐标
		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$rgbColor = imagecolorat($src_img, $x, $y);
				if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
					imagesetpixel($img, $x, $y, $rgbColor);
				}
			}
		}
		imagepng($img,$this->headimgRoundFilename);
		chmod($this->headimgRoundFilename, 0777);
	}

	/**
	* title 下载图片 
	* @author luodiao
	*/
	public function curl_file_get_contents($url = 'http://baidu.com',$filename = ''){ 
		if($filename == '' || $url == '') return false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$imageData = curl_exec($ch);
		curl_close($ch);
		$tp = @fopen($filename, 'a');
		$res = fwrite($tp, $imageData);
		fclose($tp);
		chmod($filename, 0777);
	}


    /**
     * @param $poster_rule
     * @param string $qrcodeimg
     * @param $title
     * @param $background_url
     * @param $avatar_url
     * @param $theme_url
     * @param $name
     * @return mixed
     */
    public function mergePoster($poster_rule,$qrcodeimg = '',$title,$background_url,$avatar_url,$theme_url,$name,$extend=array()){
        $this->headimgRoundFilename = $this->userPath . $this->userKey .'_headimg_round.png';
        $this->backgroundFilename = $this->userPath . $this->userKey .'_background.jpg';
        if(isset($background_url) == false){
            //当背景图不存在的时候不做任何操作
            exit('需要上传背景图');
        }

        //下载背景
//        $this->curl_file_get_contents($background_url,$this->backgroundFilename);
        $backgroundFilename = CloudStorage::getImagesTmpFile($background_url,'long');
        copy($backgroundFilename,$this->backgroundFilename);
        $postage = new gdClass($this->backgroundFilename);
        if(isset($qrcodeimg)){
            $qrcode = new gdClass($qrcodeimg);
            $qrcode->resize($poster_rule['qrcode']['width'],$poster_rule['qrcode']['height']);
            $postage->watermark($qrcode,$poster_rule['qrcode']['x'],$poster_rule['qrcode']['y']);
        }
        if(isset($poster_rule['theme']) && isset($theme_url)){
            $themeFilename = CloudStorage::getImagesTmpFile($theme_url,'less');
//            $this->curl_file_get_contents($theme_url,$this->themeFilename);
            $theme = new gdClass($themeFilename);
            $theme->resize($poster_rule['theme']['width'],$poster_rule['theme']['height'])->crop($poster_rule['theme']['width'],$poster_rule['theme']['height']);
            $postage->watermark($theme,$poster_rule['theme']['x'],$poster_rule['theme']['y']);
        }

        //判断头像是否存在
        if(isset($poster_rule['avatar'])){
            //判断是否需要圆角图片
//            $this->curl_file_get_contents($avatar_url,$this->headimgFilename);
            $headimgFilename = CloudStorage::getImagesTmpFile($avatar_url,'long');
            //先确定大小
            $headimg = new gdClass($headimgFilename);
            $headimg->resize($poster_rule['avatar']['width'],$poster_rule['avatar']['height']);
            if($poster_rule['avatar']['status'] == 'Y'){
                //进行圆角处理
                $this->roundCorners($headimgFilename,$poster_rule['avatar']['width']);
                $headimg = new gdClass($this->headimgRoundFilename);

            }
            $postage->watermark($headimg,$poster_rule['avatar']['x'],$poster_rule['avatar']['y']);
        }

        $postage->save($this->backgroundFilename,80);
        $text = $titleName = null;
        //判断名字是否存在
        if(isset($poster_rule['name'])){
            $text = $poster_rule['name'];
            $text['text'] = $name;
            $titleName['fontfile'] = $this->fontfile;
        }
        //判断标题是否存在
        if(isset($title) && isset($poster_rule['title'])){
            $titleName = $poster_rule['title'];
            $titleName['text'] = $this->wordWrap($titleName['size'],0,$this->msyhBold,$title,$titleName['width'],true,$titleName['line']);
            $titleName['fontfile'] = $this->msyhBold;
        }
        $this->write_font([$text,$titleName]);

        if(!empty($extend)){
            if(isset($extend['text'])){
                //循环写入文字
                foreach ($extend['text'] as $key => $value) {
                    $postage->text( mb_convert_encoding($value['text'],'html-entities','UTF-8'),$value['x'],$value['y'],100,$value['color'],$value['size'],$this->fontfile);
                }
            }

            if(isset($extend['images'])){
                //循环写入图片
                foreach ($extend['text'] as $key => $value) {
                    //存入图片地址
                    $this->extendImage[] = $this->userPath . $this->userKey.'extendImage'.$key.'png';

                    $extend_img[$key] = new gdClass($value['url']);
                    $extend_img[$key]->resize($value['width'],$extend_img['height']);
                    $postage->watermark($extend_img[$key],$value['x'],$value['y']);
                }
            }
        }

       
        return $this->backgroundFilename;
    }




    /**
     * PHP实现图片上写入实现文字自动换行
     * @param  $fontsize 字体大小
     * @param  $angle 角度
     * @param  $font 字体路径
     * @param  $string 要写在图片上的文字
     * @param  $width  预先设置图片上文字的宽度
     * @param  $flag   换行时单词不折行
     */
    public function wordWrap($fontsize,$angle = 0,$font,$string,$width,$flag=true,$line=4) {
        if(empty($line)){
            $line = 4;
        }
        $content = "";
        $nowline = 1;
        if($flag){
            preg_match_all("/./u", $string, $arr);
            $words = $arr[0];
            foreach ($words as $key=>$value) {
                $teststr = $content." ".$value;
                $testbox = imagettfbbox($fontsize, $angle, $font, $teststr);
                //判断拼接后的字符串是否超过预设的宽度
                if(($testbox[2] > $width)) {
                    if($nowline>=$line){
                        $len = mb_strlen($content,'utf-8');
                        $content = mb_substr($content,0,$len-2,'utf-8');
                        $content .="...";
                        break;
                    }
                    $content .= "\n";
                    $nowline+=1;
                }
                $content .= " ".$value;
            }
        }else{
            //将字符串拆分成一个个单字 保存到数组 letter 中
            for ($i=0;$i<mb_strlen($string);$i++) {
                $letter[] = mb_substr($string, $i, 1);
            }
            foreach ($letter as $l) {
                $teststr = $content.$l;
                $testbox = imagettfbbox($fontsize, $angle, $font, $teststr);
                // 判断拼接后的字符串是否超过预设的宽度
                if (($testbox[2] > $width) && ($content !== "")) {
                    $content .= "\n";
                }
                $content .= $l;
            }
        }
        return $content;
    }



    /**
     * 保存二维码
     */
    public function saveQrcode($url,$path){
        \PHPQRCode\QRcode::png($url, $path,'L', 4, 2);
        chmod($path, 0777);
        return $path;

    }


    function showImg($img){
        $info = getimagesize($img);
        $imgExt = image_type_to_extension($info[2], false); //获取文件后缀
        $fun = "imagecreatefrom{$imgExt}";
        $imgInfo = $fun($img);         //1.由文件或 URL 创建一个新图象。如:imagecreatefrompng ( string $filename )
        //$mime = $info['mime'];
        $mime = image_type_to_mime_type(exif_imagetype($img)); //获取图片的 MIME 类型
        header('Content-Type:'.$mime);
        $quality = 100;
        if($imgExt == 'png') $quality = 9;   //输出质量,JPEG格式(0-100),PNG格式(0-9)
        $getImgInfo = "image{$imgExt}";
        $getImgInfo($imgInfo, null, $quality); //2.将图像输出到浏览器或文件。如: imagepng ( resource $image )
        imagedestroy($imgInfo);
    }


    public function mergeLBBPic($data){
        $path = APP_PATH.'/public/images/';
        $filename = 'LBB'.md5(time().rand(0,9999)).'.png';
        $file = $path.$filename;
        $w = 262;$h = 70;
        $box = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocatealpha($box, 77, 77, 77, 127);
        $fgcolor = imagecolorallocate($box, 0, 0, 0);        // 字体拾色
        imagealphablending($box, false);
        imagefill($box, 0, 0, $bg);
        $data['color'] = '#FFFFFF';
//        $data['size'] = 16;
        $data['y'] = 40;
        $data['width'] = 260;
        $data['line'] = 1;
        if(isset($data)){
            $data['text'] = $this->wordWrap($data['size'],0,$this->msyhBold,$data['name'],$data['width'],true,$data['line']);
            $data['fontfile'] = $this->msyhBold;
            $data['x'] = $w/2 -strlen($data['text'])/4*$data['size'];
        }
        imagefttext($box, $data['size'], 0, $data['x'], $data['y'], $fgcolor, $data['fontfile'], $data['text']);
        imagesavealpha($box , true);
        imagepng($box, $file);
        $this->backgroundFilename = $file;

        return $this->backgroundFilename;
    }


}
