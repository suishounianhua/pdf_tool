<?php
namespace plugins;
/**
 * Created by PhpStorm.
 * User: pangxb
 * Date: 17/3/5
 * Time: 下午6:01
 */

use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;

class WxUser extends \Phalcon\Mvc\Model{
	public static $TABLE_NAME = 'wx_users';

    public $tagid_list = array();

    public function setSource($source){
        static::$TABLE_NAME = $source;
    }

    public static function findUser( $wx_id , $openid) {
        self::$TABLE_NAME = 'wx_users_'.($wx_id%512);
        return self::findFirst(array(
            'conditions' => "wx_id = :wx_id: and openid = :openid:",
            'bind' => array( 'wx_id' => $wx_id,'openid' => $openid, ),
        ));
    }
}