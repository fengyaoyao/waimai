<?php 

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Shop,UserAddress,Order,Area,User,MerBill,UserBill,DistributionHasDormitory,DistributionHasShop,RiderClockIn};
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\MyException;
use App\Http\Controllers\Traits\SignIn;
use Carbon\Carbon;

class RelayController extends Controller

{
    use SignIn;

    protected $user_id;

    protected $area_id;

    protected $user;

    protected $rider_type;



    public function __construct() {

        $this->user =  \Auth::user();

        $this->user_id = $this->user->user_id;

        $this->area_id =  $this->user->area_id;

        $this->rider_type = $this->user->rider_type;

    }





    /**

     * [scanCodeForOrder 接力扫码交接订单]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function scanCodeForOrder(Request $request) {



        $this->validate($request,['order_id'=>'required|integer|exists:order,order_id'],$this->message);



        $where = [

            'order_id' => $request->order_id,

            'shipping_status' => 1,

            'pay_status' => 1,

            'delivery_type' => 0,

            'relay_id' => 0

        ];



        $Order = Order::where($where)->whereIn('order_status',[1,2])->first();



        if (empty($Order)) {

            return respond(201,'该订单不满足接力条件！');

        }



        if ($Order->distribution_status == 0 && empty($Order->ps_id)) {

            return respond(201,'非法操作');

        }



        $Order->relay_id = $this->user_id;

        $Order->relay_time = date('Y-m-d H:i:s');



        if ( $Order->save() ) {

            return respond(200,'接力成功！');

        }



        return respond(201,'接力失败！');

    }



    /**

     * [scrambleForOrder 抢单]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function scrambleForOrder(Request $request) {

            $this->validate($request,[
                'order_id' => 'required|integer|exists:order,order_id',
                'is_scan_code' => 'sometimes|required|integer|in:0,1',
                'phases' => 'sometimes|present|string|in:one,two'
            ],$this->message);

            $User = User::where('user_id',$this->user_id)->select(['work_status','max_order'])->first();

            if(empty($User->work_status)){
                return respond(201,'你处于休息中不能抢单!');
            }

            $pick_rate = Area::where('area_id',$this->area_id)->value('pick_rate');

            if ($pick_rate > 0 && Cache::get('scramble_'.$this->user_id)) {
                return respond(201,'你的操作频率太高!');
            }

            // 骑手接单自动打卡
            try {

                $Area = Area::where('area_id',$this->area_id)->value('is_clock_in');

                if (!empty($Area)) {
                    $data = $this->check($this->user_id,$this->rider_type,$this->area_id);
                    unset($data['start_time']);
                    unset($data['end_time']);

                    $RiderClockIn = RiderClockIn::where($data)->whereDate('created_at',date('Y-m-d'))->first();

                    if(empty($RiderClockIn)){

                        RiderClockIn::create(array_merge($data,['status'=>1,'call_time'=>date('H:i:s')]));
                        
                    }else{
                        $RiderClockIn->status = 1;
                        $RiderClockIn->call_time = date('H:i:s');
                        $RiderClockIn->save();
                    }
                }
            } catch (\Exception $e) {}
            

            $HandCount = $this->getHaveInHandCount();

            if (($User->max_order > 0) && ($HandCount >= $User->max_order)) {
                return respond(201,'你已经达到最大接单量了!');
            }

            $where = [
                'order_id' => $request->order_id,
                'pay_status' => 1,
                'delivery_type' => 0,
                'distribution_status' => 0,
                'area_id' => $this->area_id
            ];

            DB::beginTransaction(); //开启事务

            $Order = Order::where($where)->whereIn('order_status',[1,2])->lockForUpdate()->first();

            if (empty($Order)) {
                return respond(201,'该订单已被抢或已被完成了');
            }

            if (empty($Order->ps_id)) {
                $this->scrambleForOneIntercept($Order);
            }else if (!empty($Order->ps_id) && empty($Order->relay_id) && $Order->is_relay) {
                $this->scrambleForTwoIntercept($Order,$request->filled('phases'));
            }else{

                if (!empty($Order->ps_id) && empty($Order->is_relay) ) {
                    return respond(201,'该订单已经被抢了!');
                }else if(!empty($Order->ps_id) && !empty($Order->relay_id) && $Order->is_relay){
                    return respond(201,'该订单已经被抢了!');
                }else{
                    return respond(201,'非法操作!');
                }
            }

    

            if ($Order->save()) {

                DB::commit();
                if ($pick_rate > 0) {
                    Cache::put('scramble_'.$this->user_id, $request->order_id, Carbon::now()->addSeconds($pick_rate));
                }
                return respond(200,'抢单成功!');
            }

            DB::rollBack();

            return respond(201,'抢单失败!');
    }



    /**

     * [jiguangSmsNotice 极光消息推送给当前区域兼职人员]

     * @return [type] [description]

     */

    public function jiguangSmsNotice($Order) {



        try {

            //是否是接力订单

            if ($Order->is_relay && ($this->rider_type == '0')) {

                //激光推送订单给骑手

                $push_id = User::where(['area_id' => $Order->area_id,'type' => 2,'work_status' => 1,'rider_type' => 1])

                                ->where('user_id','<>',$this->user_id)

                                ->whereNotNull('push_id')

                                ->pluck('push_id')

                                ->toArray();

                push_for_jiguang($push_id,"你有一笔待抢单的新订单,请注意查看!",2);

            }



        } catch (\Exception $e) {}

    }



    /**

     * [scrambleForOneIntercept 第一阶段抢单拦截]

     * @param  [type] $Order [description]

     * @return [type]        [description]

     */

    private function scrambleForOneIntercept($Order) {


        //是否是接力订单

        if ($Order->is_relay) {

            if (($this->rider_type == 1) && DistributionHasDormitory::where('user_id',$this->user_id)->exists()) {

                $delivery_pid  = \App\Model\Delivery::where('delivery_id',$Order->delivery_id)->where('pid','<>',0)->value('pid');

                $exists = DistributionHasDormitory::where('user_id',$this->user_id)->where('delivery_id',$delivery_pid)->exists();


                if (empty($delivery_pid) || !$exists) {

                    throw new MyException("该订单不属于你配送的宿舍！");
                }
            }

            if (($this->rider_type == 0) && DistributionHasShop::where('user_id',$this->user_id)->exists()) {

                $exists = DistributionHasShop::where('user_id',$this->user_id)->where('shop_id',$Order->shop_id)->exists();

                if (!$exists) {

                    throw new MyException("该订单不属于你配送的店铺！");
                }
            }


        }else{


            if ( $this->area_id != $Order->area_id ) {

                throw new MyException("该订单不属于你配送的区域！");
            }

            if (DistributionHasShop::where('user_id',$this->user_id)->exists()) {
                    
                $exists = DistributionHasShop::where('user_id',$this->user_id)->where('shop_id',$Order->shop_id)->exists();

                if (!$exists) {
                    throw new MyException("该订单不属于你配送的店铺！");
                }
            }
        }



        if (!empty($Order->ps_id)) {

            throw new MyException("来晚了!该订单已被抢走了");

        }



        if ($Order->shipping_status != 0 || !empty($Order->relay_id)) {

            throw new MyException("该订单不满足抢单条件！");

        }



        $Order->ps_id = $this->user_id;

        $Order->rush_time = date('Y-m-d H:i:s');

    }



    /**

     * [scrambleForTwoIntercept 第二阶段抢单拦截]

     * @param  [type] $Order [description]

     * @return [type]        [description]

     */

    private function scrambleForTwoIntercept($Order,$phases = false) {

        if (!empty($Order->relay_id)) {
            throw new MyException("该订单已被抢走了");
        }

        if (empty($Order->ps_id)) {
            throw new MyException("该订单不满足接力抢单条件！");
        }

        if ($Order->ps_id ==  $this->user_id) {
            throw new MyException("该订单接力人员不能是自己！");
        }

        if ($this->rider_type == 0) {
            throw new MyException("该单已由兼职人员配送中!");
        }

        if (DistributionHasDormitory::where('user_id',$this->user_id)->exists()) {

            $delivery_pid  = \App\Model\Delivery::where('delivery_id',$Order->delivery_id)->where('pid','<>',0)->value('pid');

            $exists = DistributionHasDormitory::where('user_id',$this->user_id)->where('delivery_id',$delivery_pid)->exists();

            if (!$exists) {
                throw new MyException("该订单不属于你配送的宿舍！");
            }
        }

        $Order->relay_id = $this->user_id;

        if (!$phases && $Order->shipping_status == 1) {

            $Order->relay_time = date('Y-m-d H:i:s');
        }
    }





    /**

     * [confirmConnect 确认交接]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function confirmConnect(Request $request) {



        $this->validate($request,['order_id'=>'required|integer|exists:order,order_id'],$this->message);



        $where = [

            'order_id' => $request->order_id,

            'shipping_status' => 1,

            'pay_status' => 1,

            'delivery_type' => 0

        ];



        $Order = Order::where($where)

                        ->whereIn('order_status',[1,2])

                        ->whereNotNull('relay_id')

                        ->where('relay_id','<>',0)

                        ->where(function ($query) { 

                            $query->where('relay_id', $this->user_id)->orWhere('ps_id', $this->user_id);

                        })

                        ->first();



        if (empty($Order)) {

            return respond(201,'该订单不满足交接条件！');

        }



        if ($Order->distribution_status == 0 && empty($Order->ps_id)) {

            return respond(201,'非法操作');

        }



        $Order->relay_time = date('Y-m-d H:i:s');
        


        if ( $Order->save() ) {

            return respond(200,'交接成功！');

        }



        return respond(21,'交接失败！');

    }



    /**

     * [cancelConnect 取消交接]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function cancelConnect(Request $request) {



        $this->validate($request,['order_id'=>'required|integer|exists:order,order_id'],$this->message);



        $where = [

            'order_id' => $request->order_id,

            'pay_status' => 1,

            'delivery_type' => 0,

            'relay_id' => $this->user_id

        ];



        $Order = Order::where($where)->whereIn('order_status',[1,2])->first();



        if (empty($Order) || ($Order->distribution_status == 0 && empty($Order->ps_id))) {

            return respond(201,'非法操作！');

        }





        if (!empty($Order->relay_time) && ((time() - strtotime($Order->relay_time)) > 120)) {

           

            return respond(201,'该订单已交接 不能取消!');

        }



        $Order->relay_id = 0;



        if ($Order->save()) {

            return respond(200,'取消成功！');

        }



        return respond(21,'取消失败！');

    }



 
    /**
     * [getDeliveryForDistributionHasDormitory 获取兼职绑定的宿舍]
     */
    public function getDeliveryForDistributionHasDormitory() {

        $arr_pid = DistributionHasDormitory::where('user_id',$this->user_id)->pluck('delivery_id')->toArray();
        if (Area::where('area_id',$this->area_id)->value('relay') && !empty($arr_pid)) {
            return \App\Model\Delivery::whereNotIn('delivery_id',$arr_pid)->whereIn('pid',$arr_pid)->pluck('delivery_id')->toArray();
        }else{
            return \App\Model\Delivery::where('area_id',$this->area_id)->where('pid','>',0)->pluck('delivery_id')->toArray();
        }  
    }

    /**
     * [getDistributionHasShop 获取全职绑定的店铺]
     * @return [type] [description]
     */
    public function getDistributionHasShop() {

        $DistributionHasShop = DistributionHasShop::where('user_id',$this->user_id)->pluck('shop_id')->toArray();

        if (!empty($DistributionHasShop)) {
            return  $DistributionHasShop;
        }else{
            return Shop::where('area_id',$this->area_id)->pluck('shop_id')->toArray();
        }  
    }

 

    /**

     * [getHaveInHandCount 获取当前用户正在进行中的订单数量]

     * @return [type] [description]

     */

    public function getHaveInHandCount() {

        $user_id = $this->user_id;

        if (Area::where('area_id',$this->area_id)->value('relay')) {
            return Order::whereRaw("(ps_id = {$user_id} and pay_status = 1 and order_status in(1,2) and shipping_status = 0) or (relay_id = {$user_id} and pay_status = 1 and order_status in(1,2))")->count();
        }else{
            return Order::whereRaw("ps_id = {$user_id} and pay_status = 1 and order_status in(1,2)")->count();
        }

        // return Order::whereRaw("(ps_id = {$user_id} or relay_id = {$user_id}) and pay_status = 1 and order_status in(1,2)")->whereDate('created_at',date('Y-m-d'))->count();
    }



    /**

     * [getWhereRawSql 订单列表sql条件]

     * @param  integer $type [description]

     * @return [type]        [description]

     */

    public function getWhereRawSql($type = 0) {



        $user_id = $this->user_id;

        $sql = 'pay_status = 1 and delivery_type = 0 and distribution_status = 0 and ';

        $sql .= ($type == '3') ? 'order_status = 4 and ' : 'order_status in (1,2) and area_id = '. $this->area_id . ' and ';  

        switch ($type) {

            case '0':



                    if ($this->rider_type) {

                        $DeliveryIds = join(',',$this->getDeliveryForDistributionHasDormitory());

                        if (empty($DeliveryIds)) {

                            $DeliveryIds = 0;
                        }
                        
                        // $sql .= "delivery_id in ({$DeliveryIds}) and shipping_status = 0 and ps_id = 0";

                        //是接力订单并且订单未取货状态第一阶段接了并且第二阶段没人接 并且 第一阶段接单后一分钟
                        //是接力订单并且订单已取货状态并且第一阶段接了并且第二阶段没人接
                        //不是接力订单并且未取货状态并且第一二阶段没人接单并且 商家确认时间 大于 区域配置的分钟数兼职才可以抢单
                        
                        $order_show_minute = Area::where('area_id',$this->area_id)->value('order_show_minute');
                        $sql .= "delivery_id in ({$DeliveryIds}) and (

                            (is_relay = 1 and shipping_status = 0 and ps_id > 0 and ps_id <> {$user_id} and relay_id = 0 and (TIMESTAMPDIFF(MINUTE,rush_time,now())) >= 1 ) 

                            or (is_relay = 1 and shipping_status = 1 and ps_id > 0 and ps_id <> {$user_id} and relay_id = 0) 

                            or (shipping_status = 0 and ps_id = 0 and relay_id = 0 and TIMESTAMPDIFF(SECOND,ensure_time,now()) >= {$order_show_minute})
                        )";

                    } else {

                        $ShopIds = join(',',$this->getDistributionHasShop());

                        if (!empty($ShopIds)) {

                            $sql .= "shipping_status = 0 and ps_id = 0 and shop_id in ({$ShopIds})";

                        }else{

                            $sql .= "shipping_status = 0 and ps_id = 0 and shop_id = 0";
                        }
                    }

                break;

            case '1':

                    $sql .= "((shipping_status = 0 and ps_id = {$user_id}) or (shipping_status in (0,1)  and relay_id = {$user_id} and relay_time is null))";

                break;

            case '2':

                    // $sql .= "((shipping_status = 1 and ps_id = {$user_id}) or (shipping_status = 1 and relay_id = {$user_id} and relay_time is not null))";
                    $sql .= "shipping_status = 1 and ((ps_id = {$user_id}) or (relay_id = {$user_id} and relay_time is not null))";
                    

                break;

            case '3':
                    $sql .= "shipping_status = 2 and (ps_id = {$user_id} or relay_id = {$user_id})";

                    // $sql .= "((shipping_status = 2 and ps_id = {$user_id}) or (shipping_status = 2 and relay_id = {$user_id}))";

                break;
        }

        return  $sql;
    }



    /**

     * [orderList 接力订单列表]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function orderList(Request $request) {

        // return respond(200,'获取成功！');


        $this->validate($request, 
        [
            'type'      => 'required|in:0,1,2,3',
            'page_size' => 'sometimes|required|integer',
            'attr_type' => 'sometimes|present|in:1,2',
            'search_keyword' => 'sometimes|present|string|between:1,20',
            'group_id' => 'sometimes|required|integer|exists:shop_group,id',
        ], $this->message);


        $page_size =  $request->filled('page_size') ? $request->page_size : 15;

        $attr_type = $request->filled('attr_type') ? $request->attr_type : null;

        $search_keyword = $request->filled('search_keyword') ? $request->search_keyword : null;

        $shop_ids = [];

        if ($request->filled('group_id')) {

            $shop_ids = \App\Model\Shop::where('group_id',$request->group_id)
                                        ->select('shop_id')
                                        ->pluck('shop_id')
                                        ->toArray();
        }



        $type      = $request->type;

        $orderbyraw = ($type == '0') ? 'asc' : 'desc';

        $sql = $this->getWhereRawSql($type);

        $Order  = Order::when(!empty($shop_ids), function ($query) use ($shop_ids){
                            return $query->whereIn('shop_id', $shop_ids);
                        })

                        ->whereRaw($sql)

                        ->when($attr_type, function ($query) use ($attr_type){
                            return $query->whereIn('build_attr_type', [0,$attr_type]);
                        })

                        ->when($search_keyword, function ($query) use ($search_keyword) {
                            return $query->where(function ($query) use ($search_keyword) {
                                $query->where('order_sn','like','%'.$search_keyword.'%')
                                        ->orWhere('consignee','like','%'.$search_keyword.'%')
                                        ->orWhere('mobile','like','%'.$search_keyword.'%')
                                        ->orWhere('address','like','%'.$search_keyword.'%')
                                        ->orWhere('day_num','like','%'.$search_keyword.'%');
                            });
                        })

                        ->with(['ps_info','relay_info','order_distribution','area'=>function($query) {
                            return $query->select(['area_id','process_date','part_time_delivery','full_time_delivery']);

                        },'order_shop'=>function($query){
                            return $query->select(['shop_id','shop_name','addr','mobile','tel','sell_time']);
                        }])

                        ->orderByRaw("pay_time {$orderbyraw}")

                        ->simplePaginate($page_size);

        return respond(200,'获取成功！',$Order);
    }
}