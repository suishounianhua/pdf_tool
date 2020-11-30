<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

class UserController extends ControllerBase
{




    public function testAction(){

    }

    public function idAction(){
        if( empty($this->currentId) ) {
            return $this->reqAndResponse->sendResponsePacket(10910,array(
                'id'=> 0,
                'is_staff' => false,
            ), "需要登录");
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(200,array(
                'id'=> $this->currentId,
                'is_staff' => !empty($_SESSION['Auth']['Staff']),
            ), "SUCCESS");
        }
    }

    public function infoAction(){
        if( empty($this->currentId) ) {
            return $this->reqAndResponse->sendResponsePacket(10910,null, "需要登录才能继续操作");
        }
        else{
            $item = User::findFirst([
                'conditions' => array('id' => $this->currentId),
                'colums' => array('id','username','mobile','last_login','client_ip','score','invite_code','balance','activation_key','image'),
            ]);

        }
    }




    public function verifiedPublicNoAction(){
        require_once APP_PATH."/api/plugins/baiduai/AipOcr.php";
        $data = $this->checkRequireFields(['real_name','oauth_id','mobile','code'],$this->request->getPost());
        $vcode = MobileCaptcha::findFirst([
            'conditions' => 'mobile = :mobile: AND code = :code: AND created <= :created:',
            'bind' => ['mobile'=>$data['mobile'],'code'=>$data['code'],'created'=>date('Y-m-d H:i:s',strtotime("-5 minute"))],
        ]);
        if(!$vcode){
            $this->reqAndResponse->sendResponsePacket(400,null, "验证码错误或者过期");
            return;
        }
        $isBind = Oauthbind::findFirst([
            'conditions' => 'user_id = :user_id: AND id = :oauth_id:',
            'bind' => ['user_id' => $this->currentId,'oauth_id' => $data['oauth_id']],
            "columns" => ['id as oauth_id','oauth_name','created'],
            'order' => 'id desc',
        ]);
        if(!$isBind){
            $this->reqAndResponse->sendResponsePacket(400,null, "认证失败");
            return;
        }
        $data['status'] = 2;
        $res = MallTrader::findFirst([
            'conditions' => '(user_id = :user_id: AND type = :type: AND deleted = :deleted:)',
            'bind' => ['user_id' => $this->currentId,'type' => $data['type'],'deleted'=>0],
            'hydration' => Resultset::HYDRATE_ARRAYS
        ]);
        if($res)
        {
            if(!MallTrader::safeSave($res,$data))
            {
                $this->reqAndResponse->sendResponsePacket(400,null, "认证失败");
                return;
            }
        }else{
            $trader = new MallTrader();
            $data['created'] = date('Y-m-d H:i:s');
            $data['user_id'] = $this->currentId;
            if(!MallTrader::safeSave($trader,$data))
            {
                $this->reqAndResponse->sendResponsePacket(400,null, "认证失败");
                return;
            }
        }
        $this->reqAndResponse->sendResponsePacket(200,null, "认证成功");

    }


    //个人实名认证
    public function verifiedPersonAction(){
        require_once APP_PATH."/api/plugins/baiduai/AipOcr.php";
        $data = $this->checkRequireFields([],$this->request->getPost());
        $vcode = MobileCaptcha::findFirst([
            'conditions' => 'mobile = :mobile: AND code = :code: AND created <= :created:',
            'bind' => ['mobile'=>$data['mobile'],'code'=>$data['code'],'created'=>date('Y-m-d H:i:s',strtotime("-5 minute"))],
        ]);
        if(!$vcode){
            $this->reqAndResponse->sendResponsePacket(400,null, "验证码错误或者过期");
            return;
        }
        if(!empty($data['cardimg_front']) && !empty($data['cardimg_back']))
        {
            $client = new AipOcr('11252216','9DDF0YjgtG31HbZnQlstyS3m','mKyQRldi5DylWKjxCR1x4u9fZGzGzr7u');
            $frontImg = file_get_contents($data['cardimg_front']);
            $backImg = file_get_contents($data['cardimg_back']);
            $frontRes = $client->idcard($frontImg, 'front');
            $backRes = $client->idcard($frontImg, 'back');
            if(isset($frontRes['error_code']) || isset($backRes['error_code'])){
                $this->reqAndResponse->sendResponsePacket(400,null, !empty($frontRes['error_msg'])?$frontRes['error_msg']:$backRes['error_msg']);
                return;
            }
            if(empty($frontRes['words_result']['姓名']['words']) && empty($frontRes['words_result']['公民身份号码']['words'])){
                $this->reqAndResponse->sendResponsePacket(400,null, "请检查图片是否上传正确");
                return;
            }
            if($frontRes['words_result']['姓名']['words'] != $data['real_name'] && $frontRes['words_result']['公民身份号码']['words'] != $data['card'])
            {
                $this->reqAndResponse->sendResponsePacket(400,null, "填写姓名,身份证号码和上传图片不相符");
                return;
            }

            $res = MallTrader::findFirst([
                'conditions' => '(user_id = :user_id: AND type = :type: AND deleted = :deleted:)',
                'bind' => ['user_id' => $this->currentId,'type' => $data['type'],'deleted'=>0],
                'hydration' => Resultset::HYDRATE_ARRAYS
            ]);
            $data['status'] = 2;
            if($res)
            {
                if(!MallTrader::safeSave($res,$data))
                {
                    $this->reqAndResponse->sendResponsePacket(400,null, "认证失败");
                    return;
                }
            }else{
                $trader = new MallTrader();
                $data['created'] = date('Y-m-d H:i:s');
                $data['user_id'] = $this->currentId;
                if(!MallTrader::safeSave($trader,$data))
                {
                    $this->reqAndResponse->sendResponsePacket(400,null, "认证失败");
                    return;
                }
            }
            $this->reqAndResponse->sendResponsePacket(200,null, "认证成功");

        }
//        User::verified($data,$this->currentUser['id']);
    }



    public function addPersonInfoAction(){
        $data = $this->checkRequireFields([],$this->request->getPost());
        if(MallTrader::savePersonInfo($this->currentId,$data)){
            $this->reqAndResponse->sendResponsePacket(200,null, "保存成功");
        }else{
            $this->reqAndResponse->sendResponsePacket(400,null, "保存失败");
        }
    }

    public function setMobileAction(){
        $data = $this->checkRequireFields(['mobile'],$this->request->getPost());
        // if($data['password'] == '' || $data['password'] != $data['repassword'] ){
        //     $this->reqAndResponse->sendResponsePacket(402,null, "两次密码不一致");
        //     return;
        // }
        // $len = strlen($data['password']);
        // if($len < 8){
        //     $this->reqAndResponse->sendResponsePacket(402,null, "密码必须是8位数以上");
        //     return;
        // }
        // if(!preg_match('/[a-zA-Z]/',$data['password'])){
        //     $this->reqAndResponse->sendResponsePacket(402,null, "密码必须要包含字母");
        //     return;
        // }

        if($data['mobile'] == ''){
            $this->reqAndResponse->sendResponsePacket(402,null, "手机号不能为空");
            return;
        }
        $UserObj = User::findFirst(['conditions'=>['mobile'=>$data['mobile']]]);
        if($UserObj != false){
            $this->reqAndResponse->sendResponsePacket(402,null, "当前手机号已经被注册");
            return;
        }
        $mobile_captchas = new MobileCaptcha();
        $condition = array(
            'code' => $data['code'],
            'mobile' => $data['mobile']
        );
        $codeObj = MobileCaptcha::findFirst($condition);
        if($codeObj == false ){
             $this->reqAndResponse->sendResponsePacket(402,null, "验证码错误，请重新输入");
            return;
        }
        $codeObj->delete();
        if(strtotime($codeObj->created) + 300 <= time() ){
            $this->reqAndResponse->sendResponsePacket(402,null, "验证码已过5分钟有效期");
            return;
        }
        $userfobj = User::findFirstById($this->currentId);
        if($userfobj == false){
            $this->reqAndResponse->sendResponsePacket(402,null, "登录异常");
            return;
        }
        if($userfobj->mobile != ''){
            $this->reqAndResponse->sendResponsePacket(402,null, "您已经绑定过手机号码了");
            return;
        }
        // $salt = $this->config->Security->salt;
        // $pwd = $salt.$data['password'];
        // $userfobj->password = sha1($pwd);
        $userfobj->mobile = $data['mobile'];
        $channel_id = 0;

        if(isset($data['channel_id']) && $data['channel_id'] > 0){
            $channelObj = YyznChannel::findFirst(array(
                'id' => $data['channel_id']
                )
            );
            if($channelObj != false)  $channel_id = $data['channel_id'];
            $userfobj->channel_id = $channel_id;
        }

        $userfobj->updated = date("Y-m-d H:i:s");
        if($userfobj->save()){
            $_SESSION['Auth']['User']['mobile'] = $data['mobile'];
            $yyznworks_arr = $userfobj->toArray();
            $yyznworks_arr['yyzn_channel_id'] = $channel_id;
            $yyznworks_arr['yyzn_channel_name'] = '';
            if($channel_id > 0){
                $yyznworks_arr['yyzn_channel_name'] = $channelObj->name;
                YyznChannel::increase('user_total',array('id'=>$channel_id));
            }
            Di::getDefault()->get('eventsManager')->fire('yyznworks:beforeHandleMsg','register',$yyznworks_arr);
            $ActivitiesAuthsSong = new ActivitiesAuthsSong();
            $ActivitiesAuthsSong->fk_users_id = $this->currentId;
            $ActivitiesAuthsSong->fk_id = 1;
            $ActivitiesAuthsSong->num = 1;
            $ActivitiesAuthsSong->save();
            $this->reqAndResponse->sendResponsePacket(200,null, "操作成功");
            return;
        }
        $this->reqAndResponse->sendResponsePacket(200,null, "操作失败");
            return;
    }

    //手机号注册
    public function registerAction(){
        $data = $this->request->getPost();
        $rules = [
                    ['mobile', 'presenceof', '手机号不能为空'],
                    ['mobile', 'regex', '手机号格式错误', '/^1[34578]\d{9}$/', 1],
                    ['password', 'presenceof', '密码不能为空'],
                    ['captcha', 'presenceof', '短信验证码不能为空'],
                ];
        $validate = $this->validate;
        $validate->addRules($rules);
        $validate_res = $validate->validate($data);
        foreach ($validate_res as $message) {
            $this->reqAndResponse->sendResponsePacket(402,[], $message->getMessage());exit;
        }

        //查询手机号
        $userList = User::findFirst(['mobile'=>$data['mobile']]);
        if(!empty($userList)){
            $this->reqAndResponse->sendResponsePacket(402,null, "手机号已经被注册");exit;
        }
        //判断验证码
        $Captcha = MobileCaptcha::findFirst([
            'mobile' => $data['mobile'],
            'code' => $data['captcha'],
            'created >' => date('Y-m-d H:i:s',strtotime('-30 minutes')),
            ]);
        if($Captcha == false ){
            $this->reqAndResponse->sendResponsePacket(402,null, "短信验证码错误");exit;
        }
        $salt = $this->config->Security->salt;
        $pwd = $salt.$data['password'];
        $newPwd = sha1($pwd);
        $default_images  = 'https://bdn.135editor.com/files/yy_applet/missing-face.png';
        $username = substr(md5(time()),0,6);
        $user_id = 0;
        $client_ip = get_client_ip();
        $current_time = date('Y-m-d H:i:s');
        $user_data = array(
            'password' => $newPwd,
            'username' => $username,
            'image' => $default_images,
            'sex' => 1,
            'mobile' => $data['mobile'],
            'role_id' => 2,
            'last_login' => $current_time,
            'client_ip' => $client_ip,
            'created' => $current_time,
            'city' => '',
            'province' => '',
            'activation_key' => md5( uniqid() ),
            'invite_code' => md5( uniqid() ),
            'status' => 1,
        );
        $user_ins = new User();
        if($user_ins->save( $user_data )){
            // $oauth_ins = new Oauthbind();
            // $oauth_ins->source = 'weixinPb';
            // $oauth_ins->oauth_openid = $open_id;
            // $oauth_ins->oauth_name = $userinfo['nickname'];
            // $oauth_ins->unionid =  $userinfo['unionid'];
            // $oauth_ins->user_id =  $user_id;

            // $oauth_ins->save();
            $Captcha->delete();
            $this->reqAndResponse->sendResponsePacket(200,null, "注册成功");exit;  
        }else{
              $this->reqAndResponse->sendResponsePacket(403,[],'写入失败');exit;  
        }
    }
    public function getuserinfoAction(){
        if(!empty($this->currentId)){
            #已经登录
            $userList = $_SESSION['Auth']['User'];
            $userinfo = array(
                'id' => $userList['id'],
                'username' => $userList['username'],
                'email' => $userList['email'],
                'sex' => $userList['sex'],
                'avatar' => $userList['avatar'],
                'image' => $userList['image'],
                'mobile' => $userList['mobile'],
                'role_id' => $userList['role_id'],
                'roles' => $userList['roles'],
                'code' => $userList['code'],
                'token' => $userList['token'],
                'created' => $userList['created'],
                'plugin_status' => $userList['plugin_status'],
                'admin_status' => $userList['admin_status']
            );
            $this->reqAndResponse->sendResponsePacket(200,$userinfo, "您已成功登录");exit;
        }else{
            $this->reqAndResponse->sendResponsePacket(10910,array('id'=> 0,'is_staff' => false), "需要登录");exit;
        }
    }
    /**
    * @title 运营指南登录 备注(如需其他平台登录，请自行完善功能，当前只支持运营指南登录)
    * @author luoio
    * @RequestMethod post
    */
    public function loginAction(){
        
            $data = $this->request->getPost();
            if(!isset($data['username']) || $data['username'] == '' || !isset($data['password']) || $data['password'] == '' ){
                echo json_encode(array('ret'=>-1,'msg'=>'账号密码不能为空'));
                exit;
                // $this->reqAndResponse->sendResponsePacket(-101,null, "账号密码不能为空");exit;
            }
            $rules = [['username', 'email', '用户名不能为空']];
            $validate = $this->validate;
            $validate->addRules($rules);
            $validate_res = $validate->validate($data);
            //判断是否是邮箱
            if(count($validate_res) > 0){
                //查询手机号
               $userList = User::getUserByMobile($data['username']);
            }else{
                //查询邮箱
                $userList = User::getUserByEmail($data['username']);
            }
            if(empty($userList)){
                echo json_encode(array('ret'=>-1,'msg'=>'密码错误或用户不存在'));
                exit;
                // $this->reqAndResponse->sendResponsePacket(-101,null, "密码错误或用户不存在");exit;
            }
            //再次查询
            $list = User::findFirstById($userList['id']);
            $salt = $this->config->Security->salt;
            $pwd = $salt.$data['password'];
            $password = sha1($pwd);
            if($password != $list->password){
                echo json_encode(array('ret'=>-1,'msg'=>'密码错误或用户不存在'));
                exit;
                // $this->reqAndResponse->sendResponsePacket(-101,null, "");exit;
            }
            $author_type = $data['type']?$data['type']: 'app';
            $signtoken = SignToken::genToken($userList['id'],$author_type);
            Di::getDefault()->get('eventsManager')->fire('yyznworks:beforeHandleMsg','login',$userList);
            $client_ip = get_client_ip();
            $current_time = date('Y-m-d H:i:s');
            $updateinfo = array(
                'last_login' => $current_time,
                'client_ip' => $client_ip,
            );
            User::updateAll($updateinfo, array('id' => $userList['id']));

            $userinfo = array(
                'id' => $userList['id'],
                'username' => $userList['username'],
                'email' => $userList['email'],
                'sex' => $userList['sex'],
                'avatar' => $userList['avatar'],
                'image' => $userList['image'],
                'mobile' => $userList['mobile'],
                'role_id' => $userList['role_id'],
                'roles' => count($userList['role_id']),
                'code' => $userList['invite_code'],
                'token' => $signtoken,
                'created' => $userList['created'],
                'plugin_status' => $userList['plugin_status'],
                'admin_status' => 0,
            );

            if( $_REQUEST['type'] && in_array($_REQUEST['type'],array('app','web','plugin')) ) {
                $user_data['token'] = $token = SignToken::genToken($user_id,$_REQUEST['type']);
                header('Authorization: '.$token);
            }



            $successinfo = array(
                'ret' => 0,
                'msg' => '登录成功',
                'userinfo' => User::getUserById($userList['id']),
                'tasks'=> array(array('dotype'=>'rscallback','callback'=>'loginSuccess')),
            );
            $_SESSION['Auth']['User'] = $successinfo['userinfo'];
            if( $_REQUEST['type'] && in_array($_REQUEST['type'],array('app','web','plugin')) ) {
                $successinfo['userinfo']['token'] = $token = SignToken::genToken($userList['id'],$_REQUEST['type']);
                header('Authorization: '.$token);
            }
            $successinfo['userinfo']['avatar'] = $successinfo['userinfo']['avatar'] ? $successinfo['userinfo']['avatar'] : $successinfo['userinfo']['image'];

            //查询是否是管理员
            $successinfo['userinfo']['admin_status'] = 0;
            $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$userList['id'] ]);
            if(intval($userList['id']) === 1 || $AdminOBJ != false){
                $successinfo['userinfo']['admin_status'] = 1;
            }
            $this->setSignWxes($userList['id'],$successinfo['userinfo']['admin_status']);
            echo json_encode($successinfo);


            // //查询是否是管理员
            // $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$userList['id'] ]);
            // if(intval($userList['id']) === 1 || $AdminOBJ != false){
            //     $userinfo['admin_status'] = 1;
            // }
            // $_SESSION = array();
            // $_SESSION['Auth']['User'] = $userinfo;
            // $this->setSignWxes($userList['id'],$userinfo['admin_status']);
            // $this->reqAndResponse->sendResponsePacket(200,$userinfo, "您已成功登录");exit;
        // }
    }

    /**
    * @title 运营指南短信修改密码 备注(如需其他平台登录，请自行完善功能，当前只支持运营指南修改)
    * @author luoio
    * @RequestMethod post
    */
    public function resetByMobileAction(){
        $data = $this->request->getPost();
        if(!isset($data['User']) || empty($data['User'])){
            $this->reqAndResponse->sendResponsePacket(-1,[], '手机号不能为空！');exit;
        }
        $data = $data['User'];
        $rules = [
                    ['mobile', 'presenceof', '手机号不能为空'],
                    ['mobile', 'regex', '手机号格式错误', '/^1[23456789]\d{9}$/', 1],
                    ['password', 'presenceof', '密码不能为空'],
                    ['captcha', 'presenceof', '短信验证码不能为空'],
                ];
        $validate = $this->validate;
        $validate->addRules($rules);
        $validate_res = $validate->validate($data);
        foreach ($validate_res as $message) {
            $this->reqAndResponse->sendResponsePacket(-1,[], $message->getMessage());exit;
        }

        //查询手机号
        $userList = User::findFirst(['mobile'=>$data['mobile']]);
        if(empty($userList)){
            $this->reqAndResponse->sendResponsePacket(-101,null, "手机号没有被注册");exit;
        }
        //判断验证码
        $Captcha = MobileCaptcha::findFirst([
            'mobile' => $data['mobile'],
            'code' => $data['captcha'],
            'created >' => date('Y-m-d H:i:s',strtotime('-30 minutes')),
            ]);
        if($Captcha == false ){
            $this->reqAndResponse->sendResponsePacket(-1,null, "短信验证码错误");exit;
        }
        $salt = $this->config->Security->salt;
        $pwd = $salt.$data['password'];
        $newPwd = sha1($pwd);
        $userList->password = $newPwd;
        if($userList->save()){
            //成功删除验证码
            $Captcha->delete();
            $userList = $_SESSION['Auth']['User'];
            $userinfo = array(
                'id' => $userList['id'],
                'username' => $userList['username'],
                'email' => $userList['email'],
                'sex' => $userList['sex'],
                'avatar' => $userList['avatar'],
                'mobile' => $userList['mobile'],
                'role_id' => $userList['role_id'],
                'roles' => $userList['roles'],
                'code' => $userList['code'],
                'token' => $userList['token'],
                'created' => $userList['created'],
                'plugin_status' => $userList['plugin_status'],
                'admin_status' => $userList['admin_status']
                
            );
            $this->reqAndResponse->sendResponsePacket(200,$userinfo, "密码修改成功");exit;
        }
        $this->reqAndResponse->sendResponsePacket(-1,[], "修改失败");exit;
    }

    public function homeAction(){
        $articleList =  Wzsc::find([
            'joins' => [['model'=> 'MallTrader','on'=>'Wzsc.creator = MallTrader.user_id']],
            'conditions' => 'Wzsc.deleted = :deleted: AND Wzsc.status = 2 AND MallTrader.type = 2',
            'bind' => ['deleted' => 1],
            "columns" => ['Wzsc.id','MallTrader.nick_name', 'MallTrader.head_img', 'Wzsc.name','Wzsc.content'],
            'order' => 'Wzsc.id Desc',
            'limit' => 8
        ])->toArray();
        if(!empty($articleList)){
            foreach ($articleList as &$article){
                $tag = TagRelated::find([
                    'joins' => [['model'=> 'Tag','on'=>'TagRelated.tag_id = Tag.id']],
                    'columns' => ['Tag.name'],
                    'conditions' => 'TagRelated.relatedid = :relatedid: AND TagRelated.relatedmodel = :relatedmodel: AND TagRelated.deleted = 0',
                    'bind' => ['relatedid' => $article['id'],'relatedmodel'=>'Wzsc'],
                ])->toArray();
                $article['tag'] = array_column($tag,'name');
            }
        }
        $authorList = MallTrader::find([
            'conditions' => 'status = :status: AND type = :type: AND nick_name is not null ',
            'bind' => ['status' => 2,'type'=>2],
            "columns" => ['id','nick_name','introduction'],
            'order' => 'id desc',
            'limit' => 4,
        ])->toArray();
        if(!empty($authorList)){
            foreach ($authorList as &$author){
                $tag = TagRelated::find([
                    'joins' => [['model'=> 'Tag','on'=>'TagRelated.tag_id = Tag.id']],
                    'columns' => ['Tag.name'],
                    'conditions' => 'TagRelated.relatedid = :relatedid: AND TagRelated.relatedmodel = :relatedmodel: AND TagRelated.deleted = 0',
                    'bind' => ['relatedid' => $article['id'],'relatedmodel'=>'MallTrader'],
                ])->toArray();
                $author['tag'] = array_column($tag,'name');
            }
        }
        $paperList = Paper::find([
            'joins' => [['model'=> 'User','on'=>'Paper.creator = User.id']],
            'columns' => ['Paper.id','Paper.title','Paper.budget','User.avatar'],
            'conditions' => 'Paper.status = :status:',
            'bind' => ['status' => 1],
            'order' => 'Paper.id Desc',
            'limit' => 4
        ])->toArray();
        $result['ArticleList'] =  $articleList;
        $result['AuthorList'] =  $authorList;
        $result['paperList'] =  $paperList;
        $this->reqAndResponse->sendResponsePacket(200,$result, "获取成功");

    }

    public function getTncodeAction(){
        $img = new Images();
        $title = rand(10000,99999).md5(rand(0,999999999));
        $rand_num = rand(1,4);
        $rand_bnum = rand(1,10);
        $x = rand(70,355);           
        $y = rand(70,170);
        $_SESSION['tncode_x'] = $x;
        $tncode = $img->tncode($title,$rand_num,$rand_bnum,$x,$y);
        $background = $img->tncodeBackground($title,$rand_num,$rand_bnum,$x,$y);
        $result = array(
            'y' => $y,
            'tncode' => imgToBase64($tncode),
            'background' => imgToBase64($background),
            );

        unlink($tncode);
        unlink($background);
        $this->reqAndResponse->sendResponsePacket(200,$result, "获取成功");

    }

    public function checkTncodeAction(){
        $data = $this->request->getPost();
        if(!isset($data['tncode']) || $data['tncode'] == ''){
            return $this->reqAndResponse->sendResponsePacket(402,[], "图形验证码错误");
        }
        $tncode = $_SESSION['tncode_x'];
        unset($_SESSION['tncode_x']);
        if($tncode == '' || empty($tncode)){
            return $this->reqAndResponse->sendResponsePacket(402,[], "请先操作图形验证码");
        }
        $max = $tncode + 6;
        $min = $tncode - 6;
        if($data['tncode'] < $max && $data['tncode'] > $min){
            $_SESSION['tncode_status'] = 'ok';
            return $this->reqAndResponse->sendResponsePacket(200,[], "验证成功");
        }
        return $this->reqAndResponse->sendResponsePacket(402,[], "图形验证码错误");
    }

    public function publicNoListAction(){
        $oauthList = Oauthbind::find([
            'conditions' => 'user_id = :user_id:',
            'bind' => ['user_id' => $this->currentId],
            "columns" => ['id as oauth_id','oauth_name','created'],
            'order' => 'id desc',
        ])->toArray();
        $this->reqAndResponse->sendResponsePacket(200,$oauthList, "获取成功");
    }



    public function delOauthbindAction(){
        $data = $this->checkRequireFields(['oauth_id'],$this->request->getPost());
        $oauth= Oauthbind::findFirst($data['oauth_id']);
        if(!$oauth || $oauth->user_id != $this->currentId){
            $this->reqAndResponse->sendResponsePacket(400,null, "取消授权失败");
            return;
        }
        if($oauth->delete()){
            $this->reqAndResponse->sendResponsePacket(200,null, "取消授权成功");
        }else{
            $this->reqAndResponse->sendResponsePacket(400,null, "取消授权失败");
        }

    }






    public function wzFavorAction(){
        $data = $this->checkRequireFields([],$this->request->get());
        $wzList = Favorite::wzFavorList($data,$this->currentId);
        $this->reqAndResponse->sendResponsePacket(200,$wzList, "获取成功");

    }



    public function authorFavorAction(){
        $data = $this->checkRequireFields([],$this->request->get());
        $authorList = Favorite::authorFavorList($data,$this->currentId);
        $this->reqAndResponse->sendResponsePacket(200,$authorList, "获取成功");

    }


    public function checkQrLoginAction( $scene_id ){

        $wx_id = Di::getDefault()->get('redisCache')->get('qrlogin_wxid');
        if($wx_id === NULL) {
            $wxinfo = Wx::findFirst(array(
                'conditions'=>array('oauth_appid' => Di::getDefault()->get('config')->Weixin->AppId ),
                'colunms' => array('id'),
            ));
            if(!empty($wxinfo)) {
                $wx_id = $wxinfo->id;
                Di::getDefault()->get('redisCache')->save('qrlogin_wxid',$wx_id);
            }
            else{
                echo json_encode(array('ret'=>1,'msg'=>'微信模块（Wx）没有AppId公众号信息'));
                exit;
            }
        }

        if( $this->currentUser['id'] ) {

            $user_id = $this->currentUser['id'];
            $userdata = User::getUserById($user_id);
            if($this->currentUser) {
                $_SESSION['Auth']['User'] = $userdata;
            }else{
                echo json_encode( array('ret'=> -1,'msg'=>'获取用户信息失败') );
                        exit;
            }
            if( $_REQUEST['type'] && in_array($_REQUEST['type'],array('app','web','plugin')) ) {
                $userdata['token'] = $token = SignToken::genToken($user_id,$_REQUEST['type']);
                header('Authorization: '.$token);
            }
            $userdata['avatar'] = $userdata['avatar'] ? $userdata['avatar'] : $userdata['image'];

            $successinfo = array(
                'ret' => 0,
                'msg' => '登录成功',
                'userinfo' => $userdata,
                'tasks'=> array(array('dotype'=>'rscallback','callback'=>'loginSuccess')),
            );
            //查询是否是管理员
            $successinfo['userinfo']['admin_status'] = 0;
            $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$user_id ]);
            if(intval($user_id) === 1 || $AdminOBJ != false){
                $successinfo['userinfo']['admin_status'] = 1;
            }

            $this->setSignWxes($successinfo['userinfo']['id'],$successinfo['userinfo']['admin_status']);

            echo json_encode($successinfo);
            exit;
        }
        else{
            $scanlog = WxQrScan::findFirst(array(
                'conditions'=> array( 'slug' => $scene_id,'wx_id' => $wx_id ),
                'order' => 'id desc', //获取最新的一条
            ));
            if(empty($scanlog)) {
                echo json_encode(array('ret'=>1)); //没有扫码登录,继续监控，直至setInterval过期被clear
            }
            else{
                $log = $scanlog->toArray();
                $scanlog->delete(); // 已识别的删除，减少发生冲突的几率

                if( strtotime( $log['created'] ) < time() - 900 ) {
                    //返回0,终止二维码的继续检测。防止刷爆服务器
                    echo json_encode( array('ret'=> -1,'msg'=>'二维码超时，请重新生成二维码') );
                    exit;
                }

                $open_id = $log['username'];

                WechatService::$appId = Di::getDefault()->get('config')->Weixin->AppId;
                WechatService::$secretKey = Di::getDefault()->get('config')->Weixin->AppSecret;

                $oauth = Oauthbind::findFirst(array('conditions'=>array(
                    'source' => 'weixinPb',
                    'oauth_openid' => $open_id,
                )));
                $user_id = 0; $user_data = array();
                $client_ip = get_client_ip();
                $current_time = date('Y-m-d H:i:s');

                if( empty($oauth) ) {
                    $userinfo = WechatService::getUserInfo($open_id);
                    if( empty($userinfo['errcode']) ) {
                        if ( $userinfo['sex']==1 ) {
                            $gender = 1; //男
                        } else {
                            $gender = 0; //女
                        }

                        if( $userinfo['unionid'] ) {
                            $oauth = Oauthbind::findFirst(array(
                                'conditions'=>array(
                                    'unionid' => $userinfo['unionid'],
                                )
                            ));
                            if( !empty($oauth) ) {
                                $user_id = $oauth->user_id;
                                $user_data = User::getUserById($user_id);
                            }
                        }

                        $new_flag = false;

                        if( $user_id == 0 ) {
                            $user_data = array(
                                'password' => md5(random_str(12)),
                                'username' => $userinfo['nickname'],
                                'image' => $userinfo['headimgurl'],
                                'sex' => $gender,
                                'role_id' => 2,
                                'last_login' => $current_time,
                                'client_ip' => $client_ip,
                                'created' => $current_time,
                                'city' => $userinfo['city'],
                                'province' => $userinfo['province'],
                                'activation_key' => md5( uniqid() ),
                                'invite_code' => md5( uniqid() ),
                                'status' => 1,
                                'mobile' => "",
                            );
                            $user_ins = new User();
                            $user_ins->save( $user_data );
                            //写入工作任务

                            $new_flag = true;
                            $user_id = $user_data['id'] = $user_ins->id;
                        }else{
                            //写入工作任务
                            Di::getDefault()->get('eventsManager')->fire('yyznworks:beforeHandleMsg','login',$user_data);
                        }

                        if( $user_id ) {
                            /*$oauth_data = array(
                                'source' => 'weixinPb',
                                'oauth_openid' => $open_id,
                                'oauth_name' => $userinfo['nickname'],
                                'unionid' => $userinfo['unionid'],
                                'user_id' => $user_id,
                            );*/
                            $oauth_ins = new Oauthbind();
                            $oauth_ins->source = 'weixinPb';
                            $oauth_ins->oauth_openid = $open_id;
                            $oauth_ins->oauth_name = $userinfo['nickname'];
                            $oauth_ins->unionid =  $userinfo['unionid'];
                            $oauth_ins->user_id =  $user_id;

                            $oauth_ins->save();

                            if( !$new_flag ) {
                                $updateinfo = array(
                                    'image' => $userinfo['headimgurl'],
                                    'username' => $userinfo['nickname'],
                                    'last_login' => $current_time,
                                    'client_ip' => $client_ip,
                                );
                                User::updateAll($updateinfo, array('id' => $user_id));
                            }

                            $_SESSION['Auth']['User'] = $user_data;
                        }
                    }
                    else{
                        echo json_encode( array('ret'=> -1,'msg'=>'获取微信昵称信息错误。'.$userinfo['errcode']) );
                        exit;
                    }
                }
                else{
                    $user_id = $oauth->user_id;
                    $user_data = User::getUserById($user_id);

                    $updateinfo = array(
                        'last_login' => $current_time,
                        'client_ip' => $client_ip,
                    );
                    if(empty($db_user['invite_code'])) {
                        $updateinfo['invite_code'] =  md5( uniqid() );
                    }

                    // 登录过的用户，再次登录时检测用户头像是否更新并调整
                    $userinfo = WechatService::getUserInfo($open_id);
                    if( !empty($userinfo['headimgurl'])  && $userinfo['headimgurl'] != $user_data['image'] ) {
                        $updateinfo['image'] = $userinfo['headimgurl'];
                        $updateinfo['username'] = $userinfo['nickname'];
                        $user_data['image'] = $userinfo['headimgurl'];
                        $user_data['username'] = $userinfo['nickname'];
                    }

                    User::updateAll($updateinfo, array('id' => $user_id));

                    $_SESSION['Auth']['User'] = $user_data;

                }

                if($user_id) {
                    if( $_REQUEST['type'] && in_array($_REQUEST['type'],array('app','web','plugin')) ) {
                        $user_data['token'] = $token = SignToken::genToken($user_id,$_REQUEST['type']);
                        header('Authorization: '.$token);
                    }

                    $user_data['avatar'] = $user_data['avatar'] ? $user_data['avatar'] : $user_data['image'];
                    $successinfo = array(
                        'ret' => 0,
                        'msg' => '登录成功',
                        'userinfo' => $user_data,
                        'tasks'=> array(array('dotype'=>'rscallback','callback'=>'loginSuccess')),
                    );
                    //查询是否是管理员
                    $successinfo['userinfo']['admin_status'] = 0;
                    $AdminOBJ  = AdminAuth::findFirst(['user_id'=>$user_id ]);
                    if(intval($user_id) === 1 || $AdminOBJ != false){
                        $successinfo['userinfo']['admin_status'] = 1;
                    }

                    $this->setSignWxes($successinfo['userinfo']['id'],$successinfo['userinfo']['admin_status']);

                    echo json_encode($successinfo);
                    return ;
                }
                if( empty($user_id) ) {
                    echo json_encode(array('ret'=>-1,'msg'=>'创建用户失败'));
                    return ;
                }
            }
        }
        exit;
    }

    //发送短信----小程序
    public function sendVerifAction(){
        $postData = $this->request->getPost();
        if(isset($_SESSION['verif_time'])){
            $time = $_SESSION['verif_time'];
            //判断时间是否小于60秒
            if(($time+60) > time()){
                return $this->reqAndResponse->sendResponsePacket(402,[], "一分钟内只能发送一次短信");
            }else{
                $time = time();
                $_SESSION['verif_time'] = $time;
            }
        }else{
            $time = time();
            $_SESSION['verif_time'] = $time;
        }
        if(empty($postData['mobile'])) {
            return $this->reqAndResponse->sendResponsePacket(402,[], "请输入手机号");
        }elseif(substr($postData['mobile'],0,3) == '171' || substr($postData['mobile'],0,3) == '170') {
            return $this->reqAndResponse->sendResponsePacket(402,[], '禁止170、171号段注册，请换用其它手机号');
        }
        $code = rand(1000,9999);
        // $code = "1356";
        $sms_captpl = '【运营指南】您的验证码是'.$code.'。如非本人操作，请忽略本短信';
        $result = Yunpian::single_send($postData['mobile'],$sms_captpl);
        // $result['code'] = 0;
        if(isset($result['code']) && $result['code']==0 ) {
            $mobile_captchas = new MobileCaptcha();
            $mobile_captchas->save(array(
                'mobile' => $_POST['mobile'],
                'code' => $code,
                'created'=>date("Y-m-d H:i:s",time())
            ));
            $this->reqAndResponse->sendResponsePacket(200,[], "短信已发送，请注意查收");
            return;
        }
        else{
            $this->reqAndResponse->sendResponsePacket(403,[], '短信发送失败，原因为：'.$result['msg'].'。'.$result['detail']);
            return;
        }
    }

    public function sendMsgAction(){
        $postData = $this->request->getPost();
        if($_SESSION['tncode_status'] != 'ok'){
            return $this->reqAndResponse->sendResponsePacket(402,[], "图形验证码错误");
        }
        unset($_SESSION['tncode_status']);
        if(empty($postData['mobile'])) {
            return $this->reqAndResponse->sendResponsePacket(402,[], "请输入手机号");
        }elseif(substr($postData['mobile'],0,3) == '171' || substr($postData['mobile'],0,3) == '170') {
            return $this->reqAndResponse->sendResponsePacket(402,[], '禁止170、171号段注册，请换用其它手机号');
        }
        $code = rand(100000,999999);
        $sms_captpl = '【运营指南】您的验证码是'.$code.'。如非本人操作，请忽略本短信';
        $result = Yunpian::single_send($postData['mobile'],$sms_captpl);
        if(isset($result['code']) && $result['code']==0 ) {
            $mobile_captchas = new MobileCaptcha();
            $mobile_captchas->save(array(
                'mobile' => $_POST['mobile'],
                'code' => $code,
                'created'=>date("Y-m-d H:i:s",time())
            ));
            $this->reqAndResponse->sendResponsePacket(200,[], "短信已发送，请注意查收");
            return;
        }
        else{
            $this->reqAndResponse->sendResponsePacket(403,[], '短信发送失败，原因为：'.$result['msg'].'。'.$result['detail']);
            return;
        }
    }

    public function logoutAction(){
        $_SESSION = array();
        session_destroy();
        $this->reqAndResponse->sendResponsePacket(200,[], 'ok');
        return;
    }


    public function getLoginQrAction( ){
        WechatService::$appId = Di::getDefault()->get('config')->Weixin->AppId;
        WechatService::$secretKey = Di::getDefault()->get('config')->Weixin->AppSecret;

        $scene_id = random_str(9,'num');
        $result = WechatService::qrcode_create($scene_id,'QR_SCENE');

        if($result['ticket']) {
            $result['ret'] = 0;
            $result['scene_id'] = $scene_id;
        }
        else{
            $result['ret'] = -1;
        }
        echo json_encode($result);exit;
    }

}