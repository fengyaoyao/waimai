<?php

namespace App\Listeners;

use App\Events\PushMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\Order;

class PushMessageListener
{

    public function handle(PushMessage $event)
    {
    	// 公众号消息推送
	
		$url = env('BACKEND_DN').'/mobile/api/orderStatusNotice?order_id='.$event->order_id;
		curl_request($url);
		
		
		//第三方推送
    }
}
