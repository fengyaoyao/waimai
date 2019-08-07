<?php

namespace App\Listeners;


use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\{Order,AgencyBill,Admin,OrderRatio};
use Illuminate\Support\Facades\DB;


class OrderAgencyListener
{

    /**
     * [handle description]
     * @param  ClearingAccount $event [description]
     * @return [boolean]       [注意 返回值必须是 true or false]
     */

    public function handle(OtherTrigger $event)
    {


        $Order = Order::where(['order_id'=>$event->order_id,'order_status'=>4])->select(['order_id','shop_id','goods_price'])->first();

        if (empty($Order)) {
            return '订单未找到';
        }

        if ($Order->goods_price <= 0) {
            return '商品金额为0';
        }

        //获取平台对商品的抽成金额
        $mer_rake = OrderRatio::where('order_id',$Order->order_id)->value('rake') ?? 0;


        $platform_money = ($mer_rake / 100) * $Order->goods_price; 


        //获取代理抽成金额和比例
        $ShopIDfindAdminId = Admin::where('role_id',15)->whereNotNull('shop_ids')->pluck('shop_ids','admin_id');

        if (empty($ShopIDfindAdminId)) {
            return '代理人员未找到';
        }

        $admin_id = '';

        foreach ($ShopIDfindAdminId as $k => $v) {
            if ( array_search($Order->shop_id, explode(',', $v)) != false) {
                $admin_id = $k; break;
            }
        }

        //开启事务
        DB::beginTransaction();

        try {

            $Admin = Admin::where('admin_id',$admin_id)->first();

            if (empty($Admin)) {
               return '代理人员ID未找到';
            }

            $get_money = round($platform_money * ($Admin->rake/100),2);

            $Admin->money = $Admin->money + $get_money;

            $AgencyBill = [
              'rake' => ($Admin->rake/100),
              'money' => $get_money,
              'platform_money' => $platform_money,
              'admin_id' => $admin_id,
              'order_id' => $Order->order_id,
              'desc' => '代理分成',
              'type' => 1,
              'created_at' => date('Y-m-d H:i:s')
            ];

            if (AgencyBill::insert($AgencyBill) && $Admin->save()) {
              //提交事务
              DB::commit();
              return true;
            }

            //事务回滚
            DB::rollBack();
            
        } catch (\Exception $e) {

            //事务回滚
            DB::rollBack();
            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
        }
    }
}