<?php
/**
 * 微信
 * Author: luodiao
 * Datetime: 18/11/14
 */
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;

class Liebian{
	/**
	* @title 扫码关注操作
	* @author luodiao
	* @param $user 当前关注人详细信息
	* @param $pid 当前活动上一级id
	* @param $wxid 所属公众号id
	*/
	public function subscribe($user,$pid,$wxid,$msgtype = 'subscribe'){
		$WechatLiebianinfoModel = new WechatLiebianinfo();
		$preantinfoObj = $WechatLiebianinfoModel->findFirstById($pid);
		if($preantinfoObj === false ){
			return false;
		}
		if($msgtype == 'scan'){
			if($preantinfoObj->focus_status > 1 ){

			}
		}
		//查询关注规则
		$splitObj = WechatLiebian::findFirstById($preantinfoObj->fk_split_id);

		$check = $this->checkSplit($splitObj);
		if( $check !== true){
			return $check;
		}
		$msgdata = $this->fristData($splitObj);
		if($splitObj === false){
			return false; //活动不存在
		}
		
		
		$id = $this->firstSlipt($user['id'],$splitObj->id,$wxid,$pid);
		// //判断领奖类型 0 自动叠加领奖，1兑换领奖
		
		//调用image生成海报参数
		if($id){
			if($splitObj->type < 1){
				if($this->postage($preantinfoObj) === true){
					//这里可以写中奖消息  预留扩展

				}
			}
			return $this->msg($splitObj->scan_text_frist,'1',$this->fristData($user,$splitObj,$id));
		}else{
			//失败终止
			return false;
		}
	}
	//已关注扫码
	public function scan($user,$pid,$wxid){
		$preantinfoObj = $WechatLiebianinfoModel->findFirstById($pid);
		if($preantinfoObj === false ){
			return false;
		}
	}


	/**
	* @title 参加分裂活动的逻辑写入的逻辑
	* @author ludoiao
	* @param 用户id
	*/
	public function firstSlipt($user_id,$split_id,$wx_id,$parent_id = 0){
		//查询用户是否参与活动
		$condition_info = "fk_split_id= :splitid: AND fk_user_id = :userid: AND wx_id = :wxid:";

		$bind = array(
				'splitid' => $split_id,
				'userid' => $user_id,
				'wxid' => $wx_id,
			);
		Di::getDefault()->get('logger')->debug("ld dump:查询是否参加活动".json_encode($bind));
		//查询用户是否是参与活动
		$userInfoObj = WechatLiebianinfo::findFirst(
			array(
				$condition_info,
				'bind' => $bind
				));
		if($userInfoObj !== false){
			//已经参与活动
			Di::getDefault()->get('logger')->debug("ld dump:已经参加了活动");
			return false;
		}

		Di::getDefault()->get('logger')->debug("ld dump:参加分裂活动的逻辑写入的逻辑start");
		$WechatLiebianinfoModel = new WechatLiebianinfo();//实例化活动详情
		//写入活动 (图片先不上传)
		$infoData = array(
			'wx_id' => $wx_id,
			'fk_user_id' => $user_id,
			'fk_split_id' => $split_id,
			'level' => '1',
			'parent_id' => '0',
			'media_id' => '0',
			'create_dt' => '0',
			'subscribe_num' => '0',
			'unsubscribe_num' => '0',
		);
		Di::getDefault()->get('logger')->debug("ld dump:".json_encode($infoData));

		$manager =  Di::getDefault()->get('transactions');

        $transaction = $manager->get();
		$infoSaveResult = $WechatLiebianinfoModel->create($infoData);
		if($infoSaveResult == false){
			Di::getDefault()->get('logger')->debug("ld dump:添加info");
			$transaction->rollback();
			return false;
		}

		$getModelsManager = Di::getDefault()->getModelsManager();
		//当上一级如果存在，就上一级关注+1
		if($parent_id > 0){
			$parentSql = "update WechatLiebianinfo set subscribe_num = subscribe_num + 1 where id = :id:";
			$parentSave = $getModelsManager->executeQuery($parentSql, ['id' => $parent_id]);
			if($parentSave == false){
	            $transaction->rollback();
	            return false;
			}
		}
		//总数加 1
		$splitSql = "update WechatLiebian set subscribe_num = subscribe_num + 1 where id = :id:";
		$sres = $getModelsManager->executeQuery($splitSql, ['id' => $split_id]);
		if($sres == false){
            $transaction->rollback();
            return false;
		}
		Di::getDefault()->get('logger')->debug("ld dump:参加分裂活动的逻辑写入的逻辑start");
        $transaction->commit();
        //成功返回主键id
		return $WechatLiebianinfoModel->id;
	}

	//判断活动的可用性
	public function checkSplit($splitObj){
		if($splitObj->status  > 1){
			switch ($splitObj->status) {
				case '2':
					//暂停
					$messages = $splitObj->suspend_text;
					$type = 2;
					break;
				case '3':
					$messages = $splitObj->end_text;
					$type = 3;
					break;
			}
			return $this->msg($messages,$type,$msgdata);
		}

		$splitObj->start_dt;
		$today = time();
		if($today < $splitObj->start_dt){
			//活动尚未开始
			return $this->msg('您好，本次活动暂未开始，敬请期待~',4,$msgdata);
		}
		if($today > $splitObj->end_dt){
			//活动已经结束
			return $this->msg($splitObj->end_text,3,$msgdata);
		}
		return true;
	}

	/**
	* @title 准备生成海报的参数
	* @author luodiao
	*/
	public function fristData($user,$splipObj = false,$info_id = 0){
		if($splipObj === false){
			return false;
		}
		$poster_rule = json_decode($splipObj->poster_rule,true);
		if(isset($poster_rule['picture'])){
			$poster_rule['picture']['url'] = $user['headimg'];
		}
		if(isset($poster_rule['name'])){
			$poster_rule['name']['text'] = $user['nickname'];
		}
		//准备参数
		$msgdata = array(
			'headimg' => $user['headimgurl'],
			'nickname' => $user['nickname'],
			'openid' => $user['openid'],
			'start_dt' => date("Y-m-d H:i",$splipObj->start_dt),
			'end_dt' => date("Y-m-d H:i",$splipObj->end_dt),
			'poster_rule' => $poster_rule,
			'infoid' => $info_id,
			'create_dt' => 0,
			'media_id' => 0,
			'url' => "http:://baidu.com",//排行榜的url
		);

		return $msgdata;
	}


	/**
	* @title 关键字触发
	* @author luodiao
	* @param $wx_id  微信的id
	* @param $text  关键字文案
	*/
	public function keyword($user,$text,$wxid){
		//查找 微信id一致且状态为正常的关键字 且
		$condition =  'wx_id = :wxid: AND status=1 AND end_dt >'.time().' AND start_dt < '.time()." AND keyword like :text:";
		$parameters = [
	        'wxid' => $wxid,
	        'text' => '%'.$text.'%'
	    ];
		$splitObj = WechatLiebian::findFirst(
			array(
				$condition,
				'bind' => $parameters
			));

		if($splitObj == false){

			Di::getDefault()->get('logger')->debug("ld dump:关键字'".$text."'不存在 不做任何操作");

			return false;//关键字不存在 不做任何操作
		}
		// var_dump($splitObj);exit;
		$check = $this->checkSplit($splitObj);
		if( $check !== true){
			return $check;
		}
		
		$id = $this->firstSlipt($user['id'],$splitObj->id,$wxid);
		if($id){
			return $this->msg($splitObj->scan_text_frist,'1',$this->fristData($user,$splitObj,$id));
		}else{
			//失败终止
			return false;
		}
	}

	//写入图片
	public function set_split_info_img($id,$media_res){
		$obj = WechatLiebianinfo::findFirstById($id);
		$obj->media_id = $media_res['media_id'];
		$obj->create_dt = $media_res['created_at'];
		if($obj->save()){
			return true;
		}
		//
		return false;
	}




	//奖品设置——自动开奖
	public function postage($obj){

		//查询奖品列表
		$condition = "fk_split_id = ".$obj->fk_split_id;
		$postageObj= LiebianPostage::find($condition);
		if(count($postageObj) < 1){
			return  false;//当前未设置奖励
		}
		// //查询该活动，该用户领取的几率
		$where = 'fk_user_id = '.$obj->fk_user_id. ' AND fk_split_id = '.$obj->fk_split_id;
		$LiebianPostageinfoMdel = new LiebianPostageinfo();
		$postageinfolist = LiebianPostageinfo::find($where)->toArray();
		$postage_ids = array_column($postageinfolist,'fk_postage_id');
		//查询当前用户累计关注数量
		$number = $obj->subscribe_num - $obj->unsubscribe_num;

		foreach ($postageObj as $key => $value) {
			if(in_array($value->id,$postage_ids) == false && $number >= $value->number){
				//中奖逻辑
  				$data['fk_user_id'] = $obj->fk_user_id;
  				$data['create_dt'] = time();
  				$data['fk_split_id'] = $obj->fk_split_id;
  				$data['fk_postage_id'] = $value->id;
  				if($LiebianPostageinfoMdel->save($data) == false){
  					return false;
  					//记录日志
  				}else{
  					return true;
  				}
			}
		}
		return false;
	}

	/**
	* @title 生成海报
	* @author luodiao
	* @param poster_rule 规则
	* @param qrcodeimg 二维码地址
	*/
	public function mergeImg($poster_rule,$qrcodeimg = ''){
		if(isset($poster_rule['brackground']) == false){
			//当背景图不存在的时候不做任何操作
			exit("需要上传背景图");
		}
	
		//创建图片对象 取第一张背景（有可能多张，这个先预留，目前版本不需要）
		$images = new Images();
		
		$images->big_write($poster_rule['brackground'][0]);
		//判断二维码是否存在
		if(isset($poster_rule['qrcode'])){
			$qrcode = $poster_rule['qrcode'];
			$images = $images->merge_images($qrcodeimg,$qrcode['x'],$qrcode['y'],$qrcode['width'],$qrcode['height']);

		}
		//判断头像是否存在
		if(isset($poster_rule['picture'])){
			$picture = $poster_rule['picture'];
			$imgges = $images->merge_images($picture['url'],$picture['x'],$picture['y'],$picture['width'],$picture['height'],$picture['status']);
		}

		//判断文字是否存在
		if(isset($poster_rule['name'])){
			$text = $poster_rule['name'];
			$imgges = $images->write_font($text['text'],$text['x'],$text['y'],$text['size'],$text['color']);
		}
		return $imgges->print_img();

	}

	/**
	* @tit 准备消息 --- 未完成
	*/
	public function msg($messages,$type,$data=array()){
		if($messages == ''){
			return false;
		}
		switch ($type) {
			case '1':
				//关注类型消息
				//范文如下
				#昵称#活动时间为：#时间#
				# 过期后请点击[XXXX]菜单重新获取哦。
				# 正在为您发送海报，请稍候……
				#  ☛点击进入：<a href="#排行榜#">排行榜</a>
				$messages = str_replace("#时间#", $data['start_dt'].'-'.$data['end_dt'], $messages);
				$messages = str_replace("#昵称#", $data['nickname'], $messages);
				$messages = str_replace("#排行榜#", urlencode($data['url']), $messages);

				break;
			case '2':
				//暂停类型消息
				break;
			case '3':
				//结束类型消息
				break;
			case '4':
				//未开始类型消息
				break;
			
			default:
				return false;
				break;
		}
		$data['messages'] = $messages;
		return $data;
	}


}