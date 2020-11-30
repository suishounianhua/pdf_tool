<?php

use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use \Phalcon\Mvc\Controller;
use Firebase\JWT\JWT;
use Phalcon\Http\Response;

class ControllerBase extends Controller
{
    var $auth_token = false;
    public $currentUser = null;
    public $currentId = null;

    public $currentWxes = null;
    public $currentWxid = null;

    public static $acl_list = [];
    public static $is_acl = true;
    public $modelClass = null;


    public function initialize()
    {
//        $content_type = 'application/json';
//        if (Di::getDefault()->getRequest()->isMethod("OPTIONS")) {
//            $this->response->resetHeaders();
//            $this->response->setHeader('Access-Control-Allow-Origin', ''); //666
//            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
//            $this->response->setHeader("Access-Control-Allow-Methods", 'GET,PUT,POST,DELETE,OPTIONS');
//            $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
//            $this->response->setHeader('Content-type', $content_type);
//            $this->response->sendHeaders();
//            die;
//        }
//        $this->response->resetHeaders();
//        $this->response->setHeader('Access-Control-Allow-Origin', ''); //666
//        $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
//        $this->response->setHeader("Access-Control-Allow-Methods", 'GET,PUT,POST,DELETE,OPTIONS');
//        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
//        $this->response->setHeader('Content-type', $content_type);
//        $this->response->sendHeaders();
        if( !empty($_SERVER['HTTP_AUTHORIZATION']) && substr($_SERVER['HTTP_AUTHORIZATION'],0,6) == 'Token ' ) {
            $this->auth_token = true;
        }

        // if( $_REQUEST['https']==1  || $_SERVER['REQUEST_SCHEME'] == 'https' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']=='https' )){
        //     define('IS_HTTPS',true);
        // }

    }



    public function beforeExecuteRoute()
    {
        if( Di::getDefault()->get('config')->debugMode && ($_SERVER['HTTP_ORIGIN'] || $_SERVER['HTTP_REFERER']) ){
            if($_SERVER['HTTP_ORIGIN']) {
                $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
                header('Access-Control-Allow-Origin:' . $origin);
            }
            else{
                $urlinfo = parse_url($_SERVER['HTTP_REFERER']);
                header('Access-Control-Allow-Origin:'.$urlinfo['scheme'].'://'.$urlinfo['host'].($urlinfo['port']?':'.$urlinfo['port']:''));
            }
            // 响应类型
            header('Access-Control-Allow-Methods: GET,PUT,POST,DELETE,OPTIONS');
            // 带 cookie 的跨域访问
            header('Access-Control-Allow-Credentials: true');
            // 响应头设置
            header('Access-Control-Allow-Headers: Origin, No-Cache, Referer, X-CSRF-Token, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Authorization, Cache-Control, Expires, Content-Type, X-PINGOTHER, X-E4M-With, Content-Type,Accept');
            // header('Content-type: application/json');
        }

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
            // $_SESSION = array();
            // session_destroy();
            // $this->reqAndResponse->sendResponsePacket(-133009,null, '登录已经过期，请重新登录');
            //未登录态，含有AUTHORIZATION时，使用token登录
            if( !empty($_SERVER['HTTP_AUTHORIZATION']) && substr($_SERVER['HTTP_AUTHORIZATION'],0,6) == 'Token ' ) {
                $this->auth_token = true;
                $token = substr($_SERVER['HTTP_AUTHORIZATION'],6);
                $author_type = $_SERVER['HTTP_X_CSRF_TOKEN']?$_SERVER['HTTP_X_CSRF_TOKEN']: 'app';
                $signtoken = SignToken::getToken($token,$author_type);
                if( !empty($signtoken) ){
                    $uid = $signtoken->id;
                    $userinfo = User::getUserById($uid);
                    if($userinfo) {
                        $admin_status=0;
                        $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$userList['id'] ]);
                        if(intval($userList['id']) === 1 || $AdminOBJ != false){
                            $admin_status = 1;
                        }
                        $wxes = $this->setSignWxes($uid,$admin_status);
                        if($wxes != false){
                            $this->currentWxes = $wxes;
                            $this->currentWxid = $wxes['id'];
                        }

                        $this->currentUser = $userinfo;
                        $this->currentId = $uid;
                        $_SESSION['Auth']['User'] = $userinfo;
                    }
                    else{
                        $_SESSION = array();
                        session_destroy();
                        $this->reqAndResponse->sendResponsePacket(-133009,null, '登录已经过期，请重新登录');
                        exit;
                        // $this->reqAndResponse->sendResponsePacket(-133009,null, '登录Token错误。');
                        // exit;
                    }
                }
                else{
                    $_SESSION = array();
                    session_destroy();
                    $this->reqAndResponse->sendResponsePacket(-133009,null, '登录已经过期，请重新登录');
                    exit;
                    // $this->reqAndResponse->sendResponsePacket(-133009,null, '登录Token已过期，请重新登录。');
                    // exit;
                }
            }
        }

        if(isset($_SESSION['Auth']['Wxes']['id']) && $_SESSION['Auth']['Wxes']['id'] != ''){
            $this->currentWxes = $_SESSION['Auth']['Wxes'];
            $this->currentWxid = $_SESSION['Auth']['Wxes']['id'];
        }elseif(isset($_SESSION['wx_id']) && $_SESSION['wx_id'] != '' && empty($this->currentWxid)){
            $wxesObject = Wx::findFirstById($_SESSION['wx_id']);
            $_SESSION['Auth']['Wxes'] = $wxesObject->toArray();
            $this->currentWxes = $_SESSION['Auth']['Wxes'];
            $this->currentWxid = $_SESSION['Auth']['Wxes']['id'];
        }
        if( empty($this->currentId) && (static::$is_acl?in_array($this->dispatcher->getActionName(),static::$acl_list):!in_array($this->dispatcher->getActionName(),static::$acl_list)) ){
            $this->reqAndResponse->sendResponsePacket(403,null, '无权限,需要登陆');
            exit;
        }
//        if( $this->currentUser['id'] && $this->currentUser['status'] == -1 && !empty($_POST) ) {
//            $this->__message('账号已封禁，无权提交数据与上传图片','/',5,-1);
//        }
    }


    protected function createAction(){

        if(empty($this->currentId)) {
            return $this->reqAndResponse->sendResponsePacket(10910, null, '需要登录');
        }

        if($this->request->isPost()){
            $postData = $this->request->getPost();

            $obj = new $this->modelClass();
            $fields = $obj->fields();
            $modelData = array();
            foreach($postData as $k => $v) {
                if(in_array($k,$fields)) {
                    $modelData[$k] = $v;
                }
            }
            if(in_array('created',$fields)) { $modelData['created'] = date('Y-m-d H:i:s'); }
            if(in_array('creator',$fields)) { $modelData['creator'] = $this->currentId; }

//            $relations = $this->ModelsManager->getRelations();

            if( $obj->save($modelData) ) {
                return $this->reqAndResponse->sendResponsePacket(200, $obj->toArray(), '保存成功');
            }
            else{
                return $this->reqAndResponse->sendResponsePacket(402, $postData, '保存失败');
            }
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(402, null, '请提交数据');
        }
    }


    protected function updateAction($id = null){
        if(empty($this->currentId)) {
            return $this->reqAndResponse->sendResponsePacket(10910, null, '需要登录');
        }

        if($this->request->isPost()){
            $postData = $this->request->getPost();
            if(empty($id)) {
                $id = $postData['id'];
            }
            if(empty($id)) {
                return $this->reqAndResponse->sendResponsePacket(402, null, '网址错误，请选择要修改的数据');
            }
            else{
                $postData['id'] = $id;
            }

            $obj = ($this->modelClass)::findFirst(['id'=>$id]);

            if( empty($obj) || $obj->creator != $this->currentId ) {
                return $this->reqAndResponse->sendResponsePacket(402, null, '数据不存在或无修改权限');
            }

            $fields = $obj->fields();

            $modelData = array();
            foreach($postData as $k => $v) {
                if(in_array($k,$fields)) {
                    $modelData[$k] = $v;
                }
            }
            if(in_array('updated',$fields)) { $modelData['updated'] = date('Y-m-d H:i:s'); }

            if( $obj->update($modelData) ) {
                return $this->reqAndResponse->sendResponsePacket(200, $obj->toArray(), '修改成功');
            }
            else{
                return $this->reqAndResponse->sendResponsePacket(402, $postData, '修改失败');
            }
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(402, null, '请提交数据');
        }
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

    /**
     * 刷新token
     * @param $user_in
     * @param bool $force
     */
    protected function refreshCookieToken($user_info, $force = false)
    {
        if ($force) {
            unset($user_info['Token']);
        }
        $token = $this->getToken($user_info);
        $this->cookies->set('token', $token, time() + 15 * 86400);
    }

    /**
     * @param $columns
     * @param $data
     * @return mixed
     * 判断是否有未传参数
     *
     */
    public function ckeckRequirePostFields($columns, $data)
    {
        if (count($columns) > 0) {
            foreach ($columns as $key => $value) {
                if (!array_key_exists($value, $data)) {
                    $this->reqAndResponse->sendResponsePacket(402, null, '缺少必填项 ' . $value);
                    die();
                }
            }
        }
        return $data;
    }

    protected function checkRequiredOptions($columns, $data)
    {
        if (count($columns) > 0) {
            foreach ($columns as $key => $value) {
                if (!array_key_exists($value, $data)) {
                    $this->reqAndResponse->sendResponsePacket(402, null, '缺少必填项 ' . $value);
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * 获取token
     * @param $info
     * @return string
     */
    protected function getToken($info)
    {
        if (array_key_exists('Token', $info)) {
            return $info['Token'];
        }
        $token = JWT::encode($info, $this->config->application->jwt_key);
        return $token;
    }

    /**
     * 清除token
     * @param string $name
     */
    protected function clearCookieToken($name = 'token')
    {
        $cookie = $this->cookies->get($name);
        $cookie->delete();
    }

    /**
     * 登录
     * @param bool $autoRedirect
     * @return mixed
     */
    protected function login()
    {
        //首先token登录,从cookie中找
        $token = $this->cookies->has("token");

        //找不到，从get中找
        if ($token) {
            $loginCookie = $this->cookies->get('token');
            $token = $loginCookie->getValue();
        }

        //找token试着登陆
        if (!empty($token)) {
            $this->userInfo = $this->tokenLogin($token);
            return $this->userInfo;
        }

        return false;
    }


    /**
     * token登录
     * @param $token
     * @return array
     */
    protected function tokenLogin($token)
    {
        $manager_info_object = JWT::decode($token, $this->config->application->jwt_key, array('HS256'));
        $manager_info = [];
        foreach ($manager_info_object as $key => $value) {
            $manager_info[$key] = $value;
        }
        return $manager_info;
    }

    public function checkRequireFields($columns, $data)
    {
        if (count($columns) > 0) {
            foreach ($columns as $key => $value) {
                if (!array_key_exists($value, $data)) {
                   $this->reqAndResponse->sendResponsePacket(402, null, '缺少必填项 ' . $value);
                   exit;
                }
            }
        }
        return $data;
    }



    protected function lists($modelClass = null){
        $this->modelClass = $modelClass?$modelClass:$this->dispatcher->getControllerName();
        $page = intval($_REQUEST['page']);
        $page = $page ? $page : 1;
        $rows = intval($_REQUEST['limit']);
        if(empty($rows)){
            $rows = intval($this->config[$modelClass]['pagesize']);
            $rows = $rows ? $rows : 15;
        }

        $mlimit = $_REQUEST['mlimit'] ? $_REQUEST['mlimit'] : 10;
        $joins = array();

        $conditions = getSearchOptions($_REQUEST,$this->modelClass);
        $modelObj = loadModelObject($this->modelClass);
        $schema = $modelObj->schema();

        if( isset($schema['deleted']) ) {
            $conditions['deleted'] = 0;
        }
        if( isset($schema['visible']) ) {
            $conditions['visible'] = 1;
        }
        if($_REQUEST['sort']) {
            $order = $_REQUEST['sort'];
        }elseif ($_REQUEST['order']){
            $order = $_REQUEST['order'];
        }
        elseif(isset($schema['priority'])) {
            $order = $this->modelClass.'.priority desc,'.$this->modelClass.'.id desc';
        }
        else{
            $order = $this->modelClass.'.id desc';
        }

        if( $_REQUEST['type'] == 'self' ) {
            if( isset($schema['creator']) ) {
                $conditions[$this->modelClass.'.creator'] = $this->currentUser['id'];
            }
            elseif( isset($schema['user_id']) ) {
                $conditions[$this->modelClass.'.user_id'] = $this->currentUser['id'];
            }
        }
        else if( isset($schema['published']) ) {
            if(isset($_REQUEST['published']) && ( defined('Admin') || defined('LiteAdmin'))) {
                $conditions[$this->modelClass.'.published'] = $_REQUEST['published'];
            }
            else{
                $conditions[$this->modelClass.'.published'] = 1;
            }
        }
        $recursive = $_REQUEST['recursive'] > 0 ? 1 : -1;
        if($_REQUEST['tag_id']) {
            $joins[] = array(
                'model'=> 'TagRelated','join'=>'inner',
                'on'=> 'TagRelated.relatedid = '.$this->modelClass.'.id and TagRelated.tag_id = '.intval($_REQUEST['tag_id']).' and TagRelated.relatedmodel = \''.$this->modelClass."'"
            );
        }

        if(!empty($joins) && $this->config[$this->modelClass]['list_fields'] ) {
            $list_fields = explode(',',$this->config[$this->modelClass]['list_fields']);
            foreach($list_fields as $k => &$v){
                $v = $this->modelClass.'.'.$v;
            }
        }
        else{
            $list_fields = $this->config[$this->modelClass]['list_fields']?$this->config[$this->modelClass]['list_fields']:"*";
        }

        $cacke_key = $this->modelClass.'_lst_'.guid_string($_REQUEST);
        $cache = $this->getDI()->get('redisCache');
        $datalist = $cache->get($cacke_key);
        if( empty($datalist) || $_REQUEST['type'] == 'self') {
            $find_type = in_array($_REQUEST['find'],array('list','threaded')) ? $_REQUEST['find'] : 'all';
            if( $find_type == 'threaded') {
                $order = $_REQUEST['sort'] ? $_REQUEST['sort'] : $this->modelClass.'.left asc';
                if( $_REQUEST['parent_id'] ) {
                    $parent_item = ($this->modelClass)::findFirstById($_REQUEST['parent_id']);

                    if( $this->modelClass == 'Category' && $parent_item[$this->modelClass]['model'] && $parent_item[$this->modelClass]['model'] != 'Category' && $recursive > 0 ) {
                        // 每个分类加载几条数据。便于界面的呈现
                        $cate_model = $parent_item[$this->modelClass]['model'];
                        $mlist_fields = explode(',',$this->config['$cate_model']['list_fields']);
                        if(empty($mlist_fields)) {
                            $mlist_fields = array('*');
                        }
//                        $modelClass->hasMany(array(
//                            'hasMany'=>array(
//                                $cate_model => array(
//                                    'className'     => $cate_model,
//                                    'foreignKey'    => 'cate_id',
//                                    'conditions'    => array(),
//                                    'order'    => $cate_model.'.id DESC',
//                                    'limit'        => $mlimit,
//                                    'fields' => $mlist_fields,
//                                ))),false);
                        $modelClass->hasMany(
                            'id',
                            $cate_model,
                            'cate_id',
                            [
                                'alias'    => $cate_model,
                                'params'=> [
                                    'columns' => $mlist_fields,
                                    'order'    => $cate_model.'.id DESC',
                                    'limit'        => $mlimit
                                ]
                            ]
                        );
                    }

                    unset($conditions['parent_id'],$conditions[$this->modelClass.'.parent_id']);
                    $conditions[$this->modelClass.'.left >'] = $parent_item->left;
                    $conditions[$this->modelClass.'.right <'] = $parent_item->right;
                }

            }
            else if( $_REQUEST['cate_id'] && $schema['cate_id']['selectmodel'] && $schema['cate_id']['associatetype']=='treenode' && empty($_REQUEST['skip_sub']) ) {
                // 获取分类的子类,并增加cate_id查询条件
                $modelCate = $schema['cate_id']['selectmodel'];
//                $child_cates = $this->{$modelCate}->children($_REQUEST['cate_id'],false,array('id'));
                $parent = ($this->modelClass)::findFirstById($_REQUEST['parent_id']);
                $child_cates = array();
                if($parent){
                    $child_cates = array_column($parent->children()->toArray(),'id');
                }
                if( !empty($child_cates) ) {
                    $cateids = array( $_REQUEST['cate_id'] );
                    $cateids = array_merge_recursive($cateids,$child_cates);
                    $conditions[$this->modelClass.'.cate_id'] = $cateids;
                }
            }
            $datalist = ($this->modelClass)::find( array(
                'columns' => $list_fields,
                'conditions' => $conditions,
                'joins' => $joins,
                'limit' => $rows,
                'order' => $order,
                'page' => $page,
            ));
            $cache->set($cacke_key,$datalist,3);
        }
        $ret = BaseModel::getCondition($conditions);
        if(!empty($ret['conditions'])) {
            $total = ($this->modelClass)::count(array(
                'conditions' => $ret['conditions'],
                'joins' => $joins,
                'bind' => $ret['bind']?$ret['bind']:[]
            ));
        }
        else{
            $total = ($this->modelClass)::count();
        }

        $result = array();
        $result['modelClass'] = $this->modelClass;
        $result['region_control_name'] = $this->modelClass;
        $result['datalist'] = $datalist;
        $result['total'] = $total;

//        $page_navi = getPageLinks($total, $rows, $this->request, $page);
//        $this->set('list_page_navi', $page_navi); // page_navi 在region调用中有使用，防止被覆盖，此处使用 list_page_navi

        $fields = array_keys($schema);
        if($this->currentUser['id'] && in_array('favor_nums',$fields)){
            // favor_nums在列表显示字段范围内, 查询已收藏的列表
            $data_ids = array();
            foreach($datalist as $item){
                $data_ids[] = $item['id'];
            }

            $fovarited_list = Favorite::find(array(
                'conditions'=>array(
                    'model' => $this->modelClass,
                    'data_id' => $data_ids,
                    'creator_id' => $this->currentUser['id'],
                ),
                'columns' => array('id','data_id'),
            ))->toArray();
            $result['fovarited_list']=$fovarited_list;
        }
        $result['total'] = $total;
        $this->reqAndResponse->sendResponsePacket(200,$result, "获取成功");

    }



    protected function addAction(){
        $modelClass = $this->modelClass;
        if(empty($this->currentUser['id'])){
            return $this->reqAndResponse->sendResponsePacket(10910, null, '需要登录');
        }
        $data = $this->request->getPost();
        $data['creator'] = $this->currentUser['id'];
        $post = new $modelClass();
        if($post->save($data)){
            return $this->reqAndResponse->sendResponsePacket(200,null, "保存成功");
        }else{
            return $this->reqAndResponse->sendResponsePacket(400,null, "保存失败");
        }
    }




    protected function deleteAction($id = ''){
        if(empty($id)) {
            $id = $_REQUEST['id'];
        }

        $modelObj = loadModelObject($this->modelClass);
        $schema = $modelObj->schema();
        if(isset($schema['creator'])) {
            ($this->modelClass)::findFirst(array('id' => $id, 'creator' => $this->currentUser['id']));
            ($this->modelClass)::delete();
            return $this->reqAndResponse->sendResponsePacket(200,null, "删除成功");
        }
        elseif(isset($schema['user_id'])) {
            ($this->modelClass)::findFirst(array('id' => $id, 'user_id' => $this->currentUser['id']));
            ($this->modelClass)::delete();
            return $this->reqAndResponse->sendResponsePacket(200,null, "删除成功");
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(400,null, "删除失败");
        }
        return $this->reqAndResponse->sendResponsePacket(400,null, "参数有误"); //return进入afterFilter。不直接exit
    }



    function trashAction($ids = null) {
        if(is_array($_POST['ids'])&& !empty($_POST['ids'])){
            $ids = $_POST['ids'];
        }
        else{
            if (!$ids) {
                $this->redirect(array('action' => 'mine'));
            }
            $ids = explode(',', $ids);
        }
        $this->modelClass = $this->modelClass?$this->modelClass:$this->dispatcher->getControllerName();

        $error_flag = false;
        $modelObj = loadModelObject($this->modelClass);
        $schema = $modelObj->schema();
        $fields = array_keys($schema);

        foreach ($ids as $id) {
            if ( !intval($id) )
                continue;
            $data = array();
            $data['deleted'] = 1;
            if(in_array('published',$fields)){
                $data['published'] = 0;
            }
            ($this->modelClass)::updateAll( $data, array($this->modelClass.'.id' => $id,'creator' => $this->currentUser['id']));
        }
        return $this->reqAndResponse->sendResponsePacket(200,null, "更新成功");
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


    /**
    * @title 绑定公众号至token
    * @author luoio
    * @param uid 用户id
    * @param type 0 普通用户，1管理员
    * @RequestMethod post
    */
    public function setSignWxes($uid,$type = 0){
        $signtokenObject = SignToken::findFirstById($uid);
        //如果尚未设置则取第一条记录
        if($signtokenObject->web_wxid < 1){
            //需要加上管理员
            if($type < 1){
                $wxesObject = Wx::findFirst(['creator'=>$uid]);
            }else{
                $wxesObject = Wx::findFirstById($this->config->Yyzn_default_wechat->id);
            }
            if($wxesObject == false){
                return false;
            }
            $signtokenObject->web_wxid = $wxesObject->id;
            $signtokenObject->save();
            $_SESSION['Auth']['Wxes'] = $wxesObject->toArray();
            $_SESSION['wx_id'] = $_SESSION['Auth']['Wxes']['id'];
            return $wxesObject->toArray();
        }else{
            $wxesObject = Wx::findFirstById($signtokenObject->web_wxid);
            $_SESSION['Auth']['Wxes'] = $wxesObject->toArray();
            $_SESSION['wx_id'] = $_SESSION['Auth']['Wxes']['id'];
            return $wxesObject->toArray();
        }
        
    }
    
    //校验参数
    public function validateParam($rules = false){
        $postData = $this->request->getPost();
        if($rules == false) return $postData;
        $validate = $this->validate;
        $validate->addRules($rules);
        $validate_res = $validate->validate($postData);
        foreach ($validate_res as $message) {
            $this->sendCode(402,[],$message->getMessage());
        }
        return $postData;
    }
    /**
    
    * @title 固定错误代码
    * @author luodiao
    */
    public function sendCode($code = 200,$data = [],$msg = false){
        $codeArr = array(

            '200' => '请求成功',
            //权限类异常
            '301' => '您好，你需要购买权限才能进行下一步操作，相关疑问请添加增长顾问咨询',
            '302' => '您好，您的权限已经过期，你需要购买权限才能进行下一步操作，相关疑问请添加增长顾问咨询',
            '303' => '您当前尚未选择公众号，如没有公众号，请点击左上角添加按钮',
            '304' => '系统未能检测到你的公众号，请先授权公众号，或者该公众号已经被屏蔽，请联系管理员',
            '305' => '未能有操作次功能的权限',
            '306' => '权限不足',//只能为管理员
            '307' => '权限不足',//只能为开发者
            '308' => '仅支持认证订阅号、认证服务号授权使用',
            '310' => '需要借权',
            //错误类
            '401' => "微信端错误信息",
            '402' => "参数错误",
            '403' => "请求异常",//mysql 写入失败
            '404' => "数据不存在",//mysql 无法查找到数据
            '405' => "无需要进行操作",//微信无数据
            
            //素材相关
            '601' => "你需要同步素材",
            
            //开发者相关
            '701' => "您已经提交过申请",
            '702' => "您还未提交开发者入驻申请",
            '711' => "提现金额未达到系统标致",
            '712' => "你当前存在未结算的提现订单",
            //应用相关
            '801' => "当前应用尚未审核通过",
            '802' => "最大待审应用只能为3个，请等待应用审核通过再提交",
            '803' => "您已经提交过该应用申请",
            '804' => "英文识别标识已经被使用",
            '805' => "你需要先开通开发环境",
            '806' => "当前操作 只能操作已经提交的应用",
            '807' => "当前操作 只能操作审核通过的应用",
            '808' => "开发环境已经创建，为节约资源当前不允许删除，如需删除请联系客服",
            '809' => "当前已经存在更新请求，请耐心等待信息审核",

            //用户登录相关
            '10910' => "需要登录",
            '-133009' => "登录Token已过期，请重新登录。",
            );
        $resMsg = $codeArr[$code];
        if($msg !== false){
            $resMsg = $msg;
        }
        $this->reqAndResponse->sendResponsePacket($code,$data,$resMsg);
        exit;
    }
}
