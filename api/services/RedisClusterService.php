<?php
/**
 * Created by PhpStorm.
 * User: zouqilong
 * Date: 2018/12/12
 * Time: 18:49
 */

use Phalcon\Di;

class RedisClusterService
{
    private $_redis;

    public $lifetime = 900; //默认的缓存时间

    public function __construct($servers)
    {
        $this->_redis = new \Redis();
        $this->_redis->connect($servers['host'], $servers['port']);
        $this->_redis->auth($servers['auth']);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
//        if( method_exists($this->_redis,$name) ) {
        return call_user_func_array( array($this->_redis,$name) ,$arguments );
//        }
    }

    /**
     * 使用hash结构保存一维数组
     * @param $key
     * @param $hashes
     * @param null $expire
     */
    public function hMSet($key,$hashes,$expire = null) {
        $this->_redis->hMSet($key,$hashes);
        if($expire) {
            $this->_redis->expire($key,$expire);
        }
    }
    public function incr($key,$num=1) {
        return $this->_redis->incr($key,$num);
    }

    /**
     * 使用serialize与unserialize同时兼任对象与数组（由于json处理数组和对象的混乱，没法直接兼容）
     * @param $key
     * @return bool|int|mixed|string
     */
    public function get($key){
        $value = $this->_redis->get($key);
		if ($value === false || is_null($value)) {
            return null;
        }

        if ( is_numeric($value) ) {
            return $value;
        }
        else{
            return unserialize($value);
        }
    }
    public function exists($key){
        return $this->_redis->exists($key);
    }
    public function del($key){
        return $this->_redis->del($key);;
    }
    public function hgetall($key){
        $value = $this->_redis->hgetall($key);
        if ($value === false || is_null($value)) {
            return array();
        }
        return $value;
    }
    /**
     * save为set方法的别名,兼容Cache的调用
     * @param $key
     * @param $val
     * @param null $timeout
     * @return bool
     */
    public function save($key,$val,$timeout = null) {
        return $this->set($key,$val,$timeout);
    }

    public function set($key,$value,$expire = null) {
        if ( !is_numeric($value) ) {
            $value = serialize($value);
        }
        if ($expire === null) {
            return $this->_redis->set($key, $value);
        }
        return $this->_redis->setex($key, $expire, $value);
    }

    public function setex($key,$expire,$value){
        if ( !is_numeric($value) ) {
            $value = serialize($value);
        }
        return $this->_redis->setex($key, $expire, $value);
    }

}