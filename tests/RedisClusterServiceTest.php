<?php


use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class RedisClusterServiceTest extends \PHPUnit\Framework\TestCase
{

    public function testArray()
    {
        $redis = Di::getDefault()->get('redisCache');

        $arr = ['x'=>'y','a'=>'b'];
        $redis->set('a',$arr); // = Dbcache::read($ck);

        $val = $redis->get('a');

        $this->assertTrue(is_array($val));
        $this->assertEquals($arr['x'],$val['x']);
        $this->assertEquals($arr['a'],$val['a']);

        $redis->del('a');

    }

    public function testCall(){
        $redis = Di::getDefault()->get('redisCache');
        $arr = ['x'=>'y','a'=>'b'];
        $redis->hMset('ha',$arr);
        $val = $redis->hGetAll('ha');
        print_r($val);
        $this->assertTrue(is_array($val));
        $this->assertEquals($arr['x'],$val['x']);
        $this->assertEquals($arr['a'],$val['a']);

        $this->assertEquals($arr['x'], $redis->hGet('ha','x'));
        $this->assertEquals($arr['a'], $redis->hGet('ha','a'));

        $redis->del('ha');
    }

}