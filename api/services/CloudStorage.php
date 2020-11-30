<?php

use Phalcon\Di;

/**
 * 抓取相关操作封装类
 * 扩展的Utility类名后面都加上Utility，放在类名与Model等其它类重名
 * @author Arlon
 *
 */
class CloudStorage {

    public static $recrawl_img = false;     //是否重复抓取图片
	
    /**
     * 保存内容中的图片到本站服务器，图片会缩放
     * @param $content
     * @param $url
     */
    public static function saveImagesInContent($content, $url) {
    	
        preg_match_all("/src=[\s|\"|']*((http:\/\/)?([^>]*)\.(gif|jpg|jpeg|bmp|png))/isU", $content, $imagearray);
        $imagearray = array_unique($imagearray[1]);
        
        // <img src="http://mmsns.qpic.cn/mmsns/agEQQ7NdJSNdDGUW5se1J1nB7UYjBGJ7Ew9ELOEBtIWA60qQqvETAA/0" style="border: 0px; height: auto !important;"  />
        preg_match_all("/<img[^>]*src=[\"|'](.*)[\"|']/isU", $content, $other_img);
        $imagearray = array_merge($imagearray,$other_img[1]);
        //<img data-src="http://mmbiz.qpic.cn/mmbiz/5DZAKbdPGFcPKibg0ggoHibbDSlpNDLpbGicYEHZhy3TqjHjYVS0x5JFomSxQMGZJkz5JOtIaYNM2ZZKup2ic9GHAg/0"  />
        // <img data-src="http://mmbiz.qpic.cn/mmbiz/q03j6z8KXgujx3sDxJGyOXGZAicD5vYBl1ict47w0EhboUeWLh9LibM5CSbm09B1ic4EDTicUgHdNFokRsBLB0iaicuxA/0"  />
        $imagearray = array_unique($imagearray);

        if(count($imagearray) > 5){ //5个图片的以上的不保存图片
            //$imagearray = array_slice($imagearray,0,5);
            return array('content' => $content, 'coverimg' => array());
        }

        $referer_url = $url;
        $coverimg = array();
        foreach ($imagearray as $key => $value) {
            $value = str_replace('"', '', $value);
            $value = str_replace("'", '', $value);
            $originimgurl = $value = trim($value);
            $imageurl = self::getPagelinkUrl($value, $url);

            if( strpos($imageurl,'sinaimg.cn')!==false ||
			strpos($imageurl,'gtimg.com') !== false ||
			 strpos($imageurl,'http://mmbiz.qpic.cn') !== false  || strpos($imageurl,'http://mmsns.qpic.cn') !== false) {
                continue; // 对于mmbiz.qpic.cn mmsns.qpic.cn微信中上传的图片跳过不抓取。节省图片云空间存储
            }

            $i = 0;
            do{
                if ($local_imageurl = self::saveImagesByUrl($imageurl, $referer_url)) {
                    list($width_orig, $height_orig) = getimagesize($local_imageurl);
                    if ($height_orig > 90 &&  $width_orig > 90){ // 判断宽高度要大于90px
                        $coverimg[] = $local_imageurl;
                    }
                    $local_imageurl = getStaticFileUrl($local_imageurl,true);                
                    $content = str_replace($originimgurl, $local_imageurl, $content);
                }
                else{
                    echo "=$imageurl get error.====<br/>\n";
                }
                $i++;
            }while(!$local_imageurl && $i < 3 ); //try 2 times
        }
        return array('content' => $content, 'coverimg' => $coverimg);
    }
    
    public static function saveMusic($url,$music_name,$referer='',$agent='Mozilla/5.0 (Linux; U; Android 4.1.2; zh-cn; Lenovo A820 Build/JZO54K) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30 MicroMessenger/5.3.0.50_r694338.420') {
    	
    	$year_month = date('Y-m');
    	$image_save_path = UPLOAD_FILE_PATH . 'files/remote/' . $year_month . '/';
    	$file_path = $image_save_path.$music_name;
    	
    	if (!file_exists($file_path)) {
	    	$filecontent = self::getRomoteUrlContent($url, array('header' => array('Referer' => $referer,'User-Agent'=>$agent)));
	    	if ($filecontent && strlen($filecontent) > 30) {
	    		$img_file = new File($file_path, true);
	    		$img_file->write($filecontent);
	    		$img_file->close();    	
	    	} else {
	    		return false;
	    	}
    	}
    	return '/files/remote/' . $year_month . '/' . $music_name;
    }

    public static function remoteExists($object_name,$bucket='',$remote_type=''){
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }
        if(empty($remote_type)) {
            $remote_type = Di::getDefault()->get('config')->Site->uploadRemoteType;
        }
        if($remote_type == 'oss') {
            $bucket_name = $bucket ? $bucket : Di::getDefault()->get('config')->Storage->alioss_bucket;
            if($bucket_name == Di::getDefault()->get('config')->Storage->alioss_upload_Bucket ) {
                $domain_url = Di::getDefault()->get('config')->Storage->alioss_upload_domain_url;
            }
            else{
                $domain_url = Di::getDefault()->get('config')->Storage->alioss_domain_url;
            }
            $domain_url = trim($domain_url,'/');
            try{
                $oss_client = self::getAliOssClient();
                $response = $oss_client->getObjectMeta($bucket_name,$object_name);
            }
            catch(Exception $e){
                return false;
            }

            // oss已经存在的文件，直接跳过
            if( !empty($response) && $response['info']['http_code'] == 200) {
                $url = $domain_url.'/'.$object_name;
                return $url;
            }
            else{
                return false;
            }
        }
        elseif($remote_type == 'baidu') {
            $accessKey = Di::getDefault()->get('config')->Baidu->AccessKey;
            $secretKey = Di::getDefault()->get('config')->Baidu->SecretKey;
            if(empty($bucket)){
                $bucket = Di::getDefault()->get('config')->Baidu->Bucket;
            }
            require_once APP_PATH.'/vendor/baidu/BaiduBce.phar';
            // 构建鉴权对象
            $client = new BaiduBce\Services\Bos\BosClient(array(
                'credentials' => array(
                    'accessKeyId' => $accessKey,
                    'secretAccessKey' => $secretKey,
                ),
                'protocol' => 'https',
                'endpoint' => Di::getDefault()->get('config')->Baidu->EndPoint,
            ));
            try {
                $client->getObjectMetadata($bucket, $object_name);
            } catch (\BaiduBce\Exception\BceBaseException $e) {
                if ($e->getStatusCode() == 404) {
                    return false;
                }
            }
            return Di::getDefault()->get('config')->Baidu->url.'/'.$object_name;
        }
        elseif($remote_type == 'qiniu') {
            $accessKey = Di::getDefault()->get('config')->Qiniu->accessKey;
            $secretKey = Di::getDefault()->get('config')->Qiniu->secretKey;
            if(empty($bucket)){
                $bucket = Di::getDefault()->get('config')->Qiniu->bucket;
            }

            require_once APP_PATH.'/vendor/Qiniu/autoload.php'; // 包含七牛的库文件
            // 构建鉴权对象
            $auth = new Qiniu\Auth($accessKey, $secretKey);
            // 初始化 UploadManager 对象并进行文件的上传
            $manager = new Qiniu\Storage\BucketManager($auth);
            while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
                $object_name = substr($object_name,1);
            }

            // 调用 UploadManager 的 putFile 方法进行文件的上传
            list($ret,$err) =  $manager->stat($bucket, $object_name);
            if(empty($err) ) {
                return Di::getDefault()->get('config')->Qiniu->url.'/'.$object_name;
            }
            else{
                return false;
            }
        }
        return  false;
    }

    public static function getFromRemote($object_name,$bucket='') {
        $remote_type = Di::getDefault()->get('config')->Site->uploadRemoteType;
        if($remote_type == 'oss') {
            return self::getFromAliOss($object_name,$bucket);
        }
        elseif($remote_type == 'qiniu') {
            return self::getFromQiniu($object_name);
        }
        elseif($remote_type == 'baidu') {
            return self::getFromBaidu($object_name,$bucket);
        }
        return  false;
    }

    public static function delFromRemote($object_name,$bucket='') {
        $remote_type = Di::getDefault()->get('config')->Site->uploadRemoteType;
        if($remote_type == 'oss') {
            return self::delFromAliOss($object_name,$bucket);
        }
        elseif($remote_type == 'qiniu') {
            return self::delFromQiniu($object_name,$bucket);
        }
        elseif($remote_type == 'baidu') {
            return self::delFromBaidu($object_name,$bucket);
        }
        return  false;
    }

    public static function saveToRemote($filepath,$object_name,$bucket=''){
        $remote_type = Di::getDefault()->get('config')->Site->uploadRemoteType;
        if($remote_type == 'oss') {
            $options = array();
            if(!empty($bucket)) {
                $options = array('bucket'=>$bucket);
            }
            return self::saveToAliOss($filepath,$object_name,false,$options);
        }
        elseif($remote_type == 'qiniu') {
            return self::saveToQiniu($filepath,$object_name,$bucket);
        }
        elseif($remote_type == 'baidu') {
            return self::saveToBaidu($filepath,$object_name,$bucket);
        }
        elseif(empty($remote_type)){
            $folder = date('Ymd');
            if( defined('LOGIN_USER_ID') ) {
                $folder = LOGIN_USER_ID.'/'.$folder;
            }

            $image_save_path = UPLOAD_FILE_PATH . '/files/' . $folder . '/';
            mkdir_p($image_save_path);
            $imgname = CakeText::uuid().'.jpg';
            $filename = $image_save_path . $imgname;

            $object_name = '/files/' . $folder . '/'.$imgname;
            if(is_uploaded_file($filepath)) {
                move_uploaded_file($filepath,$filename);
            }
            else{
                rename($filepath,$filename);
            }
            return  $object_name;
        }
        else{
            Di::getDefault()->get('logger')->error("saveToRemote type error. type:$remote_type");
            return false;
        }
    }

    /**
     * 保存远程图片至本地服务器缓存区，返回缓存的临时文件的地址。
     * @param $imageurl  图片地址
     * @param string $prefix  图片临时文件的前缀
     * @param array $options  图片是否自动缩小
     * @return bool|string
     */
    public static function getImagesTmpFile($imageurl, $prefix = '',$options = array()) {
        $options = array_merge(array(
            'resize' => false,
            'max_width' => 800,
        ),$options);

        $referer = $imageurl;
        // 修复了新浪云存储app的网址限制了referer的问题
        if( strpos($referer,'stor.sinaapp.com') !== false ){
            $referer = preg_replace('/-.+\.stor\./','.',$referer); //如 hhh9-wordpress.stor.sinaapp.com  => hhh9.sinaapp.com
        }

        $md5_sum = md5($imageurl); //使用md5码，忽略冲突
        $folder = substr($md5_sum,0,2).'/'.substr($md5_sum,2,3).'/';
        get_mime_type($imageurl,$ext); // 获取$ext
        if(!in_array($ext,array("image/png", "image/gif", "image/jpeg", "image/bmp", "image/jpg","jpg","png","gif","jpeg","xlsx"))){
            return false;
        }
        $image_save_path = APP_PATH . '/data/tmp/' . $folder; mkdir_p($image_save_path);
        $imgname = $prefix.$md5_sum.'.'.$ext; //random_str(8)
        $filename = $image_save_path . $imgname;

        // 设置了重复抓取图片或者图片文件不存在时，重复抓取
        if ( !file_exists($filename) ) {
            // 微信文章的图片 http://mmsns.qpic.cn 可能会被屏蔽，换成mmbiz.qpic.cn尝试继续抓取
            $img_filecontent = self::getRomoteUrlContent($imageurl, array('header' => array('Referer' => $referer)));
            if ( $img_filecontent ) {
                $tp = @fopen($filename, 'w');
                $res = fwrite($tp, $img_filecontent);
                fclose($tp);

                if( $options['resize'] && $options['width'] ){
                    //缩放处理图片,替换原图
                    $image = new \Phalcon\Image\Adapter\Gd($filename);
                    $image->resize( $options['width'], null, \Phalcon\Image::WIDTH );
                    $image->save($filename);
                }
            } else {
                Di::getDefault()->get('logger')->error("Curl get Remote image content error.The url is ".$imageurl);
                return false;
            }
        }
        return $filename;
    }

    /**
     * 保存远程图片至云存储并返回地址
     * @param $imageurl  图片地址
     * @param string $prefix  图片临时文件的前缀
     * @param array $options  图片是否自动缩小
     * @return bool|string
     */
    public static function saveImagesByUrl($imageurl, $prefix = '',$options = array()) {
    	$options = array_merge(array(
    			'resize' => false,
    			'max_width' => 800,
    	),$options);

    	while(strpos($imageurl,'http://remote.wx135.com/oss/view') !== false) {
    		$urlinfo = parse_url($imageurl);
    		parse_str($urlinfo['query '],$query);
    		if($query['d']) {
    			$imageurl = $query['d'];
    		}
    	}

    	$referer = $imageurl;
    	// 修复了新浪云存储app的网址限制了referer的问题
    	if( strpos($referer,'stor.sinaapp.com') !== false ){    	    
    	    $referer = preg_replace('/-.+\.stor\./','.',$referer); //如 hhh9-wordpress.stor.sinaapp.com  => hhh9.sinaapp.com
    	}
    	//$imageurl = str_replace('http://mmsns.qpic.cn','https://mmbiz.qlogo.cn',$imageurl);
    	$imageurl = str_replace('http://mmbiz.qpic.cn','https://mmbiz.qlogo.cn',$imageurl);
    	
        $md5_sum = md5($imageurl); //使用md5码，忽略冲突
        $folder = substr($md5_sum,0,2).'/'.substr($md5_sum,2,3);
        if( empty($options['user_id']) && defined('LOGIN_USER_ID') ) {
            $options['user_id'] = LOGIN_USER_ID;
        }
        if($options['user_id']) {
            $folder = $options['user_id'].'/'.$folder;
        }
        $image_save_path = APP_PATH . '/public/files/' . $folder . '/'; mkdir_p($image_save_path);
        get_mime_type($imageurl,$ext); // 获取$ext

        $imgname = $prefix.$md5_sum.'.'.$ext; //random_str(8)
        $filename = $image_save_path . $imgname;
        
        $object_name = $options['object_name'] ? $options['object_name'] : 'files/' . $folder . '/'.$imgname;
        $remoteType = Di::getDefault()->get('config')->Site->uploadRemoteType;
//        if( $remoteType && $options['oss']!==false && $options['remote']!==false ) {
//            if ($url = self::remoteExists($object_name)) {
//                return $url;
//            }
//        }

        // echo $filename;
        
        // 设置了重复抓取图片或者图片文件不存在时，重复抓取
        if ( !file_exists($filename) ) {
            // 微信文章的图片 http://mmsns.qpic.cn 可能会被屏蔽，换成mmbiz.qpic.cn尝试继续抓取
            $img_filecontent = self::getRomoteUrlContent($imageurl, array('header' => array('Referer' => $referer)));
            if ( $img_filecontent ) {
                $tp = @fopen($filename, 'w');
                $res = fwrite($tp, $img_filecontent);
                fclose($tp);
                
                // 根据内容写的文件重新判断图片的类型，不为jpg时更换文件后缀
                if( $options['resize'] && $options['width'] ){
                    //缩放处理图片,替换原图
                    $image = new \Phalcon\Image\Adapter\Gd($filename);
                    $image->resize( $options['width'], null, \Phalcon\Image::WIDTH );
                    $image->save($filename);
                }
            } else {
                Di::getDefault()->get('logger')->error("Curl get Remote image content error.The url is ".$imageurl);
                return false;
            }
        }

        if( $remoteType ){
            return self::saveToRemote($filename,$object_name);
        }
        else{
            return '/'.$object_name;
        }
    }
    public static $oss_client = null;

	public static function getFromAliOss($object_name,$options=array()) {

        if( empty($object_name) ){
            return false;
        }

		while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不能带斜线。
			$object_name = substr($object_name,1);
		}
        $bucket_name = Di::getDefault()->get('config')->Storage->alioss_bucket;
        if( $options['bucket_name'] ) {
            $bucket_name = $options['bucket_name'];
        }
        elseif( Di::getDefault()->get('config')->debugMode ) {
            //测试模式时，文件存储至本地
            $filename = 'files'.DIRECTORY_SEPARATOR.$object_name;
            $dist_path = DATA_PATH. $filename;
            return file_get_contents( $dist_path);
        }

        try{
            $oss_client = self::getAliOssClient();
            return $oss_client->getObject($bucket_name,$object_name);
        }
        catch(Exception $e){
            $errMsg = "Curl get alioss object {$bucket_name}:{$object_name} Url:".$_SERVER['REQUEST_URI']." Error:".$e->getMessage();
            Di::getDefault()->get('logger')->error($errMsg);
        }
		return null;
	}
    public static function delFromAliOss($object_name,$bucket_name="") {

        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不能带斜线。
            $object_name = substr($object_name,1);
        }
        if(empty($bucket_name)) {
            $bucket_name = Di::getDefault()->get('config')->Storage->alioss_bucket;
        }
        
    	$oss_client = self::getAliOssClient();
    	$response = $oss_client->deleteObject($bucket_name,$object_name);
        if($response['info']['http_code']==200 || $response['info']['http_code']==204 ){
			return true;
		}
        return false;
    }

    public static function getFromBaidu($object_name,$bucket = ''){

        $accessKey = Di::getDefault()->get('config')->Baidu->AccessKey;
        $secretKey = Di::getDefault()->get('config')->Baidu->SecretKey;
        if(empty($bucket)){
            $bucket = Di::getDefault()->get('config')->Baidu->Bucket;
        }
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }

        require_once APP_PATH.'/vendor/baidu/BaiduBce.phar';
        // 构建鉴权对象
        $client = new BaiduBce\Services\Bos\BosClient(array(
            'credentials' => array(
                'accessKeyId' => $accessKey,
                'secretAccessKey' => $secretKey,
            ),
            'protocol' => 'https',
            'endpoint' => Di::getDefault()->get('config')->Baidu->EndPoint,
        ));
        try{
            //删除$bucket 中的文件 $object_name
            $content = $client->getObjectAsString($bucket, $object_name);
            if ( $content ) {
                return $content;
            } else {
                Di::getDefault()->get('logger')->error("baidu get content error:".$object_name);
                return false;
            }
        }
        catch(Exception $e) {
            if(strpos($e->getMessage(),'NoSuchKey')) {
                return false;
            }
            else{
                return false;
            }
        }
    }

    public static function delFromBaidu($object_name,$bucket = ''){

        $accessKey = Di::getDefault()->get('config')->Baidu->AccessKey;
        $secretKey = Di::getDefault()->get('config')->Baidu->SecretKey;
        if(empty($bucket)){
            $bucket = Di::getDefault()->get('config')->Baidu->Bucket;
        }
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }

        require_once APP_PATH.'/vendor/baidu/BaiduBce.phar';
        // 构建鉴权对象
        $client = new BaiduBce\Services\Bos\BosClient(array(
            'credentials' => array(
                'accessKeyId' => $accessKey,
                'secretAccessKey' => $secretKey,
            ),
            'protocol' => 'https',
            'endpoint' => Di::getDefault()->get('config')->Baidu->EndPoint,
        ));
        try{
            //删除$bucket 中的文件 $object_name
            $response = $client->deleteObject($bucket, $object_name);
            if ( is_object($response) && $response->metadata ) {
                return true;
            } else {
                Di::getDefault()->get('logger')->error("baidu delete error:".$object_name);
                return false;
            }
        }
        catch(Exception $e) {
            if(strpos($e->getMessage(),'NoSuchKey')) {
                return true;
            }
            else{
                return false;
            }
        }
    }

    public static function clearCacheAliyun($url,$type='url') {
        include_once   APP_PATH.'/vendor/aliyun-php-sdk-core/Config.php';

        $regionId = 'cn-shanghai';
        $profile = DefaultProfile::getProfile($regionId, OSS_ACCESS_ID, OSS_ACCESS_KEY);
        $client = new DefaultAcsClient($profile);

        $request = new Cdn\Request\V20141111\RefreshObjectCachesRequest();
        if($type == 'url') {
            $request->setObjectPath($url);
            $request->setObjectType('File');
        }
        else{
            // https://bdn.135editor.com/files/users/472/4723792/201807/mv3YOP9x_Zsyc.jpg
            $path = dirname(dirname($url)).'/'; //  得到形如“https://bdn.135editor.com/files/users/472/4723792/”
            $request->setObjectPath($path);
            $request->setObjectType('Directory');
        }

        try {
            $response = $client->getAcsResponse($request);
            return true;
        } catch(ServerException $e) {
            Di::getDefault()->get('logger')->error("clearCacheAliyun Error: " . $e->getErrorCode() . " Message: " . $e->getMessage() . "\n");

        } catch(ClientException $e) {
            Di::getDefault()->get('logger')->error("clearCacheAliyun Error: " . $e->getErrorCode() . " Message: " . $e->getMessage() . "\n");
        }
        return false;
    }
    public static function clearCacheBaidu($url,$type='url') {

        require_once APP_PATH.'/vendor/baidu/BaiduBce.phar';

        $accessKey = Di::getDefault()->get('config')->Baidu->AccessKey;
        $secretKey = Di::getDefault()->get('config')->Baidu->SecretKey;
        $client = new BaiduBce\Services\Cdn\CdnClient(array(
            'credentials' => array(
                'accessKeyId' => $accessKey,
                'secretAccessKey' => $secretKey,
            ),
            'endpoint' => 'http://cdn.baidubce.com',
        ));
        if($type == 'directory') {
            $urlinfo = parse_url($url);
            // https://bdn.135editor.com/files/users/472/4723792/201807/mv3YOP9x_Zsyc.jpg
            $path = dirname(dirname($urlinfo['path'])).'/'; //  得到形如“/files/users/472/4723792/”

            $tasks = array(
                array(
                    'url' => 'https://'.$urlinfo['host'].$path,
                    'type' => 'directory',
                ),
                array(
                    'url' => 'http://'.$urlinfo['host'].$path,
                    'type' => 'directory',
                ),
            );
        }
        else{
            $tasks = array(
                array(
                    'url' => $url,
                ),
            );
        }
        $resp = $client->purge($tasks);
        if ( is_object($resp) && $resp->metadata ) {
            return true;
        } else {
            return false;
        }
    }
    public static function getFromQiniu($object_name){
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }
        $url =  Di::getDefault()->get('config')->Qiniu->url.'/'.$object_name;
        return self::getRomoteUrlContent($url);
    }

    public static function delFromQiniu($object_name,$bucket = 'editor135'){

        $accessKey = Di::getDefault()->get('config')->Qiniu->accessKey;
        $secretKey = Di::getDefault()->get('config')->Qiniu->secretKey;
        if(empty($bucket)){
            $bucket = Di::getDefault()->get('config')->Qiniu->bucket;
        }

    	while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
    		$object_name = substr($object_name,1);
    	}
        require_once APP_PATH.'/vendor/Qiniu/autoload.php'; // 包含七牛的库文件
    	$auth = new Qiniu\Auth($accessKey, $secretKey);
    	$bucketMgr = new Qiniu\Storage\BucketManager($auth);;
    	//删除$bucket 中的文件 $object_name
    	$err = $bucketMgr->delete($bucket, $object_name);
    	if ($err !== null) {
    		Di::getDefault()->get('logger')->error("qiniu delete error:".$err->message());
    		return false;
    	} else {
    		return true;
    	}
    }

    public static function qiniuFops($key,$fops){

        $accessKey = Di::getDefault()->get('config')->Qiniu->accessKey;
        $secretKey = Di::getDefault()->get('config')->Qiniu->secretKey;

        require_once APP_PATH.'/vendor/Qiniu/autoload.php'; // 包含七牛的库文件
        $auth = new Qiniu\Auth($accessKey, $secretKey);
        // 要转码的文件所在的空间
        $bucket = 'editor135';
        // 转码时使用的队列名称
        $pipeline = 'wartermark';
        // 初始化

        $pfop = new Qiniu\Processing\PersistentFop($auth, $bucket, $pipeline,null,true);

        echo $key."<br>";
        echo $fops."<br>";
        list($id, $err) = $pfop->execute($key, $fops);
        if ($err != null) {
            Di::getDefault()->get('logger')->error('qiniuFops error :'.$err);
            return false;
        } else {
            return $id;
        }
    }

    public static function qiniuToken($bucket = '',$keyToOverwrite = null,$callback = true){

        $accessKey = Di::getDefault()->get('config')->Qiniu->accessKey;
        $secretKey = Di::getDefault()->get('config')->Qiniu->secretKey;
        if(empty($bucket)) {
            $bucket = Di::getDefault()->get('config')->Qiniu->bucket;
        }
        if(empty($bucket) || empty($accessKey) || empty($secretKey) ){
            return null;
        }
        require_once APP_PATH.'/vendor/Qiniu/autoload.php'; // 包含七牛的库文件
        // 构建鉴权对象
        $auth = new Qiniu\Auth($accessKey, $secretKey);

        $expires = 7200;
        $returnBody = '{"key":"$(key)","model":"$(x:model)","field":"$(x:field)","uid":"$(x:uid)"}';
        $callbackBody = '{"key":"$(key)","hash":"$(etag)","fname":"$(fname)","model":"$(x:model)","bucket":"$(bucket)","field":"$(x:field)","fsize":$(fsize),"uid":"$(x:uid)"}';

        //  https://developer.qiniu.com/kodo/manual/1235/vars#magicvar
        if(!$callback) { //后台不使用回调。
            $policy = array(
                'returnBody' => '{"ret":"0","state":"SUCCESS","url":"'.Di::getDefault()->get('config')->Qiniu->url.'/nc/$(x:uid)/$(year)/$(etag)$(ext)"}',
                'saveKey' => 'nc/$(x:uid)/$(year)/$(etag)$(ext)', //$(ext)带有点号
            );
        }
        else{
            $policy = array(
                'returnBody' => $returnBody,
                'saveKey' => '$(x:uid)/$(year)$(mon)$(day)/$(etag)$(sec)$(ext)', //$(ext)带有点号
                'callbackUrl' => Di::getDefault()->get('config')->Qiniu->callbackUrl,//'http://www.135editor.com/uploadfiles/qn_callback',
                'callbackBody' => $callbackBody,
                'callbackBodyType' => 'application/json',
            );
        }
        $upToken = $auth->uploadToken($bucket, $keyToOverwrite, $expires, $policy, true);
        return $upToken;
        /*if($wartermarks['mark']) {
            $mark = $wartermarks['mark'];
            $gravity = $wartermarks['gravity'];

            if(strpos($mark,'http')!=='false') {
                //网址
                $policy['persistentOps'] = 'watermark/1/image/'.base64_encode($mark).'/gravity/'.$gravity.'/dissolve/60/ws/0.15';
            }
            else{ //文字
                $policy['persistentOps'] = 'watermark/2/text/'.base64_encode($mark).'/fill/#FFFFFF';
            }
            $policy['persistentPipeline'] = 'wartermark';
        }*/

    }

    public static function saveToQiniu($file_path,$object_name,$bucket = ''){
        // http://developer.qiniu.com/code/v7/sdk/php.html#upload

        $accessKey = Di::getDefault()->get('config')->Qiniu->accessKey;
        $secretKey = Di::getDefault()->get('config')->Qiniu->secretKey;
        if(empty($bucket)){
            $bucket = Di::getDefault()->get('config')->Qiniu->bucket;
        }

        require_once APP_PATH.'/vendor/Qiniu/autoload.php'; // 包含七牛的库文件
        // 构建鉴权对象
        $auth = new Qiniu\Auth($accessKey, $secretKey);
        // 生成上传 Token
        $token = $auth->uploadToken($bucket);
        // 初始化 UploadManager 对象并进行文件的上传
        $uploadMgr = new Qiniu\Storage\UploadManager();
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }

        // 调用 UploadManager 的 putFile 方法进行文件的上传
        list($ret, $err) =  $uploadMgr->putFile($token, $object_name, $file_path);
        if ($err !== null) {
            Di::getDefault()->get('logger')->error("upload qiniu error:".var_export($ret,true));
            return false;
        } else {
            //var_dump($ret); array('hash'=>'xxx','key'=>$object_name)
            return Di::getDefault()->get('config')->Qiniu->url.'/'.$object_name;
        }
    }
    
    public static function saveToBaidu($file_path,$object_name,$bucket = '',$url = ''){
        $accessKey = Di::getDefault()->get('config')->Baidu->AccessKey;
        $secretKey = Di::getDefault()->get('config')->Baidu->SecretKey;
        if(empty($bucket)){
            $bucket = Di::getDefault()->get('config')->Baidu->Bucket;
            $url = Di::getDefault()->get('config')->Baidu->url;
        }
        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不以斜线开头。
            $object_name = substr($object_name,1);
        }

        require_once APP_PATH.'/vendor/baidu/BaiduBce.phar';
    	// 构建鉴权对象
    	$client = new BaiduBce\Services\Bos\BosClient(array(
            'credentials' => array(
                'accessKeyId' => $accessKey,
                'secretAccessKey' => $secretKey,
            ),
            'protocol' => 'https',
            'endpoint' => Di::getDefault()->get('config')->Baidu->EndPoint,
        ));

    	if(is_file($file_path)) {
            $options = array('contentType'=>get_mime_type($file_path));
            $response = $client->putObjectFromFile($bucket,$object_name,$file_path,$options);
        }
    	else{
            $options = array('contentType'=>'text/plain');
            $response = $client->putObjectFromString($bucket,$object_name,$file_path,$options);
        }

    	if ($response !== null && !empty($response->metadata)) {
            return $url.'/'.$object_name;
    	} else {
            Di::getDefault()->get('logger')->error("upload baidu error:".var_export($response,true));
            return false;
    	}
    }


    public static function getAliOssClient(){
        static  $aliClient;
        if(empty($aliClient)) {
            $accessID = Di::getDefault()->get('config')->Storage->alioss_access_id ;
            $secretKey = Di::getDefault()->get('config')->Storage->alioss_access_key;
            $hostServer = Di::getDefault()->get('config')->Storage->alioss_host_server;

            require_once APP_PATH . '/vendor/aliyuncs/oss-php-sdk/autoload.php';
            $aliClient = new \OSS\OssClient($accessID, $secretKey, $hostServer);
        }
        return $aliClient;
    }
    // $file_path只允许本地文件路径，双斜线会被替换成单斜线
    /**
     * 
     * @param unknown $file_path 文件的地址，或者内容。
     * @param unknown $object_name 保存的对象的名字
     * @param string $is_content  是否为直接保存内容
     * @param string $mime	文件的媒体类型
     * @return boolean|string
     */
    public static function saveToAliOss($file_path,$object_name,$is_content = false,$options=array()){

        while(substr($object_name,0,1)=='/'){ //上传的object字符串前面不能带斜线。
            $object_name = substr($object_name,1);
        }
        $bucket_name = Di::getDefault()->get('config')->Storage->alioss_bucket;
        if( $options['bucket_name'] ) {
            $bucket_name = $options['bucket_name'];
            if($bucket_name == Di::getDefault()->get('config')->Storage->alioss_upload_Bucket ) {
                $domain_url = Di::getDefault()->get('config')->Storage->alioss_upload_domain_url;
            }
            else{
                $domain_url = $options['domain_url'];
            }
        }
        else{
            $domain_url = Di::getDefault()->get('config')->Storage->alioss_domain_url;
            if( Di::getDefault()->get('config')->debugMode ) { //测试模式时，文件存储至本地
                $filename = 'files'.DIRECTORY_SEPARATOR.$object_name;
                $dist_path = DATA_PATH. $filename;
                mkdir_p(dirname($dist_path));
                if($is_content) {
                    file_put_contents($dist_path, $file_path);
                }
                else{
                    copy($file_path,$dist_path);
                }
                return str_replace('\\','/',$filename);
            }
        }
        $domain_url = trim($domain_url,'/');

        $ossClient =  self::getAliOssClient();
    	$i=0;
    	do{
            try{
                if( !$is_content ) {
                    $file_path = str_replace('//','/',$file_path);
                    if( !file_exists($file_path) )	return false;
                    $response = $ossClient->uploadFile($bucket_name, $object_name, $file_path);
                }
                else{
                    $file_content = $file_path;
                    $response = $ossClient->putObject($bucket_name, $object_name, $file_content);
                }

                if( $response['info']['http_code']== 200 ) {
                    return $domain_url.'/'.$object_name;
                }
                else{
                    return false;
                }
            }
            catch(Exception $e){
                $errMsg =  "save to aliyun oss error.".$e->getMessage().".Object=$object_name<br>\n";
                Di::getDefault()->get('logger')->error($errMsg);
            }
            $i++;
       }while($i<3); //重试1次
       return false;
    }

    /**
     * 抓取url内容
     * @param $url 页面url地址
     * @param $options  抓取时其他参数
     * @return object Response
     */
    public static function getRomoteUrlContent($url, $options=array(),&$retheader = null) {
        $url = str_replace('&amp;','&',$url);
        $header = array(
                        'Referer' => $url,
                        //'Cache-Control' => 'no-cache',
                        //'Pragma'=>'no-cache',
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.117 Safari/537.36',
                );
        if(is_array($options['header'])) {
            $header = @array_merge($header,$options['header']);
        }

        /* 注意区分这个response对象与aliyun的OSS客户端的response对象不一致 */
        $response =  Di::getDefault()->get('curl')->get($url, array(), $header);
        
        $numargs = func_num_args();
		if( $numargs == 3 ) {
            $retheader = $response->header;
		}

		/**
		 * [0] => body    [1] => headers     [2] => cookies    [3] => httpVersion    [4] => code    [5] => reasonPhrase    [6] => raw    [7] => context
		 */
		if( $response->header->statusCode==200 ) {
		    return $response->body;
		}
        return null;
    }

}
