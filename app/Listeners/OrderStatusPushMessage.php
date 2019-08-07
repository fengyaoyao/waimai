<?php

namespace App\Listeners;

use App\Events\PushMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\Order;

class OrderStatusPushMessage
{

    public function handle(PushMessage $event)
    {
    	try {
	    	// 公众号消息推送
			$url = env('BACKEND_DN').'/mobile/api/orderStatusNotice?order_id='.$event->order_id;
			return curl_request($url);
			//第三方推送
    	} catch (\Exception $e) {
    		info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
    	}
    }
}
