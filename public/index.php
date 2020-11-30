<?php

define('APP_PATH', realpath('..'));
define('UPLOAD_PATH', substr( __DIR__, 0, strrpos(__DIR__,DIRECTORY_SEPARATOR)) .DIRECTORY_SEPARATOR."Uploads/images".DIRECTORY_SEPARATOR );
define('DATA_PATH', APP_PATH.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR);
define('QUERY_STRING',$_SERVER['QUERY_STRING']);
require_once APP_PATH . "/vendor/autoload.php";
require_once APP_PATH."/library/global_function.php";
define('__PLUGINS__','../plugins/');
define('IS_HTTPS', 1);
use Phalcon\Di;

try {

    /**
     * Read the configuration
     */
    $config = include APP_PATH . "/api/config/config.php";
    if( $config->debugMode ) {
        error_reporting(E_ALL ^ E_NOTICE);
        $debug = new \Phalcon\Debug();
        $debug->listen();
    }
    /**
     * Read auto-loader
     */
    //include APP_PATH . "/app/config/loader.php";
    $loader = include APP_PATH . "/api/config/loader.php";

    /**
     * Read services
     */
    //include APP_PATH . "/app/config/services.php";
    $di = include APP_PATH . "/api/config/services.php";
    include APP_PATH . "/api/config/router.php";
    
    // $query_string_arr = explode('/', QUERY_STRING);
    // if(strtolower($query_string_arr[1]) == 'plugins'){
        
    //     //判断文件是否存在
    //     $pluginsConfigPath = APP_PATH . "/plugins/".$query_string_arr[2]."/config/config.php";
    //     if(file_exists($pluginsConfigPath)){
    //         $di->set('plugin_config',function() use ($pluginsConfigPath){
    //             $conf = include $pluginsConfigPath;
    //             return $conf;
    //         });
    //         $di->set('plugin_footer',function(){
    //             return "<input id='plugins_status' value='{{ plugins_status }}' type='hidden'/><script>var plugins_status = $('#plugins_status').val();if(plugins_status == -133009 || plugins_status == 10910 ){top.document.location.href = '/new/vue/logout';}</script>";
    //         });
    //     }else{
    //         echo "请检查配置文件是否合法: ./plugins/".$query_string_arr[2]."/config/config.php";exit;
    //     }
    // }
    
    //创建phalcon实例
    /**
     * Handle the request
     */
    $application = new \Phalcon\Mvc\Application($di);

    $response = $application->handle();

    $response->send();

} catch (\Exception $e) {
    if($config['errorLog'])
    {
        echo '<pre>' . $e->getMessage().getExceptionTraceAsString($e) . '</pre>';
    }else{
        Di::getDefault()->get('logger')->debug($e->getMessage().getExceptionTraceAsString($e));
    }

}
