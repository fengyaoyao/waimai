<?php

namespace App\Listeners;

use App\Events\CalculateBrokerage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Model\{Order,Area,Shop,UserAddress,User, Delivery, OrderDistribution, DeliveryGroup};
use Illuminate\Support\Facades\DB;

class CalculateBrokerageListener
{

    public function handle(CalculateBrokerage $event)
    {
        try {

            DB::beginTransaction(); //开启事务

            $order = Order::where('order_id',$event->order->order_id)->lockForUpdate()->first();

            if (empty($order)) { return false; }

            $area = Area::where('area_id',$order->area_id)->select(['settlement','process_date'])->first();
            $process_date = (empty($area->process_date)) ? 10 : $area->process_date;
            $settlement = (empty($area->settlement)) ? [] : json_decode($area->settlement,true);

            $Shop = Shop::where('shop_id',$order->shop_id)->select('sell_time','group_id','is_open_distribution')->first();

            $group_id = 0;
            $sell_time = 20; 

            if (!empty($Shop)) {
                $sell_time = $Shop->sell_time; 
                $group_id = $Shop->group_id;

                if (!empty($Shop->is_open_distribution)) {
                    $order->distribution_status = 5;
                }
            }

            $finish_time = time() + (($process_date  + $sell_time) * 60); //预计完成送达时间戳
            $order->ensure_time = date('Y-m-d H:i:s'); //商家确认时间
            $order->who_cancel = 1; //代表只能由商户取消
            $order->finish_time = date('Y-m-d H:i:s', $finish_time);
            $order->order_status = 1;

            //自取订单
            if (($order->delivery_type == 1) && $order->save()) {
                DB::commit();//提交事务
                return true;
            }

            $one_commission_rake = 0; //第一阶段配送对商品抽成比例
            $two_commission_rake = 0; //第二阶段配送对商品抽成比例
            $one_commission_money = 0; //第一阶段配送对商品抽成金额
            $two_commission_money = 0; //第二阶段配送对商品抽成金额
            $base_shipping_fee = 0; //第一阶基础配送费
            $total_money = 0; //骑手获得配送佣金
            $floor_money = 0;
            
            // 第一阶段
            $ParentDelivery = DeliveryGroup::where(['group_id'=>$group_id,'delivery_id'=>$order->delivery_pid, 'delivery_pid'=>0])->first();

            if (!empty($ParentDelivery)) {
                $base_shipping_fee = $ParentDelivery->base_shipping_fee ?? 0; 
                $one_commission_rake = $ParentDelivery->commission_rake ?? 0; 
            }
            
            if(!empty($one_commission_rake)) {
                $one_commission_money = round($order->goods_price * ($one_commission_rake/100),2);
            }

            //第二阶段
            $ChildDelivery =  DeliveryGroup::where(['group_id'=>$group_id,'delivery_id'=>$order->delivery_id, 'delivery_pid'=>$order->delivery_pid])->first(); 

            if(!empty($ChildDelivery->commission_rake)) {
                $two_commission_money = round($order->goods_price * ($ChildDelivery->commission_rake/100),2);
            }

            //楼层费
            if(!empty($ChildDelivery->base_shipping_fee)) {
                $floor_money = $ChildDelivery->base_shipping_fee;
            }

            //计算骑手获得配送佣金 = 基础配送费+ 楼层费 + 第一阶段订单提成金额 + 第二阶段订单提成金额
            $total_money = $base_shipping_fee + $floor_money + $one_commission_money + $two_commission_money; 
            $order->rider_amount = $one_commission_money + $two_commission_money; //记录骑手对商品抽成的总金额
            $order->horseman_amount = $total_money;
            $distribution = [
                'order_id' => $order->order_id,
                'base_money' => $base_shipping_fee,
                'floor_money' => $floor_money,
                'one_commission_rake' => $one_commission_rake,
                'two_commission_rake' => $two_commission_rake,
            ];

            //是否是接力订单
            if ($order->is_relay) {

                $distribution['two_money'] = $floor_money + $two_commission_money;
                $distribution['one_money'] = $base_shipping_fee + $one_commission_money;
                $distribution['one_formula'] = '基本配送费' . $base_shipping_fee . ' +订单提成' . $one_commission_money;
                $distribution['two_formula'] = '楼层费' . $floor_money . ' +订单提成' . $two_commission_money;
                
            }else{

                $distribution['one_money'] = $total_money;
                $distribution['one_formula'] = '基本配送费'.$base_shipping_fee.' + 订单提成'.($one_commission_money + $two_commission_money).'+ 楼层费'.$floor_money;
            }
            
            $order->ps_formula = '基本配送费'.$base_shipping_fee.' + 订单提成'.($one_commission_money + $two_commission_money).'+ 楼层费'.$floor_money;


            if ($order->save() && OrderDistribution::insert($distribution)) {

                DB::commit();//提交事务
                return true;
            }

            DB::rollBack();//事务回滚
            return false;
            
        } catch (\Exception $e) {

            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);

            DB::rollBack();//事务回滚
            return false;
        }
    }
}
