<?php
namespace plugins;
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use \Phalcon\Mvc\Controller;
use Firebase\JWT\JWT;
use Phalcon\Http\Response;

class YyznController extends Controller
{
    var $auth_token = false;
    public $currentUser = null;
    public $currentId = null;
    public static $acl_list = [];
    public static $is_acl = true;
    public $modelClass = null;
    public $config = [];//清空系统配置

    public function initialize()
    {
        if( !empty($_SERVER['HTTP_AUTHORIZATION']) && substr($_SERVER['HTTP_AUTHORIZATION'],0,6) == 'Token ' ) {
            $this->auth_token = true;
        }

    }



    public function beforeExecuteRoute()
    {
        $this->view->setVar("plugins_status", "10910");

        $this->modelClass = str_replace('Controller','',get_class($this));
        $postStr = file_get_contents('php://input', 'r');
        //提交的内容为解析为正常json时，将内容合并到$_REQUEST变量中
        if( !empty($postStr) && substr($postStr,0,1)=='{' ) {
            $arr = json_decode($postStr,true);
            if($arr) {$_REQUEST = array_merge($_REQUEST,$arr); }
        }
        $session = Di::getDefault()->getSession();
        if(isset($_SESSION['Auth']['User']['id']) && $_SESSION['Auth']['User']['id'] != ''){
            $this->currentUser = $_SESSION['Auth']['User'];
            $this->currentId = $_SESSION['Auth']['User']['id'];
        }

        if( !is_array($this->currentUser) || empty($this->currentId) ) {
            // 未登录态，含有AUTHORIZATION时，使用token登录
            if( !empty($_SERVER['HTTP_AUTHORIZATION']) && substr($_SERVER['HTTP_AUTHORIZATION'],0,6) == 'Token ' ) {
                $this->auth_token = true;
                $token = substr($_SERVER['HTTP_AUTHORIZATION'],6);
                $author_type = $_SERVER['HTTP_X_CSRF_TOKEN']?$_SERVER['HTTP_X_CSRF_TOKEN']: 'app';
                $signtoken = SignToken::getToken($token,$author_type);
                if( !empty($signtoken) ){
                    $uid = $signtoken->id;
                    $userinfo = User::getUserById($uid);
                    if($userinfo) {
                        $this->currentUser = $userinfo;
                        $this->currentId = $uid;
                        $_SESSION['Auth']['User'] = $userinfo;
                    }
                    else{
                        header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=-133009');
                    }
                }
                else{
                    header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=-133009');
                }
            }
        }

        //判断是否是免登录
        if(!empty(static::$is_acl)){
            if(empty($this->currentId) && !in_array($this->dispatcher->getActionName(), static::$acl_list)){
                header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=-133009');exit;
            }
            if((!isset($_SESSION['wx_id']) || $_SESSION['wx_id'] == '') && !in_array($this->dispatcher->getActionName(), static::$acl_list)){
                header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=303');exit;
            }
        }else{
            //其他所有的都需要登录且选择公众号
            if(empty($this->currentId)){
                header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=-133009');exit;
            }
            if(!isset($_SESSION['wx_id']) || $_SESSION['wx_id'] == ''){
                header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=303');exit;
            }
        }
        // if((!isset($_SESSION['wx_id']) || $_SESSION['wx_id'] == '') && (!empty(static::$is_acl)?!in_array($this->dispatcher->getActionName(),static::$acl_list):false) ){
        //     header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=303');
        // }

        // if((!isset($_SESSION['wx_id']) || $_SESSION['wx_id'] == '') && (static::$is_acl?!in_array($this->dispatcher->getActionName(),static::$acl_list):true) ){
        //     header("location:http://" .$this->request->getHttpHost().'/new/error/index?code=304');
        // }
    }

    protected function viewAction($id){
        if( is_numeric($id) ) {
            $item = ($this->modelClass)::findFirst([
                "conditions" => "id = :id:",
                "bind"       => [
                    'id' => $id,
                ]
            ]);
        }
        else{
            $item = ($this->modelClass)::findFirst([
                "conditions" => "slug = ?1",
                "bind"       => [
                    1 => $id,
                ]
            ]);
        }
        if(empty($item)) {
            return $this->reqAndResponse->sendResponsePacket(404, null, '数据没有找到');
        }
        return $this->reqAndResponse->sendResponsePacket(200, ['item'=>$item->toArray(),'options'=>[]], 'SUCCESS');
    }

    public function afterExecuteRoute()
    {
        // 在每一个找到的动作后执行
    }


    //判断是否是管理员
    public function checkAdmin(){
        if(intval($this->currentId) === 1){
            return true;
        }
        $url = strtolower($this->dispatcher->getControllerName() ."/". $this->dispatcher->getActionName());
        $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$this->currentId ]);
        if($AdminOBJ == false) return false;
        $AdminGroup = AdminGroup::findFirst(['id'=>$AdminOBJ->group_id]);

        if($AdminGroup == false){
            return false;
        }
        $url_arr = explode(',', $AdminGroup->detail);
        if(empty($url_arr) || !in_array($url,$url_arr)){
            return false;
        }
        return true;
    }







}
