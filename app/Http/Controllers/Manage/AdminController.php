<?php 

namespace App\Http\Controllers\Manage;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{Admin,Goods,Order,OrderChange,User,Area,Shop,Recruitment,AdminRole,UserBill,Comment,Bound};

use App\Model\Manage\Withdrawal;

use Illuminate\Support\Facades\DB;



class AdminController extends Controller

{

    protected $admin;

    protected $request;

    protected $areas = [];

    protected $shops = [];

    protected $business = [];





    public function __construct(Request $request)

    {



       $this->request  = $request;

       $this->admin    = $this->request->get('admin');



       if ($this->admin['role_id'] == 1 ) {

            $this->areas = Area::select('area_id')->pluck('area_id')->toArray();

       }else{

            $this->areas = $this->admin['area_id'];

       }





       if($this->admin['role_id'] == 1){

            $this->shops = Shop::pluck('shop_id')->toArray();

       }else{

            if (!empty($this->admin['shop_ids'])) {

               $this->shops = Shop::select('shop_id')->whereIn('shop_id',explode(',', $this->admin['shop_ids']))->pluck('shop_id')->toArray();

            }else{

               $this->shops = Shop::whereIn('area_id',$this->areas)->pluck('shop_id')->toArray();

            }

       }



       $this->business = Bound::whereIn('shop_id', $this->shops)->pluck('user_id')->toArray(); 

    }



    function userPushIds()

    {
        $this->validate($this->request,[ 
            'shop_id' => 'required|integer|exists:shops,shop_id'
        ], $this->message);

        $row = \App\Model\Bound::where('shop_id', $this->request->shop_id)
                                ->leftJoin('users','users.user_id', '=','bounds.user_id')
                                ->whereNotNull('push_id')
                                ->select('push_id')
                                ->pluck('push_id')
                                ->toArray();
        return respond(200,'获取成功！', $row);

    }



    /**

     * 订单管理列表

     * @return [type] [description]

     */

    function order_list()

    {



       $this->validate($this->request,[

            'order_status' => 'required|in:0,1,2,3,4,5',

            'page_size'    => 'sometimes|present|integer',

            'date'         => 'sometimes|present|date_format:Y-m-d',

            'area_id'      => 'sometimes|present|integer|exists:areas,area_id',

            'shop_id'      => 'sometimes|present|integer|exists:shops,shop_id',

            'search_keyword' => 'sometimes|required|string',

            'user_id' => 'sometimes|present|integer|exists:users,user_id',
        ],

        $this->message);

        

        $status        = [[0],[1,2],[1,2],[4],[3,5,6],[4]];

        $page_size     = $this->request->input('page_size',15); 

        $date          = $this->request->filled('date')?$this->request->date : false;

        $area_id       = $this->request->filled('area_id')?$this->request->area_id : false;

        $shop_id       = $this->request->filled('shop_id')?$this->request->shop_id : false;

        $order_status  = $this->request->filled('order_status')?$this->request->order_status : 0;

        $search_keyword =  $this->request->filled('search_keyword') ?  $this->request->search_keyword : false;

        $uid =  $this->request->filled('user_id') ?  $this->request->user_id : false;

        $defualt_where = [ ['pay_status' ,'=' ,1] ];

        $is_after_sale = ($order_status == 5) ? true : false;


        switch ($order_status)

        {

            case '1':

                    $defualt_where[] = ['ps_id','=',0];

                    $defualt_where[] = ['shipping_status','=',0];

                break;

            case '2':

                    $defualt_where[] = ['ps_id','>',0];

                    $defualt_where[] = ['shipping_status','>=',0];

                break;
        }

        $area_ids = [];

        if ($this->request->filled('area_id')) {
            array_push($area_ids, $this->request->area_id);
        }else{

            if ($this->admin['role_id'] != 1) {
                $area_ids = $this->areas;
            }
        }

        $shops = [];

        if ($this->request->filled('shop_id')) {
            array_push($shops, $this->request->shop_id); 
        }else{

            if ($this->admin['role_id'] != 1) {
                $shops = $this->shops;
            }
        }


        $pr = env('DB_PREFIX');

        $Order = Order::where($defualt_where)->
                        whereIn('order_status',$status[$order_status])->
                        when($is_after_sale, function ($query) use( $pr) {
                            return $query->whereRaw("exists( select 1 from {$pr}order_after_sale where {$pr}order.order_id = {$pr}order_after_sale.order_id )");
                        })->
                        when($uid, function ($query) use ($uid) {
                            return $query->whereRaw("(ps_id = {$uid} or relay_id = {$uid})");
                        })->
                        when($shops, function ($query) use ($shops) {
                            return $query->whereIn('shop_id',$shops);
                        })->
                        when($area_ids, function ($query) use ($area_ids) {
                            return $query->whereIn('area_id',$area_ids);
                        })->
                        when($date, function ($query) use ($date) {
                            return $query->whereDate('created_at', $date);
                        })->
                        when($search_keyword, function ($query) use ($search_keyword) {

                            return $query->where(function ($query) use ($search_keyword) {

                                        $query->where('order_sn','like','%'.$search_keyword.'%')

                                            ->orWhere('consignee','like','%'.$search_keyword.'%')

                                            ->orWhere('mobile','like','%'.$search_keyword.'%')

                                            ->orWhere('address','like','%'.$search_keyword.'%')

                                            ->orWhere('day_num',$search_keyword);
                            });
                        })->
                        with(['order_goods','order_ps','order_shop','order_shop_prom','afterSale','relay_info'])->
                        orderByDesc('order_id')->
                        simplePaginate($page_size);

        foreach($Order as $key => $value) 
        {
            $num = Order::where('user_id',$value->user_id)->
                        where('shop_id',$value->shop_id)->
                        where('created_at','<=',$value->created_at)->
                        where('pay_status',1)->
                        count();

            $value->user_cont = $num??0;
        }

        return respond(200,'获取成功！',$Order);
    }



    /**

     * 订单备注

     * @return [type] [description]

     */

    function admin_note()

    {

        $this->validate($this->request, 

            [

                'order_id'    => 'required|integer|exists:order,order_id',

                'admin_note'  => 'sometimes|string|between:2,200',

            ],

        $this->message);

        

        $Order = Order::find($this->request->order_id);

        $Order->admin_note = $this->request->admin_note;



        if($Order->save())

            return respond(200,'操作成功！');

        else

            return respond(201,'操作失败！');

    }



    /**

     * 指定配送人员

     * @return [type] [description]

     */

    function appoint()

    {

        $this->validate($this->request, 

        [

                'order_id'    => 'required|integer|exists:order,order_id',

                'user_id'     => 'required|integer|exists:users,user_id',

                'type' => 'sometimes|required|string|in:one,two'


        ],

        $this->message);

        

        $Order = Order::where('order_id',$this->request->order_id)
                        ->whereIn('order_status',[0,1,2])
                        ->where('pay_status',1)
                        ->where('shipping_status',0)
                        ->where('distribution_status',0)
                        ->first();


        if(empty($Order->order_id)) {

            return respond(422,'该订单无法指派或已完成！');
        }


        $data = [
            'order_id' => $this->request->order_id,
            'ps_to_id' => $this->request->user_id,
        ];
        
        $type  = $this->request->filled('type') ? $this->request->type : 'one' ;

        switch ($type) {

            case 'one':

                $data['desc'] = '管理端第一阶段转单';
                $data['ps_id'] = $Order->ps_id;
                $Order->ps_id = $this->request->user_id;
                $Order->rush_time = date('Y-m-d H:i:s');

                break;
            case 'two':

                $data['desc'] = '管理端第二阶段转单';
                $data['ps_id'] = $Order->relay_id;

                $Order->relay_id = $this->request->user_id;

                if (!empty($Order->relay_time)) {
                    $Order->relay_time =  date('Y-m-d H:i:s');
                }

                break;

            default:

                return respond(201,'操作失败！');

                break;
        }


        if($data['ps_id'] == $this->request->user_id) {

            return respond(422,'该订单已属于当前骑手！');
        }



        if($Order->save() && OrderChange::create($data)) {

            $push_id = User::where('user_id',$this->request->user_id)->value('push_id');

            push_for_jiguang($push_id ,"你有一笔指派的配送订单({$Order->day_num}),请注意查收！",2);

            
            return respond(200,'操作成功！');
        }

        return respond(201,'操作失败！');
    }



    /**

     * 配送人员

     * @return [type] [description]

     */

    function appoint_list()

    {

        $this->validate($this->request,  [
            'area_id'  => 'required|integer|exists:areas,area_id',
            'keywords' => 'sometimes|present|string|between:1,20',
            'rider_type' => 'sometimes|present|integer|in:1,0'
        ], $this->message);

        $where = [
            'area_id' => $this->request->area_id,
            'type' => 2,
            'work_status' => 1
        ];

        $keywords = $this->request->filled('keywords') ? $this->request->keywords : false;
        $rider_type = $this->request->filled('rider_type') ? $this->request->rider_type : 0;

        $users = User::where($where)
                        ->when($this->request->filled('rider_type'), function ($query) use ($rider_type) {
                            $query->where('rider_type',$rider_type);
                        })
                        ->when($keywords, function ($query) use ($keywords) {
                            $query->where('realname','like','%'.$keywords.'%')->orWhere('mobile','like','%'.$keywords.'%');
                        })
                        ->select(['user_id','nickname','realname','username','push_id','mobile'])
                        ->get();
                        
        foreach ($users as $key => $value) {

            $value->total_num =  Order::where('ps_id',$value->user_id)->whereIn('order_status',[1,2])->count();

            $value->nickname = $value->realname;

            $value->order_ps  = json_decode($value);
        }

        return respond(200,'获取成功！',$users);
    }



    /**

     * 区域列表

     * @return [type] [description]

     */

    function area_list()

    {

        return respond(200,'获取成功！', Area::whereIn('area_id',$this->areas)->orderBy('area_id')->get());

    }



    /**

     * 店铺列表

     * @return [type] [description]

     */

    function shop_list()

    {

        $role = [

                'status'     => 'sometimes|present|integer|in:0,1',

                'page_size'  => 'sometimes|present|integer',

        ];

        

        if ($this->request->filled('area_id')) {

            $areas_str = join(',',$this->areas);

            $role['area_id'] = 'required|integer|exists:areas,area_id|in:'.$areas_str;

        }



        $this->validate($this->request, $role, $this->message);



        $page_size = $this->request->input('page_size',15);

        $status    = ($this->request->filled('status'))  ? [$this->request->status]  : [0,1];

        $areas     = ($this->request->filled('area_id')) ? [$this->request->area_id] : $this->areas;



        $Shop      = Shop::select(['shop_id','logo','shop_name','addr','business_hours','status','area_id','balance'])

                            ->whereIn('shop_id',$this->shops)

                            ->whereIn('status', $status)

                            ->whereIn('area_id', $areas)

                            ->orderByDesc('shop_id')->simplePaginate($page_size);



        foreach ($Shop as $value)

        {

            $value->today_order_amount = Order::where('shop_id',$value->shop_id)->whereDate('created_at',date('Y-m-d'))->where('order_status',4)->sum('shop_amount');

        }



        if($Shop)

            return respond(200,'获取成功！',$Shop);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 申请入驻列表

     * @return [type] [description]

     */

    function proposer_list()

    {

        $this->validate($this->request, 

        [

           'page_size'  => 'sometimes|present|integer'

        ],

        $this->message);



        $page_size = $this->request->filled('page_size') ? $this->request->page_size : 15;

        $result    = Recruitment::where('type',2)->select(['id','mobile','username','shop_name','address','created_at','status'])->orderByDesc('id')->simplePaginate($page_size);

        foreach ($result as $key => $value) {
            $value->mobile = (string)$value->mobile;
        }

        return respond(200,'获取成功！',$result);

    }



    /**

     * 首页

     * @return [type] [description]

     */

    function home()

    {

        $AdminRole               = AdminRole::select(['home_menu'])->find($this->admin['role_id']);

        $horseman_num            = User::where('type',2)->whereIn('area_id',$this->areas)->count();

        $goods_num               = Goods::select('goods_id')->whereIn('shop_id',$this->shops)->count();

        $today_order_amount      = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at',date('Y-m-d'))->sum('total_amount');

        $today_order_num         = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at',date('Y-m-d'))->count();

        $yesterday_order_amount  = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at',date('Y-m-d',strtotime('-1 day')))->sum('total_amount');

        $yesterday_order_num     = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at',date('Y-m-d',strtotime('-1 day')))->count();

        $order_pending           = Order::whereIn('shop_id',$this->shops)->where('order_status',0)->where('pay_status',1)->whereDate('created_at',date('Y-m-d'))->count();

        $balance                 = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereIn('area_id',$this->areas)->sum('order_amount');

        $data = [

            'shop_num'               => count($this->shops),

            'horseman_num'           => $horseman_num,

            'goods_num'              => $goods_num,

            'admin_role'             => $AdminRole->home_menu,

            'today_order_amount'     => $today_order_amount,    

            'today_order_num'        => $today_order_num,

            'today_order_avg'        => $today_order_amount > 0 ? round($today_order_amount/$today_order_num) : 0,

            'yesterday_order_amount' => $yesterday_order_amount,    

            'yesterday_order_num'    => $yesterday_order_num,

            'yesterday_order_avg'    => $yesterday_order_amount > 0 ? round($yesterday_order_amount/$yesterday_order_num) : 0,

            'order_pending'          => $order_pending,

            'balance'                => $balance,

            'is_see_amount'          => $this->admin['is_see_amount'],

        ];



        return respond(200,'获取成功！',$data);  

    }



    /**

     * 用户列表

     * @return [type] [description]

     */

    function user_list()

    {

        $this->validate($this->request, 

            [

                'search'    => 'sometimes|present|string',

                'page_size' => 'sometimes|present|integer',

            ],

        $this->message);



        $page_size = $this->request->filled('page_size') ? $this->request->page_size : 15;

        $search = $this->request->filled('search') ? $this->request->search : false;

        $result = User::select(['user_id','username','nickname','moeny','points','area_id','mobile','type'])

                        ->where('type',0)

                        ->when($search, function ($query) use ($search) {

                            if(is_numeric($search)) {

                                if(preg_match("/^1[3456789][0-9]{9}$/",$search)) {

                                    return $query->where('mobile', $search);

                                }else{

                                    return $query->where('user_id', $search);

                                }

                            }else{

                                return $query->where('nickname','like','%'.$search.'%');

                            }

                        })

                        ->orderByDesc('user_id')

                        ->with('DefualtAddress')

                        ->simplePaginate($page_size);



        foreach ($result as $key => $value)

        {

            $Order = Order::where('user_id',$value->user_id)->where('order_status',4)->select(['order_id','user_id','order_status','order_amount'])->pluck('order_amount')->toArray();

            $value->number_total = count($Order);

            $value->money_total  = round(array_sum($Order),2);

        }



        return respond(200,'获取成功！',$result);

    }



    /**

     * 提现记录列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    function withdrawal_list()

    {

        $this->validate($this->request,

            [

                'page_size'  => 'sometimes|present|integer',

                'status'     => 'sometimes|present|integer|in:0,1,2',

                'date'       => 'sometimes|present|date_format:Y-m-d',

                'area_id'    => 'sometimes|present|integer',

                'user_type'  => 'required|in:0,1,2',

                'user_id'    => 'sometimes|required|integer|exists:users,user_id',

            ],

        $this->message);



        $page_size = ($this->request->filled('page_size')) ? $this->request->page_size : 15;

        $date      = ($this->request->filled('date'))      ? $this->request->date : false;

        $area_id   = ($this->request->filled('area_id'))   ? $this->request->area_id : $this->areas;

        $user_id   = ($this->request->filled('user_id'))   ? $this->request->user_id : false;

        $status    = ($this->request->filled('status'))    ? [$this->request->status] : [0,1,2] ;



        $result    = Withdrawal::where('user_type',$this->request->user_type)

                                ->whereIn('status', $status)

                                ->when($user_id, function ($query) use ($user_id) {

                                    return $query->where('user_id',$user_id);

                                })

                                ->when($area_id, function ($query) use ($area_id) {

                                    if(is_array($area_id))

                                        return $query->whereIn('area_id',$area_id);

                                    else

                                        return $query->where('area_id',$area_id);

                                })

                                ->with(['user'=>function($query){

                                    return  $query->select(['nickname','headimgurl','mobile']);

                                }])

                                ->when($date, function ($query) use ($date) {

                                    return  $query->whereDate('created_at', $date);

                                })

                                ->orderByDesc('id')->simplePaginate($page_size);



        return respond(200,'获取成功！',$result);

    }





    /**

     * 配送人员管理列表

     * @return [type] [description]

     */

    function horseman_list()

    {

        $this->validate($this->request,

        [

            'page_size'  => 'sometimes|present|integer',

        ],$this->message);



        $page_size = $this->request->input('page_size',15);



        $result = User::where('type',2)

                        ->whereIn('area_id',$this->areas)

                        ->select(['user_id','nickname','headimgurl','moeny','area_id','work_status','mobile','push_id','realname','rider_money'])

                        ->with('area')

                        ->withCount([

                            'order_ps',

                            'order_ps as number_total' => function ($query) {$query->where('order_status',4);}

                        ])
                        ->orderByDesc('work_status')
                        ->simplePaginate($page_size);



        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 配送人员金额列表

     * @return [type] [description]

     */

    function delivery_amount_list()

    {

         

        $this->validate($this->request,

        [

            'page_size'  => 'sometimes|present|integer',

            'user_id'    => 'sometimes|present|integer',

            'date'       => 'sometimes|present|date_format:Y-m-d',

        ],$this->message);



        $page_size = ($this->request->filled('page_size')) ? $this->request->page_size : 15;

        $date      = ($this->request->filled('date')) ? $this->request->date : '';

        $user_id   = ($this->request->filled('user_id')) ? $this->request->user_id : '';



        $areas     = $this->areas;



        $result    = UserBill::where('type','<>',2)->when($date, function ($query) use ($date) {

                            return  $query->whereDate('created_at', $date);

                    })

                    ->when($user_id, function ($query) use ($user_id) {

                            return  $query->where('user_id', $user_id);

                    })

                    ->whereExists(function ($query) use ($areas){

                        $pr = env('DB_PREFIX');

                        $query->select(DB::raw(1))

                              ->from('users')

                              ->whereIn('area_id',$areas)

                              ->whereRaw("{$pr}user_bill.user_id = {$pr}users.user_id");

                    })

                    ->with(['order.ps_info','user'=>function($query) {

                        return $query->select(['user_id','realname','nickname','push_id','area_id']);

                    }])->orderByDesc('bill_id')->simplePaginate($page_size);

        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }

    /**

     * 订单详情

     * @return [type] [description]

     */

    function order_info()

    {

        $this->validate($this->request, 

            [

                'order_id'   => 'sometimes|required|integer|exists:order,order_id',

                'order_sn'   => 'sometimes|required|string|exists:order,order_sn',

            ],

        $this->message);



        if(!$this->request->has('order_id') && !$this->request->has('order_sn')) return respond(422,'查询条件不能为空！');



        foreach($this->request->only(['order_id', 'order_sn']) as $key => $value)

        {

            if($this->request->filled($key)) $default_where[$key] = $value;

        }



        $Order  = Order::where($default_where)

                            ->with(['order_goods',

                            'order_ps',

                            'order_shop_prom',

                            'order_shop',

                            'area'=> function($query){return $query->select(['area_id','process_date']);}

                            ])->first();



        $Order->user_cont  = Order::where('shop_id',$Order->shop_id)->where('user_id',$Order->user_id)->where('pay_status',1)->count();



        return respond(200,'获取成功！',$Order);

    }



    /**

     * 数据统计

     * @return [type] [description]

     */

    function statistics()

    {

        $this->validate(

            $this->request,

            [

                'page_size'  => 'sometimes|present|integer',

                'type'       => 'sometimes|present|in:date,month,year',

                'area_id'    => 'sometimes|present|integer',

                'days'       => 'sometimes|present|integer',
                'shop_id'  => 'sometimes|present|integer|exists:shops,shop_id',


            ],

        $this->message);



        $page_size = ($this->request->filled('page_size')) ? $this->request->page_size : 15;

        $type      = ($this->request->filled('type')) ? $this->request->type : 'date';

        $area_id   = ($this->request->filled('area_id')) ? $this->request->area_id : $this->areas;

        $days      = ($this->request->filled('days')) ? $this->request->days  : 6 ;
        $shop_id   = ($this->request->filled('shop_id')) ? $this->request->shop_id : false;



        $Order     = Order::where('order_status',4)

                            ->when($area_id, function ($query) use ($area_id) {

                                if(is_array($area_id))

                                    return $query->whereIn('area_id',$area_id);

                                else

                                    return $query->where('area_id',$area_id);

                            })
                            ->when($shop_id, function ($query) use ($shop_id) {
                                return $query->where('shop_id',$shop_id);
                            })

                            ->whereIn('shop_id',$this->shops)

                            ->whereBetween('created_at',[date("Y-m-d 00:00:00",strtotime("-{$days} day")),date("Y-m-d 23:59:59")])

                            ->selectRaw('sum(total_amount) as total_amount,date(created_at) as day')

                            ->groupBy('day')

                            ->get();



        $date = [];



        for($i=0; $i<=$days; $i++)

        {

            $day = $days-$i;

            $date[date("Y-m-d",strtotime("-{$day} day"))] = 0;

        }



        foreach($Order as $k => $v)

        {

            $date[$v->day] = $v->total_amount;

        }





        $statistics = [];

        foreach($date as $key => $value)

        {

            $statistics[] = [ 

                'total_amount' => $value,

                'day'          => $key,

            ];

        }

        unset($date);



        $map = [

            'date'  => '%Y-%m-%d',

            'month' => '%Y-%m',

            'year'  => '%Y',

        ];



        $format = $map[$type];



        $result = Order::where('order_status',4)
                        ->when($shop_id, function ($query) use ($shop_id) {
                                return $query->where('shop_id',$shop_id);
                        })

                        ->whereIn('shop_id',$this->shops)

                        ->when($area_id, function ($query) use ($area_id) {

                            if(is_array($area_id))

                                return $query->whereIn('area_id',$area_id);

                            else

                                return $query->where('area_id',$area_id);

                        })

                        ->selectRaw("sum(total_amount) as total_amount,

                            count(order_id) as order_num,

                            date_format(created_at,'{$format}') as date")

                        ->groupBy(\DB::Raw("{$type}(created_at)"))

                        ->orderByDesc('order_id')

                        ->simplePaginate($page_size);

                        

        array_multisort(array_column($statistics,'day'),SORT_ASC,$statistics);

                        

        $data = [

            'statistical_chart' => $statistics,//统计图数据

            'statistical_list'  => $result,//统计列表数据

        ];



        return respond(200,'获取成功！',$data);

    }



    /**

     * 店铺评论

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function evaluation_list(Request $request)

    {

        $this->validate($request,[

            'page_size'    => 'sometimes|integer',

            'type'         => 'required|in:pending,medium,good,bad',

            'shop_id'      => 'sometimes|present|integer'

        ],

        $this->message);

        if($request->filled('shop_id'))

            $array_shop[] = $request->shop_id;

        else

            $array_shop = $this->shops;

        

        $page_size = $request->input('page_size',15);

        $Comment   = Comment::with('comment_reply')->whereIn('shop_id',$array_shop);



        if($request->input('type') == 'pending')

        {

            $Comment->whereNotExists(function ($query){

                $pr = env('DB_PREFIX');



                $query->select(\DB::raw(1))

                      ->from('comment_reply')

                      ->whereRaw("{$pr}comment_reply.comment_id = {$pr}comment.comment_id");

            });

        }



        if($request->input('type') == 'medium')

        {

            $Comment->whereIn('service_rank',[3]);

        }



        if($request->input('type') == 'good')

        {

            $Comment->whereIn('service_rank',[4,5]);

        }



        if($request->input('type') == 'bad')

        {

            $Comment->whereIn('service_rank',[1,2]);

        }



        

        $Comment = $Comment->orderByDesc('comment_id')->simplePaginate($page_size);



        $data = [

            'good'    => Comment::whereIn('shop_id',$array_shop)->where('service_rank','>',3)->count(),

            'medium'  => Comment::whereIn('shop_id',$array_shop)->where('service_rank','=',3)->count(),

            'bad'     => Comment::whereIn('shop_id',$array_shop)->where('service_rank','<',3)->count(),

            'pending' => Comment::whereNotExists(function ($query){

                    $pr = env('DB_PREFIX');

                    $query->select(\DB::raw(1))

                          ->from('comment_reply')

                          ->whereRaw("{$pr}comment_reply.comment_id = {$pr}comment.comment_id");

                })->whereIn('shop_id',$array_shop)->count(),

        ];

        return respond(200,'获取成功！',['Comment'=>$Comment,'count'=>$data]);

    }



    /**

     * 评论详情

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function evaluation_info(Request $request)

    {

        $this->validate($request,[

            'comment_id'      => 'required|integer|exists:comment,comment_id',

        ],

        $this->message);



        $Comment   = Comment::with('comment_reply')->where('comment_id',$request->comment_id)->with(['order_goods','shop','order'])->first();

        return respond(200,'获取成功！',$Comment);

    }



    /**

     * [admin_info 管理员信息]

     * @return [type] [description]

     */

    public function admin_info()

    {

        return respond(200,'获取成功！',$this->admin);

    }



    public function statisticsInfo(Request $request)

    {

        $rules =  [

            'area_id' => 'sometimes|present|integer',

            'type'    => 'required|string|in:date,month,year'

        ];



        switch ($request->input('type')) {

            case 'date':

                $rules['format_date'] = 'required|string|date_format:Y-m-d';

                break;

            case 'month':

                $rules['format_date'] = 'required|string|date_format:Y-m';

                break;

            case 'year':

                $rules['format_date'] = 'required|string|date_format:Y';

                break;

        }



        $this->validate($request, $rules,$this->message);



        $format_date = $request->format_date;

      

        $area_id = $request->filled('area_id') ? $this->areas : [$request->area_id];



        $select_format = ['year'=>'%Y','month'=>'%Y-%m','date'=>'%Y-%m-%d'];



        $format = $select_format[$request->type];



        $pr = env('DB_PREFIX');

        $Order  = Order::where('order_status',4)

                        ->whereIn('area_id',$area_id)

                        ->whereIn('shop_id',$this->shops)

                        ->selectRaw("

                            IFNULL(sum((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 0)),0) as deduction_amount,

                            IFNULL(sum((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 2)),0) as first_amount,

                            sum(user_money) as balance_amount,

                            sum(integral_money) as integral_amount,

                            sum(delivery_cost + floor_amount) as delivery_amount,

                            sum(order_amount) as pay_amount,

                            count(order_id) as order_num,

                            sum(coupon_price) as coupon_amount,

                            sum(goods_price) as goods_price_amount,

                            sum(packing_expense) as packing_amount,

                            convert((sum(order_amount)*0.006),decimal(10,2)) as service_amount,

                            sum(total_amount) as total_amount,

                            date_format(created_at,'{$format}') as date")

                        ->groupBy('date')

                        ->havingRaw("date = '{$format_date}'")

                        ->first();



                        



        if (empty($Order)) {

           $Order = [

            'deduction_amount'=>0,

            'balance_amount'=>0,

            'integral_amount'=>0,

            'delivery_amount'=>0,

            'service_amount'=>0,

            'pay_amount'=>0,

            'order_num'=>0,

            'coupon_amount'=>0,

            'goods_price_amount'=>0,

            'packing_amount'=>0,

            'total_amount'=>0,

            'first_amount'=>0,

            'date'=> date('Y-m-d')

           ];

        }

        return respond(200,'获取成功！',$Order);

    }



    /**

     * [countInfo 统计详情]

     * @param  Request $request [description]

     * @return [type]           [description]

     */
 public function countInfo(Request $request)
    {

        $rules = [
            'area_id' => 'sometimes|present|integer',
            'type'    => 'required|string|in:date,month,year',
            'shop_id'  => 'sometimes|present|integer|exists:shops,shop_id',
        ];

        $format_date = $request->format_date;

        $format = '%Y-%m-%d';

        $between_date = [];

        switch ($request->type) {
            case 'date':

                $rules['format_date'] = 'required|string|date_format:Y-m-d';
                $format = '%Y-%m-%d';
                array_push($between_date, $format_date.' 00:00:00');
                array_push($between_date, date('Y-m-d 00:00:00', strtotime("{$format_date} +1 day")));
                break;

            case 'month':

                $rules['format_date'] = 'required|string|date_format:Y-m';
                $format = '%Y-%m';
                array_push($between_date, $format_date.'-01 00:00:00');
                array_push($between_date, date('Y-m-d 00:00:00', strtotime("{$format_date} +1 month")));
                break;

            case 'year':

                $rules['format_date'] = 'required|string|date_format:Y';
                $format = '%Y';
                array_push($between_date, $format_date.'-01-01 00:00:00');
                array_push($between_date, date('Y-m-d 00:00:00', strtotime("{$format_date}-01-01 +1 year")));
                break;
        }

        $this->validate($request,$rules,$this->message);

        $area_ids = [];

        if ($request->filled('area_id')) {
            array_push($area_ids, $request->area_id);
        }else{

            if ($this->admin['role_id'] != 1) {
                $area_ids = $this->areas;
            }
        }

        $shops = [];

        if ($request->filled('shop_id')) {
            array_push($shops, $request->shop_id); 
        }else{

            if ($this->admin['role_id'] != 1) {
                $shops = $this->shops;
            }
        }

        $pr = env('DB_PREFIX');
        // \Illuminate\Support\Facades\Log::channel('print')->info($request->all());

        if ($this->admin['role_id']  == 1 ) {
            // DB::connection()->enableQueryLog();

            $Order1 = Order::selectRaw("
                    count(*) as order_num,
                    sum({$pr}order.goods_price) as goods_price_amount,
                    sum({$pr}order.user_money) as balance_amount,
                    sum({$pr}order.integral_money) as integral_amount,
                    sum({$pr}order.coupon_price) as coupon_amount,
                    sum({$pr}order.order_amount) as pay_amount,
                    sum({$pr}order.shop_amount) as shop_amount,
                    sum({$pr}order.service_charge) as shop_service_amount,
                    sum({$pr}order.packing_expense) as packing_amount,
                    sum({$pr}order.delivery_cost + {$pr}order.floor_amount) as delivery_amount,
                    sum({$pr}order.platform_rake) as goods_rake_amount,
                    sum(IF(`distribution_status` > 0 , `horseman_amount`, 0)) as shop_self_delivery_amount,
                    date_format({$pr}order.created_at,'{$format}') as date
                ")
                ->whereBetween("order.created_at",$between_date)
                ->where('order_status',4)
                ->when($shops, function ($query) use ($shops) {
                    return $query->whereIn('shop_id',$shops);
                })
                ->when($area_ids, function ($query) use ($area_ids) {
                    return $query->whereIn('area_id',$area_ids);
                })
                ->first()
                ->toArray();

            $Order2 = Order::selectRaw("
                    sum({$pr}order_shop_prom_view.`manjian_amount`) as manjian_amount,
                    sum({$pr}order_shop_prom_view.`first_amount`) as first_amount,
                    round(sum(({$pr}order_shop_prom_view.`manjian_amount` / 100) * {$pr}order_ratio.full_ratio),2) as shop_manjian_amount,
                    round(sum(({$pr}order_shop_prom_view.`first_amount` / 100) * {$pr}order_ratio.first_ratio),2) as shop_first_amount,
                    round(sum(({$pr}order_ratio.custom_delivery_ratio / 100) * {$pr}order.delivery_cost),2) as shop_delivery_amount,
                    round(sum(({$pr}order_ratio.custom_ratio / 100) * {$pr}order.packing_expense),2) as shop_packing_amount
                ")
                ->leftJoin('order_ratio','order.order_id', '=', 'order_ratio.order_id')
                ->leftJoin('order_shop_prom_view','order.order_id', '=', 'order_shop_prom_view.order_id')
                ->whereBetween("order.created_at",$between_date)
                ->where('order_status',4)
                ->when($shops, function ($query) use ($shops) {
                    return $query->whereIn('shop_id',$shops);
                })
                ->when($area_ids, function ($query) use ($area_ids) {
                    return $query->whereIn('area_id',$area_ids);
                })
                ->first()
                ->toArray();
            $Order = array_merge($Order1 , $Order2);

            // $logs = DB::getQueryLog();
            // \Illuminate\Support\Facades\Log::channel('print')->info($logs);

        }else{


            $Order = Order::selectRaw("
                    count(*) as order_num,
                    sum({$pr}order.goods_price) as goods_price_amount,
                    sum({$pr}order.user_money) as balance_amount,
                    sum({$pr}order.integral_money) as integral_amount,
                    sum({$pr}order.coupon_price) as coupon_amount,
                    sum({$pr}order.order_amount) as pay_amount,
                    sum({$pr}order.shop_amount) as shop_amount,
                    sum({$pr}order.service_charge) as shop_service_amount,
                    sum({$pr}order.packing_expense) as packing_amount,
                    sum({$pr}order.delivery_cost + {$pr}order.floor_amount) as delivery_amount,
                    sum({$pr}order.platform_rake) as goods_rake_amount,
                    sum(IF(`distribution_status` > 0 , `horseman_amount`, 0)) as shop_self_delivery_amount,
                    sum({$pr}order_shop_prom_view.`manjian_amount`) as manjian_amount,
                    sum({$pr}order_shop_prom_view.`first_amount`) as first_amount,
                    round(sum(({$pr}order_shop_prom_view.`manjian_amount` / 100) * {$pr}order_ratio.full_ratio),2) as shop_manjian_amount,
                    round(sum(({$pr}order_shop_prom_view.`first_amount` / 100) * {$pr}order_ratio.first_ratio),2) as shop_first_amount,
                    round(sum(({$pr}order_ratio.custom_delivery_ratio / 100) * {$pr}order.delivery_cost),2) as shop_delivery_amount,
                    round(sum(({$pr}order_ratio.custom_ratio / 100) * {$pr}order.packing_expense),2) as shop_packing_amount,
                    date_format({$pr}order.created_at,'{$format}') as date
                ")
                ->leftJoin('order_ratio','order.order_id', '=', 'order_ratio.order_id')
                ->leftJoin('order_shop_prom_view','order.order_id', '=', 'order_shop_prom_view.order_id')
                ->whereBetween("order.created_at",$between_date)
                ->where('order_status',4)
                ->when($shops, function ($query) use ($shops) {
                    return $query->whereIn('shop_id',$shops);
                })
                ->when($area_ids, function ($query) use ($area_ids) {
                    return $query->whereIn('area_id',$area_ids);
                })
                ->first()
                ->toArray();
        }
        


        $array_name = [
            'order_num'=>'订单量',
            'goods_price_amount'=>'商品销售额',
            'balance_amount'=>'余额抵扣',
            'integral_amount'=>'积分抵扣',
            'coupon_amount'=>'优惠卷抵扣',
            'pay_amount'=>'在线支付金额',
            'first_amount'=>'首单优惠',
            'manjian_amount'=>'满减优惠',
            'delivery_amount'=>'配送费',
            'packing_amount'=>'餐盒费',
            'goods_rake_amount'=>'商品抽成',
            'shop_first_amount'=>'商户首单承担',
            'shop_manjian_amount'=>'商户满减承担',
            'shop_service_amount'=>'商户手续费承担',
            'shop_delivery_amount'=>'商户配送费应得',
            'shop_packing_amount'=>'商户餐盒费应得',
            'shop_amount'=>'商户营收',
            'shop_self_delivery_amount' => '商户自主配送费'
        ];

        $data = [];

        foreach ($array_name as $k => $v) {
            if (array_key_exists($k,$Order)) {
                array_push($data, ['name'=> $v,'value'=> $Order[$k]]);
            }else{
                array_push($data, ['name'=> $v, 'value'=> 0]);
            }
        }
        return respond(200,'获取成功！',$data);
    }

}