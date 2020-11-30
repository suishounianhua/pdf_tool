<?php

// 构造返回值数组
class FJUtilities
{
    public static function returnResult($result, $info)
    {
        return [
            'result' => $result,
            'info' => $info
        ];
    }
}

