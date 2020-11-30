<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class DbcacheTest extends \PHPUnit\Framework\TestCase
{

    public function testGetAndGet()
    {
        $rand = random_str(12);

        $ck = 'test_key';

        DBcache::write($ck,$rand);

        $dval = Dbcache::read($ck);

        $this->assertEquals($rand,$dval);

        $newval = array('v1'=>random_str(12),'v2'=>random_str(12));

        DBcache::write($ck,$newval);

        $dval = Dbcache::read($ck);

        $this->assertEquals($newval['v1'],$dval['v1']);
        $this->assertEquals($newval['v2'],$dval['v2']);

    }


}