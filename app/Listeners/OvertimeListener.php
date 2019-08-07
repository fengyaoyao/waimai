<?php
namespace App\Listeners;

use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Model\Order;

class OvertimeListener
{
    public function handle(OtherTrigger $event)
    {
        try{

            $Order = Order::selectRaw('
                            order_id,shop_id,area_id,order_status,pay_status
                            ,pay_time,confirm_time,ensure_time,take_time,appeared_time,
                            TIMESTAMPDIFF(MINUTE,pay_time,confirm_time) as oume,
                            TIMESTAMPDIFF(MINUTE,ensure_time,appeared_time) as sume,
                            TIMESTAMPDIFF(MINUTE,take_time,confirm_time) as dume')
                            ->where('order_id',$event->order_id)
                            ->where('pay_status',1)
                            ->where('order_status',4)
                            ->whereNotNull('pay_time')
                            ->whereNotNull('confirm_time')
                            ->whereNotNull('ensure_time')
                            ->whereNotNull('take_time')
                            ->whereNotNull('appeared_time')
                            ->with([
                              'area' => function($query){$query->select(['area_id','process_date']);},
                              'shop' => function($query){$query->select(['shop_id','sell_time']);}
                            ])
                            ->first();
            $data = [];

            if (!empty($Order)) {
                // oume 订单总耗分钟数 sume 店铺出餐总耗分钟数 dume 骑手配送总耗分钟数
                $oume = $Order->oume; 
                $sume = $Order->sume; 
                $dume = $Order->dume;

                $sell_time = $Order->shop->sell_time ;
                $process_date =  $Order->area->process_date ;
                $order_need_minutes = $sell_time + $process_date;

                $data = [
                  'all'       => ($order_need_minutes >= $oume) ? 0 : ($oume - $order_need_minutes),
                  'shop'      => ($sell_time >= $sume) ? 0 : ($sume - $sell_time),
                  'Horseman'  => ($process_date >= $dume) ? 0 : ($dume - $process_date),
                ];
            }

            if (!empty(array_filter($data))) {
                $data['order_id'] = $Order->order_id;
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::table('order_overtime')->insert($data);
            }
            
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