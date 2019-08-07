<?php 
return [
	'alipay'=>[
		'gatewayUrl'    => "https://openapi.alipay.com/gateway.do",
		'appId'         => env('ALIPAYAPPID',''),
		'rsaPrivateKey' => env('ALIPAYRSAPRIVATEKEY',''),
		'format'        => "json",
		'charset'       => "UTF-8",
		'signType'      => "RSA2",
		'alipayrsaPublicKey' => env('ALIPAYRSAPUBLICKEY','')
	],
	'wechatpay'=> [
		'key'    => env('WECHAT_APPKEY',''),
		'appid'  => env('WECHAT_APPID',''),
		'mch_id' => env('WECHAT_MCHID',''),
		'notify_url' => env('WECHAT_NOTIFY_URL',''),
	],
	'weixin' => [
		'key' => env('WX_APPKEY',''),
		'appid' => env('WX_APPID',''),
		'mch_id' => env('WX_MCHID',''),
		'notify_url' => env('WX_NOTIFY_URL',''),
		'SSLCERT_PATH' => storage_path(env('WX_SSLCERT_PATH','')),
		'SSLKEY_PATH' => storage_path(env('WX_SSLKEY_PATH','')),
	]
];

