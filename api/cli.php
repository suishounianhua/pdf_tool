<?php
define('QUERY_STRING',$_SERVER['QUERY_STRING']);
defined('APP_PATH') || define('APP_PATH', realpath('.'));
include APP_PATH . "/vendor/autoload.php";
require_once APP_PATH."/library/global_function.php";
use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;
use Phalcon\Logger;


define('APP_PATH', realpath('..'));
define('UPLOAD_PATH', substr( __DIR__, 0, strrpos(__DIR__,DIRECTORY_SEPARATOR)) .DIRECTORY_SEPARATOR."Uploads/images".DIRECTORY_SEPARATOR );




/**
 * Read the configuration
 */
$config = include APP_PATH . "/api/config/config.php";
error_reporting(E_ALL ^ E_NOTICE);

$loader = include APP_PATH . "/api/config/loader.php";

/**
 * Read services
 */
//include APP_PATH . "/app/config/services.php";
//$di = include APP_PATH . "/api/config/services.php";
$di = new CliDI();

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

// Set the models cache service
$di->setShared('modelsCache', function () use ($config) {
    if ( $config->productionCache == 0) {
        $frontCache = new FrontData([ 'lifetime' => 300, ]);
        $_redisCacheInstance = new \Phalcon\Cache\Backend\File( $frontCache, ["cacheDir" => APP_PATH."/api/cache/",]);
    } else {
        // Cache data for 2 hours
        $frontCache = new FrontData(['lifetime' => 7200, ]);
        $_redisCacheInstance = new BackRedis($frontCache, (array)$config->redisCache);
    };
    return $_redisCacheInstance;
});
$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();
    return $session;
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
    $dbInstance = new $class($dbConfig);
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
    $dbInstance = new $class($dbConfig);
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
$di->set("config", function () use ($config) {
    return $config;
}, true);

$di->setShared('logger', function () {
    $logger = new FileAdapter("api/logs/" . date('Ymd') . ".log");
    return $logger;
});
$di->set('transactions', function () {
    $manager = new TxManager();
    $manager->setDbService('dbMaster');
    return $manager;
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






// Create a console application
$console = new ConsoleApp();

$console->setDI($di);

/**
 * Process the console arguments
 */
$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k === 1) {
        $arguments["task"] = $arg;
    } elseif ($k === 2) {
        $arguments["action"] = $arg;
    } elseif ($k >= 3) {
        $arguments["params"][] = $arg;
    }
}

try {
    // Handle incoming arguments
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    echo $e->getMessage();

    exit(255);
}