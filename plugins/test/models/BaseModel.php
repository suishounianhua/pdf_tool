<?php
/**
 * @title 插件models基础类
 * User: luoio
 * Date: 2019/07/01
 * Time: 下午1:17
 */
namespace plugins\test\models;

use Phalcon\Di;

class BaseModel extends \plugins\YyznModel
{

	public function initialize()
    {
    	parent::initialize();
    	//写入配置，请勿改动
        $this->setConfig(__NAMESPACE__);
    }
}
