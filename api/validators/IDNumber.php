<?php
/** 
 * @desc 中华人民共和国居民身份证（18位）校验 
 */
use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;

class IDNumber extends Validator {

    /** 
     * @desc 执行验证 
     * @param Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(Validation $validator, $attribute) {
        $value = $validator->getValue($attribute);
        $valid = $this->verifyIDNumber($value);
        if (!$valid) {
            $message = $this->getOption('message');
            if (!$message) {
                $message = 'The ID number is not valid';
            }
            $validator->appendMessage(new Message($message, $attribute, 'IDNumber'));
            return false;
        }
        return true;
    }

    /** 
     * @desc 验证身份证号 
     * @param string $number 身份证号 
     * @return boolean 
     */
    public function verifyIDNumber(string $number) {
        $len = strlen($number);
        if($len != 18){
            return false;
        }
        $num = str_split($number);
        $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $valid = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for($i=0; $i<17;$i++){
            if(is_nan($num[$i])){
                return false;
            }
            $sum += $num[$i] * $weight[$i];
        }
        $mode = $sum % 11;
        if($valid[$mode] == strtoupper($num[17])){
            return true;
        }else{
            return false;
        }
    }
}