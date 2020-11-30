<?php
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger;
/**
 * 生成图片base64
 * @param string $img_file
 * @return string
 */
function imgToBase64($img_file) {

    $img_base64 = '';
    if (file_exists($img_file)) {
        $app_img_file = $img_file; // 图片路径
        $img_info = getimagesize($app_img_file); // 取得图片的大小，类型等

        //echo '<pre>' . print_r($img_info, true) . '</pre><br>';
        $fp = fopen($app_img_file, "r"); // 图片是否可读权限

        if ($fp) {
            $filesize = filesize($app_img_file);
            $content = fread($fp, $filesize);
            $file_content = chunk_split(base64_encode($content)); // base64编码
            switch ($img_info[2]) {           //判读图片类型
                case 1: $img_type = "gif";
                    break;
                case 2: $img_type = "jpg";
                    break;
                case 3: $img_type = "png";
                    break;
            }

            $img_base64 = 'data:image/' . $img_type . ';base64,' . $file_content;//合成图片的base64编码

        }
        fclose($fp);
    }

    return $img_base64; //返回图片的base64
}
/**
 * 取得语言对应的locale值
 * @param string $language_alias
 * @return string
 */


function is_phone_number($mobile) {
	$mobile = preg_replace('/-\s/','',$mobile);
	if(is_numeric($mobile) && strlen($mobile) ==11){ //11位数字
			return true;
	}
	elseif(strpos($mobile,'+') !== false){
		return true;
	}
	else{

	}
	return false;
}


/**
 * 清空文件夹下的所有文件
 * @param string $dir 文件夹路径
 * @param boolean $recusive 是否递归删除目录
 * @return boolean
 */
function clearFolder($dir,$recusive=false){
	if (is_dir($dir)) {
		$files = glob($dir .DS. '*');
		if ($files === false) {
			return false;
		}

		foreach ($files as $file) {
			if(in_array($file,array('.','..'))){
				continue;
			}
			elseif (is_file($file)) {
				@unlink($file);
			}
			elseif($recusive && is_dir($file)){
				clearFolder($file,$recusive);
				// 不删除文件夹，保留目录下所有文件夹。如删除缓存时，需要保留现有缓存下的文件夹
				//@rmdir($file);
			}
		}
		return true;
	}
}

function get_url_headers($url,$timeout=1) {
    $ch= curl_init();

    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HEADER,true); // 返回头部信息
    curl_setopt($ch,CURLOPT_NOBODY,true); // 不返回具体内容。
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 1);

    $data = curl_exec($ch);

    if ( curl_errno($ch) ) {
        CakeLog::error(" curl error in get_url_headers is $url. msg=".curl_error($ch) );
        curl_close($ch);
        return false; // 获取内容错误
    }
    curl_close($ch);

    $data = preg_split('/\n/',$data);

    $data= array_filter(array_map(function($data){
        $data=trim($data);
        if($data){
            $data=preg_split('/:\s/',trim($data),2);
            $length=count($data);
            switch($length){
                case 2:
                    return array($data[0]=>$data[1]);
                    break;
                case 1:
                    return $data;
                    break;
                default:
                        break;
            }
        }
    },$data));

    sort($data);

    foreach($data as $key=>$value){
        $itemKey=array_keys($value)[0];
        if( is_int($itemKey) ){
            $data[$key]=$value[$itemKey];
        }elseif(is_string($itemKey)){
            $data[$itemKey]=$value[$itemKey];
            unset($data[$key]);
        }
    }
    return $data;
}
/**
 * 仅判断http://与https://的弱校验
 * @param $url
 * @return bool
 */
function is_valid_url($url){
    App::uses('Validation', 'Utility');
    if( Validation::url($url,true) ) {
        //$heads = @get_headers($url);
        $heads = get_url_headers($url); // 带timeout，防止超时
        if( is_array($heads) && isset($heads['Content-Length']) ){ //获取了返回内容的长度
            return true;
        }else{
            return false;
        }
    }
    else{
        return false;
    }
}

function is_image($url){
	$file_type = get_mime_type($url);
	if(in_array($file_type,array ("image/png", "image/gif", "image/jpeg", "image/bmp", "image/jpg"))){
		return true;
	}
	return false;
}
/**
 * 是否为搜索引擎抓取
 * @param string $user_agent
 * @return boolean
 */
function is_search_bot($user_agent) {
    if (preg_match('/(bot|spider|baidu|google)/is', $user_agent, $matches)) {
//		print_r($matches);
        return true;
    }
    else
        return false;
}


/**
 * 获取exception的全量stack strace错误输出。$e->getTraceAsString()；默认的被截取字符串，显示不完整
 * @param Exception $exception
 * @return string
 */
function getExceptionTraceAsString($exception) {
    $rtn = "";
    $count = 0;
    foreach ($exception->getTrace() as $frame) {
        empty($frame['file']) && $frame['file'] = "[internal function]";
        empty($frame['class']) || $frame['class'] = $frame['class']."->";
        $args = "";
        if (isset($frame['args'])) {
            $args = array();
            foreach ($frame['args'] as $arg) {
                if (is_string($arg)) {
                    $args[] = "'" . $arg . "'";
                } elseif (is_array($arg)) {
                    $args[] = "Array:";//.var_export($arg,true);
                } elseif (is_null($arg)) {
                    $args[] = 'NULL';
                } elseif (is_bool($arg)) {
                    $args[] = ($arg) ? "true" : "false";
                } elseif (is_object($arg)) {
                    $args[] = get_class($arg);
                } elseif (is_resource($arg)) {
                    $args[] = get_resource_type($arg);
                } else {
                    $args[] = $arg;
                }
            }
            $args = join(", ", $args);
        }
        $rtn .= sprintf( "#%s %s(%s): %s->%s(%s)\n",
            $count,
            $frame['file'],
            $frame['line'],
            $frame['class'],
            $frame['function'],
            $args );
        $count++;
    }
    return $rtn;
}

function print_stack_trace()
{
    $array =debug_backtrace();
    //print_r($array);//信息很齐全
    unset($array[0]);
    $html = '';
    foreach($array as $row)
    {
        $html .= "<p>rows:".$row['file'].':'.$row['line'].',function:'.$row['class'].':'.$row['function']."</p>\r\n";
    }
    return $html;
}

function getStaticFileUrl($url,$full=true){
	$url = Router::url($url,$full);
	$url = str_replace(env('SCRIPT_NAME'),'',$url);
	return $url;
}


/**
 * close all open xhtml tags at the end of the string
 * @param string $html
 * @return string
 */
function closetags($html) {
	#put all opened tags into an array
	preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
	$openedtags = $result[1];
	#put all closed tags into an array
	preg_match_all('#</([a-z]+)>#iU', $html, $result);
	$closedtags = $result[1];
	$len_opened = count($openedtags);
	# all tags are closed
	if (count($closedtags) == $len_opened) {
		return $html;
	}
	$openedtags = array_reverse($openedtags);
	# close tags
	for ($i=0; $i < $len_opened; $i++) {
		if (!in_array($openedtags[$i], $closedtags)){
			$html .= '</'.$openedtags[$i].'>';
		}
		else {
			unset($closedtags[array_search($openedtags[$i], $closedtags)]);
		}
	}
	return $html;
}

/**
 * 获取搜索的链接 . 若传入的链接是CakeRequest对象，返回的链接可以直接使用。
 * 若传入的链接是一个字符串如 /product/view/1，在外部需要router::url()或者Html->url进行再次处理
 * @param mix $request string or request object.页面request对象
 * @param array $extra	搜索追加参数
 * @param array $delparams	需要删除的参数。 （需要减去的参数（如去掉搜索条件），或可能包含<,>,like等；不方便直接数组覆盖，需要手动指定删除的参数字段名）
 * @param boolean $strip_base 是否去除二级目录的信息，去除时 返回结果仍需要调用Router::url($result).在SectionHelper中使用到
 */
function getSearchLinks($url, $extra=array(), $delparams=array(),$strip_base=false) {
	if($url instanceof CakeRequest ){
		$query = $url->query;
		$url = $url->here;
		// $url = $url->base.'/'.$url->url;
		if(empty($query)) $query = array();
	}
	else{
		$query = $_GET;
	}
// 	print_r($url);
	// 删除需要删除的字段查询条件
    foreach ($query as $key => $val) {
        foreach ($delparams as $del) {
           if (strpos($key, $del) === 0) {
               unset($query[$key]);
           }
        }
    }
    // 删除要删除的项后，再合并$extra，防止$extra中的项被删除
    $query = array_delete_value($query ,''); //删除空的参数
    $query = array_merge($query,$extra);

//     $query = array_rawurlencode($query);
    unset($query['page']);  // 去除分页页码参数，搜索的条件全部默认是显示第一页。这里不需要传页码参数，仅翻页链接需要页码参数。
//     $url = Router::url($url_array);

    if (!empty($query)) {
        $querystring = http_build_query($query);
//         foreach ($query as $key => $val) {
//         	if(!empty($val)){
//             	$querystring .= urlencode($key). '=' . urlencode($val) . '&';
//         	}
//         }
//         $querystring = substr($querystring, 0, -1);
        $url = $url . '?' . $querystring;
    }
    return $url;
}

/**
 * 生成随机的串
 * @param int $length
 * @param string $type
 */
function random_str($length, $type = "char") {
    if($type == 'num') {
        $chars = "0123456789";
    }
    elseif($type == 'upper') {
        $chars = "ABCDEFGHJKMNPQRSTUVWXYZ";//去除了I,L,O
    }
    elseif($type == 'lower') {
        $chars = "abcdefghjkmnpqrstuvwxyz";//去除了i,l,o
    }
    elseif($type == 'uppernum') {
        $chars = "ABCDEFGHJKMNPQRSTUVWXYZ23456789";//去除了I,L,0,O,1
    }
    elseif($type == 'lowernum') {
        $chars = "abcdefghjkmnpqrstuvwxyz23456789"; //去除了i,1，L,0,o
    }
    else{
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz";
    }
    //$chars = ($type != 'num') ? "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz" : "123456789";
    $max = strlen($chars) - 1;
    mt_srand((double) microtime() * 1000000);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $s = $chars[mt_rand(0, $max)];
        while($i==0 && $type=='num' && $s == '0') { // 数字形式第一个字符的开始为0时，重新生成第一个字符
            $s = $chars[mt_rand(0, $max)];
        }
        $string .= $s;
    }
    return $string;
}
/**
 * 文件大小格式化
 * @param unknown_type $filesize
 * @return string
 */
function format_filesize($filesize){
	if($filesize>1024*1024){
		return round($filesize/(1024*1024),2).'M';
	}
	elseif($filesize>1024){
		return round($filesize/1024,2).'KB';
	}
	else{
		return $filesize.'B';
	}
}

/**
 * 时间格式化
 * @param int $interval 时间间隔
 * @return string
 */
function format_time($interval){
    $str = '';
    /*if($interval>86400){
        $day = floor($interval/86400);
        $str .= $day.'天';
        $interval = $interval - $day*86400;
    }*/
    if($interval >= 3600){
        $hour = floor($interval/3600);
        $str .= ($hour<10?'0':'').$hour.':';
        $interval = $interval-$hour*3600;
    }
    else{
        $str .= '00:';
    }
    if($interval >= 60){
        $min = floor($interval/60);
        $str .= ($min<10?'0':'').$min.':';
        $interval = $interval%60;
    }
    else{
        $str .= '00:';
    }
    if( $interval > 0) {
        $str .= ($interval<10?'0':'').$interval;
    }
    else{
            $str .= '00';
    }
    return $str;
}
/**
 * 时间间隔格式化，时间间隔。几月前，几天前，几时前
 * @param int $interval 时间间隔
 * @return string
 */
function format_time_interval_ago($interval){
    $str = '';
    if($interval >= 2592000){
        $month = floor($interval/2592000);
        $str .= $month.'月';
        $interval = $interval - $month*2592000;
    }
    elseif($interval >= 86400){
        $day = floor($interval/86400);
        $str .= $day.'天';
        $interval = $interval - $day*86400;
    }
    elseif($interval >= 3600){
        $hour = floor($interval/3600);
        $str .= $hour.'时';
        $interval = $interval-$hour*3600;
    }
    elseif($interval >= 60){
        $min = floor($interval/60);
        $str .= $min.'分';
        $interval = $interval%60;
    }
    elseif( $interval ) { //mb_strlen($str)<4 &&
        $str .= $interval.'秒';
    }
    return $str.'前';
}
/**
 * 时间间隔格式化,倒计时，type=1 几月几天几时几分几秒
 * type=2 几月几天几时
 * type=3 几月几天
 * @param int $interval 时间间隔
 * @return string
 */
function format_time_interval($interval,$type=1){
    $str = '';
    if($interval >= 2592000){
        $month = floor($interval/2592000);
        $str .= $month.'月';
        $interval = $interval - $month*2592000;
    }
    if($interval >= 86400){
        $day = floor($interval/86400);
        $str .= $day.'天';
        $interval = $interval - $day*86400;
    }
    if($type==1 || $type==2  ) {
        if($interval >= 3600){
            $hour = floor($interval/3600);
            $str .= $hour.'时';
            $interval = $interval-$hour*3600;
        }
    }
    if($type == 1) {
        if($interval >= 60){
            $min = floor($interval/60);
            $str .= $min.'分';
            $interval = $interval%60;
        }
        if( $interval ) { //mb_strlen($str)<4 &&
            $str .= $interval.'秒';
        }
    }
    return $str;
}

/**
 *  根据PHP各种类型变量生成唯一标识号
 * @param mix $mix
 * @return string
 */
function guid_string($mix) {
    if (is_object($mix) && function_exists('spl_object_hash')) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * 选项转换为数组
 * 0=>女
  1=>男
  2=>不详
 *
 */
function optionstr_to_array($string) {
    $return_array = array();
    $string = str_replace("\r\n","\n",$string);
    $array = explode("\n", $string);
    foreach ($array as $val) {
        if (empty($val)) {
            continue;
        }
        $temp = explode('=>', $val);
        if (count($temp) == 2) {
            $return_array[$temp[0]] = $temp[1];
        } else {
            $return_array[$temp[0]] = $temp[0];
        }
    }
    return $return_array;
}

function resetWechatContent($content) {
	$new_content = preg_replace_callback('~<img\s.*?src=["|\'](http[s]?://.+?)["|\']~is','resetWechatCallback',$content);
	$new_content = preg_replace_callback('~<image\s.*?xlink:href=["|\'](http[s]?://.+?)["|\']~is','resetWechatCallback',$new_content);
	$new_content = preg_replace_callback('~\(["|\']?(http[s]?://.+?)["|\']?\)~is','resetWechatCallback',$new_content);
	$new_content = preg_replace_callback('~&quot;(http[s]?://.+?)&quot;~is','resetWechatCallback',$new_content);
	return $new_content;
}

function resetWechatCallback($matches){
	return str_replace($matches[1],resetWechatUrl($matches[1]), $matches[0]);
}

function resetWechatUrl($srcurl)
{
    $urlinfo = parse_url($srcurl);
    if (substr($urlinfo['path'], 0, 14) == '/cache/remote/') {
        $url = substr($urlinfo['path'], 14);
        @list($url, $rule) = explode('@', $url);
        $url = base64_decode($url);
        if ($url && (strpos($url, 'mmbiz') !== false || strpos($url, 'mmsns') !== false)) { // mmbiz.qlogo.cn
            $url = str_replace('http://mmbiz.qpic.cn', 'https://mmbiz.qlogo.cn', $url);
            $url = str_replace('?wx_fmt=', '.', $url);
            return $url;
        }
    }
    return $srcurl;
}

function unicode_decode($name,$charset = 'UTF-8'){//GBK,UTF-8,big5
		$pattern = '/\\\u[\w]{4}/i';
		preg_match_all($pattern, $name, $matches);
		//print_r($matches);exit;
		if (! empty ( $matches )) {
			//$name = '';
			for($j = 0; $j < count ( $matches [0] ); $j ++) {
				$str = $matches [0] [$j];
				if (strpos ( $str, '\u' ) === 0) {
					$code = base_convert ( substr ( $str, 2, 2 ), 16, 10 );
					$code2 = base_convert ( substr ( $str, 4 ), 16, 10 );
					$c = chr ( $code ) . chr ( $code2 );
					if ($charset == 'GBK') {
						$c = iconv ( 'UCS-2BE', 'GBK', $c );
					} elseif ($charset == 'UTF-8') {
						$c = iconv ( 'UCS-2BE', 'UTF-8', $c );
					} elseif ($charset == 'BIG5') {
						$c = iconv ( 'UCS-2BE', 'BIG5', $c );
					} else {
						$c = iconv ( 'UCS-2BE', $charset, $c );
					}
					//$name .= $c;
					$name = str_replace($str,$c,$name);
				}
				//else {
				//	$name .= $str;
				//}
			}
		}
		return $name;
}

/**
 * 将模版中的列表调用参数转换成数组.
 * eval虽然是模板中代码更清晰，但不支持hiphop。另外eval方式不是非常安全，可能加入其它有危害的代码。
 * 故使用parse_str的方式，按url get方式传入字符串。
 *
 * 例如：
 *
 * model=Product|cached=900|pagelink=no|title=最新排行|options['fields']=array('Product.id','Product.name','Product.created')|portlet=default|list_tpl=scripts|limitnum=8|orderby=id desc
 * @param string $info
 * @return array
 */
function parseInfoToArray($info){
	$infos = array();
	parse_str($info,$infos);
	return $infos;
// 	$params=array();
// 	// 去除竖线分隔符两侧的空白符
// 	$info = preg_replace('/\s*\|\s*/','|',trim($info));
// 	$param_array = explode('|',$info);
// 	foreach($param_array as $variable){
// 		if(!empty($variable)){
// 			$expresions = explode('=', $variable);
// 			$key = array_shift($expresions);
// 			$value=implode('=',$expresions);
// 			if($key){
// 				$pos = strpos($key,'[');
// 				if($pos===false){ // 不含[
// 					//$params[$key]=$value;
// 					eval('$params[\''.$key.'\']='.$value.';');
// 				}
// 				else{
// 					if($pos==0) continue; // [不能在第一位
// 					//当含有[ 时，为数组，使用eval来处理赋值语句
// 					//eval('$'.$key.'='.$value.';');$params[$vkey] = $$vkey;
// 					$vkey = substr($key,0,$pos);
// 					eval('$params[\''.$vkey.'\']='.$value.';');
// 				}
// 			}
// 		}
// 	}
// 	return $params;
}

function safe_json_encode($value, $options = 0, $depth = 512) {
    $encoded = json_encode($value, $options, $depth);
    if ($encoded === false && $value && json_last_error() == JSON_ERROR_UTF8) {
        $encoded = json_encode(utf8ize($value), $options, $depth);
    }
    return $encoded;
}

function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
}
/*
 * php5.4 以后，json_encode增加了JSON_UNESCAPED_UNICODE , JSON_PRETTY_PRINT 等几个常量参数。使显示中文与格式化更方便。
 * echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
 *
 */

/** 将数组元素进行urlencode
 * @param String $val
 */
function jsonFormatProtect(&$val){
	if($val!==true && $val!==false && $val!==null){
		$val = urlencode($val);
	}
}
/** Json数据格式化
 * @param  Mixed  $data   数据
 * @param  String $indent 缩进字符，默认4个空格
 * @return JSON
 */
function jsonFormat($data, $indent=null){

    if( is_array($data) && defined('JSON_UNESCAPED_UNICODE') && defined('JSON_PRETTY_PRINT') ) {
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
    if(is_array($data)) {
        // 对数组中每个元素递归进行urlencode操作，保护中文字符
        array_walk_recursive($data, 'jsonFormatProtect');
        // json encode
        $data = json_encode($data);
    }

	// 将urlencode的内容进行urldecode
	$data = urldecode($data);

	// 缩进处理
	$ret = '';
	$pos = 0;
	$length = strlen($data);
	$indent = isset($indent)? $indent : '    ';
	$newline = "\n";
	$prevchar = '';
	$outofquotes = true;

	for($i=0; $i<=$length; $i++){

		$char = substr($data, $i, 1);

		if($char=='"' && $prevchar!='\\'){
			$outofquotes = !$outofquotes;
		}elseif(($char=='}' || $char==']') && $outofquotes){
			$ret .= $newline;
			$pos --;
			for($j=0; $j<$pos; $j++){
				$ret .= $indent;
			}
		}

		$ret .= $char;

		if(($char==',' || $char=='{' || $char=='[') && $outofquotes){
			$ret .= $newline;
			if($char=='{' || $char=='['){
				$pos ++;
			}

			for($j=0; $j<$pos; $j++){
				$ret .= $indent;
			}
		}

		$prevchar = $char;
	}

	return $ret;
}

function arrayToJson($val) {
    App::uses("Services_JSON", "Pear");
    $json = new Services_JSON();
    return $json->encode($val);
}

function jsonToArray($val) {
    App::uses("Services_JSON", "Pear");
    $json = new Services_JSON();
    $obj = $json->decode($val);
    return object_to_array($obj);
}
/**
 * 修改数组的所有索引为小写
 * @param unknown_type $array
 * @return multitype:NULL unknown
 */
function array_change_keylower($array) {
    $temparray = array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $temparray[strtolower($key)] = array_change_keylower($value);
        } else {
            $temparray[strtolower($key)] = $value;
        }
    }
    return $temparray;
}

function mkdir_p($dir, $mode = 0777)  {
	if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
	if (!mkdir_p(dirname($dir), $mode)) return FALSE;
	return @mkdir($dir, $mode);
}

/**
 * substr 对字符截取，英文字符两个占一个长度，汉字一个占一个长度
 * @param $string
 * @param $length
 * @param $strpad
 */
function gsubstr($string, $length, $strpad='') {
    if (strlen($string) > $length) {
        for ($i = 0; $i < $length; $i++)
            if (ord($string[$i]) > 128) {
                $i++;
            }
        if ($i > $length) {
            $i -= 2;
        }
        $string = substr($string, 0, $i) . $strpad;
    }
    return $string;
}

function filterEmoji($str)
{
    //遍历字符串中的每个字符，如果该字符的长度为4个字节，就将其删除
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);

    return $str;
}

/**
 * 主要用于HtmlParsers::parse,一键排版计算与判断标题字数。
 * @param $str 计算长度字符串
 * @return float 返回字数
 */
function ustrlen($str) {
    $count = 0.0;
    for ($i = 0; $i < strlen($str); $i++) {
        $value = ord($str[$i]);
        if ($value > 127) {
            if ($value >= 192 && $value <= 223) {
                $i++;
            } else if ($value >= 224 && $value <= 239) {
                $i = $i + 2;
            } else if ($value >= 240 && $value <= 247) {
                $i = $i + 3;
            } else if ($value >= 248 && $value <= 251) {
                $i = $i + 4;
            } else if ($value >= 252 && $value <= 253) {
                $i = $i + 5;
            } else {
                //die('Not a UTF-8 compatible string');
            }
            $count++;
        } else {
        	if($value == 32) {
        		$count ++;  //空格占一个长度
        	}
        	else{
        		$count = $count + 0.33;  //英文字符三个占一个长度，汉字一个占一个长度
        	}

        }
    }
    if( $count > 5 ) { // 内容较多时，采用四舍五入法取长度
        return round($count);
    }
    else{
        return ceil($count); //比如只含一个数字，或字母时，要用进一法取长度,
    }
}


function hideNameStar($str) {
    if(mb_strlen($str) == 2) {
        return mb_substr($str,0,1,'UTF-8').'*';
    }
    else{
        return mb_substr($str,0,1,'UTF-8').'**'.mb_substr($str,-1,1,'UTF-8');
    }
}

/**
 * 按字节长度截取字符
 */
function usubstr_byte($str, $position, $length=0, $strpad=false) {
    if(is_array($str)) {
        $str = implode(" \r\n",$str);
    }
    $start_byte = 0;
    $totallenth = $start_position = $end_position = strlen($str);
    $count = 0;
    for ($i = 0; $i < strlen($str); $i++) {
        if ($count >= $position && $start_position > $i) {
            $start_position = $i;
        }
        if ( $length && ($count - $position) >= $length ) {
            $end_position = $i;
            break;
        }
        $value = ord($str[$i]);
        if ($value > 127) {
            if ($value >= 192 && $value <= 223) {
                $i++; $count++;
            } else if ($value >= 224 && $value <= 239) {
                $i = $i + 2; $count+=2;
            } else if ($value >= 240 && $value <= 247) {
                $i = $i + 3;$count+=3;
            } else if ($value >= 248 && $value <= 251) {
                $i = $i + 4;$count+=4;
            } else if ($value >= 252 && $value <= 253) {
                $i = $i + 5;$count+=5;
            } else {
                $start_position++;
                //die('Not a UTF-8 compatible string');
            }
        } else {
            $count ++;  //英文字符两个占一个长度，汉字一个占一个长度
        }
    }
    $returnstr = substr($str, $start_position, $end_position - $start_position);

    if ($strpad && $totallenth > $end_position)
        $returnstr.=$strpad;
    return $returnstr;
}

/**
 * 按字数截取字符串
 * @param $str
 * @param $position
 * @param int $length
 * @param bool $strpad
 * @return bool|string
 *
 */
function usubstr($str, $position, $length=0, $strpad=false) {
    if(is_array($str)) {
        $str = implode(" \r\n",$str);
    }
    $start_byte = 0;
    $totallenth = $start_position = $end_position = strlen($str);
    $count = 0.0;
    for ($i = 0; $i < strlen($str); $i++) {
        if ($count >= $position && $start_position > $i) {
            $start_position = $i;
            $start_byte = $count;
        }
        if ( $length && ($count - $position) >= $length ) {
            $end_position = $i;
            break;
        }
        $value = ord($str[$i]);
        if ($value > 127) {
            if ($value >= 192 && $value <= 223) {
                $i++;
            } else if ($value >= 224 && $value <= 239) {
                $i = $i + 2;
            } else if ($value >= 240 && $value <= 247) {
                $i = $i + 3;
            } else if ($value >= 248 && $value <= 251) {
                $i = $i + 4;
            } else if ($value >= 252 && $value <= 253) {
                $i = $i + 5;
            } else {
                $start_position++;
                //die('Not a UTF-8 compatible string');
            }
            $count++;
        } else {
            $count = $count + 0.5;  //英文字符两个占一个长度，汉字一个占一个长度
        }
    }
    $returnstr = substr($str, $start_position, $end_position - $start_position);

    if ($strpad && $totallenth > $end_position)
        $returnstr.=$strpad;
    return $returnstr;
}

function filterUtf8($str)
{
    /*utf8 编码表：
    * Unicode符号范围           | UTF-8编码方式
    * u0000 0000 - u0000 007F   | 0xxxxxxx
    * u0000 0080 - u0000 07FF   | 110xxxxx 10xxxxxx
    * u0000 0800 - u0000 FFFF   | 1110xxxx 10xxxxxx 10xxxxxx
    *
    */
    $re = '';
    $str = str_split(bin2hex($str), 2);

    $mo =  1<<7;
    $mo2 = $mo | (1 << 6);
    $mo3 = $mo2 | (1 << 5);         //三个字节
    $mo4 = $mo3 | (1 << 4);          //四个字节
    $mo5 = $mo4 | (1 << 3);          //五个字节
    $mo6 = $mo5 | (1 << 2);          //六个字节


    for ($i = 0; $i < count($str); $i++)
    {
        if ((hexdec($str[$i]) & ($mo)) == 0)
        {
            $re .=  chr(hexdec($str[$i]));
            continue;
        }

        //4字节 及其以上舍去
        if ((hexdec($str[$i]) & ($mo6) )  == $mo6)
        {
            $i = $i +5;
            continue;
        }

        if ((hexdec($str[$i]) & ($mo5) )  == $mo5)
        {
            $i = $i +4;
            continue;
        }

        if ((hexdec($str[$i]) & ($mo4) )  == $mo4)
        {
            $i = $i +3;
            continue;
        }

        if ((hexdec($str[$i]) & ($mo3) )  == $mo3 )
        {
            $i = $i +2;
            if (((hexdec($str[$i]) & ($mo) )  == $mo) &&  ((hexdec($str[$i - 1]) & ($mo) )  == $mo)  )
            {
                $r = chr(hexdec($str[$i - 2])).
                    chr(hexdec($str[$i - 1])).
                    chr(hexdec($str[$i]));
                $re .= $r;
            }
            continue;
        }



        if ((hexdec($str[$i]) & ($mo2) )  == $mo2 )
        {
            $i = $i +1;
            if ((hexdec($str[$i]) & ($mo) )  == $mo)
            {
                $re .= chr(hexdec($str[$i - 1])) . chr(hexdec($str[$i]));
            }
            continue;
        }
    }
    return $re;
}

/**
 * 类、对象转数组
 *
 * @param object $object	类、对象
 * @param string $is_iconv	转码格式，默认为空不进行转码，格式如 'utf-8|gbk' 为把数据由 utf-8 转码为 gbk
 */
function object_to_array($object, $is_iconv = '') {
    $array = array();
    if (is_object($object)) {
        $object = get_object_vars($object);
    }
    if (is_array($object)) {
        foreach ($object as $key => $val) {
            $array[$key] = object_to_array($val, $is_iconv);
        }
    } else {
        $array = $object;
        if (!empty($is_iconv)) {
            $is_iconv = explode('|', $is_iconv);
            $array = @iconv($is_iconv[0], $is_iconv[1], $array);
        }
    }
    unset($object);
    return $array;
}

/**
 * 删除数组中为某一值的所有元素,不传value时，删除空值
 * @param $array
 * @param $value
 */
function array_delete_value($array, $value=null,$trim = false) {
    if (!empty($array) && is_array($array)) {
        foreach ($array as $k => $v) {
            if($trim && $v == trim($value)){
            	unset($array[$k]);
            }
            elseif ($v == $value) {
                unset($array[$k]);
            }
        }
    } else {
        $array = array();
    }
    return $array;
}

function array_to_table($array,$skip_empty = true){
	if(!is_array($array) || empty($array)){
		return '';
	}
	$HTML = "<table class=\"table\" width=\"100%\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\" bordercolor=\"#CCC\">";
	foreach($array as $key=> $value){
		if(empty($value) && $skip_empty){
			continue;
		}
		if(is_array($value)){
			$HTML .= "<tr><td>$key</td><td class=\"make_table_td\">".array_to_table($value)."</td></tr>";
		}
		else{
			$HTML .= "<tr><td>$key</td><td class=\"make_table_td\">".nl2br($value)."</td></tr>";
		}
	}
	$HTML .= "</table>";
	return $HTML;
}

/**
 * 对数组的各项key,value递归进行rawurlencode，并返回数组.key,value同时处理
 * @param $array
 * @return array
 */
function array_rawurlencode($array) {
    if (is_array($array)) {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $newkey = rawurlencode($key);
                $array[$newkey] = array_rawurlencode($val);
                if ($newkey != $key) {
                    unset($array[$key]);
                }
            } else {
                $newkey = rawurlencode($key);
                $array[$newkey] = rawurlencode($val);
                if ($newkey != $key) {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    } else {
        return rawurlencode($array);
    }
}

function xml_to_array($xml){
    $result = simplexml_load_string($xml,'SimpleXMLElement',LIBXML_COMPACT |LIBXML_NOCDATA);
    return get_object_vars($result);
}
function array_strip_tags($array,$trim=true){
	if (is_array($array)) {
		foreach ($array as $key => &$val) {
			if (is_array($val)) {
				$val = array_strip_tags($val);
			} else {
				if($trim){
					$val = trim(strip_tags($val));
				}
				else{
					$val = strip_tags($val);
				}
			}
		}
		return $array;
	} else {
		if($trim){
			return trim(strip_tags($array));
		}
		else{
			return strip_tags($array);
		}
	}
}

/**
 * mime_content_type函数已不建议使用，定义get_mime_type函数获取文件的mime类型。
 * 获取mime的函数环境很可能不支持，默认使用文件后缀对应类型的方法来匹配，不存在的类型再用函数获取。
 */
function get_mime_type($filename,&$ext = null) {

	$mime_types = array(
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        "asc" => "text/plain",
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'text/xml',
        "rtx" => "text/richtext",
        'rtf' => 'text/rtf',

        "xhtml" => "application/xhtml+xml",
        "xht" => "application/xhtml+xml",

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'ief' => 'image/ief',
        'djvu' => 'image/vnd.djvu',
        'djv' => 'image/vnd.djvu',
        'wbmp' => 'image/vnd.wap.wbmp',
        'ras' => 'image/x-cmu-raster',
        'pnm' => 'image/x-portable-anymap',
        'pbm' => 'image/x-portable-bitmap',
        'pgm' => 'image/x-portable-graymap',
        'ppm' => 'image/x-portable-pixmap',
        'rgb' => 'image/x-rgb',
        'xbm' => 'image/x-xbitmap',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-windowdump',
        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'mxu' => 'video/vnd.mpegurl',
        'avi' => 'video/x-msvideo',
        'movie' => 'video/x-sgi-movie',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
        'au' => 'audio/basic',
        'snd' => 'audio/basic',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'kar' => 'audio/midi',
        'mpga' => 'audio/mpeg',
        'mp2' => 'audio/mpeg',
        'aif' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'm3u' => 'audio/x-mpegurl',
        'ram' => 'audio/x-pn-realaudio',
        'rm' => 'audio/x-pn-realaudio',
        'rpm' => 'audio/x-pn-realaudio-plugin',
        'ra' => 'audio/x-realaudio',
        'wav' => 'audio/x-wav',

        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        'pdf' => 'application/pdf',

        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',

        "zip" => "application/zip",

        "so" => "application/octet-stream",
        "dll" => "application/octet-stream",
    );
	$split_arr = explode('.', $filename);
	$ext = strtolower(end($split_arr));
    if (array_key_exists($ext, $mime_types)) {
		return $mime_types[$ext];
	}
    else if(substr($filename,0,4) == 'http') { // 为网址形式
        $heads = @get_headers($filename);
        $head_arr = array();
        if(!empty($heads)) { // Content-Type: image/png
            foreach($heads as $h) {
                list($k,$v) = explode(': ',$h);
                $head_arr[$k] = $v;
            }
        }
        list($type,$ext) = explode('/',$head_arr['Content-Type']);
        return $head_arr['Content-Type'];
    }
	else if(file_exists($filename)){
        if ( class_exists('finfo') ) {
            $finfo =  new finfo(FILEINFO_MIME);
            $mime_ret = $finfo->file($filename);
            list($mime) = explode(';', $mime_ret);
        }
        elseif(function_exists('exif_imagetype')){
			$exif_type = @exif_imagetype($filename);
			if($exif_type==IMAGETYPE_GIF){
				$mime = 'image/gif';
			}
			elseif($exif_type==IMAGETYPE_JPEG){
				$mime = 'image/jpeg';
			}
			elseif($exif_type==IMAGETYPE_PNG){
				$mime = 'image/png';
			}
			elseif($exif_type==IMAGETYPE_BMP){
				$mime = 'image/bmp';
			}
		}
		if( !empty($mime) ){
			return $mime;
		}
		else{
			return 'application/octet-stream';
		}
	}
	return null;
}

function getFileType($file){
        $fp = fopen($file, "rb");
        $bin = fread($fp, 2); //只读2字节
        fclose($fp);
        $str_info  = @unpack("C2chars", $bin);
        $type_code = intval($str_info['chars1'].$str_info['chars2']);
        $file_type = '';
        switch ($type_code) {
            case 8075:
                $file_type = 'xlsx';
                break;
            case 8297:
                $file_type = 'rar';
                break;
            case 255216:
                $file_type = 'jpg';
                break;
            case 7173:
                $file_type = 'gif';
                break;
            case 6677:
                $file_type = 'bmp';
                break;
            case 13780:
                $file_type = 'png';
                break;
            case 3780:
                $file_type = 'pdf';
                break;
            case 208207:
                $file_type = 'xls';
                break;
            default:
                $file_type = 'unknown';
                break;
        }
    return $file_type;
}

function replaceLazyLoad($matches){
    if( empty($matches[2]) ) {
        return null;
    }
    if(! is_array($GLOBALS['lazy_images']) ) {
        $GLOBALS['lazy_images'] = array();
    }
    array_push($GLOBALS['lazy_images'],$matches[2]);
    if(count($GLOBALS['lazy_images'])==1 && !defined('LAZY_ALL_IMAGE') ){ //默认保留第一张图片不处理
        return $matches[0];
    }
    else{
        $attrs = $matches[1].' '.$matches[3];
        // 去掉原有的图片的class样式
        if(strpos($attrs,'class=') !== false) {
            $attrs = preg_replace('/class=[\'|"].+?["|\']/i','',$attrs);
        }
        return '<img '.$attrs.' class="lazy" src="http://static.135editor.com/img/grey.gif" data-src="'.$matches[2].'"/>';
    }
}

function strip_imgthumb_opr($imgurl){ //过滤阿里云等图片处理参数
	$idx = strpos($imgurl,'@');
	if($idx > 0) {
		return substr($imgurl,0,$idx); // 返回从start到end的位置，不包含end的那个字母
	}
	else{
        $idx = strpos($imgurl,'?');
        if($idx > 0) {
            return substr($imgurl,0,$idx);
        }
    }
	return $imgurl;
}

function  lazyloadimg($content) {
    $GLOBALS['lazy_images'] = array();

    if(strpos($content,'<img') === false){
        return $content;
    }

    //$template = preg_replace_callback('/{{varhtml (.+?)}}/is',array($this,'varhtml'),$template);
    //<img border="1" alt="" id="vimage_3462857" src="/files/remote/2010-10/cnbeta_2038145814065004.jpg" />
    // 双引号，单引号，无引号三种图片类型的代码。
    // <img  ... class="wp-image-89398 aligncenter" ...>
    // 去掉原有的图片的class样式
    //$content = preg_replace('/<img([^>]+?)class=[\'|"].+?["|\']([^>]+?)>/is','<img \\1 \\2>',$content);
    // $content = preg_replace('/<img([^>]+?)src="([^"]*?)"([^>]*?)>/is', "<img \\1src=\"" . Router::url('/img/grey.gif') . "\" class=\"lazy\" data-original=\"\\2\" \\3>", $content);

    // $content = preg_replace('/<img([^>]+?)src=\'([^\']+?)\'([^>]+?)>/is', "<img \\1src=\"" . Router::url('/img/grey.gif') . "\" class=\"lazy\" data-original=\"\\2\" \\3>", $content);
    // //[^\s"\'] 表示非空、非引号同时成立
    // $content = preg_replace('/<img([^>]+?)src=([^\s"\']+?)([^>]+?)>/is', "<img \\1src=\"" . Router::url('/img/grey.gif') . "\" class=\"lazy\" data-original=\"\\2\" \\3>", $content);

    //带双引号,单引号的
    $content = preg_replace_callback('/<img([^>]+?)src=["|\']([^"]*?)["|\']([^>]*?)\/?\s*>/is','replaceLazyLoad', $content);
    //带单引号的
    //$content = preg_replace_callback('/<img([^>]+?)src=\'([^\']+?)\'([^>]+?)>/is', array($this,'_replaceLazyLoad'), $content);
    //[^\s"\'] 表示非空、非引号同时成立，不带引号的。不带引号的暂不处理，符合条件的内容少，多匹配一次降低性能，没必要要求这么严格
    //$content = preg_replace_callback('/<img([^>]+?)src=([^\s"\']+?)([^>]+?)>/is',array($this,'_replaceLazyLoad'), $content);

   	// echo $content;exit;
    return $content;
}

/**
 * 判断一个ip是否是内网IP
 * @param unknown_type $ip
 * @return boolean
 */
function is_inner_ip($ip) {
    $segs = explode('.', $ip);
    if ($segs[0] == '10' || ($segs[0] == '172' && $segs[1] >= 16 && $segs[1] <= 31) || ($segs[0] == '192' && $segs[1] == '168')) {
        return true;
    } else {
        return false;
    }
}

/**
 *
 * 获取客户端的IP
 */
function get_client_ip() {
    $ip = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : getenv('HTTP_CLIENT_IP');
    if ($ip)
        return $ip;
    $http_x_forwarded_for = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : getenv('HTTP_X_FORWARDED_FOR');
    if ($http_x_forwarded_for) {
        $forward_ip_list = preg_split('/,\s*/', $http_x_forwarded_for);
        foreach ($forward_ip_list as $ip) {
            if (!is_inner_ip($ip)) {
                return $ip;
            }
        }
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
    if ($ip && $ip != 'unknown')
        return $ip;

    if (!empty($forward_ip_list)) {
        return $forward_ip_list[0];
    }
    return 'unknown';
}


function get_time_process($start_date,$end_date,$cur_date= ''){

	$start_time = strtotime($start_date);
	$end_time = strtotime($end_date);

	if($cur_date){
		$cur_time = strtotime($cur_date);
	}
	else{
		$cur_time = time();
	}
	if($start_time > $cur_time || $end_time <= $start_time ){
		return 0;
	}

	$total_days = ($end_time - $start_time )/86400;

	$pass_days = ceil($cur_time - $start_time)/86400;
	if($pass_days > $total_days){
		$pass_days = $total_days;
	}

	return intval($pass_days*100/$total_days);
}

function getAvatarPath($uid){
	return DS.($uid/1000%1000).DS.($uid%1000).DS;
}
function getAvatarUrl($uid){
	return UPLOAD_FILE_URL.UPLOAD_RELATIVE_PATH.'avatar/'.($uid/1000%1000).'/'.($uid%1000).'/'.$uid.'_0.jpg';
}

function getHumanFileSize($size){
	if($size > 1024*1024){
		$file_size = round($size/1024/1024,0,2).'MB';
	}
	elseif($size > 1024){
		$file_size = round($size/1024,0,2).'KB';
	}
	else{
		$file_size = $size.'B';
	}
	return $file_size;
}

function get_time_left($start_date,$end_date,$cur_date= ''){

	$start_time = strtotime($start_date);
	$end_time = strtotime($end_date);

	if($cur_date){
		$cur_time = strtotime($cur_date);
	}
	else{
		$cur_time = time();
	}

	$total_days = ($end_time - $start_time )/86400;

	if($start_time > $cur_time){
		// 没有开始,100%的剩余时间
		return array('days'=> $total_days,'percent'=> 100 );
	}
	elseif($end_time <= $cur_time){ //已结束
		return array('days'=> 0,'percent'=>0 );
	}

	$left_days = ($end_time - $cur_time )/86400;

	return array('days'=> ceil($left_days),'percent'=> intval($left_days*100/$total_days) );
}



function getSearchOptions($query,$modelClass){
    /**
     * 当包含get参数时，当参数名与数据表的字段名相同时，将此get参数加入搜索条件
     */
    $conditions = array();
    $object = loadModelObject($modelClass);
    if(empty($object) || $modelClass=='OtherDb') {
        return $conditions;
    }
    $fileds = $object->schema();
    $filedkeys = array_keys($fileds);

    if(!empty($query)){
        foreach ($query as $key => $val) {
            if($key == 'conditions') {
                continue;
            }
            if(strpos($key,$modelClass.'_')===0){ // Article.name=xxx 的get参数，变成了Article_name.xxx  转换回去。
                $key = str_replace($modelClass.'_', $modelClass.'.', $key);
            }
            preg_match('/^([\w\.]+)/',$key, $matches);// 形如field_name
            // print_r($matches);
            //strpos($key, $jv['alias'] . '.' . $mk) === 0
            if(in_array($matches[1],$filedkeys)){ // 字符部分为字段名时
                if( $key != $matches[1] ){ //不等于时追加一个空格，如"price>%3D=100"  转换成  "price >=100"
                    $fieldname = $matches[1];
                    $key = $fieldname.' '.substr($key,strlen($fieldname));
                    $key = $modelClass.'.'.$key;
                    if ( !in_array($fieldname,array('model')) && in_array($fileds[$fieldname]['type'], array(2,5,6),true) && strpos($key, ' like') !== false ) { // 文本字段加上like作模糊搜索
                        $conditions[$key] = '%' . $val . '%'; //like时自动模糊匹配
                    }
                    else{
                        $conditions[$key] = $val;
                    }
                }
                else{
                    $key = $fieldname = $matches[1];
                    if ( !in_array($fieldname,array('model')) && in_array($fileds[$fieldname]['type'], array(2,5,6),true)) { // 文本字段加上like作模糊搜索
                        if (strpos($key, ' like') !== false) {
                            $conditions[$modelClass.'.'.$key] = '%' . $val . '%';
                        } else {
                            $conditions[$modelClass.'.'.$key . ' like'] = '%' . $val . '%';
                        }
                    }
                    else{
                        $conditions[$modelClass.'.'.$key] = $val;
                    }
                }
            }
        }
    }
    // print_r($conditions);
    return $conditions;
}


function loadModelObject($params,$plugin='') {
    if(empty($params)){
        return false;
    }
    return new $params;
}


//写入日志
function Dlog(string $model,string $text = '',$level = 'info'){
    if($text == false|| strlen($text) < 1){
        return false;
    }
    
    $logger = new FileAdapter("../plugins/".$model."/logs/" .$level."_". date('Ymd') . ".log");
    switch ($level) {
        case 'info':
            $logger->info($text);
            break;
        case 'notice':
            $logger->notice($text);
            break;
        case 'error':
            $logger->error($text);
            break;
        default:
            return false;
    }
    
    return false;
}

//生成二维码
function getQrcode($url){
    $back_color = 0xFFFFFF;
    $fore_color = 0x000000;
    include APP_PATH.'/api/plugins/phpqrcode/phpqrcode.php';
    echo QRcode::png($url, false,QR_ECLEVEL_M, 10, 1, false, $back_color, $fore_color);
    exit;
}