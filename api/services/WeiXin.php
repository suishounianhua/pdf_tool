<?php
/**
 * Created by PhpStorm.
 * User: pangxb
 * Date: 17/5/18
 * Time: 上午9:21
 */

use Phalcon\Di;


class WeiXin
{

    const SX                                = '您的订单进度：';
    const WL                                = ' ';
    const HX                                = ' ';
    const YY                                = ' ';
    const QT                                = ' ';
    const TEXT                              = ' ';
    //CLICK
    const V1001_TODAY_SX                    = 'V1001_TODAY_SX';
    const V1001_TODAY_WL                    = 'V1001_TODAY_WL';
    const V1001_TODAY_HX                    = 'V1001_TODAY_HX';
    const V1001_TODAY_YY                    = 'V1001_TODAY_YY';
    const V1001_TODAY_QT                    = 'V1001_TODAY_QT';
    const V1001_TODAY_QD                    = 'V1001_TODAY_QD';

    const MSGTYPE_TEXT                      = 'text';
    const MSGTYPE_IMAGE                     = 'image';
    const MSGTYPE_LOCATION                  = 'location';
    const MSGTYPE_LINK                      = 'link';
    const MSGTYPE_EVENT                     = 'event';
    const MSGTYPE_MUSIC                     = 'music';
    const MSGTYPE_NEWS                      = 'news';
    const MSGTYPE_VOICE                     = 'voice';
    const MSGTYPE_VIDEO                     = 'video';
    const EVENT_SUBSCRIBE                   = 'subscribe';                  //订阅
    const EVENT_UNSUBSCRIBE                 = 'unsubscribe';                //取消订阅
    const EVENT_SCAN                        = 'SCAN';                       //扫描带参数二维码
    const EVENT_LOCATION                    = 'LOCATION';                   //上报地理位置
    const EVENT_MENU_VIEW                   = 'VIEW';                       //菜单 - 点击菜单跳转链接
    const EVENT_MENU_CLICK                  = 'CLICK';                      //菜单 - 点击菜单拉取消息
    const EVENT_MENU_SCAN_PUSH              = 'scancode_push';              //菜单 - 扫码推事件(客户端跳URL)
    const EVENT_MENU_SCAN_WAITMSG           = 'scancode_waitmsg';           //菜单 - 扫码推事件(客户端不跳URL)
    const EVENT_MENU_PIC_SYS                = 'pic_sysphoto';               //菜单 - 弹出系统拍照发图
    const EVENT_MENU_PIC_PHOTO              = 'pic_photo_or_album';         //菜单 - 弹出拍照或者相册发图
    const EVENT_MENU_PIC_WEIXIN             = 'pic_weixin';                 //菜单 - 弹出微信相册发图器
    const EVENT_MENU_LOCATION               = 'location_select';            //菜单 - 弹出地理位置选择器
    const EVENT_SEND_MASS                   = 'MASSSENDJOBFINISH';          //发送结果 - 高级群发完成
    const EVENT_SEND_TEMPLATE               = 'TEMPLATESENDJOBFINISH';      //发送结果 - 模板消息发送结果
    const EVENT_KF_SEESION_CREATE           = 'kfcreatesession';            //多客服 - 接入会话
    const EVENT_KF_SEESION_CLOSE            = 'kfclosesession';             //多客服 - 关闭会话
    const EVENT_KF_SEESION_SWITCH           = 'kfswitchsession';            //多客服 - 转接会话
    const EVENT_CARD_PASS                   = 'card_pass_check';            //卡券 - 审核通过
    const EVENT_CARD_NOTPASS                = 'card_not_pass_check';        //卡券 - 审核未通过
    const EVENT_CARD_USER_GET               = 'user_get_card';              //卡券 - 用户领取卡券
    const EVENT_CARD_USER_DEL               = 'user_del_card';              //卡券 - 用户删除卡券
    const API_URL_PREFIX                    = 'https://api.weixin.qq.com/cgi-bin';
    const AUTH_URL                          = '/token?grant_type=client_credential&';
    const MENU_CREATE_URL                   = '/menu/create?';
    const MENU_GET_URL                      = '/menu/get?';
    const MENU_DELETE_URL                   = '/menu/delete?';
    const GET_TICKET_URL                    = '/ticket/getticket?';
    const CALLBACKSERVER_GET_URL            = '/getcallbackip?';
    const QRCODE_CREATE_URL                 ='/qrcode/create?';
    const QR_SCENE                          = 0;
    const QR_LIMIT_SCENE                    = 1;
    const QRCODE_IMG_URL                    ='https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=';
    const SHORT_URL                         ='/shorturl?';
    const USER_GET_URL                      ='/user/get?';
    const USER_INFO_URL                     ='/user/info?';
    const USER_UPDATEREMARK_URL             ='/user/info/updateremark?';
    const GROUP_GET_URL                     ='/groups/get?';
    const USER_GROUP_URL                    ='/groups/getid?';
    const GROUP_CREATE_URL                  ='/groups/create?';
    const GROUP_UPDATE_URL                  ='/groups/update?';
    const GROUP_MEMBER_UPDATE_URL           ='/groups/members/update?';
    const GROUP_MEMBER_BATCHUPDATE_URL      ='/groups/members/batchupdate?';
    const CUSTOM_SEND_URL                   ='/message/custom/send?';
    const MEDIA_UPLOADNEWS_URL              = '/media/uploadnews?';
    const MASS_SEND_URL                     = '/message/mass/send?';
    const TEMPLATE_SET_INDUSTRY_URL         = '/message/template/api_set_industry?';
    const TEMPLATE_ADD_TPL_URL              = '/message/template/api_add_template?';
    const TEMPLATE_SEND_URL                 = '/message/template/send?';
    const MASS_SEND_GROUP_URL               = '/message/mass/sendall?';
    const MASS_DELETE_URL                   = '/message/mass/delete?';
    const MASS_PREVIEW_URL                  = '/message/mass/preview?';
    const MASS_QUERY_URL                    = '/message/mass/get?';
    const UPLOAD_MEDIA_URL                  = 'http://file.api.weixin.qq.com/cgi-bin';
    const MEDIA_UPLOAD_URL                  = '/media/upload?';
    const MEDIA_GET_URL                     = '/media/get?';
    const MEDIA_VIDEO_UPLOAD                = '/media/uploadvideo?';
    const OAUTH_PREFIX                      = 'https://open.weixin.qq.com/connect/oauth2';
    const OAUTH_AUTHORIZE_URL               = '/authorize?';
    ///多客服相关地址
    const CUSTOM_SERVICE_GET_RECORD         = '/customservice/getrecord?';
    const CUSTOM_SERVICE_GET_KFLIST         = '/customservice/getkflist?';
    const CUSTOM_SERVICE_GET_ONLINEKFLIST   = '/customservice/getonlinekflist?';
    const API_BASE_URL_PREFIX               = 'https://api.weixin.qq.com'; //以下API接口URL需要使用此前缀
    const OAUTH_TOKEN_URL                   = '/sns/oauth2/access_token?';
    const OAUTH_REFRESH_URL                 = '/sns/oauth2/refresh_token?';
    const OAUTH_USERINFO_URL                = '/sns/userinfo?';
    const OAUTH_AUTH_URL                    = '/sns/auth?';
    ///多客服相关地址
    const CUSTOM_SESSION_CREATE             = '/customservice/kfsession/create?';
    const CUSTOM_SESSION_CLOSE              = '/customservice/kfsession/close?';
    const CUSTOM_SESSION_SWITCH             = '/customservice/kfsession/switch?';
    const CUSTOM_SESSION_GET                = '/customservice/kfsession/getsession?';
    const CUSTOM_SESSION_GET_LIST           = '/customservice/kfsession/getsessionlist?';
    const CUSTOM_SESSION_GET_WAIT           = '/customservice/kfsession/getwaitcase?';
    const CS_KF_ACCOUNT_ADD_URL             = '/customservice/kfaccount/add?';
    const CS_KF_ACCOUNT_UPDATE_URL          = '/customservice/kfaccount/update?';
    const CS_KF_ACCOUNT_DEL_URL             = '/customservice/kfaccount/del?';
    const CS_KF_ACCOUNT_UPLOAD_HEADIMG_URL  = '/customservice/kfaccount/uploadheadimg?';
    ///卡券相关地址
    const CARD_CREATE                       = '/card/create?';
    const CARD_DELETE                       = '/card/delete?';
    const CARD_UPDATE                       = '/card/update?';
    const CARD_GET                          = '/card/get?';
    const CARD_BATCHGET                     = '/card/batchget?';
    const CARD_MODIFY_STOCK                 = '/card/modifystock?';
    const CARD_LOCATION_BATCHADD            = '/card/location/batchadd?';
    const CARD_LOCATION_BATCHGET            = '/card/location/batchget?';
    const CARD_GETCOLORS                    = '/card/getcolors?';
    const CARD_QRCODE_CREATE                = '/card/qrcode/create?';
    const CARD_CODE_CONSUME                 = '/card/code/consume?';
    const CARD_CODE_DECRYPT                 = '/card/code/decrypt?';
    const CARD_CODE_GET                     = '/card/code/get?';
    const CARD_CODE_UPDATE                  = '/card/code/update?';
    const CARD_CODE_UNAVAILABLE             = '/card/code/unavailable?';
    const CARD_TESTWHILELIST_SET            = '/card/testwhitelist/set?';
    const CARD_MEMBERCARD_ACTIVATE          = '/card/membercard/activate?';      //激活会员卡
    const CARD_MEMBERCARD_UPDATEUSER        = '/card/membercard/updateuser?';    //更新会员卡
    const CARD_MOVIETICKET_UPDATEUSER       = '/card/movieticket/updateuser?';   //更新电影票(未加方法)
    const CARD_BOARDINGPASS_CHECKIN         = '/card/boardingpass/checkin?';     //飞机票-在线选座(未加方法)
    const CARD_LUCKYMONEY_UPDATE            = '/card/luckymoney/updateuserbalance?';     //更新红包金额
    const SEMANTIC_API_URL                  = '/semantic/semproxy/search?'; //语义理解
    //素材
    const MEDIA_FOREVER_UPLOAD_URL = '/material/add_material?';
    const MEDIA_FOREVER_NEWS_UPLOAD_URL = '/material/add_news?';
    const MEDIA_FOREVER_NEWS_UPDATE_URL = '/material/update_news?';
    const MEDIA_FOREVER_GET_URL = '/material/get_material?';
    const MEDIA_FOREVER_DEL_URL = '/material/del_material?';
    const MEDIA_FOREVER_COUNT_URL = '/material/get_materialcount?';
    const MEDIA_FOREVER_BATCHGET_URL = '/material/batchget_material?';
    ///数据分析接口
    static $DATACUBE_URL_ARR = array(        //用户分析
        'user' => array(
            'summary' => '/datacube/getusersummary?',		//获取用户增减数据（getusersummary）
            'cumulate' => '/datacube/getusercumulate?',		//获取累计用户数据（getusercumulate）
        ),
        'article' => array(            //图文分析
            'summary' => '/datacube/getarticlesummary?',		//获取图文群发每日数据（getarticlesummary）
            'total' => '/datacube/getarticletotal?',		//获取图文群发总数据（getarticletotal）
            'read' => '/datacube/getuserread?',			//获取图文统计数据（getuserread）
            'readhour' => '/datacube/getuserreadhour?',		//获取图文统计分时数据（getuserreadhour）
            'share' => '/datacube/getusershare?',			//获取图文分享转发数据（getusershare）
            'sharehour' => '/datacube/getusersharehour?',		//获取图文分享转发分时数据（getusersharehour）
        ),
        'upstreammsg' => array(        //消息分析
            'summary' => '/datacube/getupstreammsg?',		//获取消息发送概况数据（getupstreammsg）
            'hour' => '/datacube/getupstreammsghour?',	//获取消息分送分时数据（getupstreammsghour）
            'week' => '/datacube/getupstreammsgweek?',	//获取消息发送周数据（getupstreammsgweek）
            'month' => '/datacube/getupstreammsgmonth?',	//获取消息发送月数据（getupstreammsgmonth）
            'dist' => '/datacube/getupstreammsgdist?',	//获取消息发送分布数据（getupstreammsgdist）
            'distweek' => '/datacube/getupstreammsgdistweek?',	//获取消息发送分布周数据（getupstreammsgdistweek）
            'distmonth' => '/datacube/getupstreammsgdistmonth?',	//获取消息发送分布月数据（getupstreammsgdistmonth）
        ),
        'interface' => array(        //接口分析
            'summary' => '/datacube/getinterfacesummary?',	//获取接口分析数据（getinterfacesummary）
            'summaryhour' => '/datacube/getinterfacesummaryhour?',	//获取接口分析分时数据（getinterfacesummaryhour）
        )
    );


    private $token;
    private $encodingAesKey;
    private $encrypt_type;
    private $appid;
    private $appsecret;
    private $access_token;
    private $jsapi_ticket;
    private $user_token;
    private $partnerid;
    private $partnerkey;
    private $paysignkey;
    private $postxml;
    private $_msg;
    private $_funcflag = false;
    private $_receive;
    private $_text_filter = true;
    public $debug = false;
    public $errCode = 40001;
    public $errMsg = "no access";
    public $logcallback;

    public function __construct($options)
    {
        $this->token = isset($options['token']) ? $options['token'] : '';
        $this->encodingAesKey = isset($options['encodingaeskey']) ? $options['encodingaeskey'] : '';
        $this->appid = isset($options['appid']) ? $options['appid'] : '';
        $this->appsecret = isset($options['appsecret']) ? $options['appsecret'] : '';
    }

    /**
     * For weixin server validation
     */
    private function checkSignature($signature, $timestamp, $nonce, $str = '')
    {
        /* $signature = isset($_GET["signature"])?$_GET["signature"]:'';
         $signature = isset($_GET["msg_signature"])?$_GET["msg_signature"]:$signature; //如果存在加密验证则用加密验证段
         $timestamp = isset($_GET["timestamp"])?$_GET["timestamp"]:'';
         $nonce = isset($_GET["nonce"])?$_GET["nonce"]:'';*/

        $token = $this->token;
        $tmpArr = array($token, $timestamp, $nonce, $str);
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' tmpArr : ' . json_encode($tmpArr));
        sort($tmpArr,SORT_STRING);
        $tmpStr = implode('', $tmpArr);
        $tmpStr = sha1($tmpStr);
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' tmpStr : ' . $tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * For weixin server validation
     * @param bool $return 是否返回
     */
    public function valid($return = false, $signature, $timestamp, $nonce, $echostr, $method)
    {
        $encryptStr = "";
        if ($method == "POST") {
            $postStr = file_get_contents("php://input");
            $array = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            // $this->encrypt_type = isset($_GET["encrypt_type"]) ? $_GET["encrypt_type"]: '';
            $this->encrypt_type = '';
            if ($this->encrypt_type == 'aes') { //aes加密
                Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' postStr: ' . $postStr);
                $encryptStr = $array['Encrypt'];
                $pc = new Prpcrypt($this->encodingAesKey);
                $array = $pc->decrypt($encryptStr, $this->appid);
              //  Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' after postStr pc decrypt : ' . json_encode($array));
                if (!isset($array[0]) || ($array[0] != 0)) {
                    if (!$return) {
                        die('decrypt error!');
                    } else {
                        return false;
                    }
                }
                $this->postxml = $array[1];
                if (!$this->appid)
                    $this->appid = $array[2];//为了没有appid的订阅号。
            } else {
                $this->postxml = $postStr;
            }
        } elseif ($echostr) {
            $echoStr = $echostr;
            if ($return) {
               // Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' 333333 : '.$signature." "." ".$timestamp." ".$nonce);
                if ($this->checkSignature($signature, $timestamp, $nonce)){
                    return $echoStr;
                }else{
                    return false;
                }
            } else {
                if ($this->checkSignature($signature, $timestamp, $nonce)){
                    die($echoStr);
                }else{
                    die('no access1');
                }
            }
        }
        if (!$this->checkSignature($signature, $timestamp, $nonce, $encryptStr)) {
            if ($return)
                return false;
            else
                die('no access2');
        }
        return true;
    }


    /**
     * 获取微信服务器IP地址列表
     * @return array('127.0.0.1','127.0.0.1')
     */
    public function getServerIp()
    {
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_get(self::API_URL_PREFIX . self::CALLBACKSERVER_GET_URL . 'access_token=' . $this->access_token);
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || isset($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json['ip_list'];
        }
        return false;
    }

    /**
     * 获取微信服务器发来的信息
     */
    public function getRev()
    {
        if ($this->_receive){
            return $this;
        }
        $postStr = !empty($this->postxml) ? $this->postxml : file_get_contents("php://input");
        //兼顾使用明文又不想调用valid()方法的情况
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' postStr: ' . $postStr);
        if (!empty($postStr)) {
            $this->_receive = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        return $this;
    }
    /**
     * 获取接收消息的类型
     */
    public function getRevType()
    {
        if (isset($this->_receive['MsgType'])){
            return $this->_receive['MsgType'];
        }else{
            return false;
        }
    }

    /**
     * 获取微信服务器发来的信息
     */
    public function getRevData()
    {
        return $this->_receive;
    }

    /**
     * 获取消息发送者
     */
    public function getRevFrom()
    {
        if (isset($this->_receive['FromUserName']))
            return $this->_receive['FromUserName'];
        else
            return false;
    }

    /**
     * 获取消息接受者
     */
    public function getRevTo()
    {
        if (isset($this->_receive['ToUserName']))
            return $this->_receive['ToUserName'];
        else
            return false;
    }

    /**
     * 获取接收事件推送
     */
    public function getRevEvent(){
        if (isset($this->_receive['Event'])){
            $array['event'] = $this->_receive['Event'];
        }
        if (isset($this->_receive['EventKey'])){
            $array['key'] = $this->_receive['EventKey'];
        }
        if (isset($array) && count($array) > 0) {
            return $array;
        } else {
            return false;
        }
    }

    /**
     * 获取消息ID
     */
    public function getRevID()
    {
        if (isset($this->_receive['MsgId']))
            return $this->_receive['MsgId'];
        else
            return false;
    }

    /**
     * 获取消息发送时间
     */
    public function getRevCtime()
    {
        if (isset($this->_receive['CreateTime']))
            return $this->_receive['CreateTime'];
        else
            return false;
    }

    /**
     * 获取接收消息内容正文
     */
    public function getRevContent()
    {
        if (isset($this->_receive['Content']))
            return $this->_receive['Content'];
        else if (isset($this->_receive['Recognition'])) //获取语音识别文字内容，需申请开通
            return $this->_receive['Recognition'];
        else
            return false;
    }

    /**
     * 获取接收消息图片
     */
    public function getRevPic()
    {
        if (isset($this->_receive['PicUrl']))
            return array(
                'mediaid' => $this->_receive['MediaId'],
                'picurl' => (string)$this->_receive['PicUrl'],    //防止picurl为空导致解析出错
            );
        else
            return false;
    }

    /**
     * 获取接收消息链接
     */
    public function getRevLink()
    {
        if (isset($this->_receive['Url'])) {
            return array(
                'url' => $this->_receive['Url'],
                'title' => $this->_receive['Title'],
                'description' => $this->_receive['Description']
            );
        } else
            return false;
    }

    public static function xmlSafeStr($str)
    {
        return '<![CDATA[' . preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $str) . ']]>';
    }

    /**
     * 数据XML编码
     * @param mixed $data 数据
     * @return string
     */
    public static function data_to_xml($data)
    {
        $xml = '';
        foreach ($data as $key => $val) {
            is_numeric($key) && $key = "item id=\"$key\"";
            $xml .= "<$key>";
            $xml .= (is_array($val) || is_object($val)) ? self::data_to_xml($val) : self::xmlSafeStr($val);
            list($key,) = explode(' ', $key);
            $xml .= "</$key>";
        }
        return $xml;
    }

    /**
     * XML编码
     * @param mixed $data 数据
     * @param string $root 根节点名
     * @param string $item 数字索引的子节点名
     * @param string $attr 根节点属性
     * @param string $id 数字索引子节点key转换的属性名
     * @param string $encoding 数据编码
     * @return string
     */
    public function xml_encode($data, $root = 'xml', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
    {
        if (is_array($attr)) {
            $_attr = array();
            foreach ($attr as $key => $value) {
                $_attr[] = "{$key}=\"{$value}\"";
            }
            $attr = implode(' ', $_attr);
        }
        $attr = trim($attr);
        $attr = empty($attr) ? '' : " {$attr}";
        $xml = "<{$root}{$attr}>";
        $xml .= self::data_to_xml($data, $item, $id);
        $xml .= "</{$root}>";
        return $xml;
    }

    /**
     * 设置回复图文
     * @param array $newsData
     * 数组结构:
     *  array(
     *  	"0"=>array(
     *  		'Title'=>'msg title',
     *  		'Description'=>'summary text',
     *  		'PicUrl'=>'http://www.domain.com/1.jpg',
     *  		'Url'=>'http://www.domain.com/1.html'
     *  	),
     *  	"1"=>....
     *  )
     */
    public function news($newsData=array())
    {
        $FuncFlag = $this->_funcflag ? 1 : 0;
        $count = count($newsData);

        $msg = array(
            'ToUserName' => $this->getRevFrom(),
            'FromUserName'=>$this->getRevTo(),
            'MsgType'=>self::MSGTYPE_NEWS,
            'CreateTime'=>time(),
            'ArticleCount'=>$count,
            'Articles'=>$newsData,
            'FuncFlag'=>$FuncFlag
        );
        $this->Message($msg);
        return $this;
    }

    /**
     * 获取关注者详细信息
     * @param string $openid
     * @return array {subscribe,openid,nickname,sex,city,province,country,language,headimgurl,subscribe_time,[unionid]}
     * 注意：unionid字段 只有在用户将公众号绑定到微信开放平台账号后，才会出现。建议调用前用isset()检测一下
     */
    public function getUserInfo($openid){
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_get(self::API_URL_PREFIX.self::USER_INFO_URL.'access_token='.$this->access_token.'&openid='.$openid);
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 过滤文字回复\r\n换行符
     * @param string $text
     * @return string|mixed
     */
    private function _auto_text_filter($text)
    {
        if (!$this->_text_filter) return $text;
        return str_replace("\r\n", "\n", $text);
    }

    /**
     * 设置回复消息
     * Example: $obj->text('hello')->reply();
     * @param string $text
     */
    public function text($text = '')
    {
        $FuncFlag = $this->_funcflag ? 1 : 0;
        $msg = array(
            'ToUserName' => $this->getRevFrom(),
            'FromUserName' => $this->getRevTo(),
            'MsgType' => self::MSGTYPE_TEXT,
            'Content' => $this->_auto_text_filter($text),
            'CreateTime' => time(),
            'FuncFlag' => $FuncFlag
        );
        $this->Message($msg);
        return $this;
    }

    /**
     * 设置发送消息
     * @param array $msg 消息数组
     * @param bool $append 是否在原消息数组追加
     */
    public function Message($msg = '', $append = false)
    {
        if (is_null($msg)) {
            $this->_msg = array();
        } elseif (is_array($msg)) {
            if ($append)
                $this->_msg = array_merge($this->_msg, $msg);
            else
                $this->_msg = $msg;
            return $this->_msg;
        } else {
            return $this->_msg;
        }
    }

    /**
     *
     * 回复微信服务器, 此函数支持链式操作
     * Example: $this->text('msg tips')->reply();
     * @param string $msg 要发送的信息, 默认取$this->_msg
     * @param bool $return 是否返回信息而不抛出到浏览器 默认:否
     */
    public function reply($msg = array(), $return = false)
    {
        if (empty($msg)) {
            if (empty($this->_msg))   //防止不先设置回复内容，直接调用reply方法导致异常
                return false;
            $msg = $this->_msg;
        }
        $xmldata = $this->xml_encode($msg);
        //$this->log($xmldata);
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' xmldata: ' . $xmldata);
        if ($this->encrypt_type == 'aes') { //如果来源消息为加密方式
            $pc = new Prpcrypt($this->encodingAesKey);
            $array = $pc->encrypt($xmldata, $this->appid);
            $ret = $array[0];
            if ($ret != 0) {
                // $this->log('encrypt err!');
                Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' encrypt err! ');
                return false;
            }
            $timestamp = time();
            $nonce = rand(77, 999) * rand(605, 888) * rand(11, 99);
            $encrypt = $array[1];
            $tmpArr = array($this->token, $timestamp, $nonce, $encrypt);//比普通公众平台多了一个加密的密文
            sort($tmpArr, SORT_STRING);
            $signature = implode($tmpArr);
            $signature = sha1($signature);
            $xmldata = $this->generate($encrypt, $signature, $timestamp, $nonce);
            // $this->log($xmldata);
            Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' xmldata2 :' . $xmldata);
        }
        if ($return)
            return $xmldata;
        else
            echo $xmldata;
    }

    /**
     * GET 请求
     * @param string $url
     */
    private function http_get($url)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $param
     * @param boolean $post_file 是否文件上传
     * @return string content
     */
    private function http_post($url, $param, $post_file = false)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }


    /**
     * 获取access_token
     * @param string $appid 如在类初始化时已提供，则可为空
     * @param string $appsecret 如在类初始化时已提供，则可为空
     * @param string $token 手动指定access_token，非必要情况不建议用
     */
    public function checkAuth($appid = '', $appsecret = '', $token = '')
    {
        if (!$appid || !$appsecret) {
            $appid = $this->appid;
            $appsecret = $this->appsecret;
        }
        if ($token) { //手动指定token，优先使用
            $this->access_token = $token;
            return $this->access_token;
        }

        //$authname = 'wechat_access_token'.$appid;
        $authname = $appid;
        if ($rs = Di::getDefault()->getRedisCache()->get($authname)) {
            $this->access_token = $rs;
            return $rs;
        }

        $result = $this->http_get(self::API_URL_PREFIX . self::AUTH_URL . 'appid=' . $appid . '&secret=' . $appsecret);
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || isset($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->access_token = $json['access_token'];
            $expire = $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600;
            Di::getDefault()->getRedisCache()->set($authname, $this->access_token, $expire);
            return $this->access_token;
        }
        return false;
    }

    /**
     * 删除验证数据
     * @param string $appid
     */
    public function resetAuth($appid = '')
    {
        if (!$appid) $appid = $this->appid;
        $this->access_token = '';
        $authname = 'wechat_access_token' . $appid;
        $this->removeCache($authname);
        return true;
    }

    /**
     * 删除JSAPI授权TICKET
     * @param string $appid 用于多个appid时使用
     */
    public function resetJsTicket($appid = '')
    {
        if (!$appid) $appid = $this->appid;
        $this->jsapi_ticket = '';
        $authname = 'wechat_jsapi_ticket' . $appid;
        $this->removeCache($authname);
        return true;
    }

    /**
     * 获取JSAPI授权TICKET
     * @param string $appid 用于多个appid时使用,可空
     * @param string $jsapi_ticket 手动指定jsapi_ticket，非必要情况不建议用
     */
    public function getJsTicket($appid = '', $jsapi_ticket = '')
    {
        if (!$this->access_token && !$this->checkAuth()) return false;
        if (!$appid) $appid = $this->appid;
        if ($jsapi_ticket) { //手动指定token，优先使用
            $this->jsapi_ticket = $jsapi_ticket;
            return $this->jsapi_ticket;
        }
        $authname = 'wechat_jsapi_ticket' . $appid;
        if ($rs = Di::getDefault()->getRedisCache()->get($authname)) {
            $this->jsapi_ticket = $rs;
            return $rs;
        }
        $result = $this->http_get(self::API_URL_PREFIX . self::GET_TICKET_URL . 'access_token=' . $this->access_token . '&type=jsapi');
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->jsapi_ticket = $json['ticket'];
            $expire = $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600;
            Di::getDefault()->getRedisCache()->set($authname, $this->jsapi_ticket, $expire);
            return $this->jsapi_ticket;
        }
        return false;
    }


    /**
     * 获取JsApi使用签名
     * @param string $url 网页的URL，自动处理#及其后面部分
     * @param string $timestamp 当前时间戳 (为空则自动生成)
     * @param string $noncestr 随机串 (为空则自动生成)
     * @param string $appid 用于多个appid时使用,可空
     * @return array|bool 返回签名字串
     */
    public function getJsSign($url, $timestamp = 0, $noncestr = '', $appid = '')
    {
        if (!$this->jsapi_ticket && !$this->getJsTicket($appid) || !$url) return false;
        if (!$timestamp)
            $timestamp = time();
        if (!$noncestr)
            $noncestr = $this->generateNonceStr();
        $ret = strpos($url, '#');
        if ($ret)
            $url = substr($url, 0, $ret);
        $url = trim($url);
        if (empty($url))
            return false;
        $arrdata = array("timestamp" => $timestamp, "noncestr" => $noncestr, "url" => $url, "jsapi_ticket" => $this->jsapi_ticket);
        $sign = $this->getSignature($arrdata);
        if (!$sign)
            return false;
        $signPackage = array(
            "appid" => $this->appid,
            "noncestr" => $noncestr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $sign
        );
        return $signPackage;
    }

    /**
     * 微信api不支持中文转义的json结构
     * @param array $arr
     */
    static function json_encode($arr)
    {
        $parts = array();
        $is_list = false;
        //Find out if the given array is a numerical array
        $keys = array_keys($arr);
        $max_length = count($arr) - 1;
        if (($keys [0] === 0) && ($keys [$max_length] === $max_length)) { //See if the first key is 0 and last key is length - 1
            $is_list = true;
            for ($i = 0; $i < count($keys); $i++) { //See if each key correspondes to its position
                if ($i != $keys [$i]) { //A key fails at position check.
                    $is_list = false; //It is an associative array.
                    break;
                }
            }
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) { //Custom handling for arrays
                if ($is_list)
                    $parts [] = self::json_encode($value); /* :RECURSION: */
                else
                    $parts [] = '"' . $key . '":' . self::json_encode($value); /* :RECURSION: */
            } else {
                $str = '';
                if (!$is_list)
                    $str = '"' . $key . '":';
                //Custom handling for multiple data types
                if (!is_string($value) && is_numeric($value) && $value < 2000000000)
                    $str .= $value; //Numbers
                elseif ($value === false)
                    $str .= 'false'; //The booleans
                elseif ($value === true)
                    $str .= 'true';
                else
                    $str .= '"' . addslashes($value) . '"'; //All other things
                // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
                $parts [] = $str;
            }
        }
        $json = implode(',', $parts);
        if ($is_list)
            return '[' . $json . ']'; //Return numerical JSON
        return '{' . $json . '}'; //Return associative JSON
    }

    /**
     * 获取签名
     * @param array $arrdata 签名数组
     * @param string $method 签名方法
     * @return boolean|string 签名值
     */
    public function getSignature($arrdata, $method = "sha1")
    {
        if (!function_exists($method)) return false;
        ksort($arrdata);
        $paramstring = "";
        foreach ($arrdata as $key => $value) {
            if (strlen($paramstring) == 0)
                $paramstring .= $key . "=" . $value;
            else
                $paramstring .= "&" . $key . "=" . $value;
        }
        $Sign = $method($paramstring);
        return $Sign;
    }

    /**
     * 生成随机字串
     * @param number $length 长度，默认为16，最长为32字节
     * @return string
     */
    public function generateNonceStr($length = 16)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }


    /**
     * 创建菜单(认证后的订阅号可用)
     * @param array $data 菜单数组数据
     * example:
     *    array (
     *        'button' => array (
     *          0 => array (
     *            'name' => '扫码',
     *            'sub_button' => array (
     *                0 => array (
     *                  'type' => 'scancode_waitmsg',
     *                  'name' => '扫码带提示',
     *                  'key' => 'rselfmenu_0_0',
     *                ),
     *                1 => array (
     *                  'type' => 'scancode_push',
     *                  'name' => '扫码推事件',
     *                  'key' => 'rselfmenu_0_1',
     *                ),
     *            ),
     *          ),
     *          1 => array (
     *            'name' => '发图',
     *            'sub_button' => array (
     *                0 => array (
     *                  'type' => 'pic_sysphoto',
     *                  'name' => '系统拍照发图',
     *                  'key' => 'rselfmenu_1_0',
     *                ),
     *                1 => array (
     *                  'type' => 'pic_photo_or_album',
     *                  'name' => '拍照或者相册发图',
     *                  'key' => 'rselfmenu_1_1',
     *                )
     *            ),
     *          ),
     *          2 => array (
     *            'type' => 'location_select',
     *            'name' => '发送位置',
     *            'key' => 'rselfmenu_2_0'
     *          ),
     *        ),
     *    )
     * type可以选择为以下几种，其中5-8除了收到菜单事件以外，还会单独收到对应类型的信息。
     * 1、click：点击推事件
     * 2、view：跳转URL
     * 3、scancode_push：扫码推事件
     * 4、scancode_waitmsg：扫码推事件且弹出“消息接收中”提示框
     * 5、pic_sysphoto：弹出系统拍照发图
     * 6、pic_photo_or_album：弹出拍照或者相册发图
     * 7、pic_weixin：弹出微信相册发图器
     * 8、location_select：弹出地理位置选择器
     */
    public function createMenu($data)
    {
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' create menu data : ' . json_encode($data));
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_post(self::API_URL_PREFIX . self::MENU_CREATE_URL . 'access_token=' . $this->access_token, self::json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 获取菜单(认证后的订阅号可用)
     * @return array('menu'=>array(....s))
     */
    public function getMenu()
    {
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_get(self::API_URL_PREFIX . self::MENU_GET_URL . 'access_token=' . $this->access_token);
        Di::getDefault()->get('wlogger')->debug(__FUNCTION__ . ' get menu data : ' . json_encode($result));
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || isset($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 删除菜单(认证后的订阅号可用)
     * @return boolean
     */
    public function deleteMenu()
    {
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_get(self::API_URL_PREFIX . self::MENU_DELETE_URL . 'access_token=' . $this->access_token);
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 发送模板消息
     * @param array $data 消息结构
     * ｛
    "touser":"OPENID",
    "template_id":"ngqIpbwh8bUfcSsECmogfXcV14J0tQlEpBO27izEYtY",
    "url":"http://weixin.qq.com/download",
    "topcolor":"#FF0000",
    "data":{
    "参数名1": {
    "value":"参数",
    "color":"#173177"    //参数颜色
    },
    "Date":{
    "value":"06月07日 19时24分",
    "color":"#173177"
    },
    "CardNumber":{
    "value":"0426",
    "color":"#173177"
    }
    "Type":{
    "value":"消费",
    "color":"#173177"
    }
    }
    }@
     * @return boolean|array
     */
    public function sendTemplateMessage($data){
        if (!$this->access_token && !$this->checkAuth()) return false;
        $result = $this->http_post(self::API_URL_PREFIX.self::TEMPLATE_SEND_URL.'access_token='.$this->access_token,self::json_encode($data));
        if($result){
            $json = json_decode($result,true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }


}




