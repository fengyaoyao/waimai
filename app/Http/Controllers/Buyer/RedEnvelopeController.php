<?php 

namespace App\Http\Controllers\Buyer;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{User,Order,Activity,ShopPopout,ShareRedPacket};

use Illuminate\Support\Facades\DB;



class RedEnvelopeController extends Controller

{

    protected $user_id;//用户id



    public function __construct()

    {

        $this->user_id = \Auth::id();

    }



    public function isAlertActivity(Request $request) {



        $this->validate($request, ['order_id' => 'required|integer|exists:order,order_id'],$this->message);

        

        $Order = Order::where(['order_id'=>$request->order_id, 'pay_status'=>1,'user_id'=>$this->user_id])->select('order_type','delivery_type','total_amount','shop_id','area_id')->first();



        if (empty($Order)) { return respond(200,'获取成功！',['is_alert'=>false]);}



        $activity_id = Activity::whereRaw('`area_id` = ? and `type` = ? and `status` = ? and `start_time` <= now() and `end_time` >= now()',[$Order->area_id,0,1])->value('id');



        if (empty($activity_id)) { 

            return respond(200,'获取成功！',['is_alert'=>false]);

        }



        $today_num = Order::whereNotIn('order_status',[3,5,6])->where(['user_id'=>$this->user_id, 'pay_status'=>1])->whereDate('created_at',date("Y-m-d"))->count();    



        $ShareRedPacket = ShareRedPacket::where('activity_id',$activity_id)

                                        ->where('status',0)

                                        ->where('receive_condition','<=',$Order->total_amount)

                                        ->where('order_num','<=',$today_num)

                                        ->exists();

                                        

        if (!$ShareRedPacket) { return respond(200,'获取成功！',['is_alert'=>false]);}



        $ShopPopout = ShopPopout::where(['activity_id' => $activity_id, 'shop_id'=>$Order->shop_id])->first();



        if ($activity_id && !empty($ShopPopout)) {

            $ShopPopout->content =  Activity::where('id',$activity_id)->value('desc');

            $ShopPopout->is_alert = true;

            $ShopPopout->alert_url = env('BACKEND_DN').'mobile/activity/popout?order_id='.$request->order_id.'&area_id='.$Order->area_id;  


            $ShopPopout->url =  env('BACKEND_DN').'mobile/activity/redPacket?order_id='.$request->order_id .'&user_id='.$this->user_id;  

        }



        if (!empty($ShopPopout)) {

            $data = $ShopPopout;

        }else{

            $data = [ 'is_alert' => false];

        }



        return respond(200,'获取成功！',$data);

    }

}