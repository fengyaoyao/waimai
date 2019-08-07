<?php 

namespace App\Http\Controllers\Buyer;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{Area,User,Ad,Delivery,Collect,Coupon,CouponList,AccountLog,Goods,GoodsCate,Comment,OrderGoods,Cart,Order,PromShop};

use App\Http\Requests\BuyerRequest;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;

use App\Model\Buyer\Shop;




class BuyerController extends Controller

{

    protected $user_id;//用户id



    use BuyerRequest;



    public function __construct(Request $request)

    {

        $this->user_id = \Auth::id();



        if (in_array($request->getRequestUri(),[

            '/buyer/shop_list','/buyer/shop_info'

        ]) && $request->header('Authorization')) {

            $this->middleware('auth');

        }

    }

    

    /**

     * 获取区域列表

     * @return [type] [description]

     */

    public function area(Request $request)

    {


        $test_users = config('common.test_user_id');

        $uid = ($request->filled('user_id')) ? $request->user_id : $this->user_id;;

        if (empty( $uid )) {
            $is_test_login = true;
        }else{
            $is_test_login =  (in_array($uid, $test_users)) ? false : true;
        }




        $orderByRaw = 'sort desc,created_at desc';



        if($request->filled('search_name'))

        {

            $result = Area::when($is_test_login, function ($query) {

                                return $query->where('area_id','<>',12);

                            })

                            ->where('status',1)

                            ->where('address','like','%'.$request->search_name.'%')

                            ->orderByRaw($orderByRaw)

                            ->get();



        }elseif ($request->filled('long_and_lat')) {



            //获取经纬度

            $ll = explode(',',$request->long_and_lat);



            if (empty($ll) || empty($ll[0]) || empty($ll[1]) || !is_numeric($ll[0]) || !is_numeric($ll[1]) ){

                return respond(422,'参数错误！');

            }



            $longitude = $ll[0]; //经度

            $latitude  = $ll[1]; //纬度



            $point = returnSquarePoint($longitude,$latitude,20);

            $right_bottom_lat = $point['right_bottom']['lat'];   //右下纬度

            $left_top_lat     = $point['left_top']['lat'];       //左上纬度

            $left_top_lng     = $point['left_top']['lng'];       //左上经度

            $right_bottom_lng = $point['right_bottom']['lng'];   //右下经度


            $result = Area::selectRaw("*,(2 * 6378.137 * ASIN(SQRT(POW(SIN(PI()*({$longitude}-longitude)/360),2) + COS(PI()*{$latitude}/180) * COS(latitude * PI()/180)*POW(SIN(PI()*({$latitude}-latitude)/360),2)))) as juli")
                            ->when($is_test_login, function ($query) {
                                return $query->where('area_id','<>',12);
                            })
                            ->where('status',1)
                            ->where('latitude','>',$right_bottom_lat)
                            ->where('latitude','<',$left_top_lat)
                            ->where('longitude','>',$left_top_lng)
                            ->where('longitude','<',$right_bottom_lng)
                            ->orderByRaw('juli asc')
                            ->get();

        }else{



            $result = Area::when($is_test_login, function ($query) {

                                return $query->where('area_id','<>',12);

                            })

                            ->where('status',1)

                            ->orderByRaw($orderByRaw)

                            ->get();

        }



        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }

    /**

     * 搜索店铺列表

     * @param  [type] $area_id [description]

     * @return [type]          [description]

     */

    public function shop_list(Request $request)

    {



        $page_size = ($request->filled('page_size'))? $request->page_size:30;



        $result_msg = $this->CheckSearchParameter($request);

        

        if(!empty($result_msg)) {

            return respond(422,$result_msg);

        }



        $where = array_filter([

            'area_id'    => $request->input('area_id'),//区域id

            'is_new'     => $request->input('is_new'),//新店

            'type_id'    => $request->input('type_id'),//店铺类型id 

        ]);



        $where['is_lock']  = 0;



        $order = array_filter([

            'status'        => 'desc',

            'sort'          => $request->filled('sort') ? 'asc' : '',//综合排序

            'sales'         => $request->input('sales'),//销量排序

            'avg_minute'    => $request->input('avg_minute'),//速度最快

            'store_ratings' => $request->input('store_ratings'),//评价排序

        ]);



        $order_by_arr = [];

        $order_by_str = '';


        foreach ($order as $key => $value)
        {
            array_push($order_by_arr,  "{$key} {$value}");
        }

        $order_by_str = join(',',$order_by_arr);

        $pr = env('DB_PREFIX');
        
        $data = Shop::where($where)
                    ->with(['prom'=>function($query) {
                        return $query->where('status',1)->orderByRaw('field(type,2,0,1),money asc');
                    },'redPacket'])
                    ->selectRaw("*,(if({$pr}shops.avg_minute > 0,
                            avg_minute + (SELECT process_date from {$pr}areas where {$pr}shops.area_id = {$pr}areas.area_id),
                            sell_time + (SELECT process_date from {$pr}areas where {$pr}shops.area_id = {$pr}areas.area_id))) as avg_minute")
                    ->orderByRaw($order_by_str)
                    ->simplePaginate($page_size);



        return respond(200,'获取成功！',$data);

    }



    /**

     * 配送地址列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function delivery(Request $request)

    {

        $this->validate($request, ['area_id'=>'required|integer|exists:areas,area_id'],$this->message);



        $Delivery = Delivery::where('area_id',$request->area_id)->select(['area_id','delivery_id','attr_type','build_name','pid','sort'])->get();

        

        $data = [];

        $array_delivery = $Delivery->toArray();



        foreach ($array_delivery as $key => $value)

        {

            if($value['pid'] == 0)

            {

                $data[] = $value;

                unset($array_delivery[$key]);

            }

        }



        foreach ($data as $key => $va)

        {

            foreach ($array_delivery as $k => $v) 

            {

                if($v['area_id'] == $va['area_id'] && $v['pid'] == $va['delivery_id']) {

                    $data[$key]['child'][] = $v;

                }

            }



            if (!empty($data[$key]['child'])) {

                array_multisort(array_column($data[$key]['child'], 'sort'),SORT_ASC,$data[$key]['child']);

            }

        }

        return respond(200,'获取成功！',$data);

    }







    /**

     * 店铺详情

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shop_info(Request $request)

    {

        $this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);



        $shop_id = $request->shop_id;



        //获取店铺推荐商品

        $result = Shop::where('shop_id',$shop_id)->with(['openingTime','recommend_goods.itmeForSpec','shop_type','redPacket'])->first();

                        

        if(empty($result)) return respond(201,'该店铺未找到！');



        //根据店铺分类查询商品、商品的规格和属性

        $result->cate = GoodsCate::where('shop_id',$shop_id)->has('goods')->with('goods.itmeForSpec')->orderBy('sort')->get();

        //统计评论总条数

        $result->all     = Comment::where('is_show',0)->with('comment_reply')->where('shop_id',$shop_id)->count();

        //统计有图评论总条数

        $result->picture = Comment::where('is_show',0)->with('comment_reply')->where('shop_id',$shop_id)->whereNotNull('img')->count();

        //统计差评总条数

        $result->good    = Comment::where('is_show',0)->with('comment_reply')->where('shop_id',$shop_id)->where('service_rank','>',3)->count();

        //统计好评总条数

        $result->bad     = Comment::where('is_show',0)->with('comment_reply')->where('shop_id',$shop_id)->where('service_rank','<',3)->count();



        $result->is_first   = 1;

        $result->is_collect = 0;

        $promType = [0,1,2];



        $process_date = Area::where('area_id',$result->area_id)->value('process_date');



        if (empty($result->avg_minute)) {

            $result->avg_minute = $result->sell_time + $process_date;

        }else{

            $result->avg_minute = $result->avg_minute + $process_date;

        }



        if (!empty($request->header('authorization')) && \Auth::check()) {



            $result->is_first = Order::where('user_id',\Auth::id())->count('user_id');

            $result->is_collect = Collect::where(['user_id'=>\Auth::id(),'shop_id'=>$shop_id])->count('user_id');



            if (empty($result->is_first)) {

                $promType = PromShop::where('status',1)->where('shop_id',$shop_id)->where('type',2)->exists() ? [1,2] : [0,1,2];

            }else{

                $promType = [0,1];

            }  

        }





        $result->prom = PromShop::where('status',1)->where('shop_id',$shop_id)->whereIn('type',$promType)->orderByRaw('field(type,2,0,1),money asc')->get(); 

        

        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 店铺评论

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shop_evaluation(Request $request)

    {

        $this->validate($request,[

            'page_size'    => 'sometimes|integer',

            'shop_id'      => 'required|integer|exists:shops,shop_id',

            'type'         => 'sometimes|required|in:picture,good,bad'

        ],

        $this->message);



        $page_size = $request->input('page_size',15);

        $Comment   = Comment::where('is_show',0)->with('comment_reply')->with('order_goods')->where('shop_id',$request->shop_id);



        if($request->input('type') == 'picture')

        {

            $Comment->whereNotNull('img');

        }



        if($request->input('type') == 'good')

        {

            $Comment->whereIn('service_rank',[4,5]);

        }



        if($request->input('type') == 'bad')

        {

            $Comment->whereIn('service_rank',[0,1,2]);

        }



        $Comment = $Comment->orderByDesc('created_at')->simplePaginate($page_size);



        return respond(200,'获取成功！',$Comment);

    }



    /**

     * 我的推荐

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function my_recommend(Request $request)

    {

        $this->validate($request,[

            'order_id'      => 'required|integer|exists:order,order_id'

        ],

        $this->message);

        $cate = [];

        $goods_id = [];

        $OrderGoods = OrderGoods::where('order_id',$request->order_id)->with('goods')->get();

        foreach ($OrderGoods as $key => $value) 

        {

            $cate[] = $value->goods->cate_id;

            $goods_id[] = $value->goods->goods_id;

        }

        $Goods = Goods::whereIn('cate_id',array_unique($cate))->whereNotIn('goods_id',$goods_id)->where('shelves_status',1)->limit(10)->get();

        return respond(200,'获取成功！',$Goods);

    }



    /**

     * 再来一单

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function again_orders(Request $request)

    {



        $this->validate($request, 

            [

                'order_id'   => 'required|integer|exists:order,order_id'

            ],

        $this->message);



        $Order = OrderGoods::where('order_id',$request->order_id)->get();



        $data = [];

        foreach ($Order as $key => $value)

        {

            $data[] = [

                'user_id'        =>  $this->user_id,

                'goods_id'       =>  $value->goods_id,

                'shop_id'        =>  $value->shop_id,

                'goods_num'      =>  $value->goods_num,

                'spec_key'       =>  $value->spec_key,

                'spec_key_name'  =>  $value->spec_key_name,

                'created_at'     =>  date('Y-m-d H:i:s'),

                'updated_at'     =>  date('Y-m-d H:i:s'),

            ];

        }

        if(Cart::insert($data))

            return respond(200,'操作成功！');

        else

            return respond(201,'操作失败！');

    }



    /**

     * 收藏店铺

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function collect_shop(Request $request)

    {

        

        $status = false;



        $where  =  [

            'user_id'=> $this->user_id,

            'shop_id'=> $request->shop_id

        ];



        $this->validate(

            $request,

            [

                'collect_type' => 'required|in:true,false',

                'shop_id'      => 'required|integer|exists:shops,shop_id',

            ],

        $this->message);



        if($request->collect_type == 'true')

        

            $status =  Collect::create($where);

        else

            $status = Collect::where($where)->delete();



        if($status)

            return respond(200, $request->collect_type);

        else

            return respond(201, $request->collect_type);

    }



    /**

     * 收藏店铺列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function collect_list()

    {

        $row = Collect::where('user_id',$this->user_id)->with('shop')->get();



        return respond(200, '获取成功！',$row);

    }



    /**

     * 收藏店铺列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function collectForArea(Request $request)

    {

        $this->validate($request,['area_id' =>'required|exists:areas,area_id'],$this->message);

        $area_id = $request->input('area_id');

        $shopIds = Collect::where('user_id',$this->user_id)->get()->pluck('shop_id')->toArray();

        $Shop  = Shop::whereIn('shop_id', $shopIds)->where('area_id', $area_id)->get();

        return respond(200, '获取成功！',$Shop);

    }



    /**

     * 优惠卷列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */
    
    public function coupon_list(Request $request)

    {

        $this->validate($request,[
            'area_id' => 'sometimes|present|integer|exists:areas,area_id',
            'status' => 'sometimes|present|integer|in:0,1'

        ],$this->message);

        $area_id = $request->filled('area_id') ? $request->area_id : '';
        $status = $request->filled('status') ? $request->status : '';
        $user_id = $this->user_id;
        
        try {
            DB::update("UPDATE wm_coupon_list as a INNER JOIN (SELECT id from wm_coupon_days where `end_time` < curdate()) as b on a.id = b.id set a.`status`= 2 where a.`uid`= {$user_id}");
        } catch (\Exception $e) {}

        // $CouponIds = \App\Model\CouponHasArea::when($area_id ,function($query) use ($area_id){
        //                                             return $query->where('area_id',$area_id);
        //                                         })->pluck('coupon_id')->toArray();
        // //获取用户的优惠卷信息
        // $CouponList = CouponList::where('uid',$this->user_id)
        //                         ->couponExists($CouponIds)
        //                         ->with('coupon')
        //                         ->orderBy('status','desc')
        //                         ->get()
        //                         ->toArray();
           if ( $area_id ) {

            $CouponIds = \App\Model\CouponHasArea::where('area_id',$area_id)->pluck('coupon_id')->toArray();

            $pr = env('DB_PREFIX');

            $inids = 0;

            if (!empty($CouponIds) && is_array($CouponIds)) {
                $inids = join(',',$CouponIds);
            }


            $CouponList = CouponList::where('uid',$this->user_id)
                                    ->where('status',0)
                                    ->whereExists(function ($query) use ( $inids, $pr) {
                                        $query->select(DB::raw(1))
                                              ->from('coupon')
                                              ->whereRaw("{$pr}coupon.`id` = {$pr}coupon_list.`cid`
                                                and {$pr}coupon.`status` = 0  
                                                and ( 
                                                        {$pr}coupon.`use_type` = 0
                                                        or (
                                                            {$pr}coupon.`use_type` = 1 
                                                            and {$pr}coupon.`id` in ({$inids})
                                                        )
                                                )");
                                    })
                                    ->with('coupon')
                                    ->orderBy('status','desc')
                                    ->get()
                                    ->toArray();
        }else{

            //获取用户的优惠卷信息
            $CouponList = CouponList::where('uid',$this->user_id)
                                    ->couponExists()
                                    ->with('coupon')
                                    ->orderBy('status','desc')
                                    ->get()
                                    ->toArray();
        }
  

        $today = date("Y-m-d");

        //用于限制用户使用期限
        foreach ($CouponList as $key => $value) {

            if (empty($value['coupon'])) {
                continue;
            }

      
            //是否设置了天数
            if (!empty($value['coupon']['days'])) {

                $current_day =$CouponList[$key]['coupon']['days'] - 1;

                $send_time = strtotime($CouponList[$key]['send_time']);
  
                $CouponList[$key]['coupon']['use_start_time'] = date("Y-m-d 00:00:00",$send_time);
                $CouponList[$key]['coupon']['use_end_time'] = date("Y-m-d 23:59:59",strtotime("+{$current_day} day",$send_time));
                // 优惠卷使用限制时间已过期 并且优惠劵状态为有效
                if ((time() >= strtotime($CouponList[$key]['coupon']['use_end_time']))  && ($CouponList[$key]['coupon']['status'] == 0)) {

                    $CouponList[$key]['coupon']['status'] = 1;
                }
            }

            //优惠卷未使用 并且 结束时间为当天 并且优惠卷的状态为无效的
            if (
                ($CouponList[$key]['status'] == 0) 
                && ($CouponList[$key]['coupon']['status'] == 1) 
                && ($today == date("Y-m-d",strtotime($CouponList[$key]['coupon']['use_end_time'])))
            ) {
               
                $CouponList[$key]['coupon']['status'] = 0;
            }

            //优惠卷是永久有效并且未设置过期天数
            if (!empty($CouponList[$key]['coupon']['is_forever']) && empty($CouponList[$key]['coupon']['days'])) {

                $CouponList[$key]['coupon']['status'] = 0;
                $CouponList[$key]['code']= '永久有效';

            }else{

                $use_start_time = date("Y-m-d",strtotime($CouponList[$key]['coupon']['use_start_time']));
                $use_end_time = date("Y-m-d",strtotime($CouponList[$key]['coupon']['use_end_time']));

                if (
                    ($use_start_time == $use_end_time) 
                    && ($use_end_time == $today) 
                    && ($use_start_time == $today)
                ) {
                    $CouponList[$key]['code']= '今日有效'; 
                }else{
                    $CouponList[$key]['code']= $use_start_time . ' 至 '.$use_end_time;
                }
            }
        }

        return respond(200, '获取成功！',$CouponList);
    }



    /**

     * 用户日志记录表

     * @param  Request $request [description]

     * @return [type]           [description]

     */ 

    public function account_log(Request $request)

    {

        $this->validate($request,

            [

                'page_size'  => 'sometimes|required|integer',

                'type'       => 'sometimes|required|integer|in:1,2',

            ],

        $this->message);



        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $type = $request->filled('type') ? $request->type : false;



        $AccountLog = AccountLog::when($type, function ($query) use ($type) {



            switch ($type) {

                case '1':

                    return $query->where('user_money', '<>',0);

                    break;

                case '2':

                    return $query->where('pay_points', '<>',0);

                    break;

            }



        })->where('user_id',$this->user_id)->orderByDesc('created_at')->simplePaginate($page_size);



        if($AccountLog)

            return respond(200, '获取成功！',$AccountLog);

        else

            return respond(201, '获取失败！');

    }

}