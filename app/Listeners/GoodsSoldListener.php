<?php
namespace App\Listeners;

use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\{Goods, OrderGoods};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class GoodsSoldListener
{
    public function handle(OtherTrigger $event)
    {
    	try {

	        $OrderGoods = OrderGoods::where('order_id',$event->order_id)->select(['order_id','goods_id','goods_num'])->get();
	        
	        foreach ($OrderGoods as $key => $value) {
	            Goods::where('goods_id',$value->goods_id)->increment('sold_num',$value->goods_num);
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