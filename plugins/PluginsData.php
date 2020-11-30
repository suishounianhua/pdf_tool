<?php
namespace plugins;
/**
 * Created by PhpStorm.
 * User: pangxb
 * Date: 17/3/5
 * Time: 下午6:01
 */

use \plugins\WxUser;


class PluginsData{
	public static function findUser($wx_id , $openid){
		return WxUser::findUser($wx_id , $openid);
	}
}