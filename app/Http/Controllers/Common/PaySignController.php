<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Order;
use Illuminate\Support\Facades\Log;

class PaySignController extends Controller
{
    /**
     * [getSign 获取支付签名]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function sign(Request $request) {

        $this->validate($request, 
            [
                'order_id'   => 'required|integer|exists:order,order_id',
                'pay_type'   => 'sometimes|required|string|in:alipay,wechat'
            ],
        $this->message);

        $pay_type = $request->input('pay_type','wechat');
        $Order    = Order::where(['order_id'=>$request->order_id,'user_id'=>\Auth::id(),'pay_status'=>0,'order_status'=>0])
                        ->with(['order_goods'=>function($query){
                            $query->select(['order_id','goods_name','spec_key_name']);
                        }])->first();

        if (empty($Order)) return respond(201,'该订单未找到！');

        $subject = '迪速帮 '. $Order->order_sn;

        $responseDataSign = [];

        switch ($pay_type) {
            case 'alipay':

                    $aop = new \AopClient;
                    foreach (config('pay.alipay') as $key => $value) {
                        $aop->$key = $value;
                    }
                    
                    $alipayRequest = new \AlipayTradeAppPayRequest();
                    $bizcontent = json_encode([
                        'subject'         => $subject,
                        'out_trade_no'    => $Order->order_sn,
                        'total_amount'    => $Order->order_amount,
                        'product_code'    => 'QUICK_MSECURITY_PAY',
                    ]);
                    $alipayRequest->setNotifyUrl(env('NOTIFY_URL'));
                    $alipayRequest->setBizContent($bizcontent);
                    $responseDataSign['sign'] = $aop->sdkExecute($alipayRequest);
                break;
            case 'wechat':
            
                    $wechatAppPay = new \wechatAppPay(config('pay.wechatpay'));
                    $params['body'] = $subject;
                    $params['out_trade_no'] = $Order->order_sn;
                    $params['total_fee'] = ($Order->order_amount * 100);
                    $params['trade_type'] = 'APP';
                    $result = $wechatAppPay->unifiedOrder($params);
                    if (empty($result['prepay_id'])){
                        return respond(201,$result['return_msg']);
                    }
                    $responseDataSign = $wechatAppPay->getAppPayParams($result['prepay_id']);
                break;
        }
        
        try {
            Log::channel('pay_sign')->info('order_id:'.$Order->order_id,$responseDataSign);
        } catch (\Exception $e) {}

        return respond(200,'获取成功！',$responseDataSign);
    }
}