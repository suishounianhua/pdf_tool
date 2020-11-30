<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class UserTest extends \PHPUnit\Framework\TestCase
{

    public function testGetUser()
    {
        $data = User::getUserById(1);

        $this->assertEquals(1,$data['id'],'检查用户查询结果');

//        $roles = User::roles(1);
//        print_r($roles);  print_r($data['role_id']);

        $openid1 =  User::getOauthOpenId($data,'weixinPb');

        $openid2 =  User::getOauthOpenId(['id'=>1],'weixinPb');
        $this->assertEquals($openid1,$openid2,'检查openid');
    }


}