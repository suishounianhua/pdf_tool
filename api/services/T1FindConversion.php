<?php
/**
 * Created by PhpStorm.
 * User: adonishong
 * Date: 16/9/11
 * Time: 上午2:02
 */

//namespace Fangjie\Service\FindConversion;

class T1FindConversion {

    static $_instance;

    private function __construct() {
    }

    private function getBind() {
        return array(
            'sex' => array(
                '男' => 1,
                '女' => 2,
            ),
            'nationality' => array(
                '中国' => 1,
                '其它' => 2,
            ),
        );
    }

    public function getFindParaFromSql($conditions, $rawCondition) {
        //1. 根据给出的conditions生成查找目标数组
        //(?<=\ :), 以" :"开头, (?=\: ), 以": "结尾，[^ ]*?，中间字符串不包含空格，且最短
        $targetProperty = array();
        $bindResult = array();
        preg_match_all('/(?<=\ :)[^ ]*?(?=\: )/', $conditions, $targetProperty);
        //2. 在查找目标数组中, 查看本地的转换函数，如果存在相关函数，那么对输入数据进行转换，否则使用原始数据
        //存在相关函数, getBind返回的数组中有同名key
        $bindArray = $this->getBind();
        foreach ($targetProperty[0] as $k => $v) {
            //rawCondition不存在相关字段的时候不应该直接返回false，false的状态下find可能返回所有数据
            // if (!array_key_exists($v, $rawCondition)) {
            //  return false;
            // }
            if (array_key_exists($v, $rawCondition)) {
                if (array_key_exists($v, $bindArray)) {
                    $bindResult[$v] = $bindArray[$v][$rawCondition[$v]];
                } else {
                    $bindResult[$v] = $rawCondition[$v];
                }
            }
        }
        return array(
            $conditions,
            "bind" => $bindResult,
        );
    }

    public static function getInstance() {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
            echo "create instance\n";
        }else{
            echo "get instance\n";
        }

        return self::$_instance;
    }
}