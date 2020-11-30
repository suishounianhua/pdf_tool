<?php

class Yunpian {

    static public $apikey = '3520e2d9bb84f750c48d30a2706d09b3';
    //static public $api_secret = 'dc867158';
	
	//&mobile=您的手机号码&content=".urlencode("中文 空格 换行符"
	static public $single_send_url = "https://sms.yunpian.com/v2/sms/single_send.json";
    static public $batch_send_url = "https://sms.yunpian.com/v2/sms/batch_send.json";
	
    
    //添加轨迹
    static public function single_send($mobile, $content){    	
        $data = array(
            'apikey' => self::$apikey,
            'mobile' => $mobile,
            'text' => $content,
        );
    	return self::curl(self::$single_send_url,$data);
    }
    
    static public function batch_send($mobile, $content){

        if(is_array($mobile)) {
            $mobile = implode(',',$mobile);
        }

    	$data = array(
            'apikey' => self::$apikey,
            'mobile' => $mobile,
            'text' => $content,
        );
        return self::curl(self::$single_send_url,$data);
    }
    
    static public function curl($url,$post = array()){
    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:text/plain;charset=utf-8', 'Content-Type:application/x-www-form-urlencoded','charset=utf-8'));

    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_HEADER, 0);
    	
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    	curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // 302 跳转5次
    	curl_setopt($ch, CURLOPT_TIMEOUT, 5); //页面最大执行时间为5s
    	if(!empty($post)){
	    	curl_setopt($ch, CURLOPT_POST, true);
	    	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	    	//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    	}
    	$retry = 0;
    	do{
    		$content = curl_exec($ch);
    		$retry++;
    	}while( (curl_errno($ch) !== 0) && $retry < 3);
    	
    	$content = trim($content, "\xEF\xBB\xBF");
    	
//     	$http_info = curl_getinfo($ch);
//     	print_r($http_info);print_r(curl_error($ch)); //SSL certificate problem: unable to get local issuer certificate
    	
    	if (curl_errno($ch) !== 0) {
    		$arr = array('code'=>2,'msg'=>curl_errno($ch).':'.curl_error($ch));
    		curl_close($ch);
    		return $arr;
    	}
    	else{
    		curl_close($ch);
    		return json_decode($content,true);
    	}
//     	if ($http_info['http_code'] == '200') {
//     		return ;
//     	}
    }
   
}
?>