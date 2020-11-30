<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class WxSimilarTest extends \PHPUnit\Framework\TestCase
{

    public function testIncrease()
    {




        $result = WxSimilar::getSimilarWord('你好', [2]);
//
        print_r($result);


//        $item = Wx::findFirst(['id'=>2,'view_nums'=>$denew->view_nums]);
//
//        $this->assertEquals($item->id,2,'测试find与getCondition');
    }


}