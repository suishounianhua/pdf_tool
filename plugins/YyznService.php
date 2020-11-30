<?php
namespace plugins;
/**
 * 微信事件处理器
 * Author: luoio
 */
use Phalcon\Di;

class YyznService{
	public $plugin_config = [];
	public function setConfig(string $string){
        try {
            $arr = explode('\\', $string);
            $configPATH = APP_PATH . "/plugins/".$arr[1]."/config/config.php";
            if(!file_exists($configPATH)){
                 echo "请确定配置文件是否合法";exit;
            }
            if(empty($this->plugin_config)){
                $this->plugin_config = include $configPATH;
            }
            $config = $this->plugin_config;
        } catch (\Exception $e) {
            
            echo "请确定配置文件是否合法";exit;
        }
    }
}
