<?php
//项目配置文件
// 以下配置你只能修改 databases.prefix
return [
        //
        'key' => 'test',
	//数据库配置
	'databases' => array(
		//连接类型
		'adapter' => 'Mysql',
		//地址
                'host' => 'localhost',
                //用户名
                'username' => 'root',
                //密码
                'password' => '123456',
                //数据库名称
                'dbname' => 'test',
                //字符集
                'charset' => 'utf8mb4',
                //
                'persistent' => 'true',
		
		//表前缀 你可以自定义
		'prefix' => 't_'

		)
];
