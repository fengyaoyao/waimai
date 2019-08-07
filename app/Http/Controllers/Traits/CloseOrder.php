<?php
namespace App\Http\Controllers\Traits;


use Illuminate\Support\Facades\Log;
use Exception;


trait CloseOrder {

	public function tradeClose($out_trade_no ='',$pay_code ='')
	{

        switch ($pay_code) {

		    case 'alipayApp':
		    case 'alipayMobile':

			    try {
						$aop = new \AopClient();
						// $payconfig = config('pay.alipay');
						// $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
						// $aop->appId = $payconfig['appId'];
						// $aop->rsaPrivateKey = $payconfig['rsaPrivateKey'];
						// $aop->alipayrsaPublicKey= $payconfig['alipayrsaPublicKey'];
						// $aop->apiVersion = '1.0';
						// $aop->signType = 'RSA2';
						// $aop->postCharset='UTF-8';
						// $aop->format='json';
						foreach (config('pay.alipay') as $key => $value) {
	                        $aop->$key = $value;
	                    }

						$request = new \AlipayTradeCloseRequest();
						$request->setBizContent(json_encode([
					    	'out_trade_no' => $out_trade_no,
					    	'operator_id' => '001'
					    ]));

						$result = $aop->execute($request);
						print_r($result);exit;


						if (empty($result)) {
			                throw new Exception("支付宝交易关闭异常！");
			            }

			            Log::channel('close_order')->info('支付宝交易关闭',json_decode(json_encode($result),true)['alipay_trade_close_response']);

						$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";

						$resultCode = $result->$responseNode->code;

						if(!empty($resultCode) && $resultCode == 10000) {
			                return true;
			            }else{
				            throw new Exception($result->$responseNode->sub_msg);
			            }

			    } catch (Exception $e) {

		            throw new Exception($e->getMessage());
			    }

            break;

		    case 'wechatApp':
		    case 'weixin':

				try {

		            $wechatAppPay = new \wechatAppPay(config('pay.weixin'));
		            $result = $wechatAppPay->closeOrder($out_trade_no);

		            if (empty($result)) {
		                throw new Exception("微信支付交易关闭异常！");
		            }

		            Log::channel('close_order')->info('微信支付交易关闭',$result);
				
		            if (!empty($result) && $result['result_code'] == 'SUCCESS') {
		                return true;
		            }

		            throw new Exception($result['err_code_des']);

		        } catch (Exception $e) {
		            throw new Exception($e->getMessage());
		        }

            break;

            default :
			    throw new Exception("订单交易关闭异常");
	            break;
        }	
	}
}