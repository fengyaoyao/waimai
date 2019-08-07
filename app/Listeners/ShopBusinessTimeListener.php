<?php

namespace App\Listeners;

use App\Events\Shop as EventShop;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Model\ShopOpeningTime;


class ShopBusinessTimeListener
{

    public function handle(EventShop $event)
    {
        try {

            $is_exists = ShopOpeningTime::groupBy('shop_id')->get()->pluck('shop_id')->toArray();

            if (!empty( $is_exists)) {
                
                $off = []; $on = [];

                foreach ($is_exists as $shop_id) {
                  $count = ShopOpeningTime::where('shop_id',$shop_id)->whereTime('start_time','<',date('H:i:s'))->whereTime('end_time','>',date('H:i:s'))->count();
                  $count ? array_push($off, $shop_id) : array_push($on, $shop_id);
                }

                if(!empty($off)) DB::table('shops')->whereIn('shop_id',$off)->where('is_timing',1)->update(['status'=>1]);
                if(!empty($on)) DB::table('shops')->whereIn('shop_id',$on)->where('is_timing',1)->update(['status'=>0]);
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