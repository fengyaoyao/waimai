<?php

namespace App\Listeners;

use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Model\{Order, Config, AccountLog, User};

class PresentedExpListener
{
    public function handle(OtherTrigger $event)
    {

        try {

            if((boolean)Config::where('inc_type','basic')->where('name','point_give')->value('value')) {

                $Order = Order::where('order_id',$event->order_id)->select(['order_id','user_id','goods_price'])->first();

                if ($Order->goods_price <= 0) {
                    return true;
                }

                DB::beginTransaction(); //开启事务

                $integral = intval($Order->goods_price);

                $add_points = User::where('user_id',$Order->user_id)->increment('points', $integral);

                $isInsert = AccountLog::insert([
                    'desc'       => '购买赠送积分',
                    'pay_points' => $integral,
                    'user_id'    => $Order->user_id,
                    'order_id'   => $Order->order_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if($add_points && $isInsert) {
                    //提交事务
                    DB::commit();
                    return true;
                }
            }

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