<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class BaseModelTest extends \PHPUnit\Framework\TestCase
{

    public function testUpdateAll(){
        $view_nums = rand(0,1000);
        Wx::updateAll(['view_nums' => $view_nums], array('id'=>2));
        $item = Wx::findFirstById(2);
        $this->assertEquals($view_nums,$item->view_nums,'修改数组');

        Wx::updateAll(['view_nums=view_nums+1'], array('id'=>2));
        $item = Wx::findFirstById(2);
        $this->assertEquals($view_nums + 1,$item->view_nums,'修改自增');

    }


    public function testDeleteAll(){
        $wx = new WX();
        $wx->name = '135editor_test';
        $wx->save();
        $wx = new WX();
        $wx->name = '135editor_test';
        $wx->save();
        Wx::deleteAll(array('name'=>'135editor_test'));
        $item1 = Wx::find(['name'=>'135editor_test']);
        $this->assertEquals(!empty($item1),true,'删除135editor_test');
        $wx = new WX();
        $wx->name = '135editor_test1';
        $wx->save();
        Wx::deleteAll(array('name'=>'135editor_test1'));
        $item2 = Wx::find(['name'=>'135editor_test1']);
        $this->assertEquals(!empty($item2),true,'删除135editor_test1');
    }

    public function testJoins(){
        $word = '你好'; $wx_ids = [2];
        $item4 = WxSimilar::find(array(
            'conditions' => array(
                'OR' => array( array('WxSimilar.words like' => '%'.$word.'%'), array('WxSimilar.name' => $word)),
                'wx_id' => $wx_ids,
                'status' => 1,
            ),
            'joins' => array(array('model'=>'Wx','on'=>'Wx.id=WxSimilar.wx_id')),
            'limit' => 1,
            'page' => 1,

        ));
//        print_r($item4->toArray());
//        print_r($item4->Wx);
        $this->assertEquals(1,1);
    }

    public function testFindAndFindFirst(){
        $word = '你好'; $wx_ids = [2];
        $item1 = WxSimilar::find(array(
            'conditions' => '(words like :likeword: or name = :word:) and wx_id in ({wx_id:array}) and status = :status:',
            'bind' => array('likeword'=>'%,'.$word.',%','word'=>$word, 'wx_id' => $wx_ids,'status' => 1),
        ))->toArray();

        $item2 = WxSimilar::find(array(
            'OR' => array( 'words like' => '%'.$word.'%', 'name' => $word),
            'wx_id' => $wx_ids,
            'status' => 1,
        ))->toArray();

        $item3 = WxSimilar::find( array(
            'conditions' => array(
                'OR' => array( array('words like' => '%'.$word.'%','status' => 1), array('name' => $word)),
                'wx_id' => $wx_ids,
                'status' => 1,
            ),
            'limit' => 1,
            'page' => 1,
        ))->toArray();



        $this->assertEquals(count($item1),count($item2),'testFindAndFindFirst1');
        $this->assertEquals(count($item2),count($item3),'testFindAndFindFirst2');
        $this->assertEquals($item1[0]['id'],$item2[0]['id'],'testFindAndFindFirst3');
        $this->assertEquals($item2[0]['id'],$item3[0]['id'],'testFindAndFindFirst4');

    }

    public function testIncrease()
    {

        Wx::increase('view_nums', array('id'=>2));

        $old = Wx::findFirstById(2);

        $step = 5;
        Wx::increase('view_nums',array('conditions' => "id=:id:",'bind'=> array('id'=>2)),$step);
        $new = Wx::findFirst(['id'=>2]);

        $this->assertEquals($old->view_nums+$step,$new->view_nums,'测试increase');

        $step = 2;
        Wx::decrease('view_nums',array('conditions' => "id=:id:",'bind'=> array('id'=>2)),$step);
        $denew = Wx::findFirst(['id'=>2]);

        $this->assertEquals($new->view_nums-$step,$denew->view_nums,'测试decrease');


//        $item = Wx::findFirst(['id'=>2,'view_nums'=>$denew->view_nums]);
//
//        $this->assertEquals($item->id,2,'测试find与getCondition');
    }


}