<?php
/**
* 	配置账号信息
*/
use Phalcon\Di;

class WxPayConf_pub
{
    public static function  init() {
        self::$APPID = Di::getDefault()->get('config')->get('WechatPay')->get('appid');
        self::$APPSECRET = Di::getDefault()->get('config')->get('WechatPay')->get('appsecret');
        self::$MCHID = Di::getDefault()->get('config')->get('WechatPay')->get('mch_id');
        self::$KEY = Di::getDefault()->get('config')->get('WechatPay')->get('key');
        self::$NOTIFY_URL = Di::getDefault()->get('config')->get('WechatPay')->get('notify_url');
        $cert_pem = Di::getDefault()->get('config')->get('WechatPay')->get('sslcert_pem');

//        self::$APPID = Configure::read('WechatPay.appid');
//
//        self::$APPSECRET = Configure::read('WechatPay.appsecret');
//
//        self::$MCHID = Configure::read('WechatPay.mch_id');
//        self::$KEY = Configure::read('WechatPay.key');
//        self::$NOTIFY_URL = Configure::read('WechatPay.notify_url');
//        $cert_pem = Configure::read('WechatPay.sslcert_pem');
        if($cert_pem) {
//            self::$SSLCERT_PATH = ROOT .DS. $cert_pem;
//            self::$SSLKEY_PATH = ROOT .DS. Configure::read('WechatPay.sslkey_pem');

            self::$SSLCERT_PATH = APP_PATH.'/'.$cert_pem;
            self::$SSLKEY_PATH = APP_PATH .'/'. Di::getDefault()->get('config')->get('WechatPay')->get('sslkey_pem');
        }

//        self::$SERVER_MCHID = Configure::read('WechatPay.service_mch_id');
//        $service_cert_pem = Configure::read('WechatPay.service_sslcert_pem');

        self::$SERVER_MCHID =  Di::getDefault()->get('config')->get('WechatPay')->get('service_mch_id');
        $service_cert_pem = Di::getDefault()->get('config')->get('WechatPay')->get('service_sslcert_pem');
        if($service_cert_pem) {
//            self::$SERVER_SSLCERT_PATH = ROOT .DS. $service_cert_pem;
//            self::$SERVER_SSLKEY_PATH = ROOT .DS. Configure::read('WechatPay.service_sslkey_pem');

            self::$SERVER_SSLCERT_PATH  = APP_PATH.'/'.$service_cert_pem;
            self::$SERVER_SSLKEY_PATH = APP_PATH.'/'.Di::getDefault()->get('config')->get('WechatPay')->get('service_sslkey_pem');

        }
    }
	//=======【基本信息设置】=====================================
	//微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
	public static $APPID = 'wx75c51a23fad76ecc';
	//受理商ID，身份标识。 135平台公众号支付商户
    public static $MCHID = '1225916502';
	// 服务商商户编号

    //服务商的密钥key与公众号支付的密钥key设为一致。
	//商户支付密钥Key。审核通过后，在微信发送的邮件中查看
    public static $KEY = '4wx4zH9hP6cB7cEMqUaSZSuQV2BdKzem';
	//JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
    public static $APPSECRET = 'c91c535c32a972b62f0ebedcea0a110e';
	
	//=======【JSAPI路径设置】===================================
	//获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
    public static $JS_API_CALL_URL = 'http://www.xxxxxx.com/demo/js_api_call.php';
	
	//=======【证书路径设置】=====================================
	//证书路径,注意应该填写绝对路径
    public static $SSLCERT_PATH = APP_PATH . '/vendor/tencent/WxPayPubHelper/cacert/apiclient_cert.pem';
    public static $SSLKEY_PATH = APP_PATH . '/vendor/tencent/WxPayPubHelper/cacert/apiclient_key.pem';

    public static $SERVER_MCHID = '1351327301';
    public static $SERVER_SSLCERT_PATH = APP_PATH . '/vendor/tencent/WxPayPubHelper/cacert/service_cert.pem';
    public static $SERVER_SSLKEY_PATH = APP_PATH . '/vendor/tencent/WxPayPubHelper/cacert/service_key.pem';

    //=======【异步通知url设置】===================================
	//异步通知url，商户根据实际开发过程设定
    public static $NOTIFY_URL = '' ; //'http://www.135plat.com/orders/wxpay_notify';

	//=======【curl超时设置】===================================
	//本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
    public static $CURL_TIMEOUT = 30;
}

WxPayConf_pub::init();
