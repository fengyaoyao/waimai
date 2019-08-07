<?php

namespace App\Listeners;

use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\{Shop,Order};

class ShopSoldListener
{
    public function handle(OtherTrigger $event) {
        try {
        
            $shop_id = $event->shop_id;
            $Shop = Shop::where('shop_id',$shop_id)->select(['shop_id','avg_minute','sales'])->first();
            $avg_minute = Order::selectRaw('avg(TIMESTAMPDIFF(MINUTE,ensure_time,appeared_time)) as avg_minute')
                             ->whereNotNull('appeared_time')
                             ->whereNotNull('ensure_time')
                             ->where('delivery_type',0)
                             ->where('order_type',0)
                             ->where('pay_status',1)
                             ->where('order_status',4)
                             ->where('shop_id',$shop_id)
                             ->value('avg_minute');
            // 修改店铺销量
            $Shop->sales = ($Shop->sales + 1);

            //修改店铺平均出餐时间
            $Shop->avg_minute = round($avg_minute,1);
            $Shop->save();
            
            return true;

        } catch (\Exception $e) {

            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
        }
    }
}