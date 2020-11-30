<?php
/**
 * title :生成优惠码
 *
 * php api/cli.php  Discount run  1 100  1210
 *
 *
 */
use Phalcon\DI;
use Phalcon\Cli\Task;

class DiscountTask extends Task
{
    public function mainAction()
    {
        echo "This is the default task and the default action" . PHP_EOL;
    }
//php api/cli.php Discount run 10 600 12 30 10
// 应用id 金额 最少购买月份 有效期 数量
    public function runAction($data = array())
    {
        $authObj = WxActivitiesAuth::findFirst(['id'=>$data[0]]);
        if($authObj == false){
            echo "应用不存在\n";exit;
        }
		$OrderDiscount = new OrderDiscount();
		$num = 0;
        $time = 86400 * $data[3] + time();
		$edd = array(
			'title' => $authObj->title,
			'createat' => time(),
			'remark' => $authObj->title.' ￥'.$data[1]." 最少购买月份" .$data[2]." 优惠券",
			'price' => $data[1],
			'start_at' => time(),
			'end_at' => $time,
			'fk_id' => $data[0],
			'num' => $data[2]
		);
		list($msec, $sec) = explode(' ', microtime());
	    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
	    $keydata = [];
    	for ($i = 1; $i <= $data[4]; $i++) { 
			$charid = md5(md5(uniqid(mt_rand(), true)).sha1($msectime).'crontab'.$i);
			$uuid = substr($charid,8,10);
			if(in_array($uuid,$keydata)){
				echo $uuid."已经存在\n";
				continue;
			}
			$keydata[] = $uuid;
			$edd['code'] = $uuid;
			$Discount_clone = clone $OrderDiscount;
			if($Discount_clone->save($edd)){
				$num++;
				echo "添加成功".$Discount_clone->id."\n";
			}
    	}
    	echo "执行完毕，共执行".$num."条\n";
    }

    //生成兑换码
    public function runcodeAction($data=array()){
    	$InviteCodes = new InviteCodes();
		$num = 0;
		$cate = date("YmdHis");
		$edd = array(
			'name' => '',
			'cate_id' => '',
			'creator' => '1',
			'status' => '0',
			'created' => date("Y-m-d H:i:s"),
			'updated' => date("Y-m-d H:i:s"),
			'start' => date("Y-m-d H:i:s"),
			'end' => '2030-01-01 00:00:00',
			'used' => '0',
			'role_id' => $data[0],
			'role_days' => $data[1],//共计送多少天
			'allow_nums' => '1',//可以领取多少个人
			'remark' => "渠道分销批次:".$cate."兑换码",
		);

		list($msec, $sec) = explode(' ', microtime());
	    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
	    $keydata = [];
    	for ($i = 1; $i <= $data[2]; $i++) { 
			$charid = md5(md5(uniqid(mt_rand(), true)).sha1($msectime).'crontab'.$i);
			$uuid = substr($charid,8,10);
			if(in_array($uuid,$keydata)){
				echo $uuid."已经存在\n";
				continue;
			}
			$keydata[] = $uuid;
			$edd['name'] = $uuid;
			$edd['cate_id'] = $cate .'_'.$i;
			$Invite_clone = clone $InviteCodes;
			if($Invite_clone->save($edd)){
				$num++;
				echo "添加成功".$Invite_clone ->id."\n";
			}
    	}
    	echo "执行完毕，共执行".$num."条\n";
    }

    public function mineAction($data = array()){
        $conditions = array('wx_id' => $data[0] ,'subscribe' => 1);
        $wxesObj = Wx::findFirst(['id'=>$data[0]]);
        WechatService::setAppid($wxesObj->oauth_appid);
        $pagesize = 30;
        $table = 'wx_users_'.($data[0]%512);
        WxUser::$TABLE_NAME =  $table;

        $total = WxUser::count(array(
            'conditions' => $conditions,
        ));
        $nextId = $data[1];
        $total = ceil($nextId/30);
        for ($i=0; $i < $total; $i++) { 
            
            $conditions['id <'] = $nextId;
            $datalist = WxUser::find(array(
                'conditions' => $conditions,
                'order' => 'id desc',
                'limit'=> $pagesize
            ))->toArray();
            if(empty($datalist)){
                echo "同步成功\n";
                return;
            }
            $openids = array();
            foreach($datalist as $u) {
                if(empty($u['nickname']) || empty($u['tagid_list'])) {
                    $openids[ $u['id'] ] = $u['openid'];
                }
                $nextId = $u['id'];
            }
            

            if( !empty($openids) ) {
                $result = WechatService::getBatUserInfo($openids);
                if($result['errcode'] == 0 ) {
                    foreach($result['user_info_list'] as $wxuser) {
                        if(empty($wxuser['subscribe'])) {
                            WxUser::unsubscribe($data[0],$wxuser['openid']);
                            continue;
                        }
                        $where = "id = '";
                        $where .= array_search($wxuser['openid'],$openids);
                        $where .= "'";
                        $created = date('Y-m-d H:i:s',$wxuser['subscribe_time']);
                        $qr_scene = $wxuser['qr_scene']?$wxuser['qr_scene']: $wxuser['qr_scene_str'];
                        $tagid_list = implode(",", $wxuser['tagid_list']);
                        $this->db->update("miao_".$table,['nickname','headimg','province','city','sex','country','created','actived','unionid','subscribe_scene','qr_scene','tagid_list'],[$wxuser['nickname'],$wxuser['headimgurl'],$wxuser['province'],$wxuser['city'],$wxuser['sex'],$wxuser['country'],$created,$created,$wxuser['unionid'],$wxuser['subscribe_scene'],$qr_scene,$tagid_list],$where);
                    }
                }else{
                    echo '同步失败'.$result['errcode'].':'.$result['errmsg']."\n";
                }
            }else{
                echo "什么都没有执行";exit;
            }
        }
    }
}