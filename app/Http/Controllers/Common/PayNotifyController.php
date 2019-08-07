<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Model\Order;

class PayNotifyController extends Controller
{

    /**
     * [aliPayNotify 支付宝支付回调]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function aliPayNotify(Request $request)
    {
        $post = $request->all();

        try {
            Log::channel('pay_notify')->info('',$post);
        } catch (\Exception $e) {

        }

        $aop  = new \AopClient;

        $aop->alipayrsaPublicKey = env('ALIPAYRSAPUBLICKEY');

        if ($aop->rsaCheckV1($post, NULL, "RSA2")) {

            if (($post['trade_status'] == 'TRADE_CLOSED') || ($post['trade_status'] == 'TRADE_FINISHED')) {
                return 'success';
            }

            DB::beginTransaction();

            $Order = Order::where('order_sn',$post['out_trade_no'])
                            ->where('pay_status',0)
                            ->where('order_status',0)
                            ->first();
    
            if (!empty($Order) && $post['trade_status'] == 'TRADE_SUCCESS') {

                $Order->pay_time = date('Y-m-d H:i:s');
                $Order->pay_code = 'alipayApp';
                $Order->pay_status = 1;
                $Order->transaction_id = $post['trade_no'];

                if ($Order->save()) {
                    DB::commit();
                    $this->payNotice($post['out_trade_no']);
                    return 'success';
                }else{
                    DB::rollBack();
                }
            }
        }
    }

    /**
     * [wechatPayNotify 微信支付回调]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function wechatPayNotify(Request $request)
    {
        try {
            Log::channel('pay_notify')->info(file_get_contents('php://input'));
        } catch (\Exception $e) {
            
        }

        $wechatAppPay = new \wechatAppPay(config('pay.wechatpay'));
        
        $post = $wechatAppPay->getNotifyData();

        if (empty($post) || $post == false || empty($post['sign'])) {
            return false;
        }

        $postSign = $post['sign'];

        unset($post['sign']);


        if ($wechatAppPay->MakeSign($post) != $postSign) {
            return false;
        }

        DB::beginTransaction();

        $Order = Order::where('order_sn',$post['out_trade_no'])
                ->where('pay_status',0)
                ->where('order_status',0)
                ->first();

        if (!empty($Order) && ($post['result_code'] == 'SUCCESS')) {

            $Order->pay_time = date('Y-m-d H:i:s');
            $Order->pay_code = 'wechatApp';
            $Order->pay_status = 1;
            $Order->transaction_id = $post['transaction_id'];

            if ($Order->save()) {
                DB::commit();
                $this->payNotice($post['out_trade_no']);
                $wechatAppPay->replyNotify();
            }else{
                DB::rollBack();
            }
        }
    }

    /**
     * [payNotice description]
     * @return [type] [description]
     */
    public function payNotice($order_sn = '') {

        try {
            $url = env('BACKEND_DN').'mobile/api/pay_notice?order_sn='.$order_sn;
            curl_request($url);
        } catch (\Exception $e) {}
    }
}