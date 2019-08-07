<?php

namespace App\Listeners;

use App\Events\CalculateBrokerage;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Contracts\Queue\ShouldQueue;

use App\Model\Order;

class ShopDayNumListener {

    public function handle(CalculateBrokerage $event) {
        
        try {
            
            if (empty($event->order->created_at) || empty($event->order->shop_id) || empty($event->order->order_id)) {
                return false; 
            }

            $md = date('md',strtotime($event->order->created_at));

            $day_nums = Order::where('shop_id',$event->order->shop_id)
                                ->whereDate('created_at',date('Y-m-d',strtotime($event->order->created_at)))
                                ->where('day_num','<>','0')
                                ->selectRaw("substring_index(day_num,'-',-1) as day_nums")
                                ->pluck('day_nums')
                                ->toArray();

            $num = empty($day_nums) ? 0 : max($day_nums);
            $num += 1;
            
            Order::where('order_id',$event->order->order_id)->where('shop_id',$event->order->shop_id)->update(['day_num'=>"{$md}-{$num}"]);

        } catch (\Exception $e) {
            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);

        }
    }
}