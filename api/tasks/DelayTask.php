<?php

use Phalcon\Cli\Task;
use Phalcon\Di;
class DelayTask extends Task
{
    public $notdisturbNumber = 17;//消息计数器
    public $redisKey = "split_message_for_script";

    public function mainAction()
    {
        echo "This is the default task and the default action" . PHP_EOL;
    }

    //开始执行任务
    public function runAction(){
        $forstart_m = memory_get_usage();
        do{
            //开启事务
            $manager =  Di::getDefault()->get('transactions');
            $transaction = $manager->get();
            $time = time();
            try{
                
                $DelayCronSend = WxDelayCronSend::findFirst([
                    'conditions' => array(
                        'status' => 0,
                        'send_ed <' => $time
                        ),
                ]);
                if($DelayCronSend == false ){
                    $transaction->rollback('无法找到数据');
                }

                $DelayCronSend->status = 1;
                if($DelayCronSend->save() == false){
                    $transaction->rollback('当前记录已经被操作');
                }else{
                    //准备返回值进行发送
                    $crondata = array(
                        'appid' => $DelayCronSend->appid,
                        'cron_id' => $DelayCronSend->cron_id,
                        'userinfo' => json_decode($DelayCronSend->userinfo,true),
                    );
                    $DelayCronSend->delete();
                    WechatService::setAppid($crondata['appid']);
                    $transaction->commit();
                }

                
                //准备sql
                $sql = "select * from miao_wx_delay_cron_msg where fk_id=".$crondata['cron_id']." order by weigh desc";
                $msgList = $this->db->fetchAll($sql);
                if(!empty($msgList)){
                    foreach ($msgList as $key => $value) {
                        $this->send($value,$crondata['userinfo']);
                        usleep(1000);
                    }
                }

            }catch(Exception $e) {
                //延迟
                sleep(3);
                echo $e->getMessage()."休眠3秒\n";
            }
            $forend_m = memory_get_usage();
            $m = $forend_m - $forstart_m;
            if($m >= 83886080){
                echo "定时关注程序终止等待新开脚本\n";
                exit();
            }
            //查询事务

        }while(true);
    }

    /**
    * @title 判断临时素材是否过期
    *
    */
    private function isMedia($send_data){
        $time = 255600 + $send_data['m_dt'];
        if( $time > time()){
            return $send_data['media_id'];
        }
        //上传素材
        $imagePath = CloudStorage::getImagesTmpFile($send_data['image'],'long');
        $media_res = WechatService::uploadMedia($imagePath);
        if(isset($media_res['media_id']) !== false){
            $this->db->update("miao_wx_delay_cron_msg",['media_id','m_dt'],[$media_res['media_id'],$media_res['created_at']],"id = '".$send_data['id']."'");
            return $media_res['media_id'];
        }else{
            return '';
        }
    }

    //发送
    public function send($send_data,$userinfo){
        switch ($send_data['type']) {
            case 'text':
                WechatService::sendTextMsg($userinfo['openid'],$this->replace($send_data['text'],$userinfo));
                break;

            case 'news':
                $articles= array(
                    'title'=> $send_data['title'],
                    'description'=> $send_data['description'],
                    'url' => $send_data['url'],
                    'picurl' => $send_data['image']
                );
                WechatService::sendArticleMsg($userinfo['openid'],[$articles]);

                break;
            case 'image':
                WechatService::sendImageMsg($userinfo['openid'],$this->isMedia($send_data));

                break;
            case 'miniprogrampage':
                $miniprogrampageData = array(
                    'title' => $this->replace($send_data['title'],$userinfo),
                    'appid' => $send_data['appid'],
                    'pagepath' => $send_data['pagepath'],
                    'thumb_media_id' => $this->isMedia($send_data)
                );
                WechatService::sendMiniprogrampageMsg($userinfo['openid'],$miniprogrampageData);
                break;
        }
    }

    //替换关键字
    //替换信息
    private function replace($messages,$user){
        $messages = str_replace('【昵称】',$user['nickname'], $messages);
        $messages = str_replace('【国家】',$user['country'], $messages);
        $messages = str_replace('【省份】',$user['province'], $messages);
        $messages = str_replace('【城市】',$user['city'], $messages);
        $messages = str_replace('【关注时长】',format_time_interval(time() - $user['created'],3), $messages);
        $messages = str_replace('【关注时间】',$user['created'], $messages);
        $messages = preg_replace('/href=[\'|\"](\S+)[\'|\"]/i',"href=\"".'${1}'."\"",$messages);
        if($user['sex'] == 1){
            $messages = str_replace('【性别】','男', $messages);
            $messages = str_replace('【性别1】','帅哥', $messages);
            $messages = str_replace('【性别2】','小哥哥', $messages);
            $messages = str_replace('【性别3】','学弟', $messages);
            $messages = str_replace('【性别4】','学长', $messages);
            $messages = str_replace('【性别5】','小鲜肉', $messages);
        }else{
            $messages = str_replace('【性别】','女', $messages);
            $messages = str_replace('【性别1】','美女', $messages);
            $messages = str_replace('【性别2】','小姐姐', $messages);
            $messages = str_replace('【性别3】','学妹', $messages);
            $messages = str_replace('【性别4】','学姐', $messages);
            $messages = str_replace('【性别5】','小仙女', $messages);
        }
        $hans = array(0,'星期一','星期二','星期三','星期四','星期五','星期六','星期天');
        $messages = str_replace('【星期】',$hans[ date('N')], $messages);
        $messages = str_replace('【日期】',date('Y年m月d日'), $messages);
        $messages = str_replace('【时分】',date('H点i分'), $messages);
        $h = date('G');
        if ($h < 9 && $h > 5) {
            $timeText = '早上';
        }
        elseif ($h < 12 && $h >= 9) {
            $timeText = '上午';
        }
        elseif ($h < 13  && $h >= 12 ) {
            $timeText = '中午';
        }
        elseif ($h < 18  && $h >= 13 ) {
            $timeText = '下午';
        }
        else {
            $timeText = '晚上'; // 6点到凌晨4点
        }
        $messages = str_replace('【时间段】',$timeText, $messages);
        
        $pattern="/【关注人数\+(([^【])+?)】/is";
        //截取href的正则  
        preg_match_all($pattern,$messages,$guanzhu_match);
        if(!empty($guanzhu_match[0])){
            Di::getDefault()->get('logger')->debug('luoio dump guanzhu:'.$guanzhu_match[0][0]);
            $wx_total = 0;
            $userlist = WechatService::getUserList();
            if( empty($userlist['errcode']) ) {
                $wx_total = $userlist['total'];
            }
            $baseNumber = 0;
            if(!empty($guanzhu_match[1][0])){
                $baseNumber = $guanzhu_match[1][0];
            }
            $guanzhu_number = $wx_total+$baseNumber;
            $messages = str_replace($guanzhu_match[0][0],$guanzhu_number, $messages);
        }
        return $messages;
    }

    //任务终结
    public function delete_cron($id){
        $this->db->delete("miao_wx_delay_cron_send","id = '".$id."'");
    }
}