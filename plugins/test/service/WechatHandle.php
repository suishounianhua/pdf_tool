<?php
namespace plugins\test\service;
/**
 * 微信事件处理器
 * Author: luoio
 */
use Phalcon\Di;
class WechatHandle  extends \plugins\YyznService{
	public function __construct()
    {
    	//写入配置，请勿改动
        $this->setConfig(__NAMESPACE__);
    }
	/**
	* @title 事件处理器
	* @data  array(
	*		wx:微信相关信息
	*		user:运营指南相关用户信息
	*		msg:微信原生消息结构
	*	);
	*  备注：请务必保证效率执行
	*/
	public function beforeHandleMsg($event, $obj, $data){
		#处理你的后端事件
		return false;
	}
}
