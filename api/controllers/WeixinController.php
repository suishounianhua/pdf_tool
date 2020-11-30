<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;


class WeixinController extends Controller
{


    private $encry_type = 'component'; // component , 默认加密类型，develop开发者加密， component 公众号服务加密 ， none 不加密
    private $wx_user = array();
    private $wx_total = false;
    public function initialize(){
        define('COMPONENT_APPID', $this->config->Weixin->ComponentAppID);
        define('COMPONENT_APPSECRET', $this->config->Weixin->ComponentAppSecret);
        define('COMPONENT_TOKEN', $this->config->Weixin->ComponentToken);
        define('COMPONENT_AESKEY', $this->config->Weixin->ComponentAesKey);
    }

    public function validAction($wx_id=0)
    {
        $echoStr = $_GET["echostr"];
        if($this->checkSignature($wx_id)){
            echo $echoStr;
        }else{
            echo "invalid request: echo=$echoStr";
        }
        exit;
    }
    public function msgAction($wx_id){		// 开发者模式接受的消息

        $postStr = file_get_contents('php://input', 'r');

        $this->getDI()->get('logger')->info($postStr."\nGET:".var_export($_GET,true));

        if(!empty($_GET["echostr"])){
            $this->validAction($wx_id);
        }
        elseif ( !empty($postStr) && $wx_id ){

            $wx_info = Wx::findFirst(
                [
                    'conditions' => "id = :id:",
                    'bind' => ['id'=> $wx_id],
                    "cache" => [
                        "key"      => "wx-id-".$wx_id,
                        "lifetime" => 300,
                    ],
                ]
            );
            if( empty($wx_info) ) {
                echo 'params error.';
                exit;
            }


            $settings = UserSetting::getSetting($wx_info->creator);
            $variables = $settings[$wx_id];

            $this->dev_vars = $variables;

            WechatService::$appId = $variables['wxAppId'];
            WechatService::$secretKey = $variables['wxAppSecret'];

            if( $variables['wxAESKey'] && $variables['token'] && $variables['wxAppId'] ) {

                $this->encry_type = 'develop';
                $pc = new WXBizMsgCrypt($variables['token'], $variables['wxAESKey'], $variables['wxAppId']);
                $msg = '';
                $errCode = $pc->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $postStr, $msg);
                if($errCode) return false;
                $postStr = $msg;
            }
            else{
                $this->encry_type = 'none';
            }

            // 查看文章，上报地址，消息群发完成通知，模板群发完成通知
            $skiped = array(
                '<Event><![CDATA[TEMPLATESENDJOBFINISH]]></Event>',
                '<Event><![CDATA[VIEW]]></Event>',
                '<Event><![CDATA[LOCATION]]></Event>',
            );
            foreach($skiped as $needle){
                if( strpos($postStr,$needle)!== false ){
                    echo 'success'; // 大批量的模板消息发送成功返回提示，忽略不处理。 使用strpos判断，避免xml转换数组处理
                    exit;
                }
            }

            $msgData = xml_to_array($postStr);
            $event = trim($msgData['Event']);
            /**
             * 扫码关注事件，EventKey为定义的二维码场景的值加上前缀 qrscene_
             * 授权又配置了开发者模式的公众号，扫码关注事件在开发者模式中转发。扫码事件两边同时转发。
             * 'MsgType' => 'event',
            'Event' => 'subscribe',
            'EventKey' => 'qrscene_invite_user',
             */
//            if($event == 'SCAN' && $this->getDI()->get('config')->Weixin->platOpen ) {
//                echo 'success';
//                exit;
//                // 为扫码事件时，开启了第三方平台的公众号，忽略开发者模式不处理，避免重复。
//            }
            $this->responseMsg($wx_id,$postStr,$wx_info->toArray());
        }
        exit;
    }

    public function pcallbackAction(){
        $postStr = file_get_contents('php://input', 'r');
        //$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $this->getDI()->get('logger')->info($postStr."\nGET:".var_export($_GET,true));

        if($postStr){
            $pc = new WXBizMsgCrypt( COMPONENT_TOKEN, COMPONENT_AESKEY, COMPONENT_APPID );
            $msg = '';
            if( $_GET['msg_signature'] && $_GET['nonce'] ) {
                $errCode = $pc->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $postStr, $msg);
                if ($errCode) return false;
                $postStr = $msg;
            }

            $msgData = xml_to_array($postStr);
            $this->getDI()->get('logger')->info("wechat pcallback \nArray:".var_export($msgData,true));

            if($msgData['InfoType'] == 'component_verify_ticket'){
                //file_put_contents(DATA_PATH.'component_verify_ticket.txt', $msgData['ComponentVerifyTicket']);

                // cache time must bigger than 600s. 缓存有效期必需超过10分钟，微信每十分钟才推送一次。
                $this->getDI()->get('redisCache')->set('component_verify_ticket',$msgData['ComponentVerifyTicket']);

                Dbcache::write('component_verify_ticket',$msgData['ComponentVerifyTicket']);
                echo 'success';
                exit;
            }
            elseif($msgData['InfoType'] == 'unauthorized'){ //某appid取消授权了

                $auth_appid = $msgData['AuthorizerAppid'];
                $binds = Oauthbind::find(
                    "source = 'wechatComp' and oauth_openid='$auth_appid'"
                );
                foreach ($binds as $b) {
                    $b->delete();
                }
            }
            echo 'success';
            exit;
        }
        else{
            $verify_ticket = $this->getDI()->get('redisCache')->get('component_verify_ticket');
            if(empty($verify_ticket)) {
                $verify_ticket =  Dbcache::read('component_verify_ticket');
            }
            if($verify_ticket){
                echo 'has ticket.';
            }
            else{
                echo 'no ticket.';
            }
        }
        exit;
    }

    public function test_vars(){

        $user_roles = User::roles(1);
        print_r($user_roles);
        WechatService::$appId = 'wx75c51a23fad76ecc';
        $open_id = 'oTNzrjnXV3thAWGLAtHVxnwIiDGA';

        $msg = '【昵称】，【时间段】好。今天是【日期】，【星期】';
        print_r($this->parseMsg($open_id,$msg));

        $msg = array('text'=>array($msg));

        print_r($this->parseMsg($open_id,$msg));
        exit;
    }

    private $variables = array();

    private function getVars($fromUser,$name){
        if(isset($this->variables[$name])) {
            return $this->variables[$name];
        }
        if(strpos($name, '关注人数') !== false && !isset($this->variables[$name])){
            if($this->wx_total === false){
                $userlist = WechatService::getUserList();
                if( empty($userlist['errcode']) ) {
                    $this->wx_total = $userlist['total'];
                }
            }
            $arr = explode("+", $name);
            $baseNumber = intval($arr[1]);
            $this->variables[$name] = $this->wx_total + $baseNumber;
        }
        $this->variables['日期'] = date('Y年m月d日');
        $this->variables['时分'] = date('H点i分');

        $hans = array(0,'星期一','星期二','星期三','星期四','星期五','星期六','星期天');
        $this->variables['星期'] = $hans[ date('N') ];

        $h = date('G');
        if ($h < 9 && $h > 5) {
            $timeText = '早上';
        }
        elseif ($h < 12 && $h >= 9) {
            $timeText = '上午';
        }
        elseif ($h < 13  && $h >= 12 ) {
            $timeText = '中午';
        }
        elseif ($h < 18  && $h >= 13 ) {
            $timeText = '下午';
        }
        else {
            $timeText = '晚上'; // 6点到凌晨4点
        }
        $this->variables['时间段'] = $timeText;

        if(strpos($name,'性别') !== false || in_array($name,array('昵称','国家','省份','城市','头像','关注时间','关注时长',)) ) {
            if( empty($this->wx_user)) {
                $this->wx_user = WechatService::getUserInfo($fromUser);
            }
            if( empty($this->wx_user['errcode']) ) {
                $this->variables['昵称'] = $this->wx_user['nickname'];
                $this->variables['国家'] = $this->wx_user['country'];
                $this->variables['省份'] = $this->wx_user['province'];
                $this->variables['城市'] = $this->wx_user['city'];
                $this->variables['头像'] = $this->wx_user['headimgurl'];

                $this->variables['关注时间'] = date('Y-m-d H:i:s',$this->wx_user['subscribe_time']);
                $this->variables['关注时长'] = format_time_interval(time() - $this->wx_user['subscribe_time'],3); //几月几天

                if ( $this->wx_user['sex'] == 1 ) {
                    $this->variables['性别'] = '男';
                    $this->variables['性别1'] = '帅哥';
                    $this->variables['性别2'] = '小哥哥';
                    $this->variables['性别3'] = '学弟';
                    $this->variables['性别4'] = '学长';
                    $this->variables['性别5'] = '小鲜肉';
                } else {
                    $this->variables['性别'] = '女';
                    $this->variables['性别1'] = '美女';
                    $this->variables['性别2'] = '小姐姐';
                    $this->variables['性别3'] = '学妹';
                    $this->variables['性别4'] = '学姐';
                    $this->variables['性别5'] = '小仙女';
                }
            }
        }
        if(!empty($this->variables[$name])) {
            return $this->variables[$name];
        }
        else{
            return null;
        }
    }

    private function parseMsg($fromUser,$msg){
        if(is_string($msg)) {
            return $this->parseVarItem($fromUser,$msg);
        }
        if(is_array($msg)) {
            if(is_array($msg['text'])) {
                foreach($msg['text'] as &$text){
                    $text = $this->parseVarItem($fromUser,$text);
                }
            }
            if(is_array($msg['news'])) {
                foreach($msg['news'] as &$news) {
                    $news['title'] = $this->parseVarItem($fromUser,$news['title']);
                    $news['description'] = $this->parseVarItem($fromUser,$news['description']);
                    $news['picUrl'] = $this->parseVarItem($fromUser,$news['picUrl']);
                }
            }
        }
        return $msg;
    }

    private function parseVarItem($fromUser,$string){
        // “【”与“性” 在正则表达式中冲突，需额外支持一下
        if( preg_match_all('/【(([^【]|性)+?)】/is',$string,$matches) ){
            foreach($matches[0] as $k => $n) {
                $val = $this->getVars($fromUser,$matches[1][$k]);
                if( !empty($val) ) {
                    $string = str_replace($n,$val,$string);
                }
            }
            return trim($string);
        }
        else{
            return trim($string);
        }
    }

    private function responseMsg($wx_id,$postStr,$wx_info = array())
    {
        if ( !empty($postStr) && $wx_id )
        {
            if(is_array($postStr)) {
                $msgData = $postStr;
            }
            else{
                $msgData = xml_to_array($postStr);
            }

            $this->getDI()->get('logger')->error("response msg poststr:".var_export($msgData,true));


            if(!is_array($msgData)) { //错误的格式内容
                $this->getDI()->get('logger')->error("response msg error poststr:".$postStr);
                echo $postStr;exit;
            }
            $fromUsername = $msgData['FromUserName'];
            $selfUsername = $msgData['ToUserName'];
            $keyword = trim($msgData['Content']);
            $event = trim($msgData['Event']);
            $msg_type = trim($msgData['MsgType']);
            if( !empty($event) ) {
                $upEvent = strtoupper($event);
            }
            else{
                $upEvent = $event = strtoupper($msg_type);
            }

            $msg_media_id = trim($msgData['MediaId']);
            $msg_media_format = trim($msgData['Format']);

            $time_stamp = $msgData['CreateTime'];
            $event_key = $msgData['EventKey'];
            $picUrl = $msgData['PicUrl'];

            if( $upEvent == 'UNSUBSCRIBE' ) { //更新取消关注状态
                Di::getDefault()->get('eventsManager')->fire('wechat:beforeHandleMsg',null,array(
                    'wx'=> $wx_info,
                    'msg'=>$msgData,
                ));
                WxUser::unsubscribe( $wx_id , $fromUsername);
                echo 'success';
                exit;
            }
            else if( $event == 'MASSSENDJOBFINISH' ) {
                echo 'success';
                exit;
            }
            if(in_array( $upEvent,array(
                'USER_VIEW_CARD','USER_GET_CARD','UNSUBSCRIBE',
                'USER_SCAN_PRODUCT','USER_SCAN_PRODUCT_ASYNC',
                'UPDATE_MEMBER_CARD','USER_GIFTING_CARD','CARD_SKU_REMIND',
                'USER_DEL_CARD','USER_PAY_FROM_PAY_CELL','USER_ENTER_SESSION_FROM_CARD','USER_CONSUME_CARD',
                'POI_CHECK_NOTIFY','NAMING_VERIFY_SUCCESS','MERCHANT_ORDER','CARD_PASS_CHECK',
                'SHAKEAROUNDUSERSHAKE','SUBMIT_MEMBERCARD_USER_INFO',

                'WIFICONNECTED','KF_CLOSE_SESSION','KF_CREATE_SESSION','PIC_WEIXIN','QUALIFICATION_VERIFY_SUCCESS',
                'SCANCODE_PUSH','SCANCODE_WAITMSG','CARD_EVENT_AFTER_PAY','CARD_PAY_ORDER',
                //'','','','','','',
            ))) {
                echo 'success';
                exit;
            }

            if( in_array($upEvent,array('TEXT','IMAGE','VOICE','PIC_PHOTO_OR_ALBUM','SCAN','CLICK','SUBSCRIBE')) ) {
                $this->wx_user = WechatService::getUserInfo($fromUsername);
                $wxUser = WxUser::saveUser( $wx_info['id'] , $fromUsername,$this->wx_user);
                Di::getDefault()->get('eventsManager')->fire('wechat:beforeHandleMsg',null,array(
                    'wx'=> $wx_info,
                    'msg'=>$msgData,
                    'user'=> $wxUser->toArray(),
                ));
            }

            if(empty($keyword) && $event == 'CLICK'){
                $keyword = trim($msgData['EventKey']);
            }
            if($event == 'SCAN'  || ($upEvent == 'SUBSCRIBE' && strpos($event_key,'qrscene_')===0 )) {
                $settings = UserSetting::getSetting(  $wx_info['creator'] );
                $variables = $settings[$wx_id];

                // 记录扫码记录
                if( $wx_info['record_scan'] && $event_key ){
                    $slug = str_replace('qrscene_','',$event_key);
                    $scan = new WxQrScan();
                    $scan->username = $fromUsername;
                    $scan->slug = $slug;
                    $scan->wx_id = $wx_id;
                    $scan->created = date('Y-m-d H:i:s');
                    $scan->save();
                    WxQr::increase('scan_nums',array( 'slug' => $slug,'wx_id' => $wx_id,));
                    if( $this->getDI()->get('config')->Weixin->AppId == $wx_info['oauth_appid'] && strlen($slug)==9 && is_numeric($slug) ) { // 9位数字的扫码，为登录的形式。
                        $logMsg =  $this->getDI()->get('config')->User->wechatWelcome; // '您好，欢迎使用135编辑器，祝您每日好心情。';
                        $frag = Frag::findFirst([
                                'columns'=>'summary,msg_type,media_id',
                                'conditions' => array('deleted'=>0),
                                'order' => 'rand()',
                                ]);
                        if($frag != false){
                            switch ($frag->msg_type) {
                                case 'image':
                                        $ret = WechatService::sendImageMsg($fromUsername,$frag->media_id);
                                    break;
                                default:
                                    $logMsg .= trim($frag->summary);
                                    $ret = WechatService::sendTextMsg($fromUsername,$logMsg);
                                    break;
                            }
                        }
                        $keyword = 'qrlogin';
                    }
                }
            }

            /**
             * 48小时调用客服接口,允许的动作列表
             * 1、用户发送信息
            2、点击自定义菜单（仅有点击推事件、扫码推事件、扫码推事件且弹出“消息接收中”提示框这3种菜单类型是会触发客服接口的）
            3、关注公众号
            4、扫描二维码
            5、支付成功
            6、用户维权
             */
            $search_word = $keyword ? $keyword :( $event_key ? $event_key : $event ) ;

            $auto_reply = false;

            // 需要开启自动回复的开关。
            // 关注，点击事件，发送文本消息.
            if( $wx_info['auto_replies'] && in_array($upEvent,array('TEXT','IMAGE','SCAN','CLICK','SUBSCRIBE')) ) {
                if( !empty($search_word) ) {
                    // 关注时，除参数外，还增加关注事件的回复。
                    if( $upEvent == 'SUBSCRIBE') {
                        $search_word= 'subscribe';
                    }
                    $msglist = WxAutoReply::find(array(
                        'conditions' => array(
                                "wx_id" => $wx_id,
                                "keyword" => $search_word,
                            ),
                        'limit' => 20,
                        'order' => 'priority desc'
                    ))->toArray();
                }
                else{
                    $msglist = array();
                }

                if( empty($msglist) && $search_word != 'default' && in_array($upEvent,array('TEXT','CLICK')) ) { 
                    //无规则命中时，查找默认自动回复规则
                    //判断是否出现在应用关键字
                    $yyznkeywordObj = YyznKeyword::findFirst(['wx_id'=>$wx_id,'type'=>1,'keyword'=>$search_word]);
                    if($yyznkeywordObj == false){
                        $msglist = WxAutoReply::find(array(
                            'conditions' => ['wx_id'=>$wx_id,'keyword'=>$search_word],
                            'limit' => 20,
                            'order' => 'priority desc'
                        ))->toArray();
                    }
                }

                // 用户发送的所有词作为搜索查询条件，
                //$this->getDI()->get('logger')->info(var_export($msglist,true));
                if( !empty($msglist) ){
                    $msglist = $msglist;
                    $msgs = array();
                    $text_msg = $media_id =  '';

                    $auto_reply = true;

                    foreach ($msglist as $key => $item) {
                        if($item['msg_type']=='link' || $item['msg_type']=='news'){

                            if(is_array($msgs['news']) && count($msgs['news']) >= 8) { // 多图文最多支持8条
                                continue;
                            }

                            if($item['media_id']) { // 设置了回复的图文素材
                                $msgs['media_id'][] = $item['media_id'];
                            }
                            else{
                                $imgurl = $item['coverimg'];
                                // 含“【”的为变量模式
                                if($imgurl == '' || $imgurl == null){
                                    $imgurl = "";
                                }elseif( strpos($imgurl,'【') === false && strpos($imgurl,'://') === false ){
                                    $imgurl = $this->url($imgurl);
                                }

                                $msgs['news'][] = array(
                                    'title' => $item['name'],
                                    'description' => $item['summary'],
                                    'picUrl'=> $imgurl,
                                    'url' => $item['url'],
                                );
                            }
                        }
                        elseif($item['msg_type']=='text'){
                            $msgs['text'][] = htmlspecialchars_decode($item['summary']);
                        }
                        elseif($item['msg_type']=='image' ){
                            $msgs['image'][] = $item['media_id'];
                        }
                        elseif($item['msg_type']=='voice' ){
                            $msgs['voice'][] = $item['media_id'];
                        }
                        elseif($item['msg_type']=='video' ){
                            $msgs['video'][] = array(
                                'media_id' => $item['media_id'],
                                'title' => $item['name'],
                                'description' => $item['summary'],
                            );
                        }
                        elseif($item['msg_type']=='music' ){
                            $msgs['music'][] = array(
                                'media_id' => $item['media_id'],
                                'url' => $item['url'],
                                'title' => $item['name'],
                                'description' => $item['summary'],
                            );
                        }
                    }
                    $ik = 0;
                    $msgs = $this->parseMsg($fromUsername,$msgs);
                    // 					    $type = array_rand($msgs);
                    foreach($msgs as $type => $m) {
                        $ik++;
                        if($ik == 1) { // 第一条消息使用被动回复的方式回复。
                            if($type == 'news'){
                                echo $this->newArticleMsg($fromUsername,$selfUsername,$msgs['news']);
                            }
                            elseif($type == 'media_id'){
                                $rand = array_rand($msgs['media_id']);
                                $media_id = $msgs['media_id'][$rand];

                                $media_info = WechatService::getMaterial($media_id);
                                if( is_array($media_info) && isset($media_info['news_item'])){
                                    $articles = array();
                                    foreach($media_info['news_item'] as $ak => $article) {
                                        $articles[] = array(
                                            'title' => $article['title'],
                                            'description' => $article['digest'],
                                            'picUrl'=> $article['thumb_url'], // thumb_media_id,图文消息的封面图片素材id。无法作为网址使用
                                            'url' => $article['url'],
                                        );
                                    }
                                    echo $this->newArticleMsg($fromUsername,$selfUsername,$articles);
                                }
                            }
                            elseif($type == 'text'){
                                $rand = array_rand($msgs['text']);
                                echo $this->newTextMsg($fromUsername,$selfUsername,$msgs['text'][$rand]);
                            }
                            elseif($type == 'voice'){
                                $rand = array_rand($msgs['voice']);
                                echo $this->newVoiceMsg($fromUsername,$selfUsername,$msgs['voice'][$rand]);
                            }
                            elseif($type == 'image'){
                                $rand = array_rand($msgs['image']);
                                echo $this->newImageMsg($fromUsername,$selfUsername,$msgs['image'][$rand]);
                            }
                            elseif($type == 'video'){
                                $rand = array_rand($msgs['video']);
                                echo $this->newVideoMsg($fromUsername,$selfUsername,$msgs['video'][$rand]['media_id'],$msgs['video'][$rand]['title'],$msgs['video'][$rand]['description']);
                            }
                            elseif($type == 'music'){
                                $rand = array_rand($msgs['music']);
                                echo $this->newMusicMsg($fromUsername,$selfUsername,$msgs['music'][$rand]['media_id'],$msgs['music'][$rand]['url'],$msgs['music'][$rand]['title'],$msgs['music'][$rand]['description']);
                            }
                        }
                        else{ // 第二条及之后的回复规则，使用客服消息的方式发送消息
                            if($type == 'news'){
                                //echo $this->newArticleMsg($fromUsername,$selfUsername,$msgs['news']);
                                $ret = WechatService::sendArticleMsg($fromUsername,$msgs['news']);
                            }
                            elseif($type == 'media_id'){
                                $rand = array_rand($msgs['media_id']);
                                $media_id = $msgs['media_id'][$rand];

                                $media_info = WechatService::getMaterial($media_id);
                                if( is_array($media_info) && isset($media_info['news_item'])) {
                                    $articles = array();
                                    foreach ($media_info['news_item'] as $ak => $article) {
                                        $articles[] = array(
                                            'title' => $article['title'],
                                            'description' => $article['digest'],
                                            'picUrl' => $article['thumb_url'], // thumb_media_id,图文消息的封面图片素材id。无法作为网址使用
                                            'url' => $article['url'],
                                        );
                                    }
                                    $ret = WechatService::sendArticleMsg($fromUsername,$articles);
                                }
                            }
                            elseif($type == 'text'){
                                $rand = array_rand($msgs['text']);
                                $ret = WechatService::sendTextMsg($fromUsername,$msgs['text'][$rand]);
                            }
                            elseif($type == 'voice'){
                                $rand = array_rand($msgs['voice']);
                                $ret = WechatService::sendVoiceMsg($fromUsername,$msgs['voice'][$rand]);
                            }
                            elseif($type == 'image'){
                                $rand = array_rand($msgs['image']);
                                $ret = WechatService::sendImageMsg($fromUsername,$msgs['image'][$rand]);
                            }
                            elseif($type == 'video'){
                                $rand = array_rand($msgs['video']);
                                $ret = WechatService::sendVideoMsg($fromUsername,$msgs['video'][$rand]['media_id'],$msgs['video'][$rand]['coverimg'],$msgs['video'][$rand]['title'],$msgs['video'][$rand]['description']);
                            }
                            elseif($type == 'music'){
                                $rand = array_rand($msgs['music']);
                                $ret = WechatService::sendMusicMsg($fromUsername,$msgs['music'][$rand]['media_id'],$msgs['music'][$rand]['url'],$msgs['music'][$rand]['title'],$msgs['music'][$rand]['description']);
                            }
                        }
                    }
                }

            }
            if($wxUser != false){
                Di::getDefault()->get('eventsManager')->fire('wechat:afterHandleMsg',null,array(
                    'wx'=> $wx_info,
                    'msg'=>$msgData,
                    'user'=> $wxUser->toArray(),
                ));
            }
            

            // 仅自动回复规则未命中的时候，才记录消息内容
            if($auto_reply == false) { //已命中自动回复规则回复消息则停止后续逻辑
                echo 'success';
            }
            exit;
        }
        else{
            echo 'Hello ',$wx_info['name'],"! The interface is ready for you.";
            exit;
        }
    }



    public function pmsgAction($appid){

        echo "";
        $testing_appid = 'wx570bc396a51b8ff8'; //全网发布接入检测自动测试appid
        if($appid != $testing_appid) {
            // 缓存查询结果时间为300秒
            $wx_info = Wx::findFirst(
                [
                    'conditions' => "oauth_appid = :oauth_appid:",
                    'bind' => ['oauth_appid'=> $appid],
                    "cache" => [
                        "key"      => "wx-info-".$appid,
                        "lifetime" => 300,
                    ],
                ]
            );
        }

        $postStr = file_get_contents('php://input', 'r');
        $this->encry_type = 'component';
        if( $postStr ){
            $pc = new WXBizMsgCrypt( COMPONENT_TOKEN, COMPONENT_AESKEY, COMPONENT_APPID );

            #https://mp.weixin.qq.com/debug/ 微信调试地址
            if( $_GET['msg_signature'] && $_GET['nonce'] ){ //有参数时调用解密.否则处理明文
                $msg = '';
                $errCode = $pc->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $postStr, $msg);
                if($errCode) {
                    $this->getDI()->get('logger')->error($postStr."\npmsg() called. AppId:$appid\nDecrypt Error code:".$errCode);
                    return false;
                }
                $postStr = $msg;
            }

            // MASSSENDJOBFINISH 为定时群发消息的结果通知，需要记录在服务器上
            // '<Event><![CDATA[MASSSENDJOBFINISH]]></Event>',

            if( $appid != $testing_appid ) { //全网发布接入检测不能跳过事件。
                // 查看文章，上报地址，消息群发完成通知，模板群发完成通知
                $skiped = array(
                    '<Event><![CDATA[TEMPLATESENDJOBFINISH]]></Event>',
                    '<Event><![CDATA[VIEW]]></Event>',
                    '<Event><![CDATA[LOCATION]]></Event>',
                );
                foreach($skiped as $needle){
                    if( strpos($postStr,$needle)!== false ){
                        echo 'success'; // 大批量的模板消息发送成功返回提示，忽略不处理。 使用strpos判断，避免xml转换数组处理
                        exit;
                    }
                }
            }
            $this->getDI()->get('logger')->info($appid.":".$postStr);
            // '<Event><![CDATA[CLICK]]></Event>', 菜单点击事件开启自动回复，不跳过。
        }
        else{
            echo 'success';
            exit;
        }

        // 全网发布接入检测自动测试appid
        // https://open.weixin.qq.com/cgi-bin/readtemplate?t=resource/plugin_publish_check_tmpl&lang=zh_CN&token=35cfe563ac37711e25cdc5ef4ffe2445f4543b79
        if( $appid == $testing_appid ){ // username = gh_3c884a361561

            $msgData = xml_to_array($postStr);

            $fromUsername = $msgData['FromUserName'];
            $selfUsername = $msgData['ToUserName'];
            if($msgData['MsgType'] == 'event'){
                $event = trim($msgData['Event']);
                echo $this->newTextMsg($fromUsername,$selfUsername,$event.'from_callback');
            }
            else if($msgData['MsgType'] == 'text'){
                $content = trim($msgData['Content']);
                if($content == 'TESTCOMPONENT_MSG_TYPE_TEXT'){
                    echo $this->newTextMsg($fromUsername,$selfUsername,'TESTCOMPONENT_MSG_TYPE_TEXT_callback');
                }
                else if(preg_match('/QUERY_AUTH_CODE:(.+)/is',$content,$matches)){
                    $query_auth_code = $matches[1];
                    $auth_info = WeixinCompUtility::queryAuth($query_auth_code);

                    $access_token = $auth_info['authorization_info']['authorizer_access_token'];

                    WechatService::sendTextMsg($fromUsername, $query_auth_code.'_from_api',$access_token);
                }
            }
            exit;
        }

        if( !empty($wx_info) ){

            $wx_id = $wx_info->id;
            WechatService::$appId = $wx_info->oauth_appid;

            $ret = xml_to_array($postStr);
            $this->responseMsg( $wx_id , $ret, $wx_info->toArray() );
        }
        else{
            echo 'success';
            exit;
        }
//        echo $this->view->render('category/view.volt','view',array('Category',$item->toArray()));
    }

    private function url($str){
        if( strpos($str,'://')!== false ){
            return $str;
        }
        else{
            return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST']. $str;
        }
    }

    private function tableize($str){
        $dstr = preg_replace_callback('/([A-Z]+)/',function($matchs)
             {
                                return '_'.strtolower($matchs[0]);
             },$str);
        $str = trim(preg_replace('/_{2,}/','_',$dstr),'_');

        $plural = array(
            array( '/(quiz)$/i',       "$1zes"  ),
            array( '/^(ox)$/i',        "$1en"  ),
            array( '/([m|l])ouse$/i',     "$1ice"  ),
            array( '/(matr|vert|ind)ix|ex$/i',"$1ices" ),
            array( '/(x|ch|ss|sh)$/i',    "$1es"  ),
            array( '/([^aeiouy]|qu)y$/i',   "$1ies"  ),
            array( '/([^aeiouy]|qu)ies$/i',  "$1y"   ),
            array( '/(hive)$/i',       "$1s"   ),
            array( '/(?:([^f])fe|([lr])f)$/i',"$1$2ves" ),
            array( '/sis$/i',         "ses"   ),
            array( '/([ti])um$/i',      "$1a"   ),
            array( '/(buffal|tomat)o$/i',   "$1oes"  ),
            array( '/(bu)s$/i',        "$1ses"  ),
            array( '/(alias|status)$/i',   "$1es"  ),
            array( '/(octop|vir)us$/i',    "$1i"   ),
            array( '/(ax|test)is$/i',     "$1es"  ),
            array( '/s$/i',          "s"    ),
            array( '/$/',           "s"    )
        );

        foreach ( $plural as $pattern ){
            $newstr = preg_replace( $pattern[0], $pattern[1],$str );
            if($str!==null && $newstr!=$str){
                return $str;
            }
        }
    }


    protected function transferCustomerServiceMsg($toUser, $sender,$time){
        //$time = time();
        $msg = '<xml>'
            .'<ToUserName><![CDATA['.$toUser.']]></ToUserName>'
            .'<FromUserName><![CDATA['.$sender.']]></FromUserName>'
            .'<CreateTime>'.$time.'</CreateTime>'
            .'<MsgType><![CDATA[transfer_customer_service]]></MsgType>'
            .'</xml>';
        $this->getDI()->get('logger')->info("Response kf TextMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        return $msg;
    }
    /**
     * 回复文本消息
     * @param unknown $toUser 消息回复到的用户
     * @param unknown $sender 发送者即公众号本人的微信id
     * @param unknown $content 文字内容
     * @return string
     */
    protected function newTextMsg($toUser, $sender, $content){
        $time = time();
        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[text]]></MsgType>"
            ."<Content><![CDATA[$content]]></Content>"
            ."</xml>";
        //$this->getDI()->get('logger')->info("Response TextMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response TextMsg:".$msg);
        return $msg;
    }


    /**
     * 回复图片消息
     * @param unknown $toUser 消息回复到的用户
     * @param unknown $sender 发送者即公众号本人的微信id
     * @param unknown $content 文字内容
     * @return string
     */
    protected function newImageMsg($toUser, $sender, $media_id){
        $time = time();
        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[image]]></MsgType>"
            ."<Image>"
            ."<MediaId><![CDATA[$media_id]]></MediaId>"
            ."</Image>"
            ."</xml>";
        //$this->getDI()->get('logger')->info("Response ImageMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response ImageMsg:".$msg);
        return $msg;
    }

    /**
     * 回复语音消息
     * @param unknown $toUser 消息回复到的用户
     * @param unknown $sender 发送者即公众号本人的微信id
     * @param unknown $content 文字内容
     * @return string
     */
    protected function newVoiceMsg($toUser, $sender, $media_id){
        $time = time();
        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[voice]]></MsgType>"
            ."<Voice>"
            ."<MediaId><![CDATA[$media_id]]></MediaId>"
            ."</Voice>"
            ."</xml>";
        //$this->getDI()->get('logger')->info("Response VoiceMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response VoiceMsg:".$msg);
        return $msg;
    }

    /**
     * 回复视频消息
     * @param unknown $toUser 消息回复到的用户
     * @param unknown $sender 发送者即公众号本人的微信id
     * @param unknown $content 文字内容
     * @return string
     */
    protected function newVideoMsg($toUser, $sender, $media_id,$title = '',$description= ''){
        $time = time();
        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[video]]></MsgType>"
            ."<Video>"
            ."<MediaId><![CDATA[$media_id]]></MediaId>"
            ."<Title><![CDATA[$title]]></Title>"
            ."<Description><![CDATA[$description]]></Description>"
            ."</Video>"
            ."</xml>";
        //$this->getDI()->get('logger')->info("Response VideoMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response VideoMsg:".$msg);
        return $msg;
    }

    /**
     * 回复音乐消息
     * @param unknown $toUser 消息回复到的用户
     * @param unknown $sender 发送者即公众号本人的微信id
     * @param unknown $content 文字内容
     * @return string
     */
    protected function newMusicMsg($toUser, $sender, $media_id,$url,$title = '',$description= ''){
        $time = time();
        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[music]]></MsgType>"
            ."<Music>"
            ."<Title><![CDATA[$title]]></Title>"
            ."<Description><![CDATA[$description]]></Description>"
            ."<MusicUrl><![CDATA[$url]]></MusicUrl>"
            ."<HQMusicUrl><![CDATA[$url]]></HQMusicUrl>"
            ."<ThumbMediaId><![CDATA[$media_id]]></ThumbMediaId>"
            ."</Music>"
            ."</xml>";
        //$this->getDI()->get('logger')->info("Response MusicMsg:".$msg);
        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response MusicMsg:".$msg);
        return $msg;
    }
    private function cryptMsg($msg){
        if($this->encry_type == 'none'){
            return $msg;
        }
        else if($this->encry_type == 'develop') {
            $pc = new WXBizMsgCrypt($this->dev_vars['token'], $this->dev_vars['wxAESKey'], $this->dev_vars['wxAppId']);
        }
        else if($this->encry_type == 'component') {
            $pc = new WXBizMsgCrypt( COMPONENT_TOKEN, COMPONENT_AESKEY, COMPONENT_APPID );
        }
        $encryptMsg = '';
        $errCode = $pc->encryptMsg($msg, time(), random_str(9,'num'), $encryptMsg);
        return $encryptMsg;
    }

    // array = title => '', description => '', picUrl => '', url => ''
    protected function newArticleMsg($toUser, $sender, $array){
        $time = time();
        $len = count($array);
        $items = "";

        foreach($array as $it){
            $items .= "<item>"
                ."<Title><![CDATA[{$it['title']}]]></Title> "
                ."<Description><![CDATA[{$it['description']}]]></Description>"
                ."<PicUrl><![CDATA[{$it['picUrl']}]]></PicUrl>"
                ."<Url><![CDATA[{$it['url']}]]></Url>"
                ."</item>";

        }

        $msg = "<xml>"
            ."<ToUserName><![CDATA[$toUser]]></ToUserName>"
            ."<FromUserName><![CDATA[$sender]]></FromUserName>"
            ."<CreateTime>$time</CreateTime>"
            ."<MsgType><![CDATA[news]]></MsgType>"
            ."<ArticleCount>$len</ArticleCount>"
            ."<Articles>".$items."</Articles>"
            ."</xml>";

        $msg = $this->cryptMsg($msg);
        //$this->getDI()->get('logger')->info("Response ArticleMsg:".$msg);
        return $msg;
    }


    private function checkSignature($wx_id=0)
    {
        if($wx_id){
            $wx_info = Wx::findFirst(
                [
                    'conditions' => "id = :id:",
                    'bind' => ['id'=> $wx_id],
                    "cache" => [
                        "key"      => "wx-info-id".$wx_id,
                        "lifetime" => 300,
                    ],
                ]
            );

            $signature = $_GET["signature"];
            $timestamp = $_GET["timestamp"];
            $nonce = $_GET["nonce"];


            $settings = UserSetting::getSetting($wx_info->creator);
            $variables = $settings[$wx_id];
            $token = $variables['token'];

            $tmpArr = array($token, $timestamp, $nonce);
            sort($tmpArr,SORT_STRING);
            $tmpStr = implode( $tmpArr );
            $tmpStr = sha1( $tmpStr );

            if( $tmpStr == $signature ){
                return true;
            }else{
                return false;
            }
        }
        return false;


    }


}