<?php
/**
* 微信企业号相关操作
*/
use Phalcon\Di;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger;




/**
 * 微信企业号相关操作
 * 
 * @author Arlon , Luoio
 *
 */
class WechatCorp {
    
    public static $sendurl = 'https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=%s';

    public static $corpID;  // 第三方企业ID
    public static $corpSecret;  // 第三方企业运营指南平台自建应用Secret
    public static $corpContactSecret;  // 第三方企业客户联系人Secret 
    public static $SuiteSecret;  // 咱们应用Secret
    public static $SuiteID;  // 咱们应用ID

    public static $_redis;
    public static $_log;
    public function __construct(){
        self::$_redis = DI::getDefault()->get('redisCache');
        self::$_log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
    }
    /**
     * 发送文本客服消息
     * @param $touser 用户openid
     * @param $content  消息正文
     * @return object Response
     */
    public static function sendMsg($touser, $msgType, $msg, $agentid = 1, $safe = 1) {

        $access_token = self::getAccessToken();
        if($access_token) {
            $sendurl = sprintf(self::$sendurl,$access_token);

            $post_data = array(
                'touser' => $touser['touser'] ? $touser['touser'] : '',
                'toparty' => $touser['toparty'] ? $touser['toparty'] : '',
                'totag' => $touser['totag'] ? $touser['totag'] : '',
                'agentid' => $agentid,
                'msgtype' => $msgType,
                $msgType => $msg,
                'safe' => $safe,
            );
            if(in_array($msgType ,array('voice','textcard','news'))) {
                unset($post_data['safe']);
            }
            $post_data = json_encode($post_data);
            $response =  self::post($sendurl,$post_data);
            return json_decode($response->body,true);
        }
        else{
            return array('errcode'=>-1,'msg'=>'corp access token error.');
        }
    }

    //创建群发任务
    public static function add_msg_template($data){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/add_msg_template?access_token='.$token;
        return self::post($url,json_encode($data));
    }

    //获取粉丝详情
    public static function get_user_detail($userid = false){
        if($userid == false){
            return null;
        }
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/get?access_token='.$token.'&userid='.$userid;
        return self::get($url);
    }
    public static function sync_staff($item) {
        $token = self::getAccessToken('contact');
        $userid = $item['wechatId'];
        if(empty($userid)) {
            return array('errcode'=>'-1','errmsg'=>'没有设置userid');
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/get?access_token='.$token.'&userid='.$userid;
        $response =  self::get($url);
        $ret  = json_decode($response->body,true);
        if($ret['errcode'] == 0 && !empty($ret['name'])) {
            $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/update?access_token='.$token;
            $sync_resp = self::post($url,json_encode(array(
                'userid' => $userid,
                'name' => $item['name'],
                'mobile' => $item['mobile'],
                'department' => $item['department'],
            )));
        }
        else{
            $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/create?access_token='.$token;
            $sync_resp = self::post($url,json_encode(array(
                'userid' => $userid,
                'name' => $item['name'],
                'mobile' => $item['mobile'],
                'department' => $item['department'],
            )));
        }
        $ret = json_decode($sync_resp->body,true);
        return $ret;
    }

    public static function delete_staff($userid) {
        $token = self::getAccessToken('contact');
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/delete?access_token='.$token.'&userid='.$userid;
        $response =  self::get($url);
        $ret  = json_decode($response->body,true);
        return $ret;
    }

    /**
     * 获取部门粉丝详情数据
     * @param $item
     * @return mixed
     */
    public static function get_party_users($partyid,$fetch_child = 0){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/list?access_token='.$token.'&department_id='.$partyid.'&fetch_child='.$fetch_child;
        return self::get($url);
    }
    /**

    * 添加外部联系人
    * @param $info
    * @return mixed
    */
    public static function add_contact_way(array $info){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/add_contact_way?access_token='.$token;
        return self::post($url,json_encode($info));
    }

    /**

    * 获取已经配置的外部联系人
    * @param $config_id
    * @return mixed
    */
    public static function get_contact_way(string $config_id){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/get_contact_way?access_token='.$token;
        return self::post($url,json_encode(array(
            'config_id' => $config_id
            )));
    }
    /**

    * 获取客户详情
    * @param $external_userid 外部联系人的userid，注意不是企业成员的帐号
    * @return mixed
    */
    public static function get_contact_users_list(string $external_userid){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/list?access_token='.$token.'&userid='.$external_userid;
        return self::get($url);
    }

    /**

    * 获取客户详情
    * @param $external_userid 外部联系人的userid，注意不是企业成员的帐号
    * @return mixed
    */
    public static function get_contact_users(string $external_userid){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/get?access_token='.$token.'&external_userid='.$external_userid;
        return self::get($url);
    }

    /**

    * 更新外部联系人
    * @param $info
    * @return mixed
    */
    public static function update_contact_way(array $info){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/update_contact_way?access_token='.$token;
        return self::post($url,json_encode($info));
    }

    /**

    * 删除外部联系人
    * @param $info
    * @return mixed
    */
     public static function del_contact_way(string $config_id){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/del_contact_way?access_token='.$token;
        return self::post($url,json_encode(array(
            'config_id' => $config_id
            )));
    } 

    /**
     * 获取部门数据
     * @param $item
     * @return mixed
     */
    public static function get_departments( $userid = NULL ){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token='.$token;
        if(!empty($id)) {
            $url .= '&id='.$id;
        }
        return self::get($url);
    }

    //获取应用
    public static function get_agent(string $agentid){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/agent/get?access_token='.$token.'&agentid='.$agentid;
        return self::get($url);
    }   
    //发送欢迎语
    public static function send_welcome_msg($welcome_code,$data){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/send_welcome_msg?access_token='.$token;
        $data['welcome_code'] = $welcome_code;
        return self::post($url,json_encode($data));
    }

     //获取客户群详情
    public static function get_groupchat($chat_id){
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/groupchat/get?access_token='.self::getAccessToken();
        return self::post($url,json_encode(array('chat_id'=>$chat_id)));
    }

    //创建标签组
    public static function add_corp_tag($data){
        $token = self::getContactAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/add_corp_tag?access_token='.$token;
        return self::post($url,json_encode($data));
    }
    //修改标签
    public static function edit_corp_tag($data){
        $token = self::getContactAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/edit_corp_tag?access_token='.$token;
        return self::post($url,json_encode($data));
    }

    //编辑用户标签 新增
    public static function mark_tag_add($userid,$external_userid,$add_tag){
        $token = self::getContactAccessToken();
        $data = array(
            'userid' => $userid,
            'external_userid' => $external_userid,
            'add_tag' => $add_tag,
        );
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/mark_tag?access_token='.$token;
        return self::post($url,json_encode($data));
    }

    //编辑用户标签 删除
    public static function mark_tag_remove($userid,$external_userid,$remove_tag){
        $token = self::getContactAccessToken();
        $data = array(
            'userid' => $userid,
            'external_userid' => $external_userid,
            'remove_tag' => $remove_tag,
        );
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/mark_tag?access_token='.$token;
        return self::post($url,json_encode($data));
    }

    //删除标签
    public static function del_corp_tag($data){
        $token = self::getContactAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/del_corp_tag?access_token='.$token;
        return self::post($url,json_encode($data));
    }

    //获取标签组
    public static function get_corp_tag_list($tagids=false){
        $token = self::getContactAccessToken();
        $request = array();
        if($tagid != false){
            $request['tag_id'] = $tagids;
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/externalcontact/get_corp_tag_list?access_token='.$token;
        return self::post($url,json_encode($request));
    }

    /**
     * 新增临时素材
     */
    public static function uploadMedia($filepath,$type='image',$access_token = '') { //分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/media/upload?access_token=%s&type=%s';
        $upload_url = sprintf($url,$access_token,$type);
        return self::post($upload_url,array('media' => new CURLFile($filepath)));
    }
    /**
     * 创建部门
     * @param $item
     * @return mixed
     */
    public static function create_department( $item ){
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/department/create?access_token='.$token;
        $sync_resp = self::post($url,json_encode(array(
            'name' => $item['name'],
            'parentid' => $item['third_pid'],
            'order' => $item['sort'],
        )));
        $ret = json_decode($sync_resp->body,true);
        return $ret;
    }
    //
    public static function delete_department($id) {
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/department/delete?access_token='.$token.'&id='.$id;
        $response =  self::get($url);
        $ret  = json_decode($response->body,true);
        return $ret;
    }

    /**
     * 网页授权登录，通过code获取userid
     * @param $code
     * @return mixed
     */
    public static function getUserIdByCode($code) {
        $token = self::getAccessToken();
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?access_token='.$token.'&code='.$code;
        $response =  self::get($url);
        $ret  = json_decode($response->body,true);
        return $ret;
    }





    public static function getPreAuthCode(){
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/service/get_pre_auth_code?suite_access_token='.self::getSuiteAccessToken();
        $ret =  self::get($url);
        if( $ret['pre_auth_code'] ){
            return $ret['pre_auth_code'];
        }
        return null;
    }

    public static function set_session_info(){

    }

   

    public static function getPermanentCode($auth_code){
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/service/get_permanent_code?suite_access_token='.self::getSuiteAccessToken();
        return self::post($url,json_encode(array('auth_code'=>$auth_code)));
    }

    public static function getContactAccessToken($force=false){
        //未设置第三方企业id 直接返回null
        if( empty(self::$corpID) ) {
            return null;
        }
        $corpId = self::$corpID;
        $corpSecret = self::$corpContactSecret;

        $cacheKey = 'corp_contact_access_token_'.$corpId;
        $redisService = DI::getDefault()->get('redisCache');
        $token = $redisService->get($cacheKey);
        if(empty($token) || $force) {
            $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid='.$corpId.'&corpsecret='.$corpSecret;
            do{
                $ret =  self::get($url);
                $log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
                $log->debug("CorpContactAccessToken Exception:".json_encode($ret));
                if(!empty($ret['access_token'])){
                    $token = $ret['access_token'];
                    $redisService->set($cacheKey,$token,6600);
                    return $token;
                }
                $i++;
            }while($i < 3);
        }
        return $token;
    }

    public static function getAccessToken($force=false){
        //未设置第三方企业id 直接返回null
        if( empty(self::$corpID) ) {
            return null;
        }
        $corpId = self::$corpID;
        $corpSecret = self::$corpSecret;

        $cacheKey = 'corp_access_token_'.$corpId;
        $redisService = DI::getDefault()->get('redisCache');
        $token = $redisService->get($cacheKey);
        if(empty($token) || $force) {
            $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid='.$corpId.'&corpsecret='.$corpSecret;
            do{
                $ret =  self::get($url);
                $log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
                $log->debug("CorpAccessToken Exception:".json_encode($ret));
                if(!empty($ret['access_token'])){
                    $token = $ret['access_token'];
                    $redisService->set($cacheKey,$token,6600);
                    return $token;
                }
                $i++;
            }while($i < 3);
        }
        return $token;
    }
    /**
     * 获取第三方企业access_token
     * @param $corpId
     * @return mixed
     */
    public static function getCorpAccessToken($force=false){
        //未设置第三方企业id 直接返回null
        if( empty(self::$corpID) ) {
            return null;
        }
        $corpId = self::$corpID;
        $cacheKey = 'corp_access_token_'.$corpId;
        $redisService = DI::getDefault()->get('redisCache');
        $token = $redisService->get($cacheKey);
        if(empty($token) || $force) {
            $url = 'https://qyapi.weixin.qq.com/cgi-bin/service/get_corp_token?suite_access_token='.self::getSuiteAccessToken();


            $info = CorpCompanies::findFirst(array(
                'conditions' => array('corpid' => $corpId),
            ));
            $permanent_code = $info->permanent_code;
            $i = 0;
            do{
                $ret =  self::post($url,json_encode(array('auth_corpid'=>$corpId,'permanent_code'=>$permanent_code)));
                $log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
                $log->debug("CorpAccessToken Exception:".json_encode($ret));
                if(!empty($ret['access_token'])){
                    $token = $ret['access_token'];
                    $redisService->set($cacheKey,$token,6600);
                    return $token;
                }
                $i++;
            }while($i < 3);
            
        }
        return $token;
    }


    /**
     * 获取应用token
     * @return mixed
     */
    public static function getSuiteAccessToken($force = false){
        $cacheKey = 'suite_access_token';
        $redisService = DI::getDefault()->get('redisCache');
        $token = $redisService->get($cacheKey);
        if(empty($token) || $force) {
            $ticketKey = 'qywx_suite_ticket_'.self::$SuiteID;
            $suite_ticket = $redisService->get($ticketKey);
            //ticket 过期 重新至mysql 获取
            if(empty($suite_ticket)){
                $suite_ticket = Dbcache::read($ticketKey);;
            }
            //开启事务
            $manager =  Di::getDefault()->get('transactions');
            $transaction = $manager->get();
            try{
                $dbcache_item = Dbcache::findFirst(array(
                    'conditions' => 'ckey = :ckey:',
                    'bind' => array('ckey'=> $cacheKey),
                    'order' => 'id desc',
                    'for_update' => true
                ));
                if($dbcache_item !== false){
                    if((strtotime($dbcache_item->updated) + 6580) > time() && $force == false){
                        $oldTime = time() - strtotime($dbcache_item->updated);
                        $oldTime = 6580 - $oldTime;
                        $redisService->set($cacheKey,$dbcache_item->cval,$oldTime);
                        $transaction->commit();
                        return $dbcache_item->cval;
                    }
                }
                $url = 'https://qyapi.weixin.qq.com/cgi-bin/service/get_suite_token';
                $data = array(
                    "suite_id"=> self::$SuiteID ,
                    "suite_secret"=> self::$SuiteSecret,
                    "suite_ticket"=> $suite_ticket
                );
                $post_data = json_encode($data);
                $i = 0;
                do{
                    $i++;
                    $ret = self::post($url,$post_data);
                    if( isset($ret['suite_access_token']) ){
                        $token = $ret['suite_access_token'];
                        $redisService->set($cacheKey,$token,6600);
                        if( empty($dbcache_item) ) {
                            $dbcache_item = new Dbcache();
                            $dbcache_item->created = date('Y-m-d H:i:s');
                        }
                        $dbcache_item->setTransaction($transaction);
                        $dbcache_item->json = 0;
                        $dbcache_item->ckey = $cacheKey;
                        $dbcache_item->cval = $token;
                        $dbcache_item->updated = date('Y-m-d H:i:s');
                        $dbcache_item->save();
                        $transaction->commit();
                        return $token;
                    }
                }while($i < 3);
                $transaction->rollback("Get Suite access token error.".$ret['errcode'].' '.$ret['errmsg']);
            }catch(Exception $e){
                $log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
                $log->debug("SuiteAccessToken Exception:".$e->getMessage());
            }
        }
        return $token;
    }



    public static function post($url,$data) {
        try {
            $curl = Di::getDefault()->get('curl');
            $curl->setOptions(array(
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_DNS_CACHE_TIMEOUT => 1,
                CURLOPT_TIMEOUT => 5,
            ));
            $response = $curl->post($url,$data);
            $ret = \json_decode($response->body,true);
            if( is_array($ret) && $ret['errcode'] != 0 ) {
                DI::getDefault()->get('logger')->error("WechatService post error $url.".$ret['errcode'].' '.$ret['errmsg']);
                if($ret['errcode'] == '48001') {
                    $ret['errmsg'] = '微信api接口没有权限，请检查公众号是否认证，以及授权时勾选的权限。';
                }
            }
            return $ret; 
        } catch (Exception $e) {
            return array('errcode'=>404,'errmsg'=>$e->getMessage());
        }
    }
    

    public static function get($url) {
        try {
            $response = Di::getDefault()->get('curl')->get($url);
            $ret = \json_decode($response->body,true);
            if( is_array($ret) && $ret['errcode'] != 0 ) {
                if($ret['errcode'] == '48001') {
                    $ret['errmsg'] = '微信api接口没有权限，请检查公众号是否认证，以及授权时勾选的权限。';
                }
            }
            return $ret; 
        } catch (Exception $e) {
            return array('errcode'=>404,'errmsg'=>$e->getMessage());
        }
    }
}
