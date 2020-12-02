<?php
/**
 * Services are globally registered in this file
 *
 * @var \Phalcon\Config $config
 */

use Phalcon\Cache\Backend\Memory as BackMemory;
use Phalcon\Cache\Backend\Redis as BackRedis;
use Phalcon\Cache\Frontend\Data as FrontData;
use Phalcon\Crypt;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger;
use Phalcon\Mvc\Dispatcher as MvcDispatcher;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Security;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Session\Adapter\Files as SessionAdapter;
use Phalcon\Http\Response\Cookies;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('switchEN', function () {
    $switch = new Switchzh();
    return $switch;
});

$di->setShared('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);
    return $url;
});
$di->setShared('logger', function () {
    $logger = new FileAdapter("../api/logs/" . date('Ymd') . ".log");
    return $logger;
});

$di->set('curl',function (){
    $client = new \Phalcon\Http\Client\Provider\Curl();
    $client->setOptions(array(
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_DNS_CACHE_TIMEOUT => 2,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => FALSE,
        CURLOPT_ENCODING => "", //Accept-Encoding支持所有类型 "identity", "deflate", and "gzip".
    ));
    return $client;
});

$di->set('view', function () use ($config) {
    $view = new \Phalcon\Mvc\View();
    $view->setViewsDir($config->application->viewsDir);
    $view->registerEngines(
        [
            ".html" => function ($view, $di) {
                $volt = new Volt($view, $di);
                return $volt;
            }
        ]
    );

    return $view;
});

$di->setShared("modelsMetadata",function () {
    if (extension_loaded('apc') ) {
        $metadata = new Phalcon\Mvc\Model\MetaData\Apc(["lifetime" => 86400,"metaDataDir" => APP_PATH."/api/cache/apc/","prefix"   => "mt-",]);
    }
    else{
        $metadata = new Phalcon\Mvc\Model\MetaData\Files(["lifetime" => 86400,"metaDataDir" => APP_PATH."/api/cache/file/","prefix"   => "mt-",]);
    }
    // Set a custom metadata introspection strategy
    $metadata->setStrategy( new \Phalcon\Mvc\Model\MetaData\Strategy\Introspection() );
    return $metadata;
});


$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();
    return $session;
});

$di->setShared('eventsManager',function() use($di){
    $eventsManager=new EventsManager();
    //$eventsManager->attach('what:event',new ListenerClass());

    $dbListener = new DbListener();
    $dbListener->setDi( $di );
    $eventsManager->attach( 'db', $dbListener  );
    $WxSplitHandle = new WxSplitHandle();
    $eventsManager->attach( 'wechat', $WxSplitHandle);
    // $WxInviteHandle = new WxInviteHandle();
    // $eventsManager->attach( 'wechat', $WxInviteHandle);
    $testPlugin = new plugins\test\service\WechatHandle();
    $eventsManager->attach( 'wechat', $testPlugin);
    
    $WxFestivalHandle = new WxFestivalHandle();
    $eventsManager->attach( 'wechat', $WxFestivalHandle);
    
    $WxChannelHandle = new WxChannelHandle();
    $eventsManager->attach( 'wechat', $WxChannelHandle);

    $WxDelayHandle = new WxDelayHandle();
    $eventsManager->attach( 'wechat', $WxDelayHandle);
    
    $YyznworksHandle = new YyznworksHandle();
    $eventsManager->attach( 'yyznworks', $YyznworksHandle);


    $WechatCorpSplit = new WechatCorpSplit();
    $eventsManager->attach( 'corp', $WechatCorpSplit);
    return $eventsManager;
});


$di->setShared('dbSlave', function () use ($config,$di) {
    if ($config->productionDb == 0) {
        $dbConfig = $config->databaseTest->db->toArray();
    } else {
        $dbConfig = $config->databaseSlave->db->toArray();
    }
    $adapter = $dbConfig['adapter'];
    unset($dbConfig['adapter']);
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
    $dbInstance = @new $class($dbConfig);
    $dbInstance->setEventsManager($di->get('eventsManager'));
    return $dbInstance;
});
$di->setShared('dbMaster', function () use ($config,$di) {
    if ($config->productionDb == 0) {
        $dbConfig = $config->databaseTest->db->toArray();
    } else {
        $dbConfig = $config->databaseMaster->db->toArray();
    }
    $adapter = $dbConfig['adapter'];
    unset($dbConfig['adapter']);
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
    $dbInstance = @new $class($dbConfig);
    $dbInstance->setEventsManager($di->getShared('eventsManager'));
    return $dbInstance;
});

$di->setShared('db',function ()use ($di){
    return $di->getShared('dbMaster');
});

$di->set('security', function () {
    $security = new Security();
    $security->setWorkFactor(12);
    return $security;
});
$di->setShared('redisCache',function() use($config){
    if ($config->productionCache == 0) {
        $rConf = $config->redisTestCache;
    }else{
        $rConf = $config->redisCache;
    }
    $servers = $rConf->toArray();
    $redis = new RedisClusterService( $servers );
    return $redis;
});
// Set the models cache service
$di->setShared('modelsCache', function () use ($di,$config) {
    return $di->getShared('redisCache');
});

$di->set('crypt', function () use ($config) {
    $crypt = new Crypt();
    $crypt->setKey($config->application->encryptKey); //Use your own key!
    return $crypt;
});

$di->set( "cookies", function () {
    $cookies = new Cookies();
    $cookies->useEncryption(false);
    return $cookies;
});

$di->set('userAccess', function () {
    return UserAccess::getInstance();
}, true);

$di->set('modelsManager', function () {
    $modelsManager = new ModelsManager();
    $modelsManager->setModelPrefix("miao_");
    return $modelsManager;
}, true);

$di->set("config", function () use ($config) {
    return $config;
}, true);

$di->set('jssdk', function () use ($config) {
    $jssdk = new Jssdk($config->wechat->Appid, $config->wechat->AppSecret);
    return $jssdk;
});
$di->set('reqAndResponse', function () {
    return ReqAndResponse::getInstance();
}, true);
$di->setShared(
    "transactions",
    function () {
        $manager = new TransactionManager();
        $manager->setDbService('dbMaster');
        return $manager;
    }
);
$di->set('validate', function() {
    $validate = new Validate();
    return $validate;
});

return $di;
