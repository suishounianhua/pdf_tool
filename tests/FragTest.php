<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class FragTest extends \PHPUnit\Framework\TestCase
{

    public function testRand()
    {
        $result = Frag::findFirst([
            'columns'=>'summary',
            'order' => 'rand()',
            ]);
        var_dump($result->toArray());
    }

}