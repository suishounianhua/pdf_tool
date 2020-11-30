<?php
/**
 * @title 模型demo 如需尝试，请创建一个 test表（需要包含你自己设置的表前缀）
 * User: luoio
 * Date: 2019/07/01
 * Time: 下午1:17
 */
namespace plugins\test\models;
//
class Test extends \plugins\test\models\BaseModel
{

	//去除表前缀的数据表名称
	public static $TABLE_NAME = 'test';

}
