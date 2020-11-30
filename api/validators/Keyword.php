<?php
/** 
 * @desc 平台关键字校验
 */
use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;

class Keyword extends Validator {

    /** 
     * @desc 执行验证 
     * @param Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(Validation $validator, $attribute) {
        if(isset($_SESSION['Auth']['User']['id']) && $_SESSION['Auth']['User']['id'] != ''){
            if(isset($_SESSION['wx_id']) && $_SESSION['wx_id'] != ''){
                $value = $validator->getValue($attribute);
                $model = $this->getOption('model');
                $fk_id = $this->getOption('fkid');
                $valid = $this->verifyKeyword($value,$model,$fk_id);
                if (count($valid) > 0) {
                    $validator->appendMessage(new Message($valid['msg'], $attribute, 'Keyword'));
                    return false;
                }
                return true;
            }else{
                $validator->appendMessage(new Message("未选择公众号", $attribute, 'Keyword'));
                return false;
            }
        }else{
            $validator->appendMessage(new Message("当前未登陆", $attribute, 'Keyword'));
            return false;
        }
        
    }

    /** 
     * @desc 关键字是否存在
     * @param string $text 关键字 
     * @return boolean 
     */
    public function verifyKeyword(string $text,$model,$fk_id) {
        $text_arr = explode(',',$text);
        $result = array();
        for ($i=0; $i < count($text_arr); $i++) { 
            $keywordObj = YyznKeyword::findFirst(['keyword'=>$text_arr[$i],'wx_id'=>$_SESSION['wx_id']]);
            if($keywordObj != false){
                if($keywordObj->type > 0){
                    //如果fkid不为null 直接判定为修改
                    if($fk_id !== null && $model !== null){
                        if($fk_id == $keywordObj->fk_id && $model == $keywordObj->model){
                            continue;
                        }
                    }
                    //查询是哪个活动
                    $auth = WxActivitiesAuth::findFirst([
                        'model' => $keywordObj->model
                        ]);
                    if($auth == false){
                        $keywordObj->delete();
                    }else{
                        $keywordString = '关键字：'.$text_arr[$i].' 已经存在，请在应用【'.$auth->title.'】中id为：【'.$keywordObj->fk_id.'】 删除再继续添加此关键字';
                    }
                }else{
                    if($fk_id !== null){
                        if($fk_id == $keywordObj->fk_id){
                            continue;
                        }
                    }
                    $keywordString = '关键字：'.$text_arr[$i].' 已经存在，请在自动回复中删除再继续添加此关键字';
                }

                $result['msg'] = $keywordString;
                $result['id'] = $keywordObj->fk_id;
                return $result;//存在直接跳出
            }
        } 
        return $result;
    }
}