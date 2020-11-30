<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class SignTokenTest extends \PHPUnit\Framework\TestCase
{

    public function testGenAndGet()
    {
        $token = SignToken::genToken(1,'web');

        print_r($token);
        $sign_token = SignToken::getToken($token,'web');

        print_r($sign_token->toArray());

        $this->assertEquals($token,$sign_token->web);

    }

}