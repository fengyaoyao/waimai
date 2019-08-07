<?php 

namespace App\Http\Controllers\Shop;

use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Shop,GoodsCate,Goods,PromShop,Order,Coupon,Comment,OrderGoods,CommentReply,User,Withdrawal,MerBill,Article, Bound, ShopOpeningTime,OrderHasAmount};
use App\Http\Requests\PromRequest;
use App\Events\{CalculateBrokerage, CancelOrder, ClearingAccount, PushMessage};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Traits\RefundOrder;


class ShopController extends Controller

{

    use PromRequest,RefundOrder;

    protected $user_id;

    protected $shops;



    public function __construct()

    {

        $this->user_id = \Auth::id();



        //获取当前用户下所有店铺

        $this->shops = Bound::where('user_id',$this->user_id)

                            ->whereExists(function ($query) {

                                $pr = env('DB_PREFIX');

                                $query->select(DB::raw(1))->from('shops')->whereRaw("{$pr}shops.shop_id = {$pr}bounds.shop_id");

                            })

                            ->pluck('shop_id')->toArray();

    }



    /**

     * 店铺主页

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shop_home(Request $request)

    {



        $ToDayOrder     = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at', date('Y-m-d'));

        $YesterDayOrder = Order::whereIn('shop_id',$this->shops)->where('order_status',4)->whereDate('created_at', date('Y-m-d',strtotime('-1 day')));



        $data = [

            'today'    => [

                'total'   => $ToDayOrder->sum('goods_price'),

                'number'  => $ToDayOrder->count(),

                'average' => round($ToDayOrder->count() ? $ToDayOrder->sum('goods_price') / $ToDayOrder->count() : 0,2),

            ],

            'yesterday' => [

                'total'   => $YesterDayOrder->sum('goods_price'),

                'number'  => $YesterDayOrder->count(),

                'average' => round($YesterDayOrder->count()?$YesterDayOrder->sum('goods_price') / $YesterDayOrder->count():0,2)

            ]

        ];

        return respond(200,'获取成功！',$data);

    }



    /**

     * 获取店铺信息

     * @param  int id 

     * @return json 

     */

    public function info(Request $request)

    {



        $this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],

            $this->message);

        $result = Shop::find($request->shop_id);

        

        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 获取当前用户所有的店铺

     * @return [type] [description]

     */

    public function shop_list()

    {



                        
        $result = Shop::whereIn('shop_id',$this->shops)->with('prom')->withCount('redPacket')->orderByDesc('is_main')->get();

        if($result)

            return respond(200,'获取成功！',$result);

            return respond(201,'获取失败！');

    }



    /**

     * 店铺活动

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function edit_prom(Request $request) {

        $result = $this->CheckParameter($request);

        if(!empty($result)) return respond(422,$result);



        $status = false;

        foreach ($request->all() as $key => $value)

        {

            if(empty($value)) continue;

            foreach ($value as $k => $v)

            {

                if(empty($v)) continue;

                if(!empty($v['prom_id']))

                {

                    $where = [

                        'prom_id' => $v['prom_id'],

                        'shop_id' => $v['shop_id'],

                    ];



                    $PromShop  =  PromShop::where($where)->first();

                }else{

                    $PromShop  =  new PromShop();

                }

                foreach ($v as $ke => $va) 

                {

                    if(!isset($ke)) continue;

                    $PromShop->$ke  = $va;

                }

                $status = $PromShop->save();

            }

        }



        if($status)

        return respond(200,'操作成功！',$request->all());

        return respond(201,'操作失败！');

    }





    /**

     * 删除店铺活动

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function del_prom(Request $request)

    {

        $this->validate($request, [

            'prom_id'=>'required|integer|exists:prom_shop,prom_id',

            'shop_id'=>'required|integer|exists:shops,shop_id',

        ],

        $this->message);



        $PromShop = PromShop::find($request->prom_id);



        if($PromShop->delete())

        return respond(200,'删除成功！');

        return respond(201,'删除失败！');

    }



    /**

     * 改变店铺状态

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function change_status(Request $request)

    {

        $this->validate($request,[

            'shop_id'        => 'required|integer|exists:shops,shop_id',

            'announcement'   => 'sometimes|string|between:2,45',

            'picked_up'      => 'sometimes|integer|in:0,1',

            'business_hours' => 'sometimes|string|between:2,45',

            'status'         => 'sometimes|integer|in:0,1',

            'is_timing'      => 'sometimes|integer|in:0,1',

            'logo'           => 'sometimes|required|string|between:2,100',

            'sell_time'      => 'sometimes|integer'

        ],

        $this->message);

        

        if ($request->filled('status')) {



            $request->merge(['is_timing'=>$request->input('status')]);



            $ShopOpeningTimeExists = \App\Model\ShopOpeningTime::where('shop_id',$request->shop_id)->exists();



            $ShopOpeningTime = \App\Model\ShopOpeningTime::where('shop_id',$request->shop_id)

                                                ->whereNotNull('start_time')

                                                ->whereNotNull('end_time')

                                                ->whereNotNull('shop_id')

                                                ->whereTime('start_time','<',date('H:i:s'))

                                                ->whereTime('end_time','>',date('H:i:s'))

                                                ->first();



            if ($ShopOpeningTimeExists && ($request->status == 1 ) && empty($ShopOpeningTime)) {

                return respond(201,'营业时间不在当前范围！');

            }

        }





        $Shop  =  Shop::where('shop_id',$request->input('shop_id'))->first();



        foreach ($request->only(['shop_id','announcement','picked_up','business_hours','status','logo','sell_time','is_timing']) as $key => $value) 

        {

            if($request->filled($key))

            $Shop->$key  = $value;

        }



        if($Shop->save())

            return respond(200,'获取成功！',$Shop);

            return respond(201,'获取失败！');

    }



    /**

     * 获取店铺活动

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shop_prom(Request $request)

    {

        $this->validate($request,['shop_id' => 'required|integer|exists:shops,shop_id'],$this->message);

        $prom  = [

            'type0' => PromShop::where('shop_id',$request->shop_id)->where('type',0)->get(),

            'type1' => PromShop::where('shop_id',$request->shop_id)->where('type',1)->get(),

            'type2' => PromShop::where('shop_id',$request->shop_id)->where('type',2)->get(),

        ];

        return respond(200,'获取成功！',$prom);

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

            'type'         => 'required|in:pending,medium,good,bad'

        ],

        $this->message);



        $page_size = $request->input('page_size',15);

        $Comment   = Comment::with('comment_reply')->whereIn('shop_id',$this->shops);



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

            'good'    => Comment::whereIn('shop_id',$this->shops)->where('service_rank','>',3)->count(),

            'medium'  => Comment::whereIn('shop_id',$this->shops)->where('service_rank','=',3)->count(),

            'bad'     => Comment::whereIn('shop_id',$this->shops)->where('service_rank','<',3)->count(),

            'pending' => Comment::whereNotExists(function ($query){

                    $pr = env('DB_PREFIX');

                    $query->select(\DB::raw(1))

                          ->from('comment_reply')

                          ->whereRaw("{$pr}comment_reply.comment_id = {$pr}comment.comment_id");

                })->whereIn('shop_id',$this->shops)->count(),

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



        $Comment   = Comment::with(['comment_reply','order_goods','shop','order'])->where('comment_id',$request->comment_id)->first();

        return respond(200,'获取成功！',$Comment);

    }



    /**

     * 回复评论

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function reply_message(Request $request)

    {

        $this->validate($request,[

            'shop_id'      => 'required|integer|exists:shops,shop_id',

            'comment_id'   => 'required|integer|exists:comment,comment_id',

            'content'      => 'required|between:2,100'

        ],

        $this->message);

        $CommentReply          = new CommentReply();

        $CommentReply->content = $request->content;

        $CommentReply->shop_id = $request->shop_id;

        $CommentReply->comment_id = $request->comment_id;



        if($CommentReply->save())

            return respond(200,'回复成功！',$CommentReply);

            return respond(201,'回复失败！');

    }



    /**

     * 删除回复评论

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function destroy_reply_message(Request $request)

    {

        $this->validate($request,[

            'shop_id'      => 'required|integer|exists:shops,shop_id',

            'comment_id'   => 'required|integer|exists:comment,comment_id',

            'reply_id'     => 'required|integer|exists:comment_reply,reply_id',

        ],

        $this->message);

        $CommentReply =  CommentReply::where('reply_id',$request->reply_id)->where('comment_id',$request->comment_id)->first();

        if($CommentReply->delete())

            return respond(200,'删除成功！');

            return respond(201,'删除失败！');

    }



    /**

     * 店铺订单列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shop_order(Request $request)

    {

        $this->validate($request,[
            'page_size'    => 'sometimes|integer',
            'order_status' => 'required|in:0,1,2,3,4',
            'type'         => 'sometimes|required|integer|in:0,1,2',
            'search_keyword' => 'sometimes|required|string',
        ],$this->message);

        $status = [[0],[1,2],[4],[3,5,6],[4]];
        $orderbyraw  =  ($request->order_status == 1) ? 'order_status asc, ' : '';
        $orderbyraw  .=  ($request->order_status < 2) ? 'pay_time asc' : 'pay_time desc';
        $page_size = $request->input('page_size',15);
        $type  = $request->input('type',0);
        $search_keyword = $request->filled('search_keyword') ? $request->search_keyword : false;
        $order_status  = $request->filled('order_status') ? $request->order_status : 0;
        $is_after_sale = ($order_status == 4) ? true : false;

        $map       = [

            //全部订单

            [],

            //预定订单
            [
                'order_type'=>1,
                'delivery_type'=>0,
            ],

            //自提订单
            [
                'order_type'=>0,
                'delivery_type'=>1,
            ],
        ];

        $whereArr = $map[$type];

        $pr = env('DB_PREFIX');

        $Order     = Order::whereIn('shop_id',$this->shops)->

                            whereIn('order_status',$status[$request->order_status])->

                            where('pay_status',1)->//订单支付支付状态

                            when($is_after_sale, function ($query) use( $pr) {
                                return $query->whereRaw("exists( select 1 from {$pr}order_after_sale where {$pr}order.order_id = {$pr}order_after_sale.order_id )");
                            })->

                            when($whereArr, function ($query) use ($whereArr) { return $query->where($whereArr);})->

                            with(['area'=>function($query){return $query->select(['area_id','process_date']);},
                                  'order_shop',
                                  'order_shop_prom',
                                  'order_ps',
                                  'order_goods',
                                  'afterSale'
                              ])->

                            when($search_keyword, function ($query) use ($search_keyword) {

                                return $query->where(function ($query) use ($search_keyword) {

                                            $query->where('order_sn','like','%'.$search_keyword.'%')

                                                ->orWhere('consignee','like','%'.$search_keyword.'%')

                                                ->orWhere('mobile','like','%'.$search_keyword.'%')

                                                ->orWhere('address','like','%'.$search_keyword.'%')

                                                ->orWhere('day_num','like','%'.$search_keyword.'%');
                                });

                            })->
                            orderByRaw("{$orderbyraw}")->
                            simplePaginate($page_size);

        foreach($Order as $key => $value) 

        {

            $num = Order::where('shop_id',$value->shop_id)->

                        where('user_id',$value->user_id)->

                        where('created_at','<=',$value->created_at)->

                        where('pay_status',1)->

                        count();

            $value->user_cont = $num;

        }

        return respond(200,'获取成功！',$Order);
    }







    /**

     * 设置店铺订单出餐

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function appeared_meal(Request $request)

    {

        $this->validate($request, 

            [

                'order_id'     => 'required|integer|exists:order,order_id'

            ],

        $this->message);



        $Order = Order::where(['order_id'=>$request->order_id,'pay_status'=>1])->whereIn('shop_id',$this->shops)->first();



        if(empty($Order)){

            return respond(422,'该订单没有找到');

        }





        switch ($Order->order_status) {

            case '0':

                    return respond(422,'该订单未经商家确认');

                break;

            case '2':

                    return respond(422,'该订单已出餐了！');

                break;

            case '3':

                    return respond(422,'该订单已取消！');

                break;

            case '4':

                    return respond(422,'该订单已完成！');

                break;

            case '5':

                    return respond(422,'该订单已被拒绝！');

                break;

            case '6':

                    return respond(422,'该订单正在售后中...');

                break;

        }

        

        if($Order->order_status != 1 ){

            return respond(422,'非法操作！');

        }



        $Order->appeared_time  = date('Y-m-d H:i:s'); //商家出餐确认时间

        $Order->order_status   = 2; //修改订单状态已经出餐

        if($Order->save()){

            

            if(!empty($Order->ps_id))

            {

                $push_id = User::find($Order->ps_id)->value('push_id');

                push_for_jiguang($push_id ,"你有一笔订单({$Order->day_num})商家已出餐,请尽快取餐！",2);

            }

            event(new PushMessage($Order->order_id));

            return respond(200,'操作成功！',$Order);



        }else{

            

        }

        return respond(201,'操作失败！');

    }





    /**

     * 店铺历史订单

     * @return [type] [description]

     */

    public function history_order(Request $request)

    {

        $this->validate($request, 

            [

                'shop_id'      => 'sometimes|present|integer|exists:shops,shop_id',

                'start_time'   => 'required_with:end_time|date_format:Y-m-d',

                'end_time'     => 'required_with:start_time|date_format:Y-m-d',

                'page_size'    => 'sometimes|required|integer',

                'search_keyword' => 'sometimes|required|string',

            ],

        $this->message);



        $shop_id = $request->filled('shop_id') ? $request->shop_id : false;

        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $start_time = $request->filled('start_time') ? $request->start_time : false;

        $end_time = $request->filled('end_time') ? $request->end_time : false;

        $search_keyword = $request->filled('search_keyword') ? $request->search_keyword : false;

        $select_date = ($start_time && $end_time) ? true : false;

        $shops = $this->shops;

        if ($shop_id) {
            if (!in_array($shop_id, $shops)) {
                return respond(201,'非法操作！');
            }
        }
        // DB::connection()->enableQueryLog();

        $Order = Order::whereBetween('order_status',[3,6])
                        ->where('pay_status',1)
                        ->when($shop_id , function ($query) use ($shop_id) {
                            return $query->where('shop_id',$shop_id);
                        },function ($query) use($shops) {
                            return $query->whereIn('shop_id',$shops);
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
                        ->when($select_date, function ($query) use ($start_time,$end_time) {
                            return $query->whereBetween('created_at', [$start_time.' 00:00:00', $end_time.' 23:59:59']);
                        })

                        ->with(['order_goods','order_shop'])

                        ->orderByDesc('created_at')

                        ->simplePaginate($page_size); 

        // $logs = DB::getQueryLog();

        // \Illuminate\Support\Facades\Log::channel('print')->info($logs);
        return respond(200,'操作成功！',$Order);
    }



    /**

     * 店铺商品统计

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function goods_statistics(Request $request)

    {



        $rules = [ 

            // 'type'         => 'required|in:date,month,year',

            'page_size'    => 'sometimes|required|integer',

            'orderby'      => 'required|in:total_num,total_amount,conut_stamp'

        ];



        if ($request->filled('shop_id') && !empty($request->input('shop_id'))) {



            $rules['shop_id'] = 'required|integer|exists:shops,shop_id';

        }

        $page_size = $request->input('page_size',15);



        $this->validate($request,$rules,$this->message);

        

        $shops = ($request->filled('shop_id')) ? [$request->shop_id] : $this->shops;



        $shops_str = join(',', $shops);



        $pr = env('DB_PREFIX');


        $OrderGoods = OrderGoods::leftJoin('order','order_goods.order_id', '=','order.order_id')

                            ->leftJoin('goods','order_goods.goods_id', '=','goods.goods_id')

                            ->whereRaw("{$pr}order_goods.shop_id in ($shops_str) and {$pr}order.order_status = 4 and {$pr}order.shop_id in ($shops_str)")

                            ->selectRaw("{$pr}order_goods.goods_id,

                                sum({$pr}order_goods.goods_num) as total_num,

                                sum({$pr}order_goods.goods_amount) as total_amount,

                                sum({$pr}order_goods.stamp) as conut_stamp,

                                {$pr}goods.details_figure,{$pr}goods.title")

                            ->groupBy('order_goods.goods_id')

                            ->orderByDesc($request->orderby)
                            ->get();

                        return respond(200,'获取成功！',$OrderGoods);

    }



    /**

     * 店铺商品统计

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function goodsCount(Request $request)

    {



        $rules = [ 

            // 'type'         => 'required|in:date,month,year',

            'page_size'    => 'sometimes|required|integer',

            'orderby'      => 'required|in:total_num,total_amount,conut_stamp'

        ];



        if ($request->filled('shop_id') && !empty($request->input('shop_id'))) {



            $rules['shop_id'] = 'required|integer|exists:shops,shop_id';

        }

        

        $page_size = $request->input('page_size',15);



        $this->validate($request,$rules,$this->message);

        

        $shops = ($request->filled('shop_id')) ? [$request->shop_id] : $this->shops;



        $shops_str = join(',', $shops);



        $pr = env('DB_PREFIX');

    
        $OrderGoods = OrderGoods::leftJoin('order','order_goods.order_id', '=','order.order_id')

                            ->leftJoin('goods','order_goods.goods_id', '=','goods.goods_id')

                            ->whereRaw("{$pr}order_goods.shop_id in ($shops_str) and {$pr}order.order_status = 4 and {$pr}order.shop_id in ($shops_str)")

                            ->selectRaw("{$pr}order_goods.goods_id,

                                sum({$pr}order_goods.goods_num) as total_num,

                                sum({$pr}order_goods.goods_amount) as total_amount,

                                sum({$pr}order_goods.stamp) as conut_stamp,

                                {$pr}goods.details_figure,{$pr}goods.title")

                            ->groupBy('order_goods.goods_id')
                            ->orderByDesc($request->orderby)
                            ->simplePaginate($page_size);

        return respond(200,'获取成功！',$OrderGoods);

    }



    /**

     * 店铺收益统计

     * @param  Request $request [description]

     * @return [type]           [description] 

     * 

     */

    public function earningsCount(Request $request)

    {

        $rules = [ 

            'type' => 'required|in:date,month,year',

            'page_size' => 'sometimes|required|integer'

        ];



        if ($request->filled('shop_id') && !empty($request->input('shop_id'))) {



            $rules['shop_id'] = 'required|integer|exists:shops,shop_id';

        }



        $page_size = $request->input('page_size',15);



        $this->validate($request,$rules,$this->message);

        

        $shops = (!empty($request->input('shop_id'))) ? [$request->shop_id] : $this->shops;

        

        $str = [

            'date'  => '%Y-%m-%d',

            'month' => '%Y-%m',

            'year'  => '%Y',

        ];



        $format = $str[$request->type];



        $Order  = Order::where('order_status',4)

                    ->whereIn('shop_id',$shops)

                    ->selectRaw("sum(shop_amount) as total_amount,

                        count(shop_id) as conut_shop,

                        date_format(created_at,'{$format}') as date")

                    ->groupBy(\DB::Raw("{$request->type}(created_at)"))

                    ->orderByDesc('created_at')

                    ->simplePaginate($page_size);



        return respond(200,'获取成功！',$Order);

    }







    /**

     * 店铺收益统计

     * @param  Request $request [description]

     * @return [type]           [description] 

                        {$request->type}(created_at) as date,

     * 

     */

    public function earnings_statistics(Request $request)

    {

        $rules = [ 'type' => 'required|in:date,month,year'];



        if ($request->filled('shop_id') && !empty($request->input('shop_id'))) {



            $rules['shop_id'] = 'required|integer|exists:shops,shop_id';

        }



        $this->validate($request,$rules,$this->message);

        

        $shops = (!empty($request->input('shop_id'))) ? [$request->shop_id] : $this->shops;

        

        $str = [

            'date'  => '%Y-%m-%d',

            'month' => '%Y-%m',

            'year'  => '%Y',

        ];



        $format = $str[$request->type];



        $Order  = Order::where('order_status',4)

                    ->whereIn('shop_id',$shops)

                    ->selectRaw("sum(shop_amount) as total_amount,

                        count(shop_id) as conut_shop,

                        date_format(created_at,'{$format}') as date")

                    ->groupBy(\DB::Raw("{$request->type}(created_at)"))

                    ->orderByDesc('created_at')

                    ->get();

                    

        return respond(200,'获取成功！',$Order);

    }



    /**

     * 店铺一周收益统计

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function week_statistics(Request $request)

    {



        $rules = [ 'days' => 'sometimes|required|integer|min:7'];



        if ($request->filled('shop_id')) {

            $rules['shop_id'] = 'required|integer|exists:shops,shop_id';

        }



        $this->validate($request,$rules,$this->message);

        

        $shops = ($request->filled('shop_id')) ? [$request->shop_id] : $this->shops;

        $days  = ($request->filled('days')) ? $request->days - 1 : 6;





        $Order = Order::where('order_status',4)->whereIn('shop_id', $shops)

                    ->whereBetween('created_at',[date("Y-m-d 00:00:00",strtotime("-{$days} day")),date("Y-m-d 23:59:59")])

                    ->selectRaw('sum(shop_amount) as total_amount,date(created_at) as day')

                    ->groupBy('day')

                    ->get();



        $date = [];



        for($i=0; $i<=$days; $i++)

        {

            $day = $days-$i;

            $date[date("Y-m-d",strtotime("-{$day} day"))] = 0;

        }



        $date[date("Y-m-d")] = 0;

        foreach($Order as $k => $v)

        {

            $date[$v->day] = $v->total_amount;

        }



        $statistics = [];

        foreach($date as $key => $value)

        {

            $statistics[] = [ 

                'total_amount'=>$value,

                'day'  =>$key,

            ];

        }

        array_multisort(array_column($statistics,'day'),SORT_ASC,$statistics);

        

        return respond(200,'获取成功！',$statistics);

    }



    

     /**

     * 商家自主配送

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function take_delivery(Request $request)

    {
        $this->validate($request,  [
                'order_id' => 'required|integer|exists:order,order_id',
                'distribution_status' => 'required|integer|in:1,2,3,4',
            ],$this->message);

        $where = [
                'order_id'   => $request->order_id,
                'pay_status' => 1,
        ];

        $Order = Order::where($where)->whereIn('order_status',[1,2])->first();

        if(empty($Order->order_id)) {
            return respond(201,'非法操作！');
        }

       if (($Order->distribution_status['key'] != 5)) {
            //骑手接单   && 商家已确认    超过20分钟  商家才能自主配送
            //骑手未接单 && 商家已经确认   超过10分钟  商家才能自主配送
            if(!empty($Order->ps_id)) {
                if((time() - strtotime($Order->rush_time)) < 300) return respond(201,'该订单不满足自主配送！');
            }else{

                if((time() - strtotime($Order->ensure_time)) < 300)  return respond(201,'该订单不满足自主配送！');
            }

            $Order->distribution_status = $request->distribution_status;
            $Order->save();
       }

        if((boolean)event(new ClearingAccount($Order))) {
            return respond(200,'操作成功！');
        }

        Order::where($where)->update(['distribution_status'=>0]);
        return respond(201,'操作失败！');
    }



    /**

     * 店铺取消订单

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function cancel_order(Request $request)

    {

        $this->validate($request, 

            [

                'shop_id'      => 'required|integer|exists:shops,shop_id',

                'order_id'     => 'required|integer|exists:order,order_id'

            ],

        $this->message);



        $Order = Order::where('order_id',$request->order_id)

                        ->where('shop_id',$request->shop_id)

                        ->whereIn('order_status',[1,2])

                        ->where('shipping_status',0)

                        ->first();



        if(empty($Order)) return respond(201,'该订单不能取消');



        if((boolean)event(new CancelOrder($request->order_id,3,2))) {



            event(new PushMessage($Order->order_id));

            return respond(200,'取消成功！');

        }



            return respond(201,'取消失败！');

    }





    /**

     * 接单

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function receiving_order(Request $request)

    {

        $this->validate($request, 

            [

                'order_id'     => 'required|integer|exists:order,order_id',

                'order_status' => 'required|integer|in:1,5',

            ],

        $this->message);



        $findWhere = [

            'order_id' => $request->order_id,

            'pay_status' => 1,

            'order_status' => 0

        ];



        $Order = Order::where($findWhere)->whereIn('shop_id',$this->shops)->first();



        if(empty($Order))  return respond(422,'该订单没有找到');



        switch ($request->order_status) {

            case '1':

               

                if (!((boolean)event(new CalculateBrokerage($Order)))) {

                    return respond(201,'操作失败！');

                }



                break;

            case '5':



                if(!((boolean)event(new CancelOrder($request->order_id,5)))) {

                    return respond(201,'拒绝失败！');

                }

                

                break;

        }



        event(new PushMessage($request->order_id));

        

        return respond(200,'操作成功！');        

    }



    /**

     * 检查店铺是否绑定打印机

     * @return [type] [description]

     */

    public function exists_printr(Request $request)

    {

        $this->validate($request, 

            [

                'shop_id' => 'required|integer|exists:shops,shop_id',

            ],

        $this->message);



        $Shop = Shop::where('shop_id',$request->shop_id)->with('printer')->select(['shop_id','printer_id'])->first();



        return respond(200,'获取成功！',$Shop);

    }



    /**

     * 设置住店铺

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function main_shop(Request $request)

    {

        $this->validate($request, 

            [

                'shop_id' => 'required|integer|exists:shops,shop_id',

            ],

        $this->message);



        Shop::whereIn('shop_id',$this->shops)->update(['is_main'=>0]);

        Shop::where('shop_id',$request->shop_id)->update(['is_main'=>1]);

        

        return respond(200,'设置成功！');

    }



    /**

     * [withdrawalNeedData 商户提现页面所需数据]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function withdrawalNeedData()

    {



        $result = Shop::whereIn('shop_id',$this->shops)->orderByDesc('is_main')->select(['shop_id','balance','shop_name','logo','is_main'])->get();



        $data = [

            'total_balance' =>  !empty($result) ? round($result->sum('balance'),2):0,

            'shops'         =>  empty($result)?[]:$result,

            'alipay_number' =>  \Auth::user()->alipay_number

        ];

        

        return respond(200,'获取成功！',$data);

    }



    /**

     * 商户提现列表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function withdrawal_list(Request $request)

    {

        $this->validate(

            $request,

            [

              'page_size'     => 'sometimes|required|integer'

            ],

        $this->message);



        $page_size = $request->input('page_size',15);



        $result    = Withdrawal::where('user_id',$this->user_id)->whereIn('client_type',[1,3])->orderByDesc('id')->simplePaginate($page_size);



        if($result)

            return respond(200,'获取成功！',$result);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 商户收支记录表

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function merBill(Request $request)

    {

        $this->validate(

            $request,

            [

                'page_size'  => 'sometimes|required|integer',

            ],

        $this->message);



        $page_size = $request->input('page_size',15);

        

        $MerBill   = MerBill::whereIn('shop_id',$this->shops)

                                ->with(['order'=>function($query){

                                    $query->select(['order_id','order_sn','day_num']);

                                }])

                                ->with(['shop'=>function($query){

                                    $query->select(['shop_id','shop_name']);

                                }])

                                ->orderByDesc('created_at')->simplePaginate($page_size);



        if($MerBill)

            return respond(200, '获取成功！',$MerBill);

        else

            return respond(201, '获取失败！');

    }



    /**

     * 商家公告

     * @return [type] [description]

     */

    public function notice()

    {

        $Data = Article::where('cat_id',3)->where('area_id',\Auth::user()->area_id)->get();

        if($Data)

            return respond(200,'获取成功！',$Data);

        else

            return respond(201,'获取失败！');

    }



    /**

     * 订单自提确认收货

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function confirmReceiving(Request $request)

    {



        $this->validate($request,['order_id'=>'required|integer|exists:order,order_id'],$this->message);



        $default_where = [

            'order_id'        => $request->order_id,

            'pay_status'      => 1,

            'delivery_type'   => 1,

            'order_status'    => 2,

            'shipping_status' => 0,

        ];



        $Order = Order::whereIn('shop_id',$this->shops)->where($default_where)->first();



        if (empty($Order)) return respond(201,'该订单还不满足完成条件！');

        

        if((boolean)event(new ClearingAccount($Order))) {



            return respond(200,'操作成功！');

        }



            return respond(201,'操作失败！');

    }



    

    /**

     * [identificationPhoto 编辑店铺证件照]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function editIdentificationPhoto(Request $request)

    {

        $this->validate($request, 

            [

                'shop_id' => 'required|integer|exists:shops,shop_id',

                'identification_photo' => 'required|json',

            ],

        $this->message);



        $result = Shop::where('shop_id',$request->shop_id)->update(['identification_photo'=>$request->identification_photo]);



        if($result)

            return respond(200,'操作成功！');

        else

            return respond(201,'操作失败！');

    }





    /**

     * [shopOpeningTime 设置店铺营业时间]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shopOpeningTime(Request $request)

    {

        $this->validate($request, 

        [

            'shop_id' => 'required|integer|exists:shops,shop_id',

            'times'   => 'required|array',

            'times.*.start_time' => 'required|string|date_format:H:i:s',

            'times.*.end_time' => 'required|string|date_format:H:i:s',

            'times.*.time_id' => 'sometimes|present|numeric'

        ],$this->message);



        $shop_id = $request->shop_id;



        try {

            

            foreach ($request->input('times') as  $value) {



                $strat_time = $value['start_time'];

                $end_time   = $value['end_time'];

                $time_id    = empty($value['time_id']) ? 0:(int)$value['time_id'];



                if (strtotime($strat_time) >= strtotime($end_time)) return respond(422,'开始时间不能大于结束时间！');



                if (!empty($time_id) && empty(DB::table('shop_opening_times')->where('time_id',$time_id)->count()))  return respond(422,'time_id不存在！');

                

                $row = ShopOpeningTime::where('shop_id',$shop_id)

                                        ->when($time_id, function ($query) use ($time_id) { return $query->where('time_id', '<>',$time_id); })

                                        ->where('start_time','<=',$strat_time)

                                        ->where('end_time','>=',$strat_time)

                                        ->orWhere(function ($query) use ($end_time,$shop_id,$time_id) {

                                            $query->where('shop_id',$shop_id)->whereTime('start_time','<=',$end_time)->whereTime('end_time','>=',$end_time)->when($time_id, function ($query) use ($time_id) { return $query->where('time_id', '<>',$time_id); });

                                        })->count();



                if ($row > 0) return respond(201,'时间段不能重复！');



                $data = [

                    'shop_id' => $shop_id,

                    'start_time' => $value['start_time'],

                    'end_time'   => $value['end_time'],

                    'updated_at' => date('Y-m-d H:i:s')

                ];



                if(!empty($time_id)) {

                    DB::table('shop_opening_times')->where('time_id',$time_id)->update($data);

                }else{

                    $data['created_at'] = date('Y-m-d H:i:s');

                    DB::table('shop_opening_times')->insert($data);

                }

            }



            return respond(200,'操作成功！');



        } catch (Exception $e) {



            return respond(201,'操作失败！');

        }



    }



    /**

     * [shopOpeningTimeList 设置店铺营业时间列表]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shopOpeningTimeList(Request $request)

    {

        $this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);

        

        $ShopOpeningTime = ShopOpeningTime::where('shop_id',$request->shop_id)->get();



        return respond(200,'获取成功！',$ShopOpeningTime);

    }

    

    /**

     * [shopOpeningTimeDelete 删除店铺营业时间]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function shopOpeningTimeDelete(Request $request)

    {

        $this->validate($request, 

        [

            'shop_id' => 'required|integer|exists:shops,shop_id',

            'time_id' => 'required|integer|exists:shop_opening_times,time_id'

        ],$this->message);



        $ShopOpeningTime = ShopOpeningTime::where(['shop_id'=>$request->shop_id,'time_id'=>$request->time_id])->first();

        if ($ShopOpeningTime->delete()) {

            return respond(200,'操作成功！');

        } else {

            return respond(200,'操作成功！');

        }

    }



    public function statisticsInfo(Request $request)

    {

        $rules =  [

            'shop_id' => 'sometimes|present|integer',

            'type'    => 'required|string|in:date,month,year'

        ];



        $format = '%Y-%m-%d';

       

        switch ($request->type) {

            case 'date':

                $rules['format_date'] = 'required|string|date_format:Y-m-d';

                $format = '%Y-%m-%d';

                break;

            case 'month':

                $rules['format_date'] = 'required|string|date_format:Y-m';

                $format = '%Y-%m';

                break;

            case 'year':

                $rules['format_date'] = 'required|string|date_format:Y';

                $format = '%Y';

                break;

            default:

                $rules['format_date'] = 'required|string|date_format:Y-m-d';

                $format = '%Y-%m-%d';

                break;

        }





        $this->validate($request,$rules,$this->message);



        $format_date = $request->format_date;

        $shops = [];

        $request->filled('shop_id') ? array_push($shops, $request->shop_id) : $shops = $this->shops;



        $default_arr = [

            'deduction_amount'=>0,

            'first_amount'=>0,

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

            'goods_rake_amount'=>0,

            'date'=> $format_date

        ];



        $result_arr = [];

        

        DB::statement('SET SESSION group_concat_max_len=20000000000');



        $Order  = \App\Model\Order::where('order_status',4)

                        ->whereIn('shop_id',$shops)

                        ->selectRaw("

                            GROUP_CONCAT(order_id) as order_ids,

                            count(order_id)  as order_num,

                            sum(goods_price) as goods_price_amount,

                            sum(shop_amount) as shop_amount,

                            sum(service_charge) as service_amount,

                            sum(order_amount) as pay_amount,

                            date_format(created_at,'{$format}') as date")

                        ->groupBy('date')

                        ->havingRaw("date = '{$format_date}'")

                        ->first();



        if (empty($Order)) {

            return respond(200,'获取成功！',$default_arr);

        }



        $pr = env('DB_PREFIX');



        $order_bear = DB::table('order')

                        ->whereIn('order.order_id',explode(',',$Order->order_ids))

                        ->selectRaw("

                            IFNULL((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 0),0) as deduction_amount,



                            IFNULL((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 2),0) as first_amount,



                           {$pr}order_ratio.rake,

                           {$pr}order_ratio.custom_ratio,

                           {$pr}order_ratio.custom_delivery_ratio,

                           {$pr}order_ratio.first_ratio,

                           {$pr}order_ratio.full_ratio,

                           {$pr}order_ratio.merchant_ratio,

                           {$pr}order_ratio.integral_ratio,

                           {$pr}order_ratio.coupon_ratio,

                           {$pr}order.packing_expense,

                           {$pr}order.delivery_cost,

                           {$pr}order.floor_amount,

                           {$pr}order.goods_price")

                        ->join('order_ratio','order.order_id', '=', 'order_ratio.order_id')

                        ->get()->toArray();



        $goods_price_platform = [];

        $packing_expense_shop = [];

        $delivery_cost_shop   = [];

        $deduction_amount   = [];

        $first_amount   = [];





        foreach ($order_bear as $v) {



           $goods_price_platform[] = round(($v->rake / 100) * $v->goods_price,2);

           $packing_expense_shop[] = round(($v->custom_ratio / 100) * $v->packing_expense,2);

           $delivery_cost_shop[]   = round(($v->custom_delivery_ratio / 100) * $v->delivery_cost,2);  

           $deduction_amount[]     = round(($v->deduction_amount / 100) * $v->full_ratio,2);            

           $first_amount[]         = round(($v->first_amount / 100) * $v->first_ratio,2);            

        }



        $result_arr['goods_rake_amount']  = round(array_sum($goods_price_platform),2);

        $result_arr['packing_amount']     = round(array_sum($packing_expense_shop),2);

        $result_arr['delivery_amount']    = round(array_sum($delivery_cost_shop),2);

        $result_arr['deduction_amount']   = round(array_sum($deduction_amount),2);

        $result_arr['first_amount']       = round(array_sum($first_amount),2);

        $result_arr['goods_price_amount'] = $Order->goods_price_amount;

        $result_arr['service_amount']     = $Order->service_amount;

        $result_arr['order_num']          = $Order->order_num;

        $result_arr['total_amount']       = $Order->shop_amount;

        $result_arr['pay_amount']         = $Order->pay_amount;





        return respond(200,'获取成功！',array_merge($default_arr,$result_arr));

    }

    

    /**

     * [countInfo 统计详情]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function countInfo(Request $request)

    {

        $rules =  [

            'shop_id' => 'sometimes|present|integer',

            'type'    => 'required|string|in:date,month,year'

        ];



        $format = '%Y-%m-%d';

       

        switch ($request->type) {

            case 'date':

                $rules['format_date'] = 'required|string|date_format:Y-m-d';

                $format = '%Y-%m-%d';

                break;

            case 'month':

                $rules['format_date'] = 'required|string|date_format:Y-m';

                $format = '%Y-%m';

                break;

            case 'year':

                $rules['format_date'] = 'required|string|date_format:Y';

                $format = '%Y';

                break;

            default:

                $rules['format_date'] = 'required|string|date_format:Y-m-d';

                $format = '%Y-%m-%d';

                break;

        }





        $this->validate($request,$rules,$this->message);



        $format_date = $request->format_date;

        $shops = [];

        

        $request->filled('shop_id') ? array_push($shops, $request->shop_id) : $shops = $this->shops;



        DB::statement('SET SESSION group_concat_max_len=20000000000');



        $Order  = \App\Model\Order::where('order_status',4)

                        ->whereIn('shop_id',$shops)

                        ->selectRaw("

                            GROUP_CONCAT(order_id) as order_ids,

                            count(order_id)  as order_num,

                            sum(goods_price) as goods_price_amount,

                            sum(shop_amount) as shop_amount,

                            sum(service_charge) as service_amount,

                            sum(order_amount) as pay_amount,

                            sum(IF(`distribution_status` > 0 , `horseman_amount`, 0)) as shop_delivery_amount,


                            date_format(created_at,'{$format}') as date")

                        ->groupBy('date')

                        ->havingRaw("date = '{$format_date}'")

                        ->first();



        if (empty($Order)) { return respond(200,'获取成功！',[]);}





        $pr = env('DB_PREFIX');



        $order_bear = \App\Model\Order::whereIn('order.order_id',explode(',',$Order->order_ids))

                                        ->selectRaw("

                                            IFNULL((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 0),0) as deduction_amount,

                                            IFNULL((select deduction_money from {$pr}order_shop_prom where {$pr}order_shop_prom.order_id = {$pr}order.order_id and {$pr}order_shop_prom.prom_type = 2),0) as first_amount,

                                           {$pr}order_ratio.rake,

                                           {$pr}order_ratio.custom_ratio,

                                           {$pr}order_ratio.custom_delivery_ratio,

                                           {$pr}order_ratio.first_ratio,

                                           {$pr}order_ratio.full_ratio,

                                           {$pr}order_ratio.merchant_ratio,

                                           {$pr}order_ratio.integral_ratio,

                                           {$pr}order_ratio.coupon_ratio,

                                           {$pr}order.packing_expense,

                                           {$pr}order.delivery_cost,

                                           {$pr}order.floor_amount,

                                            {$pr}order.platform_rake,

                                           {$pr}order.goods_price")

                                        ->join('order_ratio','order.order_id', '=', 'order_ratio.order_id')

                                        ->get();



        $goods_price_platform = 0;

        $packing_expense_shop = 0;

        $delivery_cost_shop   = 0;

        $deduction_amount     = 0;

        $first_amount         = 0;





        foreach ($order_bear as $v) {



           $goods_price_platform += $v->platform_rake;
           // $goods_price_platform += (($v->rake / 100) * $v->goods_price);


           $packing_expense_shop += (($v->custom_ratio / 100) * $v->packing_expense);

           $delivery_cost_shop   += (($v->custom_delivery_ratio / 100) * $v->delivery_cost);  

           $deduction_amount     += (($v->deduction_amount / 100) * $v->full_ratio);            

           $first_amount         += (($v->first_amount / 100) * $v->first_ratio);            

        }



        $Order->goods_rake_amount  = $goods_price_platform;

        $Order->packing_amount     = $packing_expense_shop;

        $Order->delivery_amount    = $delivery_cost_shop;

        $Order->deduction_amount   = $deduction_amount;

        $Order->first_amount       = $first_amount;



        $array_name = [

            'order_num'=>'订单量',

            'goods_price_amount'=>'商品销售额',

            'first_amount'=>'首单优惠',

            'deduction_amount'=>'满减优惠',

            'service_amount'=>'手续费',

            'delivery_amount'=>'配送费',

            'packing_amount'=>'餐盒费',

            'goods_rake_amount'=>'商品抽成',

            'shop_amount'=>'商家收益',
            
            'shop_delivery_amount' => '自主配送费'


        ];



        $data =  [];



        foreach ($Order->toArray() as $k => $v) {

            if (array_key_exists($k,$array_name) && isset($v)) {

                array_push($data , ['name'=> $array_name[$k],'value'=> round($v,2)]);

            }

        }



        return respond(200,'获取成功！',$data);

    }

    /**
     * [agreeOrRefuseRefund 商家售后退款]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function agreeOrRefuseRefund(Request $request)
    {
        $this->validate($request, [
            'status' => 'required|integer|in:1,2',
            'id' => 'required|integer|exists:order_after_sale,id',
            'shop_id' => 'required|integer|exists:shops,shop_id',
            'order_id' => 'required|integer|exists:order,order_id',
            'refuse_cause' => 'required_if:status,2|string|between:1,100'
        ],$this->message);


        $Order = Order::where('order_id',$request->order_id)->where('order_status',4)->select(['pay_code','transaction_id','order_amount','user_money'])->first();

        if (empty($Order))  {
            return respond(201,'该订单不满足售后条件！');
        }

        DB::beginTransaction(); //开启事务


        $OrderAfterSale = \App\Model\OrderAfterSale::where('id',$request->id)->where('status',0)->where('shop_id',$request->shop_id)->where('order_id',$request->order_id)->lockForUpdate()->first();

        if (empty( $OrderAfterSale )) {
            return respond(201,'该订单不满足售后条件！');
        }
        
        $OrderAfterSale->status = $request->status;
        $OrderAfterSale->action_desc = '商家操作';

        if ($request->filled('refuse_cause')) {
            $OrderAfterSale->refuse_cause = $request->refuse_cause;
        }

        $out_refund_no = date('YmdHis').uniqid(); 

        if ($request->status == 1 && $OrderAfterSale->money > 0 ) 
        {


            if (Shop::where('shop_id',$request->shop_id)->value('balance') < $OrderAfterSale->money) 
            {
                return respond(201,'该店铺余额不足！');
            }
            
            switch ($Order->pay_code) {
                case 'alipayApp':
                case 'alipayMobile':
   
                    $result = $this->alipayRefund($Order->transaction_id,$Order->order_amount,$OrderAfterSale->money,$out_refund_no);

                    if ($result) {
                        $OrderAfterSale->out_refund_no = $out_refund_no;
                    }else{
                        return respond(201,'操作失败！');
                    }
                    
                    break;
                case 'wechatApp':
                case 'weixin':

                    $result = $this->wechatRefund($Order->transaction_id,$Order->order_amount,$OrderAfterSale->money,$out_refund_no);

                    if ($result) {
                        $OrderAfterSale->out_refund_no = $out_refund_no;
                    }else{
                        return respond(201,'操作失败！');
                    }
                    
                    break;

                case 'deduction':
                        //退余额
                        if ($Order->user_money > 0 && ($Order->user_money >= $OrderAfterSale->money)) {
                            $AccountLog = new \App\Model\AccountLog();
                            $increment_moeny = \App\Model\User::where('user_id',$Order->user_id)->increment('moeny',$OrderAfterSale->money);
                            $AccountLog->desc = "余额抵扣返还(售后)";
                            $AccountLog->user_money = "{$OrderAfterSale->money}";
                            $AccountLog->user_id = $OrderAfterSale->user_id;
                            $AccountLog->order_id = $OrderAfterSale->order_id;

                            if($increment_moeny && $AccountLog->save()) {
                                $OrderAfterSale->out_refund_no = $out_refund_no;
                            }else{
                                DB::rollBack();//事务回滚
                                return respond(201,'操作失败！');
                            }
                        }
                    break;
            }

            //记录售后流水金额
            $mer_bill =  [
                'shop_id'  => $request->shop_id,
                'order_id' => $request->order_id,
                'money'=> $OrderAfterSale->money,
                'desc' => '商户售后退款',
                'type' => 6,
                'created_at' => date('Y-m-d H:i:s')
            ];

            //扣除商家余额 
            //记录售后流水金额
            $change_balance = Shop::where('shop_id',$request->shop_id)->lockForUpdate()->decrement('balance',$OrderAfterSale->money);

            if (!($change_balance && MerBill::insert($mer_bill)))
            {

                DB::rollBack();//事务回滚

                return respond(201,'操作失败！');
            }
        }

        if ($OrderAfterSale->save()) 
        {
            DB::commit();//提交事务
            return respond(200,'操作成功！');
        }

        DB::rollBack();//事务回滚

        return respond(201,'操作失败！');
    }

}
