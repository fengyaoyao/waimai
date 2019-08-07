<?php

namespace App\Listeners;

use App\Events\CalculateBrokerage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\User;

class DistributionPushMessageListener {

    public function handle(CalculateBrokerage $event) {

        try {
            
    	    if (empty($event->order->area_id))
    	    {
    	        return false; 
    	    }

            $map = [
                'type' => 2,
                'work_status' => 1,
                'area_id' => $event->order->area_id,
            ];

            if (!empty($event->order->is_relay)) {
                $map['rider_type'] = 0;
            }

            //激光推送订单给骑手
            $push_id = User::where($map)->whereNotNull('push_id')->pluck('push_id')->toArray();

            if (!empty($push_id)) {
                push_for_jiguang($push_id,"你有一笔待抢单的新订单,请注意查看!",2);
            }
        } catch (\Exception $e) {

            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
            
        }
    }
}