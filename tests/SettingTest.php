<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class SettingTest extends \PHPUnit\Framework\TestCase
{

    public function testWriteConfig()
    {
        Setting::writeConfiguration(true);

    }

}