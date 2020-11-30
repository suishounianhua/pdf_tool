<?php

use Phalcon\Di;

if( !defined('COMPONENT_APPID') ){
    define('COMPONENT_APPID', Di::getDefault()->get('config')->Weixin->ComponentAppID);
    define('COMPONENT_APPSECRET', Di::getDefault()->get('config')->Weixin->ComponentAppSecret);
    define('COMPONENT_TOKEN', Di::getDefault()->get('config')->Weixin->ComponentToken);
    define('COMPONENT_AESKEY', Di::getDefault()->get('config')->Weixin->ComponentAesKey);
}
/**
 * 微信相关操作
 * 扩展的Utility类名后面都加上Utility，防止类名与Model等其它类重名
 * @author Arlon
 *
 */
class WechatService {

    public static $appId = '';
    public static $secretKey = '';
    public static $source = 'wechatComp'; //默认为公众号，小程序是额外指定wechatLite

    public static $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=%s';

    public static $menu_url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=%s';

    public static $user_url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token=%s&next_openid=%s';

    public static $userinfo_url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN';

    public static $upload_url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token=%s&type=%s';

    public static function datacube($type,$begin_date,$end_date){
        $access_token = self::getAccessToken();
        if(empty($access_token))
        {
            return false;
        }
        $url = 'https://api.weixin.qq.com/datacube/'.$type.'?access_token='.$access_token;
        $post_data = '{"begin_date":"'.$begin_date.'","end_date":"'.$end_date.'"}';
        return self::post($url,$post_data);
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

        /**
     * 发送文本客服消息
     * @param $touser 用户openid
     * @param $content  消息正文
     * @return object Response
     */
    public static function sendTextMsg($touser, $content,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"text","text":{"content":"'.addslashes($content).'"}}';
        return self::post($url,$post_data);
    }

    /**
     * 发送图片客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendImageMsg($touser, $media_id,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"image","image":{"media_id":"'.$media_id.'"}}';
        return self::post($url,$post_data);
    }

    /**
     * 发送微信多图文素材客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendMpnewsMsg($touser, $media_id,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"mpnews","mpnews":{"media_id":"'.$media_id.'"}}';
        return self::post($url,$post_data);
    }
    /**
     * 发送卡券客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendCardMsg($touser, $card_id,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"wxcard","wxcard":{"card_id":"'.$card_id.'"}}';
        return self::post($url,$post_data);
    }
    /**
     * 发送语音客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendVoiceMsg($touser, $media_id,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"voice","voice":{"media_id":"'.$media_id.'"}}';
        return self::post($url,$post_data);
    }
    /**
     * 发送视频客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendVideoMsg($touser, $media_id,$thumb_media_id,$title,$description,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"video","video":{"media_id":"'.$media_id.'","thumb_media_id":"'.$thumb_media_id.'","title":"'.$title.'","description":"'.$description.'"}}';
        return self::post($url,$post_data);
    }
    /**
     * 发送视频客服消息
     * @param $touser 用户openid
     * @param $media_id  图片资源编号
     * @return object Response
     */
    public static function sendMusicMsg($touser, $media_id,$url,$title,$description,$access_token = '') {

        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }

        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"music","music":{"thumb_media_id":"'.$media_id.'","url":"'.$url.'","title":"'.$title.'","description":"'.$description.'"}}';
        return self::post($url,$post_data);
    }

    /**
     * 发送图文客服消息
     * @param $touser 用户openid
     * @param $articles  array 多维数组 { "title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"  },
     * @return object Response
     */
    public static function sendArticleMsg($touser, $articles,$access_token = '') {
        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }
        $url = sprintf(self::$url,$access_token);
        $post_data = '{"touser":"'.$touser.'","msgtype":"news","news":{"articles":'.json_encode($articles,JSON_UNESCAPED_UNICODE).'}}';
        return self::post($url,$post_data);
    }

    /**
     * 发送小程序卡片
     */
    public static function sendMiniprogrampageMsg($touser, $miniprogrampage,$access_token = '') {
        if(empty($access_token)){
            $access_token = self::getAccessToken(false,true);
        }
        $url = sprintf(self::$url,$access_token);
        $post_data = array(
            'touser' => $touser,
            'msgtype' => 'miniprogrampage',
            'miniprogrampage' => $miniprogrampage
        );
        $post_data = json_encode($post_data,JSON_UNESCAPED_UNICODE);
        return self::post($url,$post_data);
    }

    /**
     * 发送公众号模板消息
     * @param $touser
     * @param $tplid
     * @param $click_url
     * @param $datas
     * @return mixed
     */
    public static function sendTplMsg($touser,$tplid,$click_url,$datas) {
        if( $touser && $tplid ) {
            $access_token = self::getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;

            $posts = array(
                'touser' => $touser,
                'template_id' => $tplid,
                'url' => $click_url,
                'data' => $datas,
            );

            $json_data =  json_encode($posts,JSON_UNESCAPED_UNICODE);

            $ret =  self::post($url,$json_data);
            DI::getDefault()->get('logger')->log('send wechat tpl shortmessage.'.json_encode($ret));
            return $ret;
        }
        else{
            return array();
        }
    }

    public static function sendTplMiniprogramMsg($touser,$tplid,$appid,$pagepath,$datas) {
        if( $touser && $tplid ) {
            $access_token = self::getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;

            $posts = array(
                'touser' => $touser,
                'template_id' => $tplid,
                'miniprogram' => array(
                    'appid' => $appid,
                    'pagepath' => $pagepath,
                    ),
                'data' => $datas,
            );

            $json_data =  json_encode($posts,JSON_UNESCAPED_UNICODE);

            $ret =  self::post($url,$json_data);
            DI::getDefault()->get('logger')->log('send wechat tpl shortmessage.'.json_encode($ret));
            return $ret;
        }
        else{
            return array();
        }
    }

    /**
     * 发送小程序模板消息
     * @param $touser
     * @param $tplid
     * @param $click_url
     * @param $datas
     * @return mixed
     */
    public static function sendLiteTplMsg($touser,$tplid,$form_id,$page,$datas) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;

        $posts = array(
            'touser' => $touser,
            'template_id' => $tplid,
            'form_id' => $form_id,
            'page' => $page,
            'data' => $datas,
        );

        $json_data =  json_encode($posts,JSON_UNESCAPED_UNICODE);

        $result =  self::post($url,$json_data);
        if($result['errcode'] > 0) {
            DI::getDefault()->get('logger')->log('lite tpl msg result:'.var_export($posts,true).var_export($result,true));
        }
        return $result;
    }


    /**
     * 设置公众号的菜单，
     * @param unknown $menus wx_menus表内容的数组
     */
    public static function createMenu($menus,$dev = false){
        $json_arr = array();
        foreach($menus as $item){
            if(!empty($item['children'])){
                $sub_menu = array('name'=>$item['WxMenu']['name']);
                $sub_menu['sub_button'] = array();
                foreach($item['children'] as $sub_item){

                    $sub_item['WxMenu']['link'] = str_replace('&amp;','&',$sub_item['WxMenu']['link']);

                    if($sub_item['WxMenu']['type']=='click'){
                        $sub_menu['sub_button'][] = array('key'=>$sub_item['WxMenu']['slug'], 'type'=>$sub_item['WxMenu']['type'],'name'=>$sub_item['WxMenu']['name']);
                    }
                    elseif(in_array($sub_item['WxMenu']['type'],array('media_id','view_limited'))){
                        $sub_menu['sub_button'][] = array('media_id'=>$sub_item['WxMenu']['media_id'], 'type'=>$sub_item['WxMenu']['type'],'name'=>$sub_item['WxMenu']['name']);
                    }
                    else{
                        $sub_menu['sub_button'][] = array('url'=>$sub_item['WxMenu']['link'], 'type'=>$sub_item['WxMenu']['type'],'name'=>$sub_item['WxMenu']['name']);
                    }
                }
                $json_arr['button'][] = $sub_menu;
            }
            else{
                $item['WxMenu']['link'] = str_replace('&amp;','&',$item['WxMenu']['link']);

                if($item['WxMenu']['type']=='click'){
                    $json_arr['button'][] = array('key'=>$item['WxMenu']['slug'], 'type'=>$item['WxMenu']['type'],'name'=>$item['WxMenu']['name']);
                }
                elseif(in_array($item['WxMenu']['type'],array('media_id','view_limited'))){
                    $json_arr['button'][] = array('media_id'=>$item['WxMenu']['media_id'], 'type'=>$item['WxMenu']['type'],'name'=>$item['WxMenu']['name']);
                }
                else{
                    $json_arr['button'][] = array('url'=>$item['WxMenu']['link'], 'type'=>$item['WxMenu']['type'],'name'=>$item['WxMenu']['name']);
                }
            }
        }
        $json_menu =  json_encode($json_arr,JSON_UNESCAPED_UNICODE);


        return self::createMenuJson($json_menu,$dev);
    }
    public static function createMenuNew($data){
        $json_menu =  json_encode($data,JSON_UNESCAPED_UNICODE);
        return self::createMenuJson($json_menu,$dev);
    }

    public static function createMenuJson($json_menu,$dev = false) {

        $access_token = self::getAccessToken(false,$dev);
        $menu_url = sprintf(self::$menu_url,$access_token);
        return self::post($menu_url,$json_menu);
    }

    public static function deleteMenu(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token='.$access_token;
        return self::get($url);
    }

    public static function getAutoReplyInfo(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/get_current_autoreply_info?access_token='.$access_token;
        return self::get($url);
    }

    public static function getCurrentMenu(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info?access_token='.$access_token;
        return self::get($url);
    }

    public static function resetMenu($verify = true){
        $menu = self::getCurrentMenu();
        //if($menu['is_menu_open']) {

        if(!$verify) { // 未认证的公众号
            //print_r($menu);
            foreach($menu['selfmenu_info']['button'] as &$me) {
                if(!empty($me['sub_button'])) {
                    foreach($me['sub_button']['list'] as &$item) {
                        if($item['type'] == 'view') {
                            $item['type'] = 'view_limited';
                            $item['media_id'] = '';
                            unset($item['url']);
                        }
                        elseif($item['type'] == 'click') {
                            $item['type'] = 'media_id';
                            $item['media_id'] = '';
                            unset($item['url']);
                        }
                        $me['sub_button'][] = $item;
                    }
                    unset($me['sub_button']['list']);
                }
            }
            $json = json_encode($menu['selfmenu_info'],JSON_UNESCAPED_UNICODE);
        }
        else{
            foreach($menu['selfmenu_info']['button'] as &$me) {
                if(!empty($me['sub_button'])) {
                    $me['sub_button'] = $me['sub_button']['list'];
                }
            }
            $json = json_encode($menu['selfmenu_info'],JSON_UNESCAPED_UNICODE);
        }
        return self::createMenuJson($json);
//     	 }
//     	 else{
//     	 	return 0;
//     	 }
    }
    /**二维码类型，QR_SCENE为临时整型参数值,QR_LIMIT_SCENE为永久的整型参数值; QR_STR_SCENE为临时的字符串参数值，QR_LIMIT_STR_SCENE为永久的字符串参数值**/
    public static function qrcode_create($scene_id,$action='QR_SCENE'){

        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$access_token;

        if($action=='QR_SCENE') {
            $posts = array(
                'expire_seconds'=> 2592000,
                "action_name"=> $action,
                "action_info" => array("scene" => array('scene_id' => $scene_id )),
            );
        }
        elseif($action=='QR_STR_SCENE') {
            $posts = array(
                'expire_seconds'=> 2592000,
                "action_name"=> $action,
                "action_info" => array("scene" => array('scene_str' => $scene_id )),
            );
        }
        else{
            if($action == 'QR_LIMIT_SCENE'){
                $posts = array(
                    "action_name"=> 'QR_LIMIT_SCENE',// "QR_LIMIT_SCENE", "QR_LIMIT_STR_SCENE",
                    "action_info" => array("scene" => array('scene_id' => $scene_id )),
                );
            }
            else{
                $posts = array(
                    "action_name"=> 'QR_LIMIT_STR_SCENE',// "QR_LIMIT_SCENE", "QR_LIMIT_STR_SCENE",
                    "action_info" => array("scene" => array('scene_str' => $scene_id )),
                );
            }
        }

        $json_data =  json_encode($posts,JSON_UNESCAPED_UNICODE);

        return self::post($url,$json_data);
    }

    /**
     *
     * @param unknown $paramArr see at http://pay.weixin.qq.com/wiki/doc/api/index.php?chapter=9_1
     * @return unknown
     */
    public static function wxPrepay($paramArr,$secret) {

        $prepay_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

        $paramArr['sign'] = $sign = self::getSignature($paramArr,$secret);
        $xml = array_to_xml($paramArr);
        $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>','',$xml);

        return self::post($prepay_url,$xml);
    }


    public static function getWechatPayUrl($appid,$secret,$mch_id,$product_id,$nonce_str=''){
        if(empty($nonce_str)) $nonce_str = strtolower(random_str(16));
        $arr = array(
            'appid' => $appid,
            'mch_id' => $mch_id,
            'nonce_str' => $nonce_str,
            'product_id' => $product_id,
            'time_stamp' => time(),
        );
        $sign = self::getSignature($arr,$secret);

        $url = 'weixin://wxpay/bizpayurl?'.http_build_query( $arr ).'&sign='.$sign;
        //echo "=====$appid,$secret====$url";exit;
        return $url;
    }

    public static function getSignature($tmpArr=array(),$secKey = '')
    {
        ksort($tmpArr);
        $tmpStr = http_build_query( $tmpArr );
        $tmpStr = str_replace('%2F','/',$tmpStr);
        $tmpStr = str_replace('%25','%',$tmpStr);

        $signStr = $tmpStr.'&key='.$secKey;
        //echo "$signStr\n<br/>";
        return strtoupper(md5($signStr));
    }

    /**
     * 增加永久图文
     * @param unknown $articles
     */
    public static function add_news($articles){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $i = 0;
        do{
            $add_url = 'https://api.weixin.qq.com/cgi-bin/material/add_news?access_token='.$access_token;
            $result =  self::post($add_url,json_encode(array('articles'=>$articles),JSON_UNESCAPED_UNICODE));
            $i ++;
        }while( empty($result) && $i < 3 );

        return $result;
    }

    /**
     * 编辑永久图文
     * @param unknown $articles
     */
    public static function update_news($media_id,$index,$article){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        if(empty($index)) $index = 0;
        $update_url = 'https://api.weixin.qq.com/cgi-bin/material/update_news?access_token='.$access_token;
        return self::post($update_url,json_encode(array('media_id' =>$media_id, 'index' => $index,'articles'=>$article),JSON_UNESCAPED_UNICODE));
    }


    /**
     * 查询群发消息发送状态【订阅号与服务号认证后均可用】
     * @param $msg_id
     * @return array|mixed
     */
    public function querySend($msg_id){
        $access_token = self::getAccessToken();
        if($access_token){
            $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/get?access_token='.$access_token;
            $posts = array(
                "msg_id" => $msg_id,
            );
            return self::post($url,json_encode($posts,JSON_UNESCAPED_UNICODE));
        }
        else{
            return array('errcode'=> 13501,'errmsg' => 'get access token error');
        }
    }
    /**
     * 群发接口
     * @param $media_id
     * @param string $type
     * @param int $send_ignore_reprint
     * @param string $groupid
     * @return array|mixed
     */
    public static function sendAll($media_id,$type='mpnews',$send_ignore_reprint = 1,$groupid= ''){
        $access_token = self::getAccessToken();
        if($access_token){
            $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token='.$access_token;
            if($type=='news') $type = 'mpnews';
            $posts = array(
                "filter" => array(
                    "is_to_all" => !$groupid ? true: false,
                    "group_id" => $groupid
                ),
                $type => array( "media_id" => $media_id),
                "msgtype" => $type,
                "send_ignore_reprint" => $send_ignore_reprint,
            );
            $posts['clientmsgid'] = guid_string($posts); //开发者侧群发msgid，避免重复群发
            return self::post($url,json_encode($posts,JSON_UNESCAPED_UNICODE));
        }
        else{
            return array('errcode'=> 13501,'errmsg' => 'get access token error');
        }
    }


    /**
     * 获取群发速度
     * @return array|mixed
     */
    public static function getSpeed(){
        $access_token = self::getAccessToken();
        if($access_token){
            $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/speed/get?access_token='.$access_token;
            return self::get($url);
        }
        else{
            return array('errcode'=> 13501,'errmsg' => 'get access token error');
        }
    }

    /**
     * 设置群发速度
     * @param $speed  0	80w/分钟;  1	60w/分钟;  2	    45w/分钟;  3	    30w/分钟;  4	    10w/分钟
     * @return array|mixed
     */
    public static function setSpeed($speed){
        $access_token = self::getAccessToken();
        if($access_token){
            $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/speed/set?access_token='.$access_token;
            $posts = array(
                "speed" => $speed,
            );
            return self::post($url,json_encode($posts,JSON_UNESCAPED_UNICODE));
        }
        else{
            return array('errcode'=> 13501,'errmsg' => 'get access token error');
        }
    }

    //添加模板消息
    public static function addTemplate($short){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.$access_token;
        $posts = array(
                "template_id_short" => $short
            );
        return self::post($url,json_encode($posts));
    }

    //查询模板列表
    public static function getAllTemplate(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token='.$access_token;
        return self::get($url);
    }

    //删除模板消息
    public static function delTemplate($template_id){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token='.$access_token;
        $posts = array(
                "template_id" => $template_id
            );
        return self::post($url,json_encode($posts));
    }
    //设置行业信息
    public static function setIndustry($industry_id1,$industry_id2){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/template/api_set_industry?access_token='.$access_token;
        $posts = array(
                "industry_id1" => $industry_id1,
                "industry_id2" => $industry_id2,
            );
        return self::post($url,json_encode($posts));
    }
    //获取公众号标签
    public static function getIndustry(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token='.$access_token;
        return self::get($url);
    }
    //重写appid
    public static function setAppid($appid){
        self::$appId = $appid;
    }
    //获取公众号标签
    public static function getTags(){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/get?access_token='.$access_token;
        return self::get($url);
    }

    //编辑公众号标签
    public static function update_tag($tag_id,$tag_name){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/update?access_token='.$access_token;
        $posts = array(
            'tag' => array(
                'id' => $tag_id,
                'name' => $tag_name
                )
            );
        $posts = '{"tag":{"id":"'.$tag_id.'","name":"'.$tag_name.'"}}';
        return self::post($url,$posts);
    }

    //创建标签
    public static function create_tag($tag_name){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/create?access_token='.$access_token;
        // $posts = array(
        //     'tag' => array(
        //         'name' => $tag_name
        //         )
        //     );
        $posts = '{"tag":{"name":"'.$tag_name.'"}}';
        return self::post($url,$posts);
    }

    //删除公众号标签
    public static function delete_tag($tag_id){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/delete?access_token='.$access_token;
        $posts = array(
            'tag' => array(
                'id' => $tag_id
                )
            );
        return self::post($url,json_encode($posts));
    }
    
    /**
     * 给用户打标签
     * @param unknown $openid_arr 用户openid列表
     * @param unknown $tag_id 标签id
     */
    public static function setTagsForUsers($openid_arr,$tag_id){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?access_token='.$access_token;
        $posts = array(
                "openid_list" => $openid_arr,
                "tagid" => $tag_id,
            );
        return self::post($url,json_encode($posts));
    }

    /**
     * 给用户取消标签
     * @param unknown $openid_arr 用户openid列表
     * @param unknown $tag_id 标签id
     */
    public static function delTagsForUsers($openid_arr,$tag_id){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/members/batchuntagging?access_token='.$access_token;
        $posts = array(
                "openid_list" => $openid_arr,
                "tagid" => $tag_id,
            );
        return self::post($url,json_encode($posts));
    }

    /**
     * 消息预览
     * @param unknown $media_id
     * @param unknown $touser
     * @param string $msgtype
     */
    public static function preview($media_id,$touser,$msgtype='mpnews'){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token='.$access_token;
        return self::post($url,json_encode(array( "touser"=>$touser, $msgtype=>array('media_id'=>$media_id), "msgtype"=>$msgtype )));
    }
    /**
     * 获取素材总数
     */
    public static function get_materialcount(){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token='.$access_token;
        return self::get($url);
    }

    public static function getTempMedia($media_id){
        $access_token = self::getAccessToken();
        if(!empty($access_token)){
            $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$access_token.'&media_id='.$media_id;
            return self::get($url);
        }
        else{
            return null;
        }
    }

    public static function getTmpMaterial($media_id) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$access_token.'&media_id='.$media_id;
        return self::get($url);
    }

    public static function getMaterial($media_id) {
        $access_token = self::getAccessToken();
        $batch_url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.$access_token;
        return self::post($batch_url,json_encode(array('media_id'=>$media_id)));
    }

    /**
     *新增视频永久素材
     */
    public static function add_video($mediafile,$title,$introduction){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $batch_url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token;
        return self::post($batch_url,array('media'=> '@'.$mediafile,'type'=>'video','description'=>json_encode(array('title'=>$title,'introduction'=>$introduction))));
    }


    /**
     *新增其他类型永久素材
     */
    public static function add_material($mediafile,$type='image'){


        $src_media = $mediafile;
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $is_remote = false; $localfile = $cacke_key = '';
        if(strpos($mediafile,'http://') !==false || strpos($mediafile,'https://') !==false) {

            $cacke_key = 'media_id_'.self::$appId.'_'.$type.'_'.md5($mediafile); //对远程的网址内容才缓存media_id
            $media_id = DI::getDefault()->get('redisCache')->get($cacke_key);
            if( $media_id !== false ){
                return array('media_id' => $media_id);
            }

            App::uses('CrawlUtility', 'Utility');
            $localfile = CrawlUtility::saveImagesByUrl($mediafile,'',array('oss'=>false,'resize'=>false));
            if( !$localfile ) {
                return array('errcode' => -1,'errmsg' => '封面图片下载错误，图片不要太大，使用可用的图片外链');
            }
            $is_remote = true;
            $mediafile = UPLOAD_FILE_PATH.$localfile;
            $mediafile = str_replace('//','/',$mediafile);
        }
        else{
            if(!file_exists($mediafile)) {
                return array('errcode' => -1,'errmsg' => '封面图片网址错误');
            }
        }

        $i = 0;
        do{
            $batch_url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token.'&type='.$type;
            $ret =  self::post($batch_url,array('media'=> '@'.$mediafile,'type'=> $type ));

            if($ret['media_id']) {
                if( $is_remote ){ //为远程文件时，删除下载的临时文件
                    @unlink($mediafile);
                }
                if($cacke_key) {
                    DI::getDefault()->get('redisCache')->set($cacke_key, $ret['media_id']);
                }
                return $ret;
            }
            elseif($ret['errcode']){ //有错误码时，直接返回错误内容。 其它未知问题及网络原因时，重试。
                if( $is_remote ){ //为远程文件时，删除下载的临时文件
                    @unlink($mediafile);
                }
                return $ret;
            }
            else{
                DI::getDefault()->get('logger')->error( "wechat add_material $mediafile try $i error. $src_media: ".var_export($ret,true) );
            }
            $i++;
        }
        while( empty($ret['media_id'])  && !isset($ret['errcode']) && $i < 2); // 若上传失败，最多重试2次

        return $ret;
    }

    public static function del_material($media_id) {
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/material/del_material?access_token='.$access_token;
        $params = json_encode( array('media_id'=>$media_id) );
        return self::post($url,$params);
    }

    public static function getImageMediaUrl($media_id){
        $result = self::batchget_material('image');
        foreach($result['item'] as $image){
            if($image['media_id'] == $media_id) {
                return $image['url'];
            }
        }
    }
    /**
     * 批量获取多媒体素材
     * @param string $type 素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param number $page 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param number $limit 返回素材的数量，取值在1到20之间
     */
    public static function batchget_material($type = 'news',$page=1,$limit = 20){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.$access_token;
        $params = json_encode(array('type'=>$type,'offset'=>($page-1)*$limit,'count'=>$limit));
        return self::post($url,$params);
    }

    /**
     * 上传图文消息内的图片获取URL
     * 本接口所上传的图片不占用公众号的素材库中图片数量的5000个的限制。
     * 图片仅支持jpg/png格式，大小必须在1MB以下。
     */
    public static function uploadImg($filepath,$access_token = '') {
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }

        if(empty($access_token)){
            return array('errcode'=>111,'errmsg'=>'empty access token. The appid:'.self::$appId);
        }
        else{
            $upload_url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$access_token;
            $i = 0;
            do{
                if( $i > 0 ) usleep(100);
                $ret =  self::post($upload_url,array('media' => new CURLFile($filepath)));
                //{"url":  "xxx"}
                $i++;
            }
            while( empty($ret['url']) && !isset($ret['errcode']) && $i < 2 );

            if( $ret['url'] ) {
                $ret['url'] = str_replace('http://mmbiz.qpic.cn','https://mmbiz.qlogo.cn',$ret['url']);
            }
            else{
                DI::getDefault()->get('logger')->error( 'wechat uploadimg error,'.$filepath.': '.var_export($ret,true));
            }
            return $ret;
        }
    }


    /**
     * 新增临时素材
     */
    public static function uploadMedia($filepath,$type='image',$access_token = '') { //分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $upload_url = sprintf(self::$upload_url,$access_token,$type);
        return self::post($upload_url,array('media' => new CURLFile($filepath)));
    }

    /**
    * 公众号数据统计
    * @param begin_date 启始时间
    * @param end_date 截止时间
    */

    public static function getusersummary($begin_date,$end_date,$access_token = ''){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://api.weixin.qq.com/datacube/getusersummary?access_token='.$access_token;
        $params = json_encode(array('begin_date'=>$begin_date,'end_date'=>$end_date));
        return self::post($url,$params);
    }

    /**
    * 公众号数据统计
    * @param begin_date 启始时间
    * @param end_date 截止时间
    */

    public static function getusercumulate($begin_date,$end_date,$access_token = ''){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $url = 'https://api.weixin.qq.com/datacube/getusercumulate?access_token='.$access_token;
        $params = json_encode(array('begin_date'=>$begin_date,'end_date'=>$end_date));
        return self::post($url,$params);
    }

    /**
     * 获取公众号的给定用户信息
     * @param string $openid
     */
    public static function getUserInfo($openid = '',$access_token = ''){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $userinfo_url = sprintf(self::$userinfo_url,$access_token,$openid);
        return self::get($userinfo_url);
    }
    /**
     * 批量获取公众号的用户信息
     * @param string $openid
     */
    public static function getBatUserInfo($openids = array(),$access_token = ''){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }

        $userinfo_url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token='.$access_token;
        $user_list = array();
        foreach($openids as $oid) {
            $user_list[] = array('openid' => $oid);
        }
        return self::post($userinfo_url,json_encode(array('user_list'=>$user_list)));
    }


    public static function getJsApiTicket(){

        $cacheKey = 'wx_js_ticket_'.self::$appId;
        $data = DI::getDefault()->get('redisCache')->get($cacheKey);

        if ( !is_object($data) || is_object($data) && $data->expire_time < time()) {
            $access_token = self::getAccessToken();
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$access_token";
            $res =  self::get($url);

            if( empty($res) || empty($res['ticket']) ) {
                return false;
            }
            else{
                $ticket = $res['ticket'];
                if ($ticket) {
                    $data = new stdClass();
                    $data->expire_time = time() + 7000;
                    $data->jsapi_ticket = $ticket;
                    DI::getDefault()->get('redisCache')->set($cacheKey, $data,900);
                }
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }

        return $ticket;
    }

    /**
     * 获取公众号的粉丝openid列表
     * @param string $next_openid
     */
    public static function getUserList($next_openid = '',$access_token = ''){
        if(empty($access_token)){
            $access_token = self::getAccessToken();
        }
        $user_url = sprintf(self::$user_url,$access_token,$next_openid);
        return self::get($user_url);
    }

    /******************************************************************************/
    /**
     * 绑定小程序测试人员
     * @param $wechatid
     * @return mixed
     */
    public static function lite_bind_tester($wechatid) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/bind_tester?access_token='.$access_token;

        return self::post($url,json_encode(array('wechatid'=>$wechatid)));
    }
    /**
     * 取消绑定小程序测试人员
     * @param $wechatid
     * @return mixed
     */
    public static function lite_unbind_tester($wechatid) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/unbind_tester?access_token='.$access_token;

        return self::post($url,json_encode(array('wechatid'=>$wechatid)));
    }


    /**
     * 小程序修改域名
     * @param string $action
     * @param array $domains
     * @return mixed
     */
    public static function lite_modify_domain($action='set',$domains = array()){
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/modify_domain?access_token='.$access_token;
        if(is_array($domains)) {
            $post_data = array_merge(array('action'=>$action),$domains);
        }
        return self::post($url,json_encode($post_data));
    }

    /**
     * 小程序上传代码
     * @param $item_list
     * @return mixed
     */
    public static function lite_commit($template_id,$ext,$version,$desc) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/commit?access_token='.$access_token;

        return self::post($url,json_encode(array(
            'template_id'=>$template_id,
            'ext_json' => json_encode($ext, JSON_UNESCAPED_UNICODE),
            'user_version' => $version,
            'user_desc' => $desc,
        ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 小程序发布已审核版本
     * @param $item_list
     * @return mixed
     */
    public static function lite_release() {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/release?access_token='.$access_token;
        return self::post($url,'{}');
    }

    /**
     * 小程序获取体验二维码
     * @param $item_list
     * @return mixed
     */
    public static function lite_get_qrcode() {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/get_qrcode?access_token='.$access_token;
        return self::get($url);
    }

    /**
     * 小程序可选类目
     * @param $item_list
     * @return mixed
     */
    public static function lite_get_category() {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/get_category?access_token='.$access_token;
        return self::get($url);
    }

    /**
     * 小程序页面配置
     * @param $item_list
     * @return mixed
     */
    public static function lite_get_page() {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/get_page?access_token='.$access_token;
        return self::get($url);
    }

    /**
     * 小程序提交审核
     * @param $item_list
     * @return mixed
     */
    public static function lite_submit_audit($item_list) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/submit_audit?access_token='.$access_token;

        return self::post($url,json_encode(array('item_list'=>$item_list), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 小程序查询审核状态
     * @param $auditid
     * @return mixed
     */
    public static function lite_get_auditstatus($auditid) {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/get_auditstatus?access_token='.$access_token;
        return self::post($url,json_encode(array('auditid'=>$auditid)));
    }
    /**
     * 小程序查询最后一次审核状态
     * @param $auditid
     * @return mixed
     */
    public static function lite_latest_auditstatus() {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token='.$access_token;
        return self::get($url);
    }




    /**
     * 获取微信的access_token
     * @param string $force 是否不使用缓存，强制获取
     * @return string
     */
    public static function getAccessToken($force=false,$dev=false){

        if( empty(self::$appId) ) {
            return null;
        }

        /* 开发者模式配置，微信也效验IP了。用户配置的无法正常工作
        */

        if( Di::getDefault()->get('config')->Weixin->platOpen ){ //非开发者模式或开发者模式配置异常的

            $access_token = self::compGetAccessToken(); //内部包含事务，提出来放在这里的事务之外。

            $manager =  Di::getDefault()->get('transactions');
            $transaction = $manager->get();

            try{
                // 直接查找第三方平台授权的公众号或小程序
                $oauthbind = Oauthbind::findFirst(array(
                    'conditions' => "source= :source: and oauth_openid = :oauth_openid:",
                    'bind' => array(
                        'source'=> self::$source, // array('wechatComp','wechatLite')
                        'oauth_openid' => self::$appId
                    ),
                    'for_update' => true
                ));

                if( !empty($oauthbind) ){
                    if( $force || $oauthbind->updated < time() - 7100 ) {
                        $refresh_result = self::compRefreshToken($oauthbind->oauth_openid,$oauthbind->refresh_token,$access_token);
                        if(empty($refresh_result)) { // 网络原因未取得结果，跳过不处理。下次继续请求
                            usleep(300); // 暂停200毫秒
                            $refresh_result = self::compRefreshToken($oauthbind->oauth_openid,$oauthbind->refresh_token,$access_token);
                        }

                        if(empty($refresh_result['errcode']) && !empty($refresh_result['authorizer_access_token'])){

                            $oauthbind->setTransaction($transaction);
                            $oauthbind->oauth_token = $refresh_result['authorizer_access_token'];
                            $oauthbind->expires = $refresh_result['expires_in'];
                            $oauthbind->refresh_token = $refresh_result['authorizer_refresh_token'];
                            $oauthbind->updated = time();
                            $oauthbind->save();
                            $transaction->commit();
                            return $refresh_result['authorizer_access_token'];
                        }
                        else{
                            $transaction->rollback('refresh access_token error');
                            DI::getDefault()->get('logger')->error('GetAccessToken refresh error ('.$force.'). Appid:'.self::$appId.' refresh result:'.var_export($refresh_result,true));
                        }
                    }
                    else if( $oauthbind->oauth_token ) {
                        $transaction->commit();
                        return $oauthbind->oauth_token;
                    }
                }
                else{
                    $transaction->rollback('empty oauthbind');
                }
            }
            catch(Exception $e) {
                DI::getDefault()->get('logger')->error("getAccessToken Exception:".$e->getMessage());
            }
        }
        if( self::$secretKey ){ // 配置了开发模式的公众号
            $cacheKey = 'wx_app_token_'.self::$appId;
            $token = DI::getDefault()->get('redisCache')->get($cacheKey);
            if( empty($token) || $force ){
                $i = 0;
                do{
                    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".self::$appId."&secret=".self::$secretKey;
                    $ret = self::get($url);
                    $i++;
                    if(empty($ret['errcode'])) {
                        break;
                    }
                    else{
                        DI::getDefault()->get('logger')->error('get access_token error'.var_export($ret,true));
                    }
                }while($i < 2);

                $token = $ret['access_token'];
                DI::getDefault()->get('redisCache')->set($cacheKey,$token,6600);
            }
            return $token;
        }
        return null;
    }

    private static function unicode_decode($name,$charset = 'UTF-8'){//GBK,UTF-8,big5
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




    /*--------------------------第三方平台相关-----------------------------------------------------*/

    /**
     * 查询授权的公众号的信息
     * @param unknown $auth_code 通过授权登录的回调地址get参数获得
     * @return unknown
     */
    public static function compQueryAuth($auth_code){
        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.$token;
        $data = array('component_appid' => COMPONENT_APPID,'authorization_code'=> $auth_code );
        $post_data = json_encode($data);
        return self::post($url,$post_data);
    }



    /**
     * 获取公众号服务的预授权码，用于生成授权登录的跳转链接
     * @return unknown
     */
    public static function compPreAuthcode(){

        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.$token;
        $data = array('component_appid' => COMPONENT_APPID);
        $post_data = json_encode($data);
        $ret = self::post($url,$post_data);
        // if($ret['errcode']==40001) {
        //     self::compGetAccessToken(true);
        //     return self::compPreAuthcode();
        // }
        return $ret['pre_auth_code'];
    }

    /**
     * 获取授权方的账户信息
     * @return unknown
     */
    public static function compAuthInfo( $auth_appid ) {
        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.$token;
        $data = array('component_appid' => COMPONENT_APPID,'authorizer_appid' => $auth_appid);
        $post_data = json_encode($data);
        $ret = self::post($url,$post_data);
        // if($ret['errcode'] == 40001) {
        //     $token = self::compGetAccessToken(true);
        //     return self::compAuthInfo( $auth_appid );
        // }
        return $ret;
    }


    /**
     *  获取授权方的账户选项设置
     * @param unknown $auth_appid
     * @param unknown $option_name	location_report(地理位置上报选项),voice_recognize（语音识别开关选项）,customer_service（客服开关选项）
     * @return unknown
     */
    public static function compAuthOption( $auth_appid ,$option_name) {
        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_option?component_access_token='.$token;
        $data = array('component_appid' => COMPONENT_APPID,'authorizer_appid' => $auth_appid,'option_name'=>$option_name);
        $post_data = json_encode($data);
        return self::post($url,$post_data);
    }
    /**
     * 设置授权方的选项信息
     * @param unknown $auth_appid
     * @param unknown $option_name
     * @param unknown $option_value
     * @return unknown
     */
    public static function compAuthSetOption( $auth_appid ,$option_name,$option_value) {
        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_set_authorizer_option?component_access_token='.$token;
        $data = array('component_appid' => COMPONENT_APPID,'authorizer_appid' => $auth_appid,'option_name'=>$option_name,'option_value'=>$option_value);
        $post_data = json_encode($data);
        return self::post($url,$post_data);
    }

    /**
     * 刷新用户授权的access_token
     * @param unknown $auth_appid	用户的appid
     * @param unknown $refresh_token	刷新token
     * @param string $access_token access_token
     * @return unknown
     */
    public static function compRefreshToken($auth_appid,$refresh_token,$access_token ){
        if($access_token) {
            $url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$access_token;
            $data = array('component_appid' => COMPONENT_APPID,'authorizer_appid'=> $auth_appid,'authorizer_refresh_token'=> $refresh_token );
            $post_data = json_encode($data);

            $ret = self::post($url,$post_data);
            if($ret['errcode']==0) {
                return $ret;
            }
            elseif(  in_array($ret['errcode'],array(40001,40014,41001,42001)) ) { // 循环重试只使用一次强制刷新access token
                // usleep(300); //重试一次
                // $access_token = self::compGetAccessToken(true);
                // if($access_token) {
                //     $url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$access_token;
                //     $ret = self::post($url,$post_data);
                // }
            }
            return $ret;
        }
        else{
            return array('errcode'=>1,'errmsg'=>'get app access_token error.');
        }
    }

    /**
     * 小程序code 换取 session_key
     * https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1492585163_FtTNA&token=&lang=zh_CN
     * @param $appid
     * @param $code
     * @return array|mixed
     */
    public static function jscode2session($appid,$code) {
        $token = self::compGetAccessToken();
        if( $token ) {
            $url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$appid.'&js_code='.$code.'&grant_type=authorization_code&component_appid='.COMPONENT_APPID.'&component_access_token='.$token;
            return self::get($url);
        }
        else{
            return array('errcode'=>1,'errmsg'=>'get app access_token error.');
        }
    }

    public static function compClearQuota($token = ''){
        if(empty($token)) {
            $token = self::compGetAccessToken();
        }
        if($token) {
            $url = 'https://api.weixin.qq.com/cgi-bin/component/clear_quota?component_access_token='.$token;
            $data = array('component_appid' => COMPONENT_APPID );
            $post_data = json_encode($data);
            $ret = self::post($url,$post_data);
            return $ret;
        }
        else{
            return array('errcode'=>1,'errmsg'=>'get app access_token error.');
        }
    }

    /**
     * 获取微信的access_token
     * @param string $force 是否不使用缓存，强制获取
     * @return string
     */
    public static function compGetAccessToken($force=false){
        $redisService = DI::getDefault()->get('redisCache');
        $cacheKey = 'wx_comp_token_'.COMPONENT_APPID;
        $token = $redisService->get($cacheKey);
        if( empty($token) || $force ){
            $component_verify_ticket = Di::getDefault()->get('redisCache')->get('component_verify_ticket');
            if(empty($component_verify_ticket)){
                $component_verify_ticket = Dbcache::read('component_verify_ticket');
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
                
                $url = "https://api.weixin.qq.com/cgi-bin/component/api_component_token";
                $data = array(
                    'component_appid' => COMPONENT_APPID,
                    'component_appsecret' => COMPONENT_APPSECRET,
                    'component_verify_ticket' => $component_verify_ticket,
                );
                $post_data = json_encode($data);
                $i = 0;
                do{
                    $i++;
                    $ret = self::post($url,$post_data);
                    if( !$ret['errcode'] ){
                        $token = $ret['component_access_token'];
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
                }
                while($i < 3); //重试3次
                $transaction->rollback("Get comp access token error.".$ret['errcode'].' '.$ret['errmsg']);
            }
            catch(Exception $e){
                DI::getDefault()->get('logger')->error("compGetAccessToken Exception:".$e->getMessage());
            }

        }
        return $token;
    }

    /**
    * 网页授权获取CODE
    * 
    */
    public static function compGetWebCode($appid,$state = 'STATE',$redirect_uri = "",$scope = 'snsapi_userinfo'){
        if($redirect_uri == ''){
            return false;
        }
        $redirect_uri = urlencode($redirect_uri);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope='.$scope.'&state='.$state.'&component_appid='.COMPONENT_APPID.'#wechat_redirect';
        header("location:" . $url);
        die;
    }

    /**
    * 网页授权获取accesstoken
    * 
    */
    public static function compGetWebAccessToken($appid,$code){
        $token = self::compGetAccessToken();
        $url = 'https://api.weixin.qq.com/sns/oauth2/component/access_token?appid='.$appid.'&code='.$code.'&grant_type=authorization_code&component_appid='.COMPONENT_APPID.'&component_access_token='.$token;
        $ret = self::get($url);
        if(!$ret['errcode']){
            return $ret;
        }else{
            DI::getDefault()->get('logger')->error("Get access token error $appid.".$ret['errcode'].' '.$ret['errmsg']);
            return $ret;
        }  
    }

    /**
    * 网页授权获取用户信息
    * 
    */
    public static function compGetWebUserInfo($openid,$access_token){
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        $response = self::get($url);
        return $response;
    }
}
