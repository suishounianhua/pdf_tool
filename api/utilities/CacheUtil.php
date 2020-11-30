<?php

use Phalcon\Di;

class CacheUtil
{
    public static function getCached($cacheKey, $func) {
        $cache = Di::getDefault()->getRedisCache();
        $logger = Di::getDefault()->get('logger');
        if ($cache->exists($cacheKey)) {
            $result = $cache->get($cacheKey);
            $logger->log('get result from cache: '.json_encode($result));
        } else {
            $result = $func();
            $cache->save($cacheKey, $result);
            $logger->log('cache saved: '.json_encode($result));
        }
        return $result;
    }

    public static function removeCache($cacheKey) {
        $cache = Di::getDefault()->getRedisCache();
        $logger = Di::getDefault()->get('logger');
        if ($cache->exists($cacheKey)) {
            $cache->delete($cacheKey);
            $logger->log('removed cache for '.$cacheKey);
        }
    }

}
