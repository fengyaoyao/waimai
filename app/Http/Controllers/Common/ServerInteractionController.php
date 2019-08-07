<?php 
namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Order,User,Shop};
use App\Events\CalculateBrokerage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class ServerInteractionController extends Controller{


	/**
	 * [confirmOrder 确认订单]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function confirmOrder(Request $request)



	{



		$this->validate($request,['order_id' => 'required|integer|exists:order,order_id'],$this->message);







		$Order = Order::where('order_id',$request->order_id)->where('pay_status',1)->first();







		if(empty($Order)) {



			return respond(422,'该订单没有找到');



		}







		if($Order->order_status > 0 ) {



			return respond(422,'该订单已经确认过了');



		}







		$auto_order = Shop::where('shop_id',$Order->shop_id)->value('auto_order');







		if($auto_order != 1 ) {



			return respond(422,'该商家未开启自动接单');



		} 







		if (event(new CalculateBrokerage($Order))) {



			return respond(200,'操作成功！');



		}







		return respond(201,'操作失败！');



	}







	/**



	 * 查找用户



	 * @return [type] [description]



	 */



	public function find_user(Request $request)



	{



		$this->validate($request, ['user_id'=>'required|integer|exists:users,user_id'],$this->message);







		$UserModel   =  User::find($request->input('user_id'));



		if(empty($UserModel)) return respond(203,'获取失败!');







		$password    =  Crypt::decryptString($UserModel->password);



		$encrypt_str =  rsa_encrypt($password,'server_rsa_public_key');







	    if($encrypt_str)



			return respond(200,'获取成功!',$encrypt_str);



		else



			return respond(203,'获取失败!');



	}











	/**



	 * 加密



	 * @return [type] [description]



	 */



	public function encrypt_str(Request $request)



	{



		$this->validate($request, ['encrypt_str'=>'required|string'],$this->message);



		$need_encrypt_str = $request->input('encrypt_str');



		$encrypt_str      =  Crypt::encryptString($need_encrypt_str);







	    if($encrypt_str)



			return respond(200,'获取成功!',$encrypt_str);



		else



			return respond(203,'获取失败!');



	}







	/**



	 * [batchAddAttributeSpecifications批量添加商品的属性和规格]



	 * @param  Request $request [description]



	 * @return [type]           [description]



	 */



	public function batchAddAttributeSpecifications(Request $request) {



        // print_r($request->input('shop'));exit;







      



		$this->validate($request, 



        [



            'shop' => 'required|array',



            'shop.*.name' => 'required|string|between:1,45',



            'shop.*.shop_id' => 'required|integer|exists:shops,shop_id',



            'shop.*.goods_id' => 'required|integer|exists:goods,goods_id',



            'shop.*.select_type' => 'required|integer|in:0,1',



            'shop.*.specification' => 'required|array',



            'shop.*.specification.*.name' => 'required|string|between:1,45',



            'shop.*.specification.*.price'  => 'required|integer',



        ],



        $this->message);







        $created_at = date('Y-m-d H:i:s');







        try {





            foreach ($request->shop as $attr) {







                $is_exists = DB::table('specs')->where([

					                	'name' => $attr['name'], 

                                        'shop_id' => $attr['shop_id'],

                                        'goods_id' => $attr['goods_id']

                                    ])->exists();



                if ($is_exists) {



                    return respond(422,"“{$attr['name']}”属性已经存在了");



                }





                $spec_id = DB::table('specs')->insertGetId([



                        'name' => $attr['name'], 



                        'shop_id' => $attr['shop_id'],



                        'goods_id' => $attr['goods_id'],



                        'select_type' => $attr['select_type'],



                        'created_at' => $created_at,



                    ]);







                if (!$spec_id) {



                    return respond(422,"属性添加出错了！");



                }







                $specification = [];







                foreach ($attr['specification'] as $spec) {



                    array_push($specification, [ 



                        'shop_id' => $attr['shop_id'],



                        'goods_id' => $attr['goods_id'],



                        'spec_id' => $spec_id,



                        'item' => $spec['name'],



                        'price' => $spec['price'],



                        'created_at' => $created_at,



                    ]);



                }



                



                DB::table('spec_items')->insert($specification);



            }







            return respond(200,'操作成功!');







        } catch (\Exception $e) {







            return respond(201,$e->getMessage());



        }



	}







	public function  goodsJson($shop_id){







		if (\App\Model\Shop::where('shop_id',$shop_id)->exists()) {







			$Goods = \App\Model\Goods::where('shop_id',$shop_id)



								       ->select(['shop_id','title as name','goods_id','goods_id as id'])



								       ->get();



			return response([



		         'status' => 200,



		         'code' => 0,



		         'message' => '获取成功！',



		         'data' => $Goods



		    ],200);







		}else{



	        return respond(422,'店铺未找到！');



		}       



	}

	/**
	 * [confirmOrderDelivery 确认未完成的订单]
	 * @return [type] [description]
	 */
	public function confirmOrderDelivery(Request $request)
	{
        Log::channel('server')->info($request->getClientIp(),$request->header());

        try {

			DB::table('order')
				->where('order_status',1)
				->where('pay_status',1)
				->where('distribution_status',5)
				->whereNotNull('ensure_time')
				->update(['appeared_time' => date('Y-m-d H:i:s'),'order_status'=>2]);

		} catch (\Exception $e) {
			
		}

		try {

            $Orders = Order::where('order_status',2)
				            ->where('pay_status',1)
				            ->where('distribution_status',5)
				            ->whereNull('deleted_at')
							->whereNotNull('ensure_time')
                            ->whereRaw('TIMESTAMPDIFF(SECOND,ensure_time,now()) >= 86400')
				            ->get();

            foreach ($Orders as $Order) {
                if (!empty($Order)) {
                    event(new \App\Events\ClearingAccount($Order));
                }
            }

        } catch (\Exception $e) {

        }
	}
}