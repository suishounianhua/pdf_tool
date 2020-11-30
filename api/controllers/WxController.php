<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Di;

class WxController extends ControllerBase
{
    public function initialize(){
        define('COMPONENT_APPID', $this->config->Weixin->ComponentAppID);
        define('COMPONENT_APPSECRET', $this->config->Weixin->ComponentAppSecret);
        define('COMPONENT_TOKEN', $this->config->Weixin->ComponentToken);
        define('COMPONENT_AESKEY', $this->config->Weixin->ComponentAesKey);
        parent::initialize();
    }

    public function beforeExecuteRoute()
    {
        parent::beforeExecuteRoute();
        $ActionName = $this->dispatcher->getActionName();
        if(empty($this->currentUser['id']) && !in_array($ActionName,array('getSignPackage'))) {
            $this->reqAndResponse->sendResponsePacket(10910,null, "请登录");
            exit;
        }
    }

    public function testAction(){
        echo 1;
    }
    


    public function pauthAction(){
//        // 跳转到旧项目来授权。
//        if( defined('IS_HTTPS') ) {
//            $redirect_uri = 'https://'.$_SERVER['HTTP_HOST'].'/wxes/plogin?type=vue';
//        }
//        else{
//            $redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/wxes/plogin?type=vue';
//        }

        $this->getDI()->get('redisCache')->del('wx-list-'.$this->currentId);
        if( defined('IS_HTTPS') ) {
            $redirect_uri = 'https://'.$_SERVER['HTTP_HOST'].'/new/wx/plogin';
        }
        else{
            $redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/new/wx/plogin';
        }

        $pre_auth_code = WechatService::compPreAuthcode();

        $url_str = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid='.COMPONENT_APPID.'&pre_auth_code='.$pre_auth_code.'&redirect_uri='.urlencode($redirect_uri);
        return $this->reqAndResponse->sendResponsePacket(200,array(
            'url' => $url_str,
        ));
    }

    public function ploginAction() {

        $auth_info = WechatService::compQueryAuth($_REQUEST['auth_code']);

        if($auth_info['errcode']){
            echo $auth_info['errcode'].':'.$auth_info['errmsg'];exit; //发生错误时，直接退出
        }

        $auth_appid = $auth_info['authorization_info']['authorizer_appid'];

        $rizer_info = WechatService::compAuthInfo($auth_appid);

        $wxid = $rizer_info['authorizer_info']['user_name'];
        if( empty($wxid) ) {
            echo 'api get error';exit;
        }

        // 取出旧的授权信息更新为新的授权信息
        $authbinds = Oauthbind::findFirst(array('conditions'=>	array(
            'source' => 'wechatComp',
            'oauth_openid' => $auth_appid,
        )));

        $domain = $rizer_info['authorizer_info']['alias'];
        $oauth_appid = $auth_info['authorization_info']['authorizer_appid'];

        if(empty($authbinds)){
//            $data['id'] = $authbinds->id;
            $authbinds = new Oauthbind();
            $authbinds->source = 'wechatComp';
        }
        $authbinds->user_id = $this->currentUser['id'];
        $authbinds->domain = $domain;
        $authbinds->oauth_name =  $rizer_info['authorizer_info']['nick_name'];
        $authbinds->oauth_openid = $oauth_appid;
        $authbinds->oauth_token= $auth_info['authorization_info']['authorizer_access_token'];
        $authbinds->expires =  $auth_info['authorization_info']['expires_in'];
        $authbinds->refresh_token = $auth_info['authorization_info']['authorizer_refresh_token'];
        $authbinds->func_info = json_encode($auth_info['authorization_info']['func_info']);
        $authbinds->updated = time();
        $authbinds->save();


        /*
         * 重新授权后，之后修改之前已授权的Oauthbind用户编号，
         * 若Wx表中已存在记录，则将Wx表中的用户编号与Oauthbind的设为一致
         * */
        $wxes = Wx::findFirst(array('conditions'=>array('wxid'=>$wxid)));

        if( !empty($wxes) ){
            $wx_id = $wxes->id;
            Wx::updateAll(array(
                'coverimg' => $rizer_info['authorizer_info']['head_img'],
                'slug' => $domain,
                'creator' => $this->currentUser['id'],
                'published' => 1,
                'oauth_appid' => $oauth_appid,
                'verify_type' => $rizer_info['authorizer_info']['verify_type_info']['id'],
                'service_type' => $rizer_info['authorizer_info']['service_type_info']['id'],
                /**
                 * 授权方认证类型，
                 * -1代表未认证， 0代表微信认证，1代表新浪微博认证， 2代表腾讯微博认证，
                 * 3代表已资质认证通过但还未通过名称认证， 4代表已资质认证通过、还未通过名称认证，但通过了新浪微博认证，
                 * 5代表已资质认证通过、还未通过名称认证，但通过了腾讯微博认证
                 */
                /**
                 * 授权方公众号类型，
                 *   0代表订阅号，  1代表由历史老帐号升级后的订阅号， 2代表服务号
                 */
            ),array( 'id'=> $wx_id ) );
        }
        else {
            $wx_ins = new Wx();
            if( ! $wx_ins->save(array(
                'name' => $rizer_info['authorizer_info']['nick_name'],
                'coverimg' => $rizer_info['authorizer_info']['head_img'],
                'cate_id' => 31,
                'published' => 1,
                'save_flag' => 1,
                'creator' => $this->currentUser['id'],
                'slug' => $domain,
                'wxid' => $wxid,
                'oauth_appid' => $oauth_appid,
                'verify_type' => $rizer_info['authorizer_info']['verify_type_info']['id'],
                'service_type' => $rizer_info['authorizer_info']['service_type_info']['id'],
            )) ) {
                echo 'save error.';exit;
            }
            $wx_id = $wx_ins->id;
        }
        $wxes = Wx::findFirst(array('conditions'=>array('wxid'=>$wxid)));
        $_SESSION['Auth']['Wxes'] = $wxes->toArray();
        $_SESSION['wx_id'] = $_SESSION['Auth']['Wxes']['id'];
        SignToken::updateAll(array('web_wxid'=>$_SESSION['Auth']['Wxes']['id']),array('id'=>$this->currentUser['id']));
        echo 'Success.<script>top.location.reload();</script>';
        exit;
    }


    public function getSignPackageAction() {
        // https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1421823488&token=&lang=zh_CN
        /**
         * 第三方平台开发者代替公众号使用JS SDK
         * 3、通过config接口注入权限验证配置，但在获取jsapi_ticket时，不通过公众号的access_token来获取，
         * 而是通过第三方平台的授权公众号token（公众号授权给第三方平台后，第三方平台通过“接口说明”中的api_authorizer_token接口得到的token），来获取jsapi_ticket，
         * 然后使用这个jsapi_ticket来得到signature，进行JS SDK的配置和开发。
         * 注意JS SDK的其他配置中，其他信息均为正常的公众号的资料（而非第三方平台的）。
         * @var Ambiguous $jsapiTicket
         */

        $wx_id = $_SESSION['wx_id'] ? $_SESSION['wx_id'] : $_REQUEST['wx_id'];
        if($wx_id) {
            WechatService::$appId = Wx::getWxAppid($wx_id);
        }
        if(empty(WechatService::$appId)) {
            WechatService::$appId = $this->config->Weixin->AppId;
        }
        if( WechatService::$appId ) {
            $jsapiTicket = WechatService::getJsApiTicket();

            // 注意 URL 一定要动态获取，不能 hardcode.
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

            if($_REQUEST['uri']) {
                $url = $_REQUEST['uri']; //传入当前页的参数。 'uri=' + encodeURIComponent(window.location.href)
            }
            else{
                $url = $_SERVER['HTTP_REFERER']; //接口调用，没有传入uri时，默认使用referer地址
            }

            $timestamp = time();
            $nonceStr = $this->createNonceStr();

            // 这里参数的顺序要按照 key 值 ASCII 码升序排序
            $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

            $signature = sha1($string);

            $signPackage = array(
                "appId"     => WechatService::$appId,
                "nonceStr"  => $nonceStr,
                "timestamp" => $timestamp,
                "url"       => $url,
                "signature" => $signature,
                "rawString" => $string,
                "jsapiTicket" => $jsapiTicket
            );
            return $this->reqAndResponse->sendResponsePacket(200,$signPackage, "SUCCESS");
        }
        else{
            return $this->reqAndResponse->sendResponsePacket(40001,null, "获取签名异常");
        }
    }

    protected function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function myauthAction()
    {
        if($this->checkAdmin() === true){
            $where = array(
                'Oauthbind.source' => 'wechatComp',
            );
        }else{
            $where = array(
                'Wx.creator' => $this->currentUser['id'],
                'Oauthbind.source' => 'wechatComp',
            );
        }
        $result = Wx::find(array(
            'conditions'=>$where,
            'joins' => array(array(
                'model' => 'Oauthbind',
                'on' => 'Oauthbind.oauth_openid=Wx.oauth_appid'
            )),
            "cache" => [
                "key"      => "wx-list-".$this->currentUser['id'],
                "lifetime" => 300,
            ],
        ));
        $data = array();
        $default_status = false;
        foreach($result as $wx){
            if($this->config->Yyzn_default_wechat->id == $wx->id){
                $default_status = true;
            }
            $data[] = array(
                'id' => $wx->id,
                'name' => $wx->name,
                'wid' => $wx->slug,
                'coverimg' => $wx->coverimg,
                'oauth_appid' => $wx->oauth_appid,
                'verify_type' => $wx->verify_type,
                'service_type' => $wx->service_type,
                'auto_replies' => $wx->auto_replies,
            );
        }
        
        return $this->reqAndResponse->sendResponsePacket(200,$data, "SUCCESS");
    }


    public function optWxAction(){
        $wx_id = $_REQUEST['wx_id'];
        if(empty($wx_id)) return $this->reqAndResponse->sendResponsePacket(303,[], "您当前尚未选择公众号，如没有公众号，请点击左上角添加按钮");
        $result = Wx::findFirst(['id'=>$wx_id]);
        $data = array(
                'id' => $result->id,
                'name' => $result->name,
                'coverimg' => $result->coverimg,
                'service_type' => $result->service_type,
                'verify_type' => $result->verify_type,
                'auto_replies' => $result->auto_replies,
                'oauth_appid' => $result->oauth_appid,
                'wxid' => $result->wxid,
        );

        if($result->creator === $this->currentUser['id'] || $this->config->Yyzn_default_wechat->id == $wx_id || $this->checkAdmin() === true){
            $_SESSION['wx_id'] = $result->id;
            $_SESSION['wx'] = $data;
            $_SESSION['wx']['oauth_appid'] = $result->oauth_appid;
            $_SESSION['Auth']['Wxes'] = $result->toArray();
            SignToken::updateAll(array('web_wxid'=>$result->id),array('id'=>$this->currentUser['id']));
            return $this->reqAndResponse->sendResponsePacket(200,$data, "请求成功");
        }
        return $this->reqAndResponse->sendResponsePacket(305,[], "未能有操作该公众号的权限");
    }

    //查询7日增减数据
    public function fansTrendAction(){
        $wx_id = $_REQUEST['wx_id'];
        if(!empty($this->currentWxid)){
            $wx_id = $this->currentWxid;
        }
        if(empty($wx_id)){
            return $this->reqAndResponse->sendResponsePacket(303,[], "您当前尚未选择公众号，如没有公众号，请点击左上角添加按钮");
        } 
        $begin_date = $_REQUEST['begin_date']?$_REQUEST['begin_date']:date("Y-m-d",strtotime("-7 day"));
        $end_date = $_REQUEST['end_date']?$_REQUEST['end_date']:date("Y-m-d",strtotime("-1 day"));
        $conditions = array(
                        'wxid' => $wx_id,
                        'ref_date >=' => $begin_date,
                        'ref_date <=' => $end_date
                    );
        $list = WxAnalysisSummaries::find(
                array(
                    'conditions' => $conditions,
                    'columns' => array('ref_date','new_user','cancel_user')
                )
            )->toArray();
        $begin_date = strtotime($begin_date);
        $end_date = strtotime($end_date);
        $diff_time = $end_date - $begin_date;
        if( $diff_time >= 86400){
            $day_num = ceil($diff_time/86400);
            $res_data = array(
                'ref_date' => '2020-06-01',
                'new_user' => '0',
                'cancel_user' => '0',
                );
            $resietm = array();
            for ($i=$day_num -1; $i >=0 ; $i--) {  
                $time_xz  = $begin_date + (86400 * $i);
                $res_data['ref_date'] = date("Y-m-d",$time_xz);
                $resietm[$time_xz] = $res_data;
            }

            foreach ($list as $key => $value) {
                $times_date = strtotime($value['ref_date']);
                if($value['ref_date'] == $resietm[$times_date]['ref_date']){
                    $resietm[$times_date]['new_user'] = $value['new_user'];
                    $resietm[$times_date]['cancel_user'] = $value['cancel_user'];
                }
            }
            $list = array();
            $resietm = array_reverse($resietm,false);
            foreach ($resietm as $key => $value) {
                $list[] = $value;
            }
        }
        return $this->reqAndResponse->sendResponsePacket(200,$list, "查询成功");
    }



    public function fansAnalysisAction(){
        $wx_id = $_REQUEST['wx_id'];
        if(!empty($this->currentWxid)){
            $wx_id = $this->currentWxid;
        }
        if(empty($wx_id)) return $this->reqAndResponse->sendResponsePacket(303,[], "您当前尚未选择公众号，如没有公众号，请点击左上角添加按钮");
        //查询昨日数据
        $yesterday = $_REQUEST['ref_date']?$_REQUEST['ref_date']:date("Y-m-d",strtotime("-1 day"));
        $CumulatesObj = WxAnalysisCumulates::findFirst(['wxid'=>$wx_id,'ref_date'=>$yesterday]);
        $res['all_user'] = 0;
        if($CumulatesObj != false){

            $res['all_user'] = $CumulatesObj->cumulate_user;
        }
        $wxesObj = Wx::findFirst(['id'=>$wx_id]);
        if($wxesObj == false){
            return $this->reqAndResponse->sendResponsePacket(404,[], "公众号不存在");
        }
        WechatService::setAppid($wxesObj->oauth_appid);
        $users = WechatService::getUserList(''); // 每次返回1万个openid
        if(isset($users['errcode']) && $users['errcode'] > 0){

        }else{
            $res['all_user'] = $users['total'] ;
        }
        $yesterday = date("Y-m-d",strtotime("-1 day"));
        $SummariesObj = WxAnalysisSummaries::findFirst(array('wxid'=>$wx_id,'ref_date'=>$yesterday));
        if($SummariesObj == false){
            $res['new_user'] = 0;
            $res['cancel_user'] = 0;
        }else{
            $res['new_user'] = $SummariesObj->new_user;
            $res['cancel_user'] = $SummariesObj->cancel_user;
        }
        //获取新增
        // $res['new_user'] = WxAnalysisSummaries::sum(['column' => 'new_user','conditions'=> "wxid = '".$wx_id."'"]);
        // if(empty($res['new_user'])){
        //     $res['new_user'] = 0;
        // }
        //获取取关
        // $res['cancel_user'] = WxAnalysisSummaries::sum(['column' => 'cancel_user','conditions'=> "wxid = '".$wx_id."'"]);
        // if(empty($res['cancel_user'])){
        //     $res['cancel_user'] = 0;
        // }

        //获取净增
        $res['real_new_user'] = $res['new_user'] - $res['cancel_user'];

        //获取男用户总数
        // WxUser::$TABLE_NAME = 'wx_users_'.($wx_id % 512);
        // $sex_conditions = array(
        //     'wx_id' => $wx_id,
        //     'sex'   => 1
        //     );
        // $res['user_man_count'] = WxUser::count(['conditions'=>$sex_conditions]);
        $res['user_man_count'] = 0;
        // $sex_conditions['sex'] = 2;
        // $res['user_women_count'] = WxUser::count(['conditions'=>$sex_conditions]);
        $res['user_women_count'] = 0;
        return $this->reqAndResponse->sendResponsePacket(200,$res, "查询成功");
    }

}