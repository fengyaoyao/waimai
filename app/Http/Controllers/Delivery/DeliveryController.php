<?php 

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{Goods,Spec,SpecItem,Shop,UserAddress,Order,OrderGoods,Area,Comment,User,MerBill,UserBill,Withdrawal,CheckIn,Article,AreaShift,UserHasShift,DistributionHasDormitory,DistributionHasShop, RiderClockIn};

use App\Events\{Scramble, PushMessage, CalculateBrokerage, ClearingAccount};

use Illuminate\Support\Facades\Redis;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Traits\SignIn;
use App\Http\Controllers\Traits\AlibabaCloudSms;



class DeliveryController extends Controller

{

    use SignIn,AlibabaCloudSms;



    protected $user_id;

    protected $area_id;

    protected $rider_type;



    public function __construct()

    {

        $this->user_id = \Auth::id();

        $this->area_id = \Auth::user()->area_id;

        $this->rider_type = \Auth::user()->rider_type;

    }

    
    /**
     * [deliveryGroupFindShop 获取配送分组的店铺]
     * @return [type] [description]
     */
    public function deliveryGroupFindShop()
    {
        $ShopGroup = \App\Model\ShopGroup::where('area_id',$this->area_id)->orderByDesc('sort')->get();
        return respond(200,'获取成功！', $ShopGroup);
    }



    /**

     * 配送端首页

     * @return [type] [description]

     */

    public function index()

    {

        //今日完成订单数量

        $getToDayNum = Order::distribution($this->user_id)->whereDate('created_at',date("Y-m-d"))->count();    



        $average_arr = [];

        $average     = Order::distribution($this->user_id)->where('distribution_status',0)->get();



        foreach ( $average as $key => $value) 

        {

          $average_arr[]  =  abs(strtotime($value->confirm_time) - strtotime($value->finish_time)) / 60;

        }



        //获取所有正常完成的订单

        $rate = Order::distribution($this->user_id)->where('distribution_status',0)->whereColumn('confirm_time','<=','finish_time')->count();

        

        //所有完成的订单

        $total_number = Order::distribution($this->user_id)->where('distribution_status',0)->count();



        $check_in_time   = '请点击打卡！';

        $is_check_in_time = false;

        $is_show = 0;

        $time_bucket = '';



        try {



            $CheckData = $this->check($this->user_id,$this->rider_type,$this->area_id);



            // $check_in_time = substr($CheckData['start_time'], 0,-3).'-'.substr($CheckData['end_time'],0,-3);



            unset($CheckData['start_time']);

            unset($CheckData['end_time']);



            $RiderClockIn = RiderClockIn::where($CheckData)->where('status',1)->whereDate('created_at',date('Y-m-d'))->first();

            $is_show = 1;



            if ($RiderClockIn) {

                $time_bucket = $RiderClockIn->call_time;

                $is_check_in_time = true;

            }

        } catch (\Exception $e) {
            
            switch ($e->getCode()) {
                case '1':
                    $is_show = 0;
                    break;
                 case '2':
                    $is_show = 1;
                    $check_in_time = '未申请值班！';
                    break;
                case '3':
                    $is_show = 1;
                    $check_in_time = '请点击打卡！';
                    break;
            }
        }





        $if_rate  = empty($rate) ? 0 : round(($rate/$total_number)*100);



        $data = [

            'order_month'  =>  [

                'day'          => date("Y-m-d"),

                'total_number' => $getToDayNum

            ],

            'average_minute'   => !empty($average_arr) ? ceil(array_sum($average_arr) / count($average_arr)) : 0,

            'rate'             => empty($total_number) ? 0 : $if_rate,

            'check_in_time'    => $check_in_time,

            'time_bucket'      => $time_bucket,

            'is_check_in_time' => $is_check_in_time,//是否打卡

            'is_show'          => $is_show,//是否显示

        ];

        

        return respond(200,'获取成功！',$data);

    }



    /**

     * 订单列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function order_list(Request $request)

    {

        $this->validate($request, 

            [

                'type'      => 'required|in:0,1,2,3',

                'page_size' => 'sometimes|required|integer',

                'attr_type' => 'sometimes|present|in:1,2',
                'group_id' => 'sometimes|required|integer|exists:shop_group,id',


            ],

        $this->message);



        $page_size = $request->input('page_size',15);
        $attr_type = $request->filled('attr_type') ? $request->attr_type : '';

        $shop_ids = [];
        // try {
            
        if ($request->filled('group_id')) {
            $shop_ids = \App\Model\Shop::where('group_id',$request->group_id)
                                        ->select('shop_id')
                                        ->pluck('shop_id')
                                        ->toArray();
        }

        // } catch (Exception $e) {}

        $map = [

            //待接单

           [

                ['shipping_status','=',0],

                ['pay_status','=',1],

                ['ps_id','=',0],

                ['area_id','=',$this->area_id ],

                ['delivery_type','=',0]

            ],

            //待取货

           [

                'shipping_status' => 0,

                'pay_status'      => 1,

                'delivery_type'   => 0,

                'ps_id'           => $this->user_id,

            ],

            //待送达

            [

                'shipping_status'=> 1,

                'pay_status'     => 1,

                'delivery_type'  => 0,

                'ps_id'          => $this->user_id,

            ],

            //已完成

            [

                'shipping_status'=> 2,

                'pay_status'     => 1,

                'delivery_type'  => 0,

                'ps_id'          => $this->user_id,

            ]

        ];



        $status = [[1,2],[1,2],[1,2],[4]];



        $orderbyraw =  ($request->type < 1) ? 'asc' : 'desc';

        info($request->all());

        $Order  = Order::whereIn('order_status',$status[$request->type])

                        ->when($attr_type, function ($query) use ($attr_type){

                            return $query->where('build_attr_type', $attr_type);

                        })

                        ->when($shop_ids, function ($query) use ($shop_ids){

                            return $query->whereIn('shop_id', $shop_ids);
                        })

                        ->where($map[$request->type])

                        ->with(['area'=>function($query){

                             return $query->select(['area_id','process_date']);

                        }])

                        ->with(['order_shop'=>function($query){

                            $query->select(['shop_id','shop_name','addr','mobile','tel','sell_time']);

                        }])

                        ->orderByRaw("pay_time {$orderbyraw}")

                        ->simplePaginate($page_size);



        return respond(200,'获取成功！',$Order);

    }

 

    /**

     * 抢单

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function scramble(Request $request)

    {

        $is_work = User::where('work_status',1)->where('user_id',$this->user_id)->first();

        if(empty($is_work)) return respond(201,'你处于休息中不能抢单！');

        

        $this->validate($request, 

            [

                'order_id'   => 'required|integer|exists:order,order_id',

            ],

        $this->message);



        DB::beginTransaction(); //开启事务



        $Order = Order::where('order_id',$request->order_id)->lockForUpdate()->first();



        if(!in_array($Order->order_status, [1,2]))  return respond(201,'该订单存在问题 请联系管理员');

 

        if($Order->ps_id > 0 || !empty($Order->ps_id)) return respond(201,'来晚了,已有人接单');



        $Order->ps_id = $this->user_id;

        $Order->rush_time = date('Y-m-d H:i:s');



        if($Order->save()){



            DB::commit();//提交事务

            return respond(200,'抢单成功！',$Order);

        }else{



            DB::rollBack();//事务回滚

            return respond(201,'抢单失败！');

        }

    }



    /**

     * 店铺取货或确认买家收货

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function take_delivery(Request $request)

    {

        $this->validate($request, 

            [

                'order_id' => 'required|integer|exists:order,order_id',

                'type'     => 'required|in:confirm_time,take_time',

            ],

        $this->message);



        $date  =  date('Y-m-d H:i:s');



        $where = [

                'order_id'   => $request->order_id,

                'pay_status' => 1,

                'distribution_status' => 0

        ];



        $Order = Order::where($where)

                        ->whereIn('order_status',[1,2])

                        ->where(function ($query) { 

                            $query->where('relay_id', $this->user_id)->orWhere('ps_id', $this->user_id);

                        })

                        ->first();

        if(empty($Order->order_id))  return respond(201,'该订单状态已改变！');



        $updateStatus = false;



        switch ($request->input('type')) {



            case 'confirm_time'://确认买家收货



                    if (($Order->relay_id && $Order->ps_id) && ($this->user_id !=  $Order->relay_id))
                    {
                        return respond(201,'该订单状态已改变！');
                    }

                    $updateStatus = (boolean)event(new ClearingAccount($Order));

                    try {
                        $confirmation_order = Area::where('area_id',$this->area_id)->value('confirmation_order');
                        if ($confirmation_order)
                        {
                            $this->sendSms([
                                'PhoneNumbers' => $Order->mobile,
                                'TemplateCode' =>'order_inform',
                                'TemplateParam' => [
                                    'tel' => \Auth::user()->mobile,
                                    'sn' => $Order->order_sn
                                ]
                            ]);
                        }
                    } catch (\Exception $e) {

                    }

                break;

                

            case 'take_time': //确认取餐



                    $Order->take_time = $date;

                    $Order->shipping_status = 1;



                    //骑手接了单取了货 但是商家未操作出餐 

                    if($Order->order_status == 1) {



                        $Order->appeared_time  = $date; //商家出餐确认时间

                        $Order->order_status   = 2; //修改订单状态已经出餐

                    } 



                    $updateStatus = $Order->save();



                break;

        }



        if($updateStatus) {



            event(new PushMessage($Order->order_id));



            return respond(200,'操作成功！',$Order);



        }else{



            return respond(201,'操作失败！');

        }

    }



    /**

     * 配送统计

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function statistics()

    {

        $month  = 6;

        $pr = env('DB_PREFIX');
        $user_id = $this->user_id;

        $Order = Order::distribution($this->user_id)

                    ->where('distribution_status',0)
                    // whereRaw("(ps_id = {$user_id} or relay_id = {$user_id}) and order_status = 4");
                    ->whereMonth('order.created_at','>=',date("m",strtotime("-{$month} month")))
                    // ->selectRaw("sum(horseman_amount) as total_amount,date_format(created_at,'%Y-%m') as month")
                    ->join('order_distribution', 'order.order_id', '=', 'order_distribution.order_id')
                    ->selectRaw("
                        sum(
                            CASE  
                                WHEN {$pr}order.ps_id = $user_id THEN {$pr}order_distribution.one_money
                                WHEN {$pr}order.relay_id = $user_id THEN {$pr}order_distribution.two_money
                                ELSE 0 
                            END
                        ) as total_amount,
                        date_format(wm_order.`created_at`,'%Y-%m') as month"
                    )

                    ->groupBy('month')

                    ->get();

        $date = [];



        $current_month = date("Y-m");



        for($i=5; $i>=1; $i--)

        {

            $date[date("Y-m",strtotime("$current_month -{$i}month"))] = 0;

        }

        

        $date[$current_month] = 0;



        foreach($Order as $k => $v)

        {

            $date[$v->month] = $v->total_amount;

        }



        $sixth_statistics = [];

        foreach($date as $key => $value)

        {

            $sixth_statistics[] = [ 

                'total_amount' => $value,

                'month'         => $key

            ];

        }



        $OrderMonth = Order::distribution($this->user_id)

                            ->where('distribution_status',0)

                            ->whereYear('order.created_at', date("Y"))

                            ->whereMonth('order.created_at',date("m"))

                            // ->selectRaw("sum(horseman_amount) as total_amount,

                            //     count(ps_id) as total_number,

                            //     date_format(created_at,'%Y-%m') as month")

                            ->join('order_distribution', 'order.order_id', '=', 'order_distribution.order_id')
                            ->selectRaw("
                                sum(
                                    CASE  
                                        WHEN {$pr}order.ps_id = $user_id THEN {$pr}order_distribution.one_money
                                        WHEN {$pr}order.relay_id = $user_id THEN {$pr}order_distribution.two_money
                                        ELSE 0 
                                    END
                                ) as total_amount,
                                count(*) as total_number,
                                date_format(wm_order.`created_at`,'%Y-%m') as month"
                            )

                            ->groupBy('month')

                            ->first();



        $Area       = Area::find($this->area_id);

        $settlement = empty($Area->settlement) ? [] : json_decode($Area->settlement,true);



        //获取所有正常完成的订单

        $rate  = Order::distribution($this->user_id)->where('distribution_status',0)->whereColumn('confirm_time','<=','finish_time')->count();



        //所有完成的订单

        $total_number = Order::distribution($this->user_id)->where('distribution_status',0)->count();



        $if_rate  = empty($rate) ? 0 : round(($rate/$total_number)*100);



        $data = [

            'chart' => $sixth_statistics,

            'current_month' => (!empty($OrderMonth)) ? $OrderMonth : ['total_amount'=>0,'total_number'=>0,'month'=>date('Y-m')] ,

            'other' => [

                'task'    => empty($settlement['order_num']) ? 0 : $settlement['order_num'],

                'average' => round(Comment::where('ps_id',$this->user_id)->avg('deliver_rank'),2),

                'rate'    => empty($total_number) ? 100 : $if_rate,

            ]

        ];

        return respond(200,'获取成功！', $data);

    }



    /**

     * 订单明细

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function order_detail(Request $request)

    {

        $this->validate($request,['page_size' => 'sometimes|present|integer'], $this->message);

        $page_size = $request->input('page_size',15);

        $pr = env('DB_PREFIX');
        $user_id = $this->user_id;

        $Order = Order::distribution($this->user_id)
                        ->where('distribution_status',0)
                        ->join('order_distribution', 'order.order_id', '=', 'order_distribution.order_id')
                        ->selectRaw("
                            sum(
                                CASE  
                                    WHEN {$pr}order.ps_id = $user_id THEN {$pr}order_distribution.one_money
                                    WHEN {$pr}order.relay_id = $user_id THEN {$pr}order_distribution.two_money
                                    ELSE 0 
                                END
                            ) as total_amount,
                            count(*) as total_number,
                            date_format(`wm_order`.`created_at`,'%Y-%m-%d') as day"
                        )
                        ->groupBy('day')
                        ->orderByDesc('day')
                        ->simplePaginate($page_size);
        return respond(200,'获取成功！', $Order);
    }





    /**

     * 历史订单

     * @return [type] [description]

     */

    public function history_order(Request $request)

    {

        $this->validate($request, 

        [

                'start_time'   => 'required_with:end_time|date_format:Y-m-d',

                'end_time'     => 'required_with:start_time|date_format:Y-m-d',

                'page_size'    => 'sometimes|required|integer',

                'search_keyword' => 'sometimes|required|string',

        ],

        $this->message);





        $search_keyword = $request->filled('search_keyword') ? $request->search_keyword : null;

        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $start_time = $request->filled('start_time') ? $request->start_time : '';

        $end_time = $request->filled('end_time') ? $request->end_time : '';



        $boolean_search_time = ($start_time && $end_time);



        $Order = Order::distribution($this->user_id)

                        ->when($boolean_search_time, function ($query) use ($start_time, $end_time) {

                            return $query->whereBetween('created_at', [$start_time.' 00:00:00', $end_time.' 23:59:59']);

                        })

                        ->when($request->filled('search_keyword'), function ($query) use ($search_keyword) {

                            return $query->where(function ($query) use ($search_keyword) {

                                        $query->where('order_sn','like','%'.$search_keyword.'%')

                                            ->orWhere('consignee','like','%'.$search_keyword.'%')

                                            ->orWhere('mobile','like','%'.$search_keyword.'%')

                                            ->orWhere('address','like','%'.$search_keyword.'%')

                                            ->orWhere('day_num','like','%'.$search_keyword.'%');

                            });

                        })

                        ->with(['order_goods','shop'])

                        ->orderByDesc('order_id')

                        ->simplePaginate($page_size);



        return respond(200,'获取成功！',$Order);

    }



    /**

     *骑手收支记录表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function userBill(Request $request)

    {

        $this->validate(

            $request,

            [

                'page_size'  => 'sometimes|required|integer',

            ],

        $this->message);



        $page_size = $request->input('page_size',15);

        

        $MerBill = UserBill::where('user_id',$this->user_id)

                            ->with('order.getShopName')

                            ->orderByDesc('created_at')

                            ->simplePaginate($page_size);

        if($MerBill)

            return respond(200, '获取成功！',$MerBill);

        else

            return respond(201, '获取失败！');

    }



    /**

     * 骑手提现列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function withdrawal_list(Request $request)

    {

        $this->validate( $request,['page_size' => 'sometimes|required|integer'],$this->message);



        $page_size = $request->input('page_size',15);



        $result = Withdrawal::where('user_id',$this->user_id)->where('client_type',2)->orderByDesc('id')->simplePaginate($page_size);



        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }



    /**

     * [checkIn 打卡签到]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function checkIn(Request $request)

    {

        $data = $this->check($this->user_id,$this->rider_type,$this->area_id);

        unset($data['start_time']);

        unset($data['end_time']);



        $RiderClockIn = RiderClockIn::where($data)->whereDate('created_at',date('Y-m-d'))->first();



        if(empty($RiderClockIn)){

            $result = RiderClockIn::create(array_merge($data,['status'=>1,'type'=>1,'call_time'=>date('H:i:s')]));

        }else{

            $RiderClockIn->type = 1;
            $RiderClockIn->status = 1;
            $RiderClockIn->call_time = date('H:i:s');
            $result = $RiderClockIn->save();
        }



        if ($result) {

            return respond(200,'操作成功！');

        }else{

            return respond(201,'操作失败！');

        }

    }



    /**

     * [cancelOrder 骑手2分钟内取消订单]

     * @return [type] [description]

     */

    public function cancelOrder(Request $request)

    {
        $this->validate($request, ['order_id'=>'required|integer|exists:order,order_id'],$this->message);
        $Order = Order::where(['order_id'=> $request->order_id ,'ps_id'=> $this->user_id])->first();

        if (empty($Order))  return respond(201,'该订单不能进行取消操作！');

        if (!(!empty($Order->rush_time) && (strtotime($Order->rush_time) + 120 > time()) && empty($Order->take_time) && ($Order->order_status <= 2) )) {

          return respond(201,'该订单不能进行取消操作！');
        }

        $ps_order_cancel_num = Area::where('area_id',$Order->area_id)->value('ps_order_cancel_num');

        if ($ps_order_cancel_num) {
            if (DB::table('order_ps_cancel')->where('ps_id',$this->user_id)->whereDate('created_at',date('Y-m-d'))->count() >= $ps_order_cancel_num) {
                return respond(201,'你超出了订单取消次数限制');
            }
        }
        
        if ($ps_order_cancel_num < 0) {
            return respond(201,'该订单不能进行取消操作！');
        }

        $Order->rush_time = null;

        $Order->ps_id = 0;

        if ($Order->save()) {

            DB::table('order_ps_cancel')->insert(['order_id' => $request->order_id,'ps_id' => $this->user_id,'created_at' => date('Y-m-d H:i:s')]);

            return respond(200,'取消成功！');

        }



        return respond(201,'取消失败！');

    }



    

    /**

     * 骑手公告

     * @return [type] [description]

     */

    public function notice()

    {

        $Data = Article::where('cat_id',2)->where('area_id',$this->area_id)->get();

        if($Data)

            return respond(200,'获取成功！',$Data);

        else

            return respond(201,'获取失败！');

    }



    /**

     * [zhiBan 获取当前校区是否存在值班]

     * @return [type] [description]

     */

    public function zhiBan() {



        $AreaShift = AreaShift::where('area_id',$this->area_id)->exists();



        return respond(200,'获取成功！',$AreaShift );

    }

}