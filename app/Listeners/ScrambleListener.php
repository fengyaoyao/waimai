<?php

namespace App\Listeners;

use App\Events\Scramble;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Exceptions\MyException;

class ScrambleListener
{
    /**
     * Handle the event.
     * @param  Register  $event
     * @return void
     */
    public function handle(Scramble $event)
    {

        $order_id = $event->order_id;

        $redis    = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->select(15);
        // 判断key是否存在
        if(!($redis->lget("orderid{$order_id}",0))) throw new MyException("来晚了,已有人接单！");
        // 弹出队列
        $redis->lpop("orderid{$order_id}");
        // 删除key 防止没有弹出队列
        $redis->del("orderid{$order_id}");
    }
}
