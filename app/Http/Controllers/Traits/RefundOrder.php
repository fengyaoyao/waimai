<?php



namespace App\Http\Controllers\Traits;
use Illuminate\Support\Facades\Log;

trait RefundOrder

{



    /**

     * [alipayRefund 支付宝退款]

     * @param  string $value [description]

     * @return [type]        [description]

     */

    public function alipayRefund($transaction_id,$total_fee,$refund_fee,$out_request_no) {

        try {

            $aop = new \AopClient;
            foreach (config('pay.alipay') as $key => $value) {
                $aop->$key = $value;
            }

            $aop->version = '1.0';

            $aop->timestamp = date('yyyy-MM-dd HH:mm:ss');

            $aop->method = 'alipay.trade.refund';

            $alipayRequest = new \AlipayTradeRefundRequest();

            $params = [

                'trade_no' => $transaction_id,

                'refund_amount' => $refund_fee,
            ];

            $params['out_request_no'] = $out_request_no;
            $alipayRequest->setBizContent(json_encode($params));
            $result = $aop->execute($alipayRequest);

            if (empty($result)) {
                throw new \Exception("支付宝退款失败！");
            }

            $responseNode = str_replace(".", "_", $alipayRequest->getApiMethodName()) . "_response";

            $log_str = "transaction_id:{$transaction_id} refund_fee:{$refund_fee}";

            $resultCode = $result->$responseNode->code;

            if(!empty($resultCode) && $resultCode == 10000) {
                
                Log::channel('refund')->info($log_str);
                return true;
            }

            Log::channel('refund')->info($log_str . ' sub_code:' .$result->$responseNode->sub_code .' sub_msg:' . $result->$responseNode->sub_msg);

            throw new \Exception($result->$responseNode->sub_msg);

        } catch (\Exception $e) {

            throw new \Exception($e->getMessage());
        }
    }



    /**

     * [wechatRefund 微信退款]

     * @param  string $value [description]

     * @return [type]        [description]

     */

    public function wechatRefund($transaction_id,$total_fee,$refund_fee,$out_refund_no) {
        try {

            $wechatAppPay = new \wechatAppPay(config('pay.weixin'));

            $params['transaction_id'] = $transaction_id;

            $params['total_fee'] = ($total_fee * 100 );

            $params['refund_fee'] = ($refund_fee * 100 );

            $params['out_refund_no'] = $out_refund_no;


            $result = $wechatAppPay->refundOrder($params);

            if (empty($result)) {
                throw new \Exception("微信款失败！");
            }

            $log_str = "transaction_id:{$transaction_id} refund_fee:{$refund_fee}";

            if (!empty($result) && $result['result_code'] == 'SUCCESS') {

                Log::channel('refund')->info($log_str);
                return true;
            }

            Log::channel('refund')->info($log_str . ' err_code:' . $result['err_code'] . ' err_code_des:' . $result['err_code_des'] );

            throw new \Exception($result['err_code_des']);

        } catch (\Exception $e) {

            throw new \Exception($e->getMessage());
        }
    }
}