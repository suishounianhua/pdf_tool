<?php

/*
####################################################################
###   命令行进入当前目录，执行如下命令
###   phpunit --bootstrap bootstrap.php  CloudStorageTest.php
###   最后的文件名表示执行改文件的所有测试样例
###
###   phpunit --bootstrap bootstrap.php  ./
###   phpunit --bootstrap bootstrap.php  .
###
#####################################################################
*/
define('APP_PATH', realpath('..'));
define('UPLOAD_PATH', substr( __DIR__, 0, strrpos(__DIR__,DIRECTORY_SEPARATOR)) .DIRECTORY_SEPARATOR."Uploads/images".DIRECTORY_SEPARATOR );

require_once APP_PATH . "/vendor/autoload.php";
require_once APP_PATH."/library/global_function.php";

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
$di = include APP_PATH . "/api/config/services.php";

