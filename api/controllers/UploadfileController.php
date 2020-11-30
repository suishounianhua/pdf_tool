<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

class UploadfileController extends ControllerBase
{
    private $maxFileSize = 10485760;//5242880;
    //上传besa64图片
    public function UploadBase64Action(){
        $data = $this->request->get();
        if(!isset($data['file']) || $data['file'] == ''){
            return $this->reqAndResponse->sendResponsePacket(400,null,'你需要上传图片后再请求服务');
        }
        $content = $data['file'];
        $length = strlen($content);
        $imgtype = $imgcontent = '';
        $savepath = 'users/'.intval($this->currentUser['id']/10000).'/'.mt_rand(1,10000).'/'.date('Ym').'/';
        if('data:image/' == substr($content,0,11)){
            for( $j=11; $j<$length && $content[$j]!=';'; $j++ ) {
                $imgtype .= $content[$j];
            }
            $imgcontent = substr($content,$j+7);
            $file_name =  md5(time() . mt_rand(1,1000000)) .'.'.$imgtype;
            $imgcontent = base64_decode($imgcontent);
            $size = $file_length = strlen($imgcontent);
            if( $file_length > $this->maxFileSize ) {
                return $this->reqAndResponse->sendResponsePacket(400,null,'图片文件大于10M，禁止上传');
            }else{
                $path = APP_PATH.'/data/tmp/';
                $img_file = $path.'_'.date('Ym').'_'.$file_name;
                mkdir_p($path);
                file_put_contents($img_file, $imgcontent);
                chmod($img_file, 0777);
            }
        }else{
            return $this->reqAndResponse->sendResponsePacket(400,null,'数据格式错误');
        }
        $hash = md5_file($img_file);
        $object_name = $savepath.$hash.'/'.$file_name;

        $url = CloudStorage::saveToBaidu($img_file,$object_name);
        if(file_exists($img_file)){
            @unlink($img_file);
        }
        if( $url ) {
            return $this->reqAndResponse->sendResponsePacket(200,array(
                    'url' => $url,
                    'size' => $size,
                    'name' => $file_name,
                    'mime' => $imgtype,
                ), "文件上传成功");
        }
        return $this->reqAndResponse->sendResponsePacket(400,null,'数据格式错误');
    }
    /**
     * vue iview的上传接口。上传后，不插入数据库记录
     * @return mixed
     */
    public function iviewUploadAction(){

        if( !empty($_FILES) && !empty($_FILES['file']) ) {
            $fileinfo = $_FILES['file'];

            $fileparts = explode('.', $fileinfo['name']);
            $fileext = strtolower(array_pop($fileparts));
            //允许上传的格式
            if(!in_array($fileext,array('xls','xlsx','gif','png','jpg','jpeg','pdf','zip','doc','docx','txt','rar'))){
                return $this->reqAndResponse->sendResponsePacket(10691);
            }
            if( in_array($fileext,array('php','html','htm','shtml','sh','js','shtm','asp','exe','cgi')) ){
                $fileext = '_'.$fileext;
            }
            
            // 使用md5_file生成hash，可避免同一文件在同一月份里重复上传存在多份数据，浪费空间。
            // 又因为上传文件按月份存在不同的目录，MD5file的hash值冲突的情况的完全忽略。
            $hash = md5_file($fileinfo['tmp_name']); // 用户目录下，根据md5_file排重
            $savepath = 'users/'.intval($this->currentUser['id']/10000).'/'.$this->currentUser['id'].'/'.date('Ym').'/';
            mkdir_p($savepath);

            $object_name = $savepath.$hash.'.'.$fileext;

            $result = CloudStorage::saveToRemote($fileinfo['tmp_name'],$object_name);
            if( !empty($result) ) {
                return $this->reqAndResponse->sendResponsePacket(200,array(
                    'url' => $result,
                    'size' => $fileinfo['size'],
                    'name' => $fileinfo['name'],
                    'mime' => $fileinfo['type'],
                ), "文件上传成功");
            }
            else{
                return $this->reqAndResponse->sendResponsePacket(10691);
            }
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(400,null,'请上传文件');
        }
    }

    //远程图片保存至云端
    public function UploadByUrlAction(){
        $data = $this->request->get();
        if(!isset($data['url']) || $data['url'] == ''){
            return $this->reqAndResponse->sendResponsePacket(400,null,'请选择合法的图片地址再请求服务');
        }
        if(empty($this->currentId)){
            return $this->reqAndResponse->sendResponsePacket(400,null,'请先登录再请求服务');
        }
        $option = array(
            'user_id' => $this->currentId,
        );
        try {
            $result = CloudStorage::saveImagesByUrl($data['url'],'',$option);
            return $this->reqAndResponse->sendResponsePacket(200,array(
                    'url' => $result
                ), "文件上传成功");
        } catch(Throwable $e) {
            return $this->reqAndResponse->sendResponsePacket(403,[], "上传文件出现了一些问题，请稍后再进行尝试");
        }
    }

    public function beforeExecuteRoute()
    {
        parent::beforeExecuteRoute();
        if($_SERVER['HTTP_ORIGIN'] || $_SERVER['HTTP_REFERER']){
            if($_SERVER['HTTP_ORIGIN']) {
                $this->response->setHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
            }
            else{
                $urlinfo = parse_url($_SERVER['HTTP_REFERER']);
                $this->response->setHeader('Access-Control-Allow-Origin', $urlinfo['scheme'].'://'.$urlinfo['host'].($urlinfo['port']?':'.$urlinfo['port']:''));
            }
            $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, No-Cache, Referer, X-CSRF-Token, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Authorization, Cache-Control, Expires, Content-Type, X-PINGOTHER, X-E4M-With');
            $this->response->setHeader("Access-Control-Allow-Methods", 'GET,PUT,POST,DELETE,OPTIONS');
        }
//        if( empty($this->currentUser['id']) ) {
//            $this->reqAndResponse->sendResponsePacket( 10910 );
//            exit;
//        }
//        elseif( $this->currentUser['id'] && $this->currentUser['status'] == -1 ) {
//            $this->reqAndResponse->sendResponsePacket( 403 , null,'账号已封禁无权提交数据' );
//            exit;
//        }
    }

}