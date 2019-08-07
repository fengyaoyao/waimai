<?php
namespace App\Listeners;
use App\Events\CancelOrder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Model\{Order,AccountLog,User,RefundOrder,CouponList};
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\GoodsStock;
class CancelOrderListener
{
    use GoodsStock;
    /**
     * 取消订单  订单状态 0 待确认 1 已确认 2 已出餐 3 已取消  4 已完成  5 已拒绝 6 取消订单失败和退款中
     */
    public function handle(CancelOrder $event)
    {
        DB::beginTransaction(); //开启事务
        $order_id = $event->order_id;
        $Order = Order::where('order_id',$order_id)->lockForUpdate()->first();
        if (empty($Order)) { return false; }
        //退余额
        if ($Order->user_money > 0) {
            $AccountLogOne = new AccountLog();
            $result_boolean_one = User::where('user_id',$Order->user_id)->increment('moeny',$Order->user_money);
            $AccountLogOne->desc       = "余额抵扣返还";
            $AccountLogOne->user_money = "{$Order->user_money}";
            $AccountLogOne->user_id    = $Order->user_id;
            $AccountLogOne->order_id   = $order_id;
            if( !($result_boolean_one && $AccountLogOne->save()) ) {
                DB::rollBack();//事务回滚
                return false;
            }
        }
        //退积分
        if ($Order->integral > 0 ) {
            $AccountLogTwo = new AccountLog();
            $result_boolean_two = User::where('user_id',$Order->user_id)->increment('points',$Order->integral);
            $AccountLogTwo->desc       = "积分抵扣返还";
            $AccountLogTwo->pay_points = "{$Order->integral}";
            $AccountLogTwo->user_id    = $Order->user_id;
            $AccountLogTwo->order_id   = $order_id;
            if( !($result_boolean_two && $AccountLogTwo->save()) ) {
                DB::rollBack();//事务回滚
                return false;
            }
        }
        //退优惠卷
        if ($Order->coupon_price > 0) {
            $CouponList = CouponList::where('order_id',$order_id)->where('uid', $Order->user_id)->first();
            if (!empty($CouponList)) {
                $CouponList->status = 0;
                $CouponList->order_id = null;
                $CouponList->use_time = null;
                if (!$CouponList->save()) {
                    DB::rollBack();//事务回滚
                    return false;
                }
            }
        }
        
        // 退红包
        if ($Order->red_packet_money > 0) {

            $UserRedPacket = \App\Model\UserRedPacket::where('user_id',$Order->user_id)->where('order_id',$order_id)->first();

            if (!empty($UserRedPacket)) {
                $UserRedPacket->status = 0;
                $UserRedPacket->order_id = null;
                $UserRedPacket->use_time = null;

                if (!$UserRedPacket->save()) {
                    DB::rollBack();//事务回滚
                    return false;
                }
            }
        }
        $Order->order_status = $event->order_status; //订单状态
        $Order->cancel_time  = date('Y-m-d H:i:s'); //取消时间
        $Order->who_cancel   = $event->type;
        if ($Order->save()) {
            $this->changeStockNum($order_id,'increment');
            DB::commit();//提交事务
        }else{
            DB::rollBack();//事务回滚
            return false;
        }
        if ($Order->pay_status == 0) return true;
        //请求退款接口
        if ($Order->order_amount > 0) {
             try {
                $request_result = curl_request(env('BACKEND_DN')."mobile/payment/refund?order_id={$order_id}");
            } catch (\Exception $e) {
                $request_result = [
                    'message' => '退款异常！',
                    'status' => 201
                ];
            }
            $arr = [
                    'desc'           => $request_result['message'],
                    'user_id'        => $Order->user_id,
                    'order_id'       => $order_id,
                    'money'          => $Order->order_amount,
                    'pay_code'       => $Order->pay_code,
                    'transaction_id' => $Order->transaction_id,
                    'type'           => $event->type,//代表商户取消
            ];
            if(!empty($request_result) && $request_result['status'] == 200) {
                Order::where('order_id',$order_id)->update(['is_refund_account' => 1]);
                RefundOrder::create(array_merge($arr,['status'=>1])); //记录退款成功数据
            }else{
                Order::where('order_id',$order_id)->update(['order_status' => 6]);
                RefundOrder::create($arr); //记录退款失败数据
            }                         
        }
        $push_id = User::find($Order->user_id)->value('push_id');
        push_for_jiguang($push_id ,"你有一笔订单已经取消",0);
        return true;
    }
}
