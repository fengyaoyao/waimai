<?php

namespace App\Events;

use App\Model\Order;

class OtherTrigger extends Event
{
    public $order_id;
    public $user_id;
    public $shop_id;
    public $area_id;


    
    /**
     * 注册事件
     * @param  int $order_id [订单id]
     */
    
    public function __construct($order_id)
    {
    	if (empty($order_id)) {
            throw new \Exception("Parameter cannot be empty!");
        }


        $Order = Order::where('order_id',$order_id)
				        ->select(['order_id','user_id','shop_id','area_id'])
				        ->first();

        if (empty($Order)) {
            throw new \Exception("Order not find!");
        }

        foreach ($Order->only(['order_id','user_id','shop_id','area_id']) as $key => $value) {

        	if (isset($value)) {
        		$this->{$key} = $value;
        	}else{
        		throw new \Exception("There is a problem with the order parameters!");
        	}
        }
    }
}