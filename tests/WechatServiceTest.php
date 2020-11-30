<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class WechatServiceTest extends \PHPUnit\Framework\TestCase
{

    public function testClearQuota()
    {
        $token = WechatService::compGetAccessToken();
        $this->assertNotNull($token,'Get Access Token Error.');
        if($token) {

            $ret = WechatService::compClearQuota();

            $this->assertEquals(0,$ret['errcode'],'clear quota error');
        }

    }
}