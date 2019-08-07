<?php

namespace App\Events;

class GoodsSold extends Event
{
    public $order_id;
    
    /**
     * 注册事件
     * @param  int $order_id [用户id]
     */
    
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }
}