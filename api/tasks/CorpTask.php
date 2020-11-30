<?php

use Phalcon\Cli\Task;
use Phalcon\Di;
class CorpTask extends Task
{
    public $notdisturbNumber = 17;//消息计数器
    public $redisKey = "split_message_for_script";

    public function mainAction()
    {
        echo "This is the default task and the default action" . PHP_EOL;
    }

    public function runAction($data){
        $next_id = $data[1];
        $limit = 20;
        $userCompanyObj = CorpCompanies::findFirst([
            'conditions'=>array('id'=>$data[0])]
        );
        if($userCompanyObj == false){
            echo "未能找到企业信息\n";exit;
        }
        $suite_infoconditions = array('corp_id'=> $userCompanyObj->corpid);
        $suite_info = CorpSuite::findFirst(array('conditions'=>$suite_infoconditions));
        if($suite_info == false){
            echo "未能找到配置信息\n";exit;
        }
        
        

        WechatCorp::$corpID = $suite_info->corp_id;
        WechatCorp::$corpSecret = $suite_info->suite_secret;
        WechatCorp::$corpContactSecret = $suite_info->contact_suite_secret;

        //获取企业id
        $company_id = $userCompanyObj->id;

        CorpCompaniesCustomer::$TABLE_NAME = 'complaints_customer_'.($company_id%128);
        //计算总数
        $total = CorpCompaniesCustomer::count(['conditions'=>array('company_id' => $company_id)]);
        if( $total < 1){
             echo "无数据可以操作\n";exit;
        }
        $cron_total = ceil($total/$limit);
        for ($i=0; $i < $cron_total; $i++) { 
            $list = CorpCompaniesCustomer::find([
                'conditions' => array(
                    'company_id' => $company_id,
                    'id >' => $next_id,
                    ),
                'order' => 'id asc',
                'limit' => $limit
                ])->toArray();
            if(empty($list)){
                echo "无数据可以操作\n";exit;
            }
            foreach ($list as  $itrm_v) {
                # code...
                $External_Ret = WechatCorp::get_contact_users($itrm_v['external_userid']);
                if($External_Ret['errcode'] == 0){
                    //合成外部联系人数组
                    $follow_user = false;
                    if(isset($External_Ret['follow_user']) && count($External_Ret['follow_user']) > 0){
                        $follow_user = $External_Ret['follow_user'];
                    }
                    $usersObj = CorpCompaniesCustomer::saveuser_cron($userCompanyObj->id,$External_Ret['external_contact'],$follow_user);
                }
                $next_id = $itrm_v['id'];
                echo $next_id." 写入成功\n";
            }
        }
        echo "写入成功\n";exit;
    }

    /**
    * @title 根据活动置上企业微信标签
    * @param 活动id 标签id 企业id 初始执行id 
    */

    public function set_split_tagAction($data=array('0','0','0','0')){
        if($data[0] <= 0 || $data[1] <= 0){
            echo "参数异常\n";exit;
        }
        $limit = 20;
        //查询裂变活动
        $splitList = WechatLiebian::find(['id'=>$data[0],'is_del'=>1]);
        if($splitList == false){
            echo "无法找到该活动\n";exit;
        }

        //查询企业配置
        $userCompanyObj = CorpCompanies::findFirst([
            'conditions'=>array('id'=>$data[2])]
        );
        if($userCompanyObj == false){
            echo "未能找到企业信息\n";exit;
        }
        $suite_infoconditions = array('corp_id'=> $userCompanyObj->corpid);
        $suite_info = CorpSuite::findFirst(array('conditions'=>$suite_infoconditions));
        if($suite_info == false){
            echo "未能找到配置信息\n";exit;
        }
        
        WechatCorp::$corpID = $suite_info->corp_id;
        WechatCorp::$corpSecret = $suite_info->suite_secret;
        WechatCorp::$corpContactSecret = $suite_info->contact_suite_secret;

        CorpCompaniesCustomer::$TABLE_NAME = 'complaints_customer_'.($data[2]%128);
        //查找用户参与记录
        $next_id = $data[3];
        $conditions = array(
            'fk_split_id' => $data[0],
            'id >' => $next_id,
            'customer_id >' => 0
            );
        //总数
        $total = WechatLiebianinfo::count(['conditions'=>$conditions]);
        if( $total < 1){
             echo "无数据可以操作\n";exit;
        }
        $cron_total = ceil($total/$limit);
        for ($i=0; $i < $cron_total; $i++) { 
            $conditions = array(
                'fk_split_id' => $data[0],
                'id >' => $next_id,
                'customer_id >' => 0
            );
            $list = WechatLiebianinfo::find([
                'conditions' => $conditions,
                'order' => 'id asc',
                'limit' => $limit
                ])->toArray();
            if(empty($list)){
                echo "无数据可以操作\n";exit;
            }
            //查询企业用户
            foreach ($list as  $itrm_v) {
                $this->setcorptag($itrm_v['customer_id'],$data[1],$data[2]);
                $next_id = $itrm_v['id'];
                echo "完毕：".$itrm_v['id']."\n";

            }
        }
    }

    //写入企业标签
    public function setcorptag($Customer_id,$tag_id = 0,$company_id){
        //开始打标签
        if($tag_id < 1){
            echo "标签id 不存在\n";
            return;
        }
        $CustomerObj = CorpCompaniesCustomer::findFirst(['conditions' => array(
            'id' => $Customer_id,
            'company_id' => $company_id,
        )]);
        if($CustomerObj == false){
            echo "企业用户:".$Customer_id." 不存在\n";
            return;
        }

        if($CustomerObj->follow_user == ''){
            echo "企业用户:".$Customer_id." 无客服\n";
            return;
        }
        $follow_user_arr = explode(',',$CustomerObj->follow_user);
        //判断标签是否存在
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
             echo "企业标签:".$tag_id." 不存在\n";
            return;
        }

        $tag_add_res = WechatCorp::mark_tag_add($follow_user_arr[0],$CustomerObj->external_userid,[$tagObj->tag_id]);
        if($tag_add_res['errcode'] == 0){
            $tag_arr[] =  $tag_id;

            if($CustomerObj->group_id == ''){
                $group_tag_arr = array();
            }else{
                $group_tag_arr = explode(',',$CustomerObj->group_id);
            }
            if(!in_array($tagObj->pid,$group_tag_arr)){
                $group_tag_arr[] = $tagObj->pid;
                $CustomerObj->group_id = implode(',',$group_tag_arr);
            }
            $CustomerObj->tag_id = implode(',',$tag_arr);
            $CustomerObj->save();
        }else{
            echo "写入标签失败".$Customer_id.":\n".json_encode($tag_add_res)."\n";
        }
    }
}