<?php 

namespace App\Http\Controllers\Common;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth,Crypt,DB,Mail,Cache};
use App\Model\{User,Collect,Coupon,AccountLog,CouponList,Order,Area,Shop};
use App\Exceptions\MyException;
use Carbon\Carbon;
use App\Http\Controllers\Traits\RefundOrder;
use App\Http\Controllers\Traits\AlibabaCloudSms;
use App\Http\Controllers\Traits\CloseOrder;

use Illuminate\Support\Facades\Log;


class TestController extends Controller {
  
	use RefundOrder,AlibabaCloudSms,CloseOrder;

    public function index(Request $request)
    {


    	echo json_encode(['orders' => [169812,169813,169831]]);exit;
    	// 89222

		$data = array (
		  'shop_id' => '3',
		  'address_id' => 88340,
		  'pay_code' => 'weixin',
		  'delivery_type' => 0,
		  'user_money' => 0,
		  'integral' => 0,
		  'pay_money' => '21.30',
		  'goods_row' => 
		  array (
		    0 => 
		    array (
		      'goods_id' => 331,
		      'goods_num' => 1,
		      'spec_key' => '35176_35178',
		      'discount_num' => 1,
		      'is_discount' => 1,
		    ),
		    1 => 
		    array (
		      'goods_id' => 331,
		      'goods_num' => 1,
		      'spec_key' => '35177_35179',
		      'discount_num' => 1,
		      'is_discount' => 1,
		    ),
		  ),
		  'place_order_type' => 0,
		  'estimated_delivery' => '',
		);

		echo json_encode($data);exit;
    }
}