<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class UserSettingTest extends \PHPUnit\Framework\TestCase
{

    public function testGetAndGet()
    {
        $rand = random_str(12);

        $settings = UserSetting::getSetting(1);

        UserSetting::setKeyVal(1,"xx",$rand);
        UserSetting::setKeyVal(1,"aa",$rand.'aa');

        $settings = UserSetting::getSetting(1);

        $this->assertEquals($rand,$settings['xx']);
        $this->assertEquals($rand.'aa',$settings['aa']);

        UserSetting::writeSetting(1,array('write'=>$rand.'write'));

        $settings = UserSetting::getSetting(1);

        $this->assertEquals($rand,$settings['xx']);
        $this->assertEquals($rand.'aa',$settings['aa']);
        $this->assertEquals($rand.'write',$settings['write']);

    }


}