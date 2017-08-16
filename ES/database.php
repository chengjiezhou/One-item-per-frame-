<?php

return array (
    //测试环境
	'default' => array (
        'hostname' => 'rdsxgo101fr8d1qj848b.mysql.rds.aliyuncs.com:3306',
		'database' => 'hailiang_db',
		'username' => 'kunlun',
		'password' => 'kunlun_root',
		'tablepre' => 'information_',
		'charset' => 'utf8',
		'type' => 'mysql',
		'debug' => true,
		'pconnect' => 0,
		'autoconnect' => 0
	),
	// 'default_sub' => array (
 //        	'hostname' => '127.0.0.1:3306',//从库
 //        	'database' => 'qingbao3dot0_test',
 //        	'username' => 'root',
 //        	'password' => '',
 //        	'tablepre' => 'information_',
 //        	'charset' => 'utf8',
 //        	'type' => 'mysql',
 //        	'debug' => true,
 //        	'pconnect' => 0,
 //        	'autoconnect' => 0
 //    ),
    'mongo' =>array(
		'hostname' => 'mongodb://192.168.0.96:27017', // 测试机
		'database' => 'genShoufeiDPTweiborenwuOld150421',
		'username' => '',
		'password' => '',
		'autoconnect' => 1,
		'type' => 'mongo'
	),
	'redis' =>array(
		'hostname' => '192.168.0.78', // 测试机,没什么用,不需要配置
		'port' => '6379', // 测试机,没什么用,不需要配置
		'type' => 'redis',
		'tag_tree' => 3, // 标签树用的库
		'cache' => 7,	 //缓存
		'cache_time' => 600,	 //缓存过期时间，单位（秒）
	),
	'elasticsearch'=>array(
		//'hostname' => '100.98.168.52:9200',
		'hostname' => array(
			0 => '100.98.168.52:9200',
		),
		'hostname_day' => array(
			0 => '10.132.43.209:9200',
		),
		'cluster' =>'hylanda_es_cluster',
		'username' => '',
		'password' => '',
		'index_pre' => 'hylanda_',
		'index_day_pre' => 'hylanda_7d_',
		'type' => 'elasticsearch'
	),
	'sentinel'=>array(
		'mastername' => 'hlcluster',
		'cluster' => array(
			0 => array(
				'hostname' => '10.117.60.208',
				'port' => '26379'
			),
			1 => array(
				'hostname' => '10.174.176.120',
				'port' => '26379'
			)
		)
	),
);

?>
