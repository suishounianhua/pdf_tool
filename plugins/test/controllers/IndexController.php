<?php
use Phalcon\Http\Response;
use Phalcon\Di;
class IndexController extends  \plugins\YyznController{
	public static $acl_list = ['test'];
	public function indexAction(string $string = 'string'){
		$this->view->setVar("title", "欢迎开发运营指南");
		$this->view->setVar("msg", "Hello word");
	}

	public function testAction(string $string = 'string'){
		echo '此方法不需要用户登录，且不需要选择公众号';
	}
}