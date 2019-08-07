<?php
// 注意里面数组键的位置不能改变
return [

	//阿里大于短信
	'alidayu' => [
		'accessKeyId' => env('ALIDAYU_ACCESS_KEY_ID','') ,
	    'accessSecret' => env('ALIDAYU_ACCESS_KEY_SECRET',''),
	    'templateCode' => [
			'user_login' => 'SMS_149400190',  //用户登录
			'bind_phone' => 'SMS_149405182',  //绑定手机
			'user_register' => 'SMS_149400189', //用户注册
			'order_inform' => 'SMS_164513807',  //订单通知
	    ]
	],

	//极光
	'jiguang' => [

		// 商户
		'mer' => [
			'key' => env('JIGUANG_MER_KEY',''),
			'secret' => env('JIGUANG_MER_SECRET',''),
		],

		// 骑手
		'rider' => [
			'key' => env('JIGUANG_RIDER_KEY',''),
    		'secret' => env('JIGUANG_RIDER_SECRET',''),
		],

		// 用户
		'user' => [
			'key' => env('JIGUANG_USER_KEY',''),
    		'secret' => env('JIGUANG_USER_SECRET',''),
		]
	],

	// 个推
	'getui'=> [

		// 商户
		'mer' => [
			'appid' => env('GETUI_MER_APPID',''),
			'appkey' => env('GETUI_MER_APPKEY',''),
			'master_secret' => env('GETUI_MER_MASTER_SECRET',''),
			'app_secret' => env('GETUI_MER_APP_SECRET',''),
		],

		// 骑手
		'rider' => [
			'appid' => env('GETUI_RIDER_APPID',''),
			'appkey' => env('GETUI_RIDER_APPKEY',''),
			'master_secret' => env('GETUI_RIDER_MASTER_SECRET',''),
			'app_secret' => env('GETUI_RIDER_APP_SECRET',''),
		],

		// 用户
		'user' => [
			'appid' => env('GETUI_USER_APPID',''),
			'appkey' => env('GETUI_USER_APPKEY',''),
			'master_secret' => env('GETUI_USER_MASTER_SECRET',''),
			'app_secret' => env('GETUI_USER_APP_SECRET',''),
		]
	],
	'test_user_id' => [
		1,2,3,4,5,6,7,8,9,10,11,41,42,48,49,67,69,1344,1524,1526,1525,822,5518,5519,5528,7114,11494,7766,52,8827,37891,89222,5527,89389
	],
];


