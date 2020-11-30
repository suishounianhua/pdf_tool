<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset\Simple;

use PHPUnit\Framework\TestCase;

class WxUserTest extends \PHPUnit\Framework\TestCase
{

    public function testSave()
    {
        $rand = random_str(12);

        $user = new WxUser();
        $user->save(array('nickname'=>$rand,'tagid_list'=>[128,3]));

        $findUser = WxUser::findById($user->id) ; //$user->save(array('nickname'=>$rand,'tagid_list'=>[128,3]));
        print_r( $findUser->toArray() );
        foreach($findUser as $item) {
            print_r( $item->toArray() );
        }



//        $this->assertTrue(in_array(128,$findUser->tagid_list));
//        $this->assertTrue(in_array(3,$findUser->tagid_list));
//        $this->assertEquals(2,count($findUser->tagid_list));

        #######################
        $this->assertTrue(in_array(128,$user->tagid_list));
        $this->assertTrue(in_array(3,$user->tagid_list));
        $this->assertEquals(2,count($user->tagid_list));

        $user->delete();
    }

}