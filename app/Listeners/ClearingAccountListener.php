<?php
namespace App\Listeners;
use App\Events\ClearingAccount;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\{Shop,Order,MerBill,UserBill,User,OrderDistribution,Area,Delivery};
use Illuminate\Support\Facades\DB;
class ClearingAccountListener
{
    /**
     * [handle description]
     * @param  ClearingAccount $event [description]
     * @return [boolean]       [注意 返回值必须是 true or false]
     */
    public function handle(ClearingAccount $event)
    {
        try {
            DB::beginTransaction(); //开启事务
            $findWhere = [
                'is_closing' => 0,
                'flag' => 0,
                'relay_closing' => 0,
                'order_status' => 2,
                'pay_status' => 1,
                'order_id' => $event->order->order_id
            ];
            $Order = Order::where($findWhere)->lockForUpdate()->first();
            if (empty($Order)) { return false; }
            $shop_id    = $Order->shop_id;
            $ps_id      = $Order->ps_id;
            $order_id   = $Order->order_id;
            $date       = date('Y-m-d H:i:s');
            $is_closing = ($Order->horseman_amount > 0) ? 0 : 1;
            $relay_closing = 0;
            // 0外送订单 1到店自提
            if( $Order->delivery_type == 0 && $Order->horseman_amount > 0) {
                $OrderDistribution = OrderDistribution::where('order_id',$Order->order_id)->first();
                //是否是商家配送
                if ($Order->distribution_status['key'] > 0 ) {
                    $ShopFirst = Shop::where('shop_id',$shop_id)->lockForUpdate()->increment('balance',$Order->horseman_amount);
                    //记录配送入金额
                    $MerBillFirst = MerBill::insert([
                        'shop_id'  => $shop_id,
                        'order_id' => $order_id,
                        'money'=> $Order->horseman_amount,
                        'desc' => '商户自主配送收入',
                        'type' => 1,
                        'created_at' => $date
                    ]);
                    if ($ShopFirst && $MerBillFirst) {
                        $is_closing = 1;
                    }else {
                        DB::rollBack();
                        return false;
                    }
                } else {
                    if ($Order->is_relay && $Order->relay_id > 0) {
                        //第二阶段结算
                        $UserRelay = User::where('user_id',$Order->relay_id)->lockForUpdate()->increment('rider_money',$OrderDistribution->two_money);
                        $UserRelayBill = UserBill::insert([
                            'user_id'    => $Order->relay_id,
                            'money'      => $OrderDistribution->two_money,
                            'desc'       => '配送收入',
                            'order_id'   => $order_id,
                            'created_at' => $date
                        ]);
                        if ($UserRelay && $UserRelayBill) {
                            $relay_closing = 1;
                            $floor_amount = 0;
                        }else{
                            DB::rollBack();
                            return false;
                        }
                        //第一阶段结算
                        $User = User::where('user_id',$ps_id)->lockForUpdate()->increment('rider_money',$OrderDistribution->one_money);
                        $UserBill = UserBill::insert([
                            'user_id'    => $ps_id,
                            'money'      => $OrderDistribution->one_money,
                            'desc'       => '配送收入',
                            'order_id'   => $order_id,
                            'created_at' => $date
                        ]);
                        if ($User && $UserBill) {
                            $is_closing = 1;
                        }else{
                            DB::rollBack();
                            return false;
                        }
                    } else {
                        $User = User::where('user_id',$ps_id)->lockForUpdate()->increment('rider_money',$Order->horseman_amount);
                        $UserBill = UserBill::insert([
                            'user_id'    => $ps_id,
                            'money'      => $Order->horseman_amount,
                            'desc'       => '配送收入',
                            'order_id'   => $order_id,
                            'created_at' => $date
                        ]);
                        if ($User && $UserBill) {
                            $is_closing = 1;
                        }else{
                            DB::rollBack();
                            return false;
                        }
                        if ($Order->is_relay && $Order->relay_id == 0) {
                            $OrderDistribution->one_money = $Order->horseman_amount;
                            $OrderDistribution->one_formula = '基本配送费'.$OrderDistribution->base_money.' + 订单提成'.$Order->rider_amount.'+ 楼层费'.$OrderDistribution->floor_money;
                            $OrderDistribution->two_money = '';
                            $OrderDistribution->two_formula = '';
                            if (!$OrderDistribution->save()) {
                                DB::rollBack();
                                return false;
                            }
                        }
                    }
                }
            }
            //修改订单信息
            $Order->flag            = 1; 
            $Order->is_closing      = $is_closing;
            $Order->relay_closing   = $relay_closing;
            $Order->shipping_status = 2;
            $Order->order_status    = 4;
            $Order->confirm_time    = $date;
            $MerBillSecond = [
                'shop_id' => $shop_id,
                'order_id' => $order_id,
                'money' => $Order->shop_amount,
                'desc' => '订单收入',
                'type' => 1,
                'created_at' => $date
            ];
            $ShopSecond = Shop::where('shop_id',$shop_id)->lockForUpdate()->increment('balance',$Order->shop_amount);
            if($Order->save() && $ShopSecond && MerBill::insert($MerBillSecond)) {
                //提交事务
                DB::commit();
                //触发事件 
                event(new \App\Events\OtherTrigger($order_id));
                return true;
            }else{
                DB::rollBack();
                return false;
            } 
        } catch (\Exception $e) {
            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
            //事务回滚
            DB::rollBack();
            return false;
        }
    }
}