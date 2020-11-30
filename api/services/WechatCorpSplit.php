<?php
use Phalcon\Di;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger;

/**
 * 微信企业号裂变监听
 * 
 * @author Arlon , Luoio
 *
 */
class WechatCorpSplit {
    public $_log = false;
    public $_redis = false;
    public $_corp = false;//企业配置
    public $_msg = false;//事件类型
    public $_users = false;//企业微信用户详细
    public $splitInfoObj = false;
    public $splitObj = false;
    public $preantinfoObj = false;//父级id
    public $wxesObject = false;//公众号信息
    public $postageList = false;//公众号信息
    public $preant_number = 0;//父级人气值
    public function __construct(){
        $this->notdisturbNumber = 17;
        $this->wechatauthredirect = Di::getDefault()->get('config')->Wxsplit->wechatauth;
        $dev = Di::getDefault()->get('config')->Yyzn->split_temporary_host;
        $url = 'yunyingzhinan.com.cn';
        if($dev == 'dev.135editor.com'){
            $url = 'dev.135editor.com';
        }
        //炮灰域名
        $this->ranking_url = 'http://'.$url.'/new/yyznsplitmobile/ranking?splitid=';//排行榜
        $this->goods_url = 'http://'.$url.'/new/yyznsplitmobile/goods_detail?splitid=';//奖品链接

        $this->_log = new FileAdapter("../api/logs/companies_" . date('Ymd') . ".log");
    }
    //按附带参数查询记录
    public function findInfo_state(){
        $info_id = str_replace('splitinfo_','',$this->_msg['State']);
        $where = array(
            'id' => $info_id,
        );
        $this->_log->debug('1 ');
        $this->splitInfoObj = WechatLiebianinfo::findFirst($where);
        if($this->splitInfoObj == false){
            return false;
        }
        //查询活动
        $this->splitObj = WechatLiebian::findFirst(['id'=>$this->splitInfoObj->fk_split_id,'is_del'=>0]);
        if($this->splitObj == false){
            return false;
        }
        $this->_log->debug('2 ');

        //判断是否是本人
        $new_headimg = substr($this->splitInfoObj->headimg,0,strlen($this->splitInfoObj->headimg)-3);
        $new_headimg .= '0';
        $new_headimg  = CloudStorage::getImagesTmpFile($new_headimg,'long');
        $cmd = APP_PATH."/public/split/luoiodhash.py ".$new_headimg;
        exec($cmd, $dhash_array,$ret);
        $dist = $this->hamDist($this->_users['avatar_dhash'],$dhash_array[0]);
        $this->_log->debug('luodiao dump Customer_item: corp||'.$this->_users['avatar_dhash'].' wx||'.$dhash_array[0].' dist||'.$dist);
        $this->_log->debug('3 ');
        if($dist !== false && $dist <= 12 && $this->_users['name'] == $this->splitInfoObj->nickname){
            $this->splitInfoObj->customer_id = $this->_users['id'];
            $this->splitInfoObj->save();
            //找到对应
            $ContactInfoObj = CorpCompaniesContact::findFirst(['state'=>'splitinfo_'.$this->splitInfoObj->id]);
            $ContactInfoObj->fk_customer_id = $this->_users['id'];
            $ContactInfoObj->save();
            //暂时无做任何操作
        }else{
            return false;
            //查询自己本次活动是否生成过二维码
            $ContactInfoObj = CorpCompaniesContact::findFirst(['fk_split_id'=>$this->splitObj->id,'wx_id'=>$this->splitObj->wx_id,'fk_customer_id'=>$this->_users['id']]);
            if($ContactInfoObj == false){
                //无生产记录则 新增 活动参与记录 且创造二维码 待后续拓展新的业务
                $infoData = array(
                    'wx_id' => $this->splitObj->wx_id,
                    'fk_user_id' => 0,
                    'fk_split_id' => $this->splitObj->id,
                    'level' => $this->splitInfoObj->level,
                    'parent_id' => $this->splitInfoObj->parent_id,
                    'media_id' => '0',
                    'create_dt' => '0',
                    'createat' => time(),
                    'subscribe_num' => '0',
                    'unsubscribe_num' => '0',
                    'headimg' => $this->_users['avatar'],
                    'nickname' => $this->_users['name'],
                    'openid' => '',
                    'unionid' => '',
                    'channel_id' => $this->splitInfoObj->channel_id,
                    'corp_status' => '1',
                    'corp_subscribe_num' => '0',
                    'corp_unsubscribe_num' => '0',
                    'corp_number' => '0',
                    'customer_id' => $this->_users['id']
                );
                //创建记录
                $WechatLiebianinfoModel = new WechatLiebianinfo();//实例化活动详情
                $infoSaveResult = $WechatLiebianinfoModel->create($infoData);
                if($infoSaveResult == false){
                    return false;
                }
                $this->splitInfoObj = $WechatLiebianinfoModel;
                
                //创建企业微信联系码
                $Contact_Request_Data = array(
                        'type' => 1,
                        'scene' => 2,
                        'remark' => $this->splitInfoObj->nickname,
                        'state' => 'splitinfo_'.$this->splitInfoObj->id,
                        'user' => [$this->_msg['UserID']]
                    );
                //请求创建外部联系人
                $add_contactRet = WechatCorp::add_contact_way($Contact_Request_Data);
                if($add_contactRet['errcode'] == 0){
                    $get_contactRet = WechatCorp::get_contact_way($add_contactRet['config_id']);
                    if($get_contactRet['errcode'] == 0){
                        $contactData = array(
                            'company_id' => $this->_users['company_id'],
                            'config_id' => $add_contactRet['config_id'],
                            'type' => 1,
                            'scene' => 2,
                            'remark' => $this->splitObj->title,
                            'state' => 'splitinfo_'.$this->splitInfoObj->id,
                            'user' => json_encode([$this->_msg['UserID']]),
                            'party' => json_encode($get_contactRet['contact_way']['party']),
                            'expires_in' => $get_contactRet['expires_in'],
                            'unionid' => $get_contactRet['contact_way']['unionid'],
                            'qr_code' => $get_contactRet['contact_way']['qr_code'],
                            'conclusions' => json_encode($get_contactRet['contact_way']['conclusions']),
                            'wx_id' => $this->splitObj->wx_id,
                            'fk_split_id' =>$this->splitObj->id,
                            'create_dt' =>0,
                            'media_id' =>'',
                            'fk_customer_id' => $this->_users['id'],
                        );
                        $ContactInfoObj = new CorpCompaniesContact();
                        $ContactInfoObj->create($contactData);
                    }else{
                        $this->_log->debug('系统发送企业微信二维码异常 get_conf');
                        return false;
                    }
                }else{
                        $this->_log->debug('系统发送企业微信二维码异常');
                        return false;
                }

            }else{
                $info_id = str_replace('splitinfo_','',$ContactInfoObj->state);
                $where = array(
                    'id' => $info_id,
                );
                $this->splitInfoObj = WechatLiebianinfo::findFirst($where);
                $this->splitInfoObj->customer_id = $this->_users['id'];
                $this->splitInfoObj->save();
                if($this->splitInfoObj == false){
                    return false;
                }
            }
        }
        return true;
    }
    public function hamDist($s1, $s2){
        $len1 = strlen($s1);
        $len2 = strlen($s2);
        if($len1 != $len2)
        {
            return false;
        }
        $dist = 0;
        for($i = 0; $i < $len1; $i++)

        {
            if($s1[$i] != $s2[$i])
            {
                $dist++;
            }
        }
        return $dist;
    }

    public function default_Welcome(){
        $this->_log->debug('发送欢迎语开始：'.$this->_users['name']);
        if(!isset($this->_msg['WelcomeCode'])) return false;
        $status = false;
        if(strpos($this->_msg['State'],'yyzn_welcome_group_') !== false){
             $welcomeid = str_replace('yyzn_welcome_group_','',$this->_msg['State']);
             $Welcome = CorpCompaniesWelcome::findFirst([
                'conditions' => array(
                    'id' => $welcomeid,
                    ),
                ]);
            if($Welcome != false){
                $status = true;
            }
        }
        if($status === false){
            $Welcome = CorpCompaniesWelcome::findFirst([
                'conditions' => array(
                    'company_id' => $this->_users['company_id'],
                    'userids find_in_set' => [$this->_msg['UserID']]
                    ),
                'order' => 'updated desc'
                ]); 
        }
        
        if($Welcome == false){
            $this->_log->debug('发送欢迎语：没有找到结果'.$this->_users['name']);

            return false;
        }
        $reques_welcome = array();
        //判断是否有文字消息
        if($Welcome->text_status > 0){
            $reques_welcome['text']['content'] = str_replace('#昵称#',$this->_users['name'], $Welcome->text);
        }
        switch ($Welcome->type) {
            case '1':
                # 图片消息
                $media = $this->get_welcome_media($Welcome,$Welcome->image_pic_url);
                if($media != false){
                    $reques_welcome['image']['media_id'] = $media;
                }
                break;
            case '2':
                $reques_welcome['link'] = array(
                    'title' => $Welcome->link_title,
                    'url' => $Welcome->link_url,
                    );
                if($Welcome->link_pic_url != ''){
                    $reques_welcome['link']['picurl'] = $Welcome->link_pic_url;
                }
                if($Welcome->link_desc != ''){
                    $reques_welcome['link']['desc'] = $Welcome->link_desc;
                }
                break;
            case '3':
                # 小程序消息
                $reques_welcome['miniprogram'] = array(
                    'title' => $Welcome->miniprogram_title,
                    'pic_media_id' => '',
                    'appid' => $Welcome->miniprogram_appid,
                    'page' => $Welcome->miniprogram_page,
                    );
                $media = $this->get_welcome_media($Welcome,$Welcome->miniprogram_pic_url);
                if($media != false){
                    $reques_welcome['miniprogram']['pic_media_id'] = $media;
                }else{
                    return false;
                }
                break;
        }
        //判断是否需要置企业标签
        if($Welcome->tag_id > 0){
            $this->setcorptag($this->_users['id'],$Welcome->tag_id);
        }
        $welcome_res = WechatCorp::send_welcome_msg($this->_msg['WelcomeCode'],$reques_welcome);
            $this->_log->debug('发送欢迎语：结束'.json_encode($welcome_res));

    }

    public function get_welcome_media($Welcome,$url){
        if($this->isMedia($Welcome->create_at)){
            $imagePath = CloudStorage::getImagesTmpFile($url,'long');
            $media_res = WechatCorp::uploadMedia($imagePath);
            if($media_res['errcode'] == 0){
                $this->_log->debug('生成图片成功: '.var_export($media_res,true));
                $reques_welcome['image']['media_id'] = $media_res['media_id'];
                $Welcome->create_at = $media_res['created_at'];
                $Welcome->media_id = $media_res['media_id'];
                $Welcome->save();
                return $media_res['media_id'];
            }else{
                return false;
                 $this->_log->debug('上传失败: '.var_export($media_res,true));
            }
        }else{
            return $Welcome->media_id;
        }
    }
    /**
    * @title 判断临时素材是否过期
    * @author luodiao
    * @param  create_dt 创建时间
    * @return bool : false 过期不能使用 true 可以使用 
    */
    private function isMedia($create_dt = 0){
        $time = 255600 + $create_dt;
        if( $time < time()){
            return true;
        }
        return false;
    }
    /**
     * @title 接受事件
     * @author luodiao
     *
     */
    public function beforeHandleMsg($event, $obj, $data)
    {
        if(!isset($data['msg']['MsgType'])) return false;//"事件不存在";
        $this->_log->debug('接受事件: '.var_export($data,true));

        $this->_corp = $data['corp'];
        $this->_msg = $data['msg'];
        $this->_users = $data['users'];

        if(isset($this->_msg['MsgType']) && $this->_msg['MsgType'] == 'event' && in_array($this->_msg['ChangeType'], ['add_external_contact','del_follow_user'])){
            switch ($this->_msg['ChangeType']) {
                case 'add_external_contact':
                    if(strpos($this->_msg['State'],'splitinfo_') !== false){ 
                        if($this->findInfo_state() == false || $this->findWechat() == false){
                            $this->default_Welcome();
                            return false;
                        }
                    }elseif(strpos($this->_msg['State'],'split_') !== false){
                        if($this->findSplit() == false || $this->findInfo() == false || $this->findWechat() == false){
                            $this->default_Welcome();
                            return false;
                        }
                    }else{
                        $this->default_Welcome();
                        return false;
                    }
                    if($this->checkSplit() == false){
                        $this->default_Welcome();

                        return false;
                    }

                    Di::getDefault()->get('db')->execute("UPDATE miao_wx_split_info SET corp_status=1,corp_id='".$this->_corp['corp_id']."'  WHERE id=".$this->splitInfoObj->id);
                    Di::getDefault()->get('db')->execute("UPDATE miao_wx_split SET corp_subscribe_num=corp_subscribe_num+1,corp_participants_num=corp_participants_num+1 WHERE id=".$this->splitObj->id);
                    $this->_log->debug('自增成功3: ');

                    //上级存在
                    if($this->findPreantInfo()){

                        //添加净增，参与
                         Di::getDefault()->get('db')->execute("UPDATE miao_wx_split_info SET corp_subscribe_num=corp_subscribe_num+1,corp_number=corp_number+1  WHERE id=".$this->preantinfoObj->id);
                        //再次获取一次父级记录
                        $this->findPreantInfo();
                        if($this->splitObj->postage_type > 0){
                            $this->postageList = $this->get_receive_postage_desc($this->preantinfoObj->fk_user_id,$this->preantinfoObj->corp_number);
                        }else{
                            $this->postageList = $this->postageDesc();
                        }
                        //判断是否领奖
                        if($this->postageList != false ){
                            if($this->preant_number >= $this->postageList['number']){
                                if($this->splitObj->postage_type > 0){
                                    $this->send_receive_msg($this->postageList);
                                }else{
                                    //发送奖品
                                    if($this->setPostage()){
                                        //库存-1
                                        LiebianPostage::decrease('inventory',array('id'=> $this->postageList['id']));
                                        //增加完成总数
                                        WechatLiebian::increase('finish_num',array('id'=>$this->splitObj->id));
                                        //发送奖品消息
                                        $this->sendPostageMsg();
                                    } 
                                }
                                
                            }else{
                                //发送助力消息
                                $this->sendSubscribeMsg();
                            }
                        }
                        //发送欢迎语
                        $text_welcome = $this->scantext_next();
                    }else{
                        $text_welcome = $this->scantext_frist();
                    }
                    if($this->splitObj->corp_have_tagid > 0){
                        $this->setcorptag($this->_users['id'],$this->splitObj->corp_have_tagid);
                    }
                    if(!isset($this->_msg['WelcomeCode'])){
                        return false;
                    }

                    //获取media_id
                    $scene_id = $this->splitInfoObj->id;
                    if($this->splitObj->service_type < 2){
                        $qrcode_url = Di::getDefault()->get('config')->Wxsplit->qrcode;
                        $qrcode['url'] = $qrcode_url."?splitid=".$this->splitObj->id."&infoid=".$this->splitInfoObj->id;
                    }else{
                        $qrcode = WechatService::qrcode_create($scene_id);
                    }

                    $this->userFilename = 'corp_'.$this->splitInfoObj->wx_id.'_'.$this->splitInfoObj->fk_user_id;
                    $this->qrcodeFilename = APP_PATH.'/public/split/'.$this->userFilename.'_qecode.png';

                    $this->saveQrcode($qrcode['url'],$this->qrcodeFilename);
                    if(!file_exists($this->qrcodeFilename)){
                        //图片不存在
                        $this->_log->debug('未能找到二维码,重新生成');
                        if($this->splitObj->service_type < 2){
                            $qrcode_url = Di::getDefault()->get('config')->Wxsplit->qrcode;
                            $qrcode['url'] = $qrcode_url."?splitid=".$this->splitObj->id."&infoid=".$this->splitInfoObj->id;
                        }else{
                            $qrcode = WechatService::qrcode_create($scene_id);
                        }
                        $this->userFilename = 'corp_'.$this->splitInfoObj->wx_id.'_'.$this->splitInfoObj->fk_user_id.'_'.time();
                        $this->qrcodeFilename = APP_PATH.'/public/split/'.$this->userFilename.'_qecode.png';
                        $this->saveQrcode($qrcode['url'],$this->qrcodeFilename);
                    }
                    $this->_log->debug('准备生成图片');
                    $ImagesClass = new Images($this->userFilename,APP_PATH.'/public/split/');

                    $poster_rule = json_decode($this->splitObj->poster_rule,true);
                    //兼容新老海报模式
                    if(isset($poster_rule['is_new'])){
                        $poster_total = count($poster_rule['data']);
                        $back_key = 0;
                        if($poster_total > 1){
                            $back_key = rand(0,$poster_total-1);
                        }
                        $poster_rule = $poster_rule['data'][$back_key];
                        $poster_rule['background'] = $poster_rule['background']['url'];
                    }
                    if(isset($poster_rule['picture'])){
                        $poster_rule['picture']['url'] = $this->splitInfoObj->headimg;
                    }
                    if(isset($poster_rule['name'])){
                        $poster_rule['name']['text'] = $this->splitInfoObj->nickname;
                    }
                    try {
                        $imagePath = $ImagesClass->mergeImg($poster_rule,$this->qrcodeFilename);
                    } catch(Throwable $e) {
                        $this->_log->debug('生成图片失败致命错误: '.$e->getMessage());
                        $imagePath = $this->digui_create_haibao($this->userFilename,$poster_rule,$this->qrcodeFilename);
                    }
                    $reques_image = false;
                    if(file_exists($imagePath)){
                        $media_res = WechatCorp::uploadMedia($imagePath);
                        if($media_res['errcode'] == 0){
                            $reques_image = array(
                                    'media_id' =>  $media_res['media_id']
                                );
                        }else{
                             $this->_log->debug('上传失败: '.var_export($media_res,true));
                        }
                    }else{
                        $this->_log->debug('生成图片失败: '.var_export($imagePath,true));
                    }

                    $msg_data = array(
                        'text' => array(
                            'content' => $text_welcome,
                        )
                    );

                    if( $reques_image !== false){
                        $this->_log->debug('图片生成成功: ');
                        $msg_data['image'] = $reques_image;
                    }
                    if(isset($this->_msg['WelcomeCode'])){
                        WechatCorp::send_welcome_msg($this->_msg['WelcomeCode'],$msg_data);
                    }
                    
                    break;
                case 'del_follow_user':
                    $this->unsubscribe();
                    break;
                
                default:
                    # code...
                    break;
            }
        }
    }

    //查找用户可以领取的奖品  查找规则：取当前用户已经领取的最大值
    //粉丝自主领取模式
    private function get_receive_postage_desc($user_id,$user_number=0){
   
        $count = LiebianPostage::count(['conditions'=>array(
                'fk_split_id'=>$this->splitObj->id,
                'inventory >' => 0,
                'status'=>1,
            )]);
        if($count < 1){
            $this->splitObj->update(['status'=>2]);
            $old_appid = WechatService::$appId;
            WechatService::setAppid(Di::getDefault()->get('config')->Weixin->AppId);
            WechatService::sendTextMsg($this->splitObj->create_openid,"您好你的活动【".$this->splitObj->title."】 所有奖品截止库存已经为零,为了保护更好的用户体验，接下来系统将暂停您的这条活动，如需继续继续运行此活动请在后台进行开启");
            WechatService::setAppid($old_appid);
            return  false;
        }

        //查询已经领取的奖品
        $where = array(
            'fk_user_id' => $user_id,
            'wx_id' => $this->wxesObject->id,
            'fk_split_id' =>$this->splitObj->id,
            'is_del'=>0
        );
        $postageinfolist = LiebianPostageinfo::find(['conditions'=>$where,'columns'=>'id,fk_postage_id'])->toArray();
        $postage_ids = [];
        foreach ($postageinfolist as $key => $value) {
            $postage_ids[] = $value['fk_postage_id'];
        }
        $total = 0;//目前消耗的助力数
        $max_postage_number = 0;//最大奖品兑换数
        if(count($postage_ids) > 0){
            //计算已经兑换过的奖品所消耗的助力数总和
            $Postage_user = LiebianPostage::find(['columns' => 'number','conditions'=>array(
                'id' => $postage_ids,
            )]);
            foreach ($Postage_user as  $postage_value) {
                if($postage_value['number'] > $max_postage_number){
                    $max_postage_number = $postage_value['number'];
                }   
                $total += $postage_value['number'];
            }
            if(empty($total)){
                $total = 0;
            }
        }
        $this->preant_number = $user_number - $total;
        //当前有效助力数大于0
        $columns = 'id,title,market_money,inventory,number,images,type,content,media_createat';
        $PostageList = LiebianPostage::findFirst(
            [
                'conditions'=>array(
                    'fk_split_id'=>$this->splitObj->id,
                    'number >' => $max_postage_number,
                    'inventory >' => 0,
                    'status'=>1,
                ),
                'columns'=>$columns
                ]
            );
        if($PostageList != false){
            return $PostageList->toArray();
        }
        return false;
    }

    public function digui_create_haibao($userFilename,$poster_rule,$qrcodeFilename){
        try {
            $ImagesClass = new Images($userFilename,APP_PATH.'/public/split/');
            $imagePath = $ImagesClass->mergeImg($poster_rule,$qrcodeFilename);
        } catch(Throwable $e) {
            $this->_log->debug('生成图片失败致命错误递归: '.$e->getMessage());
        }
        return $imagePath;
    }
    //取消好友
    public function unsubscribe(){
        $conditions = array(
            'corp_id' => $this->_corp['corp_id'],
            'customer_id' => $this->_users['id'],
            'corp_status' => 1,
        );
        $info_list_Obj = WechatLiebianinfo::find($conditions);
        $parent_arr = $split_arr = $info_arr = [];
        foreach ($info_list_Obj as  $value) {
            $con = CorpCompaniesContact::findFirst(['state'=>'splitinfo_'.$value->id]);
            if($con == false){
                continue;
            }
            $userarr = json_decode($con->user,true);
            if(!in_array($this->_msg['UserID'], $userarr)){
                continue;
            }
            if($value->parent_id > 0){
                $parent_arr[] = $value->parent_id;
            }
            $info_arr[] = $value->id;
            $split_arr[] = $value->fk_split_id;
        }
        if(!empty($info_arr) && count($info_arr)> 0){
            $info_ids = implode(',',$info_arr);
            Di::getDefault()->get('db')->execute("UPDATE miao_wx_split_info SET corp_status=2 WHERE id in(".$info_ids.')');
        }

        if(!empty($parent_arr) && count($parent_arr)> 0){
            $parent_ids = implode(',',$parent_arr);
            Di::getDefault()->get('db')->execute("UPDATE miao_wx_split_info SET corp_unsubscribe_num=corp_unsubscribe_num+1,corp_number=corp_number-1 WHERE id in(".$parent_ids.')');
        }
        if(!empty($split_arr) && count($split_arr)> 0){
            $split_ids = implode(',',$split_arr);
            Di::getDefault()->get('db')->execute("UPDATE miao_wx_split SET corp_unsubscribe_num=corp_unsubscribe_num+1,corp_subscribe_num=corp_subscribe_num-1 WHERE id in(".$split_ids.')');
        }
        

        return false;
    }

    /**
    * 保存二维码
    */
    private function saveQrcode($url,$path){
        include APP_PATH.'/api/plugins/phpqrcode/phpqrcode.php'; 
        QRcode::png($url, $path,'L', 4, 2);
        chmod($path, 0777);
    }
    //判断活动是否有效
    private function checkSplit(){
        $msg_status = false;
        $text = '';
        $today = time();
        if($today < $this->splitObj->start_dt){
            if($this->splitObj->end_text != ''){
                $text = $this->splitObj->notstart_text;
                $msg_status = true;

            }
        }
        if($today > $this->splitObj->end_dt){
            if($this->splitObj->end_text != ''){
                $text = $this->splitObj->end_text;
                $msg_status = true;
            }
        }
        if($this->splitObj->status  > 1){
            switch ($this->splitObj->status) {
                case '2':
                    //暂停
                    if($this->splitObj->suspend_text != ''){
                        $text = $this->splitObj->suspend_text;
                        $msg_status = true;
                    }
                    break;
                case '3':
                    //结束
                    if($this->splitObj->end_text != ''){
                        $text = $this->splitObj->end_text;
                        $msg_status = true;
                    }
                    break;
            }
        }
        if($msg_status){
            //发送欢迎语
            $data = array(
                'text' => array(
                    'content' => $text
                )
            );
            if(isset($this->_msg['WelcomeCode'])){
                WechatCorp::send_welcome_msg($this->_msg['WelcomeCode'],$data);
            }
            return false;
        }
        return true;
    }

    //非扫码回复
    private function scantext_frist(){
        $scan_text_frist = $this->splitObj->corp_text;
        if($scan_text_frist != ''){
            $scan_text_frist = str_replace('#昵称#',$this->splitInfoObj->nickname, $scan_text_frist);
            $scan_text_frist = str_replace('#时间#',date("Y-m-d",$this->splitObj->start_dt) .'-'.date("Y-m-d",$this->splitObj->end_dt), $scan_text_frist);
            $wechatauthredirect = $this->ranking_url.$this->splitObj->id;
            $scan_text_frist = str_replace('#排行榜#',"<a href=\"".$wechatauthredirect."\">排行榜</a>", $scan_text_frist);

            return $scan_text_frist;
        }
        return '';
    }

    //扫码回复
    private function scantext_next(){
        $scan_text = $this->splitObj->corp_text;
        if($scan_text != ''){
            $scan_text = str_replace('#昵称#',$this->splitInfoObj->nickname, $scan_text);
            $scan_text = str_replace('#上级#',$this->preantinfoObj->nickname, $scan_text);
            $scan_text = str_replace('#时间#',date("Y-m-d",$this->splitObj->start_dt) .'-'.date("Y-m-d",$this->splitObj->end_dt), $scan_text);
            $wechatauthredirect = $this->ranking_url.$this->splitObj->id;
            $scan_text = str_replace('#排行榜#',"<a href=\"".$wechatauthredirect."\">排行榜</a>", $scan_text);
            return $scan_text;
        }
        return '';
    }

    //助力上级收到的消息
    public function sendSubscribeMsg(){
        $url = $this->goods_url.$this->splitObj->id.'&id='.$this->postageList['id'];
        switch ($this->splitObj->msg_type){
            case '1':
                //送文字消息
                if($this->preantinfoObj->send_status > 0){
                    return false;
                }
                //发送一天文字消息
                $messages = $this->replaceSubscribe($this->splitObj->subscribe_parenttext,$url);
                WechatService::sendTextMsg($this->preantinfoObj->openid,$messages);

                $notdisturb_text = '为了不频繁地打扰您，任务进度就为您汇报到这里，任务完成时，您会收到任务完成通知。如需查看自己的人气值，请点击#排行榜#';
                if($this->splitObj->notdisturb_text != ''){
                    $notdisturb_text = $this->splitObj->notdisturb_text;
                }
                WechatService::sendTextMsg($this->preantinfoObj->openid,$notdisturb_text);
                $this->preantinfoObj->update(['send_status'=>1]);
                break;
            case '3':
                //发送模板消息
                $url = '';
                $subscribe_parenttext = json_decode($this->splitObj->subscribe_parenttext,true);
                $WxTemplateInfoObj = $this->getTemplateDetatil($subscribe_parenttext['template_id']);
                if($WxTemplateInfoObj == false){
                    return false;//不存在则不发送模板消息
                }
                if(isset($subscribe_parenttext['redirect_type'])){
                    switch ($subscribe_parenttext['redirect_type']) {
                        case '2':
                            $url =  $this->goods_url.$this->splitObj->id;
                            break;
                        case '3':
                            $url =  $subscribe_parenttext['redirect_url'];
                            break;
                    }
                }
                $messages = json_decode($WxTemplateInfoObj->json_data,true);
                foreach ($messages as $key => &$item_ok) {
                    $item_ok = $this->replaceSubscribe($item_ok,$url);
                }
                WechatService::sendTplMsg($this->preantinfoObj->openid,$WxTemplateInfoObj->template_id,$url,$messages);
                break;
         }
    }

    public function replaceSubscribe($messages,$url = ""){
        $messages = str_replace('#昵称#',$this->preantinfoObj->nickname, $messages);
        $messages = str_replace('#下级#',$this->splitInfoObj->nickname, $messages);
        $messages = str_replace('#人气值#',$this->preant_number, $messages);
        $messages = str_replace('#差值#',$this->postageList['number'] - $this->preant_number, $messages);
        $messages = str_replace('#奖品名#',$this->postageList['title'], $messages);
        $messages = str_replace('#奖品链接#',"<a href=\"".$url."\">".$this->postageList['title'].'</a>', $messages);
        $desclistUrl = $this->ranking_url.$this->splitObj->id;

        $messages = str_replace('#排行榜#',"<a href=\"".$desclistUrl."\">排行榜</a>", $messages);
        if($this->haveEmojiChar($messages)){
            $messages .= ' ';
        }
        return $messages;
    }

    //发送父级可以兑换的消息
    private function send_receive_msg($postagelist){
        if($postagelist === false){
            return false;//不存在下一步直接返回
        }
        if($this->preant_number != $postagelist['number']){
            return false;
        }

        $wechatauthredirect = $this->goods_url;
        $wechatauthredirect .=  $this->splitObj->id . '&id=' .$postagelist['id'];
        //发送可兑换的完成消息
        switch ($this->splitObj->msg_type) {
            case '1':
                //文字消息
                $messages = $this->replacePostage($this->splitObj->ok_text,$wechatauthredirect);
                $this->setSplitMessage($messages);
                break;
            case '3':
                //模板消息
                $ok_text = json_decode($this->splitObj->ok_text,true);
                $WxTemplateInfoObj = $this->getTemplateDetatil($ok_text['template_id']);
                if($WxTemplateInfoObj == false){
                    return false;//不存在则不发送模板消息
                }
                
                $messages = json_decode($WxTemplateInfoObj->json_data,true);
                foreach ($messages as $key => &$item_ok) {
                    $item_ok = $this->replacePostage($item_ok,$wechatauthredirect);
                }
                WechatService::sendTplMsg($this->preantinfoObj->openid,$WxTemplateInfoObj->template_id,$wechatauthredirect,$messages);
                break;
        }
        return false;
    }
    //发送完成消息
    public function sendPostageMsg(){
        $url =  $this->goods_url.$this->splitObj->id.'&id='.$this->postageList['id']; 
        //完成提示：
        switch ($this->splitObj->msg_type) {
            case '1':
                //文字消息
                if($this->postageList['type'] == 2){
                    $url = $this->postageList['content'];
                }
                $messages = $this->replacePostage($this->splitObj->ok_text,$url);
                $this->setSplitMessage($messages);
                break;
            case '3':
                # 模板消息
                $ok_text = json_decode($this->splitObj->ok_text,true);
                $WxTemplateInfoObj = $this->getTemplateDetatil($ok_text['template_id']);
                if($WxTemplateInfoObj == false){
                    return false;//不存在则不发送模板消息
                }
                if(isset($ok_text['redirect_type'])){
                    switch ($ok_text['redirect_type']) {
                        case '2':
                            $url =  $this->ranking_url.$this->splitObj->id;
                            break;
                        case '3':
                            $url =  $ok_text['redirect_url'];
                            break;
                    }
                }
                if($this->postageList['type'] == 2){
                    $url = $this->postageList['content'];
                }
                $messages = json_decode($WxTemplateInfoObj->json_data,true);
                foreach ($messages as $key => &$item_ok) {
                    $item_ok = $this->replacePostage($item_ok,$url);
                }
                WechatService::sendTplMsg($this->preantinfoObj->openid,$WxTemplateInfoObj->template_id,$url,$messages);
                break;
        }

        //发送奖品
        switch ($this->postageList['type']) {
            case '3':
                #兑换码
                //查询出一条兑换码
                $condition = array(
                    'status' =>1,
                    'fk_postage_id' =>$this->postageList['id'],
                );
                $codeObj = SplitPostagecode::findFirst($condition);
                if($codeObj == false){
                    //兑换码不存在
                    return false;
                }
                $codeObj->fk_user_id = $this->preantinfoObj->fk_user_id;
                $codeObj->receive_dt = time();
                $codeObj->status = 2;
                if($codeObj->save()){
                    $this->setSplitMessage("您的兑换码为：".$codeObj->code,'text',$this->postageList['id']);
                }
                break;
            case '4':
                #卡劵
                $this->setSplitMessage($this->postageList['content'],'card',$this->postageList['id']);
                break;
            case '5':
                #图片
                $this->setSplitMessage($this->postageList['id'],'image',$this->postageList['id']);
                break;
        }
    }

    //写入完成消息队列 等待后台进程发送
    private function setSplitMessage($msg,$type = 'text'){
        $splitmessagesData = array(
            'wx_id' => $this->wxesObject->id,
            'appid' => WechatService::$appId,
            'user_id' => $this->preantinfoObj->fk_user_id,
            'openid' => $this->preantinfoObj->openid,
            'msg_type' => $type,
            'msg_value' => $msg,
            'createat' => time(),
            'scriptat' => time(),
            'postage_id' => $this->postageList['id']
            );
        $Splitmsg = new Splitmsg();
        $Splitmsg->create($splitmessagesData);
    }


    //获取模板信息
    private function getTemplateDetatil($template_id){
        return WxTemplateInfo::findFirst(['wx_id'=>$this->wxesObject->id,'id'=>$template_id,'status'=>1]);
    }

    //替换中奖信息
    private function replacePostage($messages,$url = false){
        $messages = $messages != '' ? $messages : "您好#昵称#恭喜您中奖了\n进度：#进度#\n奖品：#奖品#";
        $messages = str_replace('#昵称#',$this->preantinfoObj->nickname, $messages);
        $messages = str_replace('#人气值#',$this->preant_number, $messages); 
        $messages = str_replace('#进度#',$this->preant_number."/".$this->postageList['number'], $messages);
        $messages = str_replace('#差值#',$this->postageList['number'] - $this->preant_number, $messages);
        $messages = str_replace('#下级#',$this->splitInfoObj->nickname, $messages);
        $messages = str_replace('#奖品名#',$this->postageList['title'], $messages);
        if($url == false){
           $postageUrl = $this->goods_url.$this->splitObj->id.'&id='.$this->postageList['id']; 
        }else{
            $postageUrl = $url;
        }
        
        $messages = str_replace('#奖品链接#',"<a href=\"".$postageUrl."\">".$this->postageList['title'].'</a>', $messages);
        $desclistUrl = $this->ranking_url.$this->splitObj->id;
        $messages = str_replace('#排行榜#',"<a href=\"".$desclistUrl."\">排行榜</a>", $messages);
        if($this->haveEmojiChar($messages)){
            $messages .= ' ';
        }
        return $messages;
    }

    private function haveEmojiChar($str)
    {
        if(!is_string($str)){
            return false;
        }
        $mbLen = mb_strlen($str);
        
        $strArr = [];
        for ($i = 0; $i < $mbLen; $i++) {
            $strArr[] = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($strArr[$i]) >= 4) {
                return true;
            }
        }
        return false;
    }

    //写入奖品记录
    public function setPostage(){
        $postageData = array(
            'fk_user_id' => $this->preantinfoObj->fk_user_id,
            'create_dt' => time(),
            'nick' => '',
            'mobile' => '',
            'address' => '',
            'email' => '',
            'status' => 0,
            'update_dt' => 0,
            'deliver_dt' => 0,
            'fk_split_id' => $this->preantinfoObj->fk_split_id,
            'fk_postage_id' => $this->postageList['id'],
            'wx_id' => $this->preantinfoObj->wx_id,
            'wx_nickname' => $this->preantinfoObj->nickname,
            'wx_headimg' => $this->preantinfoObj->headimg,
        );
        if($this->postageList['type'] > 1){
            $postageData['status'] = 2;
        }
        $LiebianPostageinfoMdel = new LiebianPostageinfo();
        //写入成功标签
        if($this->splitObj->postage_tagid > 0){
            $this->settag($this->preantinfoObj->openid,$this->splitObj->postage_tagid);
            WechatService::setTagsForUsers([$this->preantinfoObj->openid],$this->splitObj->postage_tagid);
        }
        if($this->preantinfoObj->customer_id > 0){
            if($this->splitObj->corp_postage_tagid > 0){
                $this->setcorptag($this->preantinfoObj->customer_id,$this->splitObj->corp_postage_tagid);
            }
        }
        return $LiebianPostageinfoMdel->create($postageData);
    }

    //写入企业标签
    public function setcorptag($Customer_id,$tag_id = 0){
        if($tag_id < 1){
            return;
        }
        CorpCompaniesCustomer::$TABLE_NAME = 'complaints_customer_'.($this->_users['company_id']%128);
        $CustomerObj = CorpCompaniesCustomer::findFirst(['conditions' => array(
            'id' => $Customer_id,
            'company_id' => $this->_users['company_id'],
        )]);
        if($CustomerObj == false){
            return;
        }

        if($CustomerObj->follow_user == ''){
            return;
        }
        $follow_user_arr = explode(',',$CustomerObj->follow_user);
        
        if($CustomerObj->tag_id == ''){
            $tag_arr = array();
        }else{
            $tag_arr = explode(',',$CustomerObj->tag_id);
        }
        if(in_array($tag_id,$tag_arr)){
            return;
        }
        
        //标签赋予用户
        $tagObj = CorpCompaniesTag::findFirst(['id'=>$tag_id,'is_del'=>0,'pid >'=>0]);
        if($tagObj == false){
            return;
        }

        $tag_add_res = WechatCorp::mark_tag_add($follow_user_arr[0],$CustomerObj->external_userid,[$tagObj->tag_id]);
        if($tag_add_res['errcode'] == 0){
            $tag_arr[] =  $tag_id;
            $CustomerObj->tag_id = implode(',',$tag_arr);
            if($CustomerObj->group_id == ''){
                $group_tag_arr = array();
            }else{
                $group_tag_arr = explode(',',$CustomerObj->group_id);
            }
            if(!in_array($tagObj->pid,$group_tag_arr)){
                $group_tag_arr[] = $tagObj->pid;
                $CustomerObj->group_id = implode(',',$group_tag_arr);
            }
            $CustomerObj->save();
        }else{
            Di::getDefault()->get('logger')->debug('luodiao dump 标签失败:'.json_encode($tag_add_res));
        }
    }

    public function settag($openid,$tag_id){
        $tag_user_Obj = WxUser::findUser($this->splitObj->wx_id,$openid);
        if($tag_user_Obj != false){
            $tagid_list = [];
            if($tag_user_Obj->tagid_list != '' && !empty($tag_user_Obj->tagid_list)){
                $tagid_list = $tag_user_Obj->tagid_list;
            }
            if(!in_array($tag_id, $tagid_list)){
                $tag_result = WechatService::setTagsForUsers([$openid],$tag_id);
                if(isset($tag_result['errcode']) && $tag_result['errcode'] == 0){
                    $tagid_list[] = $tag_id;
                    $tag_user_Obj->tagid_list = implode(',', $tagid_list);
                    $tag_user_Obj->save();
                }
            }
        }
        return;
    }

    //查找临近的一个奖品
    private function postageDesc(){
        $conditions = array(
            'fk_split_id'=>$this->splitObj->id,
            'inventory >' => 0,
            'status'=>1,
        );
        $columns = 'id,title,market_money,inventory,number,images,type,content,media_createat';
        //查询该活动所有的奖品
        $PostageList = LiebianPostage::find(['conditions'=>$conditions,'columns'=>$columns,'order'=>'number ASC'])->toArray();
        if(empty($PostageList)){
            $this->splitObj->update(['status'=>2]);
            $old_appid = WechatService::$appId;
            WechatService::setAppid(Di::getDefault()->get('config')->Weixin->AppId);
            WechatService::sendTextMsg($this->splitObj->create_openid,"您好你的活动【".$this->splitObj->title."】 所有奖品截止库存已经为零,为了保护更好的用户体验，接下来系统将暂停您的这条活动，如需继续继续运行此活动请在后台进行开启");
            WechatService::setAppid($old_appid);
            return  false;//当前未设置奖品
        }
        $count = LiebianPostage::count(['conditions'=>$conditions]);
        if($count < 1){
            $this->splitObj->update(['status'=>2]);
            $old_appid = WechatService::$appId;
            WechatService::setAppid(Di::getDefault()->get('config')->Weixin->AppId);
            WechatService::sendTextMsg($this->splitObj->create_openid,"您好你的活动【".$this->splitObj->title."】 所有奖品截止库存已经为零,为了保护更好的用户体验，接下来系统将暂停您的这条活动，如需继续继续运行此活动请在后台进行开启");
            WechatService::setAppid($old_appid);
            return  false;//当前未设置奖品
        }
        

        $where = array(
            'fk_user_id' => $this->preantinfoObj->fk_user_id,
            'wx_id' => $this->wxesObject->id,
            'fk_split_id' =>$this->splitObj->id,
            'is_del'=>0
            );
        $postageinfolist = LiebianPostageinfo::find(['conditions'=>$where,'columns'=>'id,fk_postage_id'])->toArray();
        if(empty($postageinfolist)){
            $postage_ids = false;
        }else{
            $postage_ids = array_column($postageinfolist,'fk_postage_id');
        }
        $ret = array();
        foreach ($PostageList as $key => $postage_item) {
            if($postage_item['inventory'] < 1 ){
                continue;
            }
            if($postage_ids == false || !in_array($postage_item['id'],$postage_ids)){
                $res = $postage_item;
                if($count < 2 && $count > 0){
                    if($res['inventory'] < 1){
                        $this->splitObj->update(['status'=>2]);
                        $old_appid = WechatService::$appId;
                        WechatService::setAppid(Di::getDefault()->get('config')->Weixin->AppId);
                        WechatService::sendTextMsg($this->splitObj->create_openid,"您好你的活动【".$this->splitObj->title."】 所有奖品截止库存已经为零,为了保护更好的用户体验，接下来系统将暂停您的这条活动，如需继续继续运行此活动请在后台进行开启");
                        WechatService::setAppid($old_appid);
                    }
                }
                return $res;
            }
        }
        return false;
    }

    //查询活动
    public function findSplit(){
        $split_id = str_replace('split_','',$this->_msg['State']);
        $where = array(
            'id' => $split_id,
        );
        $this->splitObj = WechatLiebian::findFirst($where);
        if($this->splitObj == false){
            $this->_log->debug('dump:活动不存在');
            //活动不存在
            return false;
        }
        //查询当前用户最后一次参与
        $infowhere = array(
            'wx_id' => $this->splitObj->wx_id,
            'unionid' => $this->_users['unionid'],
            'corp_time >'=> 0,
        );
        $this->splitInfoObj = WechatLiebianinfo::findFirst([
                'conditions' => $infowhere,
                'order' => 'corp_time desc'
            ]
        );
        if($this->splitInfoObj != false){
            $where = array(
                'id' => $this->splitInfoObj->fk_split_id,
            );
            $this->splitObj = WechatLiebian::findFirst($where);
            if($this->splitObj == false){
                $this->_log->debug('dump:活动不存在');
                //活动不存在
                return false;
            }
        }

        if($this->splitObj->is_del > 0){
            $this->_log->debug('dump:活动已经被删除');
            return false;
        }
        return true;
    }

    //查询当前用户参与记录
    public function findInfo(){
        if($this->splitInfoObj != false){
            return true;
        }
        $where = array(
            'fk_split_id' => $this->splitObj->id,
            'unionid' => $this->_users['unionid'],
        );
        $this->splitInfoObj = WechatLiebianinfo::findFirst($where);
        if($this->splitInfoObj == false){
            //未找到参与记录
            $this->_log->debug('dump:记录不存在'.var_export($where,true));

            return false;
        }
        return true;
    }

    //查询父级用户参与记录
    public function findPreantInfo(){
        if($this->splitInfoObj->parent_id <= 0){
            return false;
        }
        $where = array(
            'id' => $this->splitInfoObj->parent_id,
            'fk_split_id' => $this->splitObj->id,
        );
        $this->preantinfoObj = WechatLiebianinfo::findFirst($where);
        if($this->preantinfoObj == false){
            //未找到参与记录

            $this->_log->debug('dump:父级不存在');
            return false;
        }
        $this->preant_number = $this->preantinfoObj->corp_number;

        return true;
    }

    //获取公众号
    public function findWechat(){

        $this->wxesObject = Wx::findFirstById($this->splitObj->wx_id);
        if($this->wxesObject == false){
            $this->_log->debug('dump:公众号不存在');

            return false;
        }
        WechatService::$appId = $this->wxesObject->oauth_appid;
        return true;
    }
}