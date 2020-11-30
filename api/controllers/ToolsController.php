<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Di;
use Phalcon\Image\Adapter\Gd as gdClass;

class ToolsController extends ControllerBase
{
    public function testAction(){
        echo 1;
    }

    public static $acl_list = ['getIfuncun'];


    public function createPictrueForLbbAction(){
        $data = $this->checkRequireFields(['name'],$this->request->get());
        $image= new Images();
        $poster = $image->mergeLBBPic($data);
        $img = file_get_contents($poster,true);
        header("Content-Type: image/png;text/html; charset=utf-8");
        echo $img;

    }

    public function getIfuncunAction(){
        $ifuncun_table = 'ifuncun_checktext';
        $cache = $this->getDI()->get('redisCache');
        $month = date('n');
        $last_count = 100000;
        $dataList = array(1,1);
        if($data = $cache->hGet($ifuncun_table,$this->currentId)){
            $dataList = explode(":",$data);
            if($dataList[0] == $month){
                if(count($this->currentUser['Role']) <= 0){
                    if($dataList[1] >= 3){
                        return $this->reqAndResponse->sendResponsePacket(400,null, '调用一个月不能超过三次');
                    }
                }else {
                    if ($this->currentUser['Role'][0]['id'] == 8) {
                        if ($dataList[1] >= 30) {
                            return $this->reqAndResponse->sendResponsePacket(400, null, '调用一个月不能超过三十次');
                        }
                    }
                    if ($this->currentUser['Role'][0]['id'] == 10) {
                        if ($dataList[1] >= 100) {
                            return $this->reqAndResponse->sendResponsePacket(400, null, '调用一个月不能超过一百次');
                        }
                    }
                }
                $dataList[1]+=1;
                $cache->hSet($ifuncun_table,$this->currentId,implode(':',$dataList));
            }else{
                $cache->hSet($ifuncun_table,$this->currentId,$month.':1');
            }
        }else{
            $cache->hSet($ifuncun_table,$this->currentId,$month.':1');
        }
        if(count($this->currentUser['Role']) <= 0){
            $last_count = 3 - $dataList[1];
        }else {
            if ($this->currentUser['Role'][0]['id'] == 8) {
                $last_count = 30 - $dataList[1];
            }
            if ($this->currentUser['Role'][0]['id'] == 10) {
                $last_count = 100 - $dataList[1];
            }
        }
        $api_list = array(
            'proofreadHtml' => 'http://api.ifuncun.cn:8081/v1/proofreadHtml'
        );
        if($_REQUEST['method'] == 'post'){
            $data = $this->checkRequireFields(['method','api'],$this->request->getPost());
        }else{
            $data = $this->checkRequireFields(['method','api'],$this->request->get());
        }
        if(!isset($api_list[$data['api']])){
            return $this->reqAndResponse->sendResponsePacket(400,null, '这个api方法不支持');
        }
        $data['userid'] = '135editor';
        $data['ukey'] = '135editor';
        $url = $api_list[$data['api']];
        unset($data['method']);
        unset($data['api']);
        $response = $this->getDI()->get('curl')->post($url,json_encode($data));
        $result = json_decode($response->body,true);
        $result['last_count'] = $last_count;
        $this->reqAndResponse->sendResponsePacket(200,$result, '获取成功');
    }




    public function generatePosterAction(){
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 604800)." GMT");
        header("Cache-Control:max-age=604800");
        if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || $_SERVER['HTTP_IF_NONE_MATCH']) {
            Header("HTTP/1.0 304 Not Modified");exit;
        }

        $data = $this->checkRequireFields(['post_id','user_id'],$this->request->get());
        $user_id = $this->currentUser['id']?$this->currentUser['id']:$data['user_id'];
        $wechat = array();
       
        $stencil = PosterRule::findFirstById($data['post_id']);
        if(!$stencil){
            exit('无模版');
        }
        if($data['type']==2) {
            if(is_array($this->currentUser['Oauthbind'])) {
                foreach($this->currentUser['Oauthbind'] as $auth){
                    if($auth['source']=='weixinPb' || $auth['source']=='weixinQr' ){
                        $wechat = $auth;
                        break;
                    }
                }
            }
            if (empty($wechat)) {
                $this->reqAndResponse->sendResponsePacket(400, '/users/index?op=bindwx', "您需要绑定微信号才能生成海报操作");
                exit;
            } 
        }
        $user_id = !empty($this->currentId)?$this->currentId:(!empty($_REQUEST['user_id'])?$_REQUEST['user_id']:0);
        $user = User::findFirstById($user_id);
        if(!empty($user)){
            $data['username'] = $user->username;
        }else{
            $this->reqAndResponse->sendResponsePacket(400, '', "该用户不存在");
            exit;
        } 
        $album = '';
        if(!empty($data['data_id'])){
            $album = $stencil->model::findFirst(array(
                'conditions'=>"id = :id:",
                'bind' => ['id' => $data['data_id']],
            ));
            if(!$album){
                $this->reqAndResponse->sendResponsePacket(400,null, '无对应的数据');
                exit;
            }
        }



        $promote_url = '';
        if(!empty($stencil->promote_url)){
            $promote_url = $stencil->promote_url;
        }else{
            ///按各模块业务逻辑处理
        }


        $image= new Images();

        $md5_sum = md5($promote_url); //使用md5码，忽略冲突
        $folder = substr($md5_sum,0,2).'/'.substr($md5_sum,2,3).'/';

        $image_save_path = APP_PATH . '/data/tmp/' . $folder; mkdir_p($image_save_path);
        $imgname = 'long'.$md5_sum.'.'.'png'; //random_str(8)
        $qrcodefilename = $image_save_path . $imgname;

        if(!file_exists($qrcodefilename)){
            $qrcodeimg = $image->saveQrcode($promote_url,$qrcodefilename);
        }

        $arr = array(
            'name' => $data['type']==2 ? $wechat['oauth_name'] : $user->username,
            'avatar_url' => $user->image,
            'theme_url' =>  !empty($album->coverimg)?$album->coverimg:'',
            'qrcodeimg' =>  $qrcodefilename,
            'title' => !empty($album->name)?$album->name:'',
            'download' => 2,
        );
        $poster = $image->mergePoster(json_decode($stencil->rule,true),$arr['qrcodeimg'],$arr['title'],$stencil->background_url,$arr['avatar_url'],$arr['theme_url'],$arr['name']);
        $img = file_get_contents($poster,true);
        $now = gmdate("D, d M Y H:i:s") . " GMT";
        header("Content-Type: image/jpeg; charset=utf-8");
        header('Last-Modified: '.$now);
        header('ETag: '.md5($arr['qrcodeimg'].$stencil->background_url.$arr['avatar_url']));
        echo $img;

    }




}
