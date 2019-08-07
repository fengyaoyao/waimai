<?php
namespace App\Http\Controllers\Delivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Order,FightOrder,Area,User,RiderClockIn,Delivery,DistributionHasShop,DistributionHasDormitory,Shop, AggregationDelivery};
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Traits\SignIn;
use App\Exceptions\MyException;
use Carbon\Carbon;


class AggregationController extends Controller
{
    use SignIn;
    protected $user_id;
    protected $area_id;
    protected $rider_type;
    protected $sex;
    protected $is_aggregation;

    public function __construct() {
        $user = Auth::user();
        $this->user_id = $user->user_id;
        $this->area_id = $user->area_id;
        $this->rider_type = $user->rider_type;
        $this->sex = $user->sex;
        $this->is_aggregation = $user->is_aggregation;
    }

	/**
	 * [orderList 聚合抢单列表]
	 * @return [type] [description]
	 */
	public function orderList(Request $request)
	{

        $this->validate($request, [
            'attr_type' => 'sometimes|present|in:1,2',
            'search_keyword' => 'sometimes|present|string|between:1,20',
            'group_id' => 'sometimes|required|integer|exists:shop_group,id',
        ], $this->message);

        $group_id = $request->filled('group_id') ? $request->group_id : null;

        $attr_type = $request->filled('attr_type') ? $request->attr_type : null;

        $search_keyword = $request->filled('search_keyword') ? $request->search_keyword : null;

        $area_id = $this->area_id;
        $user_id = $this->user_id;
        $rider_type = $this->rider_type;

        $shopIds = [];

        $deliveryIds = [];

        $order_show_minute = 5;

        $Area = Area::where('area_id',$area_id)->select(['relay','order_show_minute','aggregation_num'])->first();

        $aggregation_num = empty($Area->aggregation_num ) ? 6 : $Area->aggregation_num;

        if ($rider_type) {

            $order_show_minute = $Area->order_show_minute;

            if ($Area->relay && DistributionHasDormitory::where('user_id',$user_id)->exists()) {

                // 获取兼职绑定的宿舍
                $arr_pid = DistributionHasDormitory::where('user_id',$user_id)->pluck('delivery_id')->toArray();
                $deliveryIds = Delivery::whereNotIn('delivery_id',$arr_pid)->whereIn('pid',$arr_pid)->pluck('delivery_id')->toArray();
            }

        }else{

            if (DistributionHasShop::where('user_id',$user_id)->exists()) {
                // 获取全职绑定的店铺
                $shopIds = DistributionHasShop::where('user_id',$user_id)->pluck('shop_id')->toArray();
            }
        }

        $Order = FightOrder::where('area_id',$area_id)
                        ->when($group_id, function ($query, $group_id){
                            return $query->where('group_id', $group_id);
                        })
                        ->when($attr_type, function ($query) use ($attr_type){
                            return $query->whereIn('build_attr_type', [0,$attr_type]);
                        })
                        ->when($rider_type, function ($query, $user_id,$deliveryIds,$order_show_minute) {
                            return $query->when($deliveryIds, function ($query,$deliveryIds) {
                                    return $query->whereIn('delivery_id', $deliveryIds);
                            })
                            /**
                             * 1、 是接力订单并且订单未取货状态第一阶段接了并且第二阶段没人接 并且 第一阶段接单后一分钟
                               2、 是接力订单并且订单已取货状态并且第一阶段接了并且第二阶段没人接
                               3、 不是接力订单并且未取货状态并且第一二阶段没人接单并且 商家确认时间 大于 区域配置的分钟数兼职才可以抢单
                             */
                            ->whereRaw('((is_relay = 1 and shipping_status = 0 and ps_id > 0 and ps_id <> ? and relay_id = 0 and (TIMESTAMPDIFF(MINUTE,rush_time,now())) >= 1)
                                or (is_relay = 1 and shipping_status = 1 and ps_id > 0 and ps_id <> ? and relay_id = 0)
                                or (shipping_status = 0 and ps_id = 0 and relay_id = 0 and TIMESTAMPDIFF(SECOND,ensure_time,now()) >= ?))',[$user_id,$user_id,$order_show_minute]
                            );
                        }, function ($query,$shopIds) {
                            return $query->where(['ps_id'=>0,'shipping_status'=>0])
                                        ->when($shopIds, function ($query,$shopIds) {
                                            return $query->whereIn('shop_id', $shopIds);
                                        });
                        })
                        ->when($search_keyword, function ($query) use ($search_keyword) {
                            return $query->where(function ($query) use ($search_keyword) {
                                $query->where('order_sn','like','%'.$search_keyword.'%')
                                        ->orWhere('consignee','like','%'.$search_keyword.'%')
                                        ->orWhere('mobile','like','%'.$search_keyword.'%')
                                        ->orWhere('day_num','like','%'.$search_keyword.'%');
                            });
                        })
                        ->get()
                        ->toArray();

        // 获取宿舍数据
        $deliverygroupPid = Delivery::where('area_id',$area_id)->where('pid',0)->pluck('delivery_id','delivery_id')->toArray();
        $AggregationDelivery = AggregationDelivery::where('area_id',$area_id)->pluck('join_delivery_id','delivery_id')->toArray();

        if (!empty($AggregationDelivery )) {
            foreach ($AggregationDelivery as $key => $value) {
                $deliverygroupPid[$key] = $value;
            }
        }

        $deliverygroupPidArr = [];

        foreach ($deliverygroupPid as $deliveryPid => $deliveryPidStr) {
            $deliverygroupPidArr[$deliveryPid] = explode(',', $deliveryPidStr);
        }

        // 对数据进行大分组
        $deliverygroup = [];

        foreach ($Order as $v) {
            if (in_array($v['delivery_pid'], $deliverygroupPidArr[$v['delivery_pid']])) {
                $v['delivery_pid_sort'] = array_search($v['delivery_pid'], $deliverygroupPidArr[$v['delivery_pid']]);
                $deliverygroup[$deliverygroupPid[$v['delivery_pid']]][] = $v;
            } 
        }

        // 对订单数据经行指定大小分组
        $order_row = [];
        foreach ($deliverygroup as  $group) {
            array_multisort(array_column($group,'delivery_pid_sort'),SORT_ASC,$group);
            foreach (array_chunk($group,$aggregation_num) as  $value) {
                $order_row[] = $value;
            }
        }

        return respond(200,'获取成功!',$order_row);
	}

	/**
     * [fightOrder 聚合抢单]
     * @return [type] [description]
     */
    public function fightOrder(Request $request)
    {

        // $request->merge([
        //     'orders' =>[169812,169813,169828,169831]
        // ]);

        $this->validate($request,[
            'orders' =>'required|array',
            'attr_type' => 'sometimes|present|in:1,2',
            'orders.*' => 'required|integer|exists:order,order_id',

        ],$this->message);

        $area_id = $this->area_id;
        $user_id = $this->user_id;
        $rider_type = $this->rider_type;

        $User = User::where('user_id',$user_id)->select(['work_status','max_order'])->first();

        if(empty($User->work_status)){
            return respond(201,'你处于休息中不能抢单!');
        }

        $pick_rate = Area::where('area_id',$area_id)->value('pick_rate');

        if ($pick_rate > 0 && Cache::get("scramble_{$user_id}")) {
            return respond(201,'你的操作频率太高!');
        }

        $requestCountOrder = count($request->orders);

        // 骑手接单自动打卡
        $this->autoExecute();

        $HandCount = Order::whereRaw("ps_id = ? and pay_status = ? and order_status in(?)",[$user_id,1,'1,2'])->count();

        if (($User->max_order > 0) && (($HandCount +  $requestCountOrder) >= $User->max_order)) {
            return respond(201,'你已经达到最大接单量了!');
        }

        $where = [
            'area_id' => $area_id,
            'pay_status' => 1,
            'delivery_type' => 0,
            'distribution_status' => 0,
        ];

        $error = [];
        $succeed = 0;
        foreach ($request->orders as $order_id) {

            try {

                DB::beginTransaction(); //开启事务

                $Order = Order::where('order_id',$order_id)->where($where)->whereIn('order_status',[1,2])->lockForUpdate()->first();

                if (empty($Order)) {
                    // array_push($error, "“{$day_num}”发生了变化!");
                    continue;
                }

                $day_num = $Order->day_num;

                if (empty($Order->ps_id)) {

                    // 第一阶段
                    if (!empty($Order->ps_id)) {
                        // array_push($error,"“{$day_num}”已被抢走了!");
                        continue;
                    }

                    if ($Order->shipping_status != 0) {
                       // array_push($error,"“{$day_num}”不满足抢单条件!");
                       continue;
                    }

                    if (DistributionHasShop::where('user_id',$user_id)->exists()) {
                        $exists = DistributionHasShop::where('user_id',$user_id)->where('shop_id',$Order->shop_id)->exists();
                        if (!$exists) {
                            // array_push($error,"“{$day_num}”不属于你配送的店铺!");
                            continue;
                        }
                    }

                    $Order->ps_id = $user_id;
                    $Order->rush_time = date('Y-m-d H:i:s');

                }else if (!empty($Order->ps_id) && empty($Order->relay_id) && $Order->is_relay) {

                    // 接力订单第二阶段
                    if (!empty($Order->relay_id)) {
                        // array_push($error,"“{$day_num}”已被抢走了!");
                        continue;
                    }

                    if (empty($Order->ps_id)) {
                        // array_push($error,"“{$day_num}”不满足接力抢单条件!");
                        continue;
                    }

                    if ($Order->ps_id == $user_id) {
                        // array_push($error,"“{$day_num}”接力人员不能是自己!");
                        continue;
                    }

                    if ($this->rider_type == 0) {
                        // array_push($error,"“{$day_num}”已由兼职人员配送中!");
                        continue;
                    }

                    if (DistributionHasDormitory::where('user_id',$user_id)->exists()) {
                        $delivery_pid  = Delivery::where('delivery_id',$Order->delivery_id)->where('pid','<>',0)->value('pid');
                        $exists = DistributionHasDormitory::where('user_id',$user_id)->where('delivery_id',$delivery_pid)->exists();

                        if (!$exists) {
                            // array_push($error,"“{$day_num}”不属于你配送的宿舍!");
                            continue;
                        }
                    }

                    if ($Order->shipping_status == 1) {
                        $Order->relay_time = date('Y-m-d H:i:s');
                    }

                    $Order->relay_id = $user_id;

                }else{
                    // array_push($error,"“{$day_num}”非法操作!");
                    continue;
                }

                if ($Order->save()) {

                    DB::commit();

                    $succeed +=1;
                    // array_push($error,"“{$day_num}抢单成功!");

                }else{
                    DB::rollBack();
                    // array_push($error,"“{$day_num}抢单失败!");
                }

            } catch (\Exception $e) {

                DB::rollBack();

                // array_push($error,"“{$day_num}抢单失败!");
            }
        }
      
        if ($pick_rate > 0) {
            Cache::put("scramble_{$user_id}", $user_id, Carbon::now()->addSeconds($pick_rate));
        }

        if ($succeed == 0) {

            return respond(201,'抢单失败!');

        }else if($succeed == $requestCountOrder){

            return respond(200,'抢单成功!');

        }else{

            $failed = $requestCountOrder - $succeed;
            
            return respond(200,"({$succeed})单成功,({$failed})失败!");
        }
    }
}