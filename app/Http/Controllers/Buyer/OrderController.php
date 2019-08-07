<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Cart,Goods,Spec,SpecItem,UserAddress,Order,Coupon,Comment,OrderGoods,AccountLog,RefundOrder, CouponList,AreaDelivery, Area,PromShop,Config, DeliveryGroup,Delivery};
use App\Http\Requests\CartRequest;
use App\Http\Controllers\Logic\OrderLogicController;
use App\Model\Buyer\Shop;
use App\Events\{CancelOrder,ClearingAccount, PushMessage, CalculateBrokerage};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Traits\GoodsStock;



class OrderController extends Controller
{
    use GoodsStock;
    protected $user_id;
    protected $user_type;


    public function __construct(Request $request)
    {
        $this->user_id = \Auth::id();
        $this->user_type = \Auth::user()->type;
    }

    /**
     * 提交订单
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function submit_order(Request $request)
    {
        $this->validate($request, 
        [
            'shop_id' => 'required|integer|exists:shops,shop_id',
            'goods_row' => 'required|array',
            'goods_row.*.goods_id'  => 'required|integer|exists:goods,goods_id',
            'goods_row.*.goods_num' => 'required|integer',
            'goods_row.*.spec_key'  => 'sometimes|present|string',
            'goods_row.*.is_discount' => 'sometimes|present|integer|in:0,1',
            'goods_row.*.discount_num' => 'sometimes|present|integer',
        ],
        $this->message);

        if ($request->filled('user_red_packet_id') && $request->filled('cid')) {
            return respond(422,"优惠卷和红包只能使用其中一个");
        }

        $shop_id = $request->shop_id;

        $shop = Shop::find($shop_id);

        if(!$shop->status) {

            return respond(201,'该店铺还在休息中...');
        }

        //必点商品
        $where = ['shop_id'=>$shop_id,'is_required'=>1,'shelves_status' => 1];
        
        if (Goods::where($where)->select('goods_id','title')->exists()) 
        {
            $requiredSelectGoods = Goods::where($where)->select('goods_id','title')->get();

            $select_goods = array_column($request->goods_row ,'goods_id');

            foreach ($requiredSelectGoods  as  $goods) {
                if (array_search($goods->goods_id, $select_goods) === false) 
                {
                    return respond(422,'该店铺的“'.$goods->title .'”是必选商品！');
                }
            }
        }

        $Logic = new OrderLogicController($request);

        if($order = $Logic->createOrder()) {

            $this->changeStockNum($order->order_id,'decrement');

            //应付款为0 、已支付、和商家开启自动接单然后执行商家确认订单事件

            if($order->order_amount <= 0 && $order->pay_status) {

                if ($shop->auto_order) {
                    event(new CalculateBrokerage($order));
                }

                $url = env('BACKEND_DN').'mobile/api/IntegralBalancePayCallback';

                curl_request($url,['order_id'=>$order->order_id,'shop_id'=>$order->shop_id]);
            }

            return respond(200,'提交成功！',$order);
        }

            return respond(201,'提交失败！');
    }

    /**
     * 确认订单
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function confirm_order(Request $request)
    {
              $this->validate($request,
            [

                'shop_id'   =>'required|integer|exists:shops,shop_id',

                'address_id'=>'sometimes|present|exists:user_addresses,address_id',

                'goods_row' =>'required|array',

                'goods_row.*.goods_id'  => 'required|integer|exists:goods,goods_id',

                'goods_row.*.goods_num' => 'required|integer',

                'goods_row.*.spec_key'  => 'sometimes|present|string',

                'goods_row.*.is_discount' => 'sometimes|present|integer|in:0,1',

                'goods_row.*.discount_num' => 'sometimes|present|integer',
            ],

            $this->message);

            if ($request->filled('user_red_packet_id') && $request->filled('cid')) {
                return respond(422,"优惠卷和红包只能使用其中一个");
            }
            
            $shop_id = $request->shop_id;

            $goods_row = $request->goods_row;

            $address_id = ($request->filled('address_id')) ? $request->address_id : '';


            //必点商品
            $where = ['shop_id'=>$shop_id,'is_required'=>1,'shelves_status' => 1];

            if (Goods::where($where)->select('goods_id','title')->exists()) 
            {
                $requiredSelectGoods = Goods::where($where)->select('goods_id','title')->get();

                $select_goods = array_column($goods_row ,'goods_id');

                foreach ($requiredSelectGoods  as $key => $goods) {
                    if (array_search($goods->goods_id, $select_goods) === false) 
                    {
                       return respond(422,'该店铺的“'.$goods->title .'”是必选商品！');
                    }
                }
            }

            //根据购物车查询商品及商品的规格

            $Goods = [];

            foreach ($goods_row as $key => $value) {

                $where = [

                    'goods_id' => $value['goods_id'],

                    'shop_id'  => $shop_id,

                ];

                $spec_key = empty($value['spec_key']) ? '' : $value['spec_key'];

                $GoodsRow = Goods::where($where)->with(['itme' => function($query) use ($spec_key) {

                                        $query->whereIn('item_id',array_filter(explode('_',$spec_key)));

                                    }])->first();

                if (empty($GoodsRow)) {
                    return respond(422,"非法操作！");
                    break;
                }

                if ($GoodsRow->shelves_status != 1) {
                    return respond(422,"{$GoodsRow->title} 已下架！");
                    break;
                }

                //购买数量限制
                if ($GoodsRow->purchase_quantity > 0 ) {
                    if ($value['goods_num'] > $GoodsRow->purchase_quantity) {
                        return respond(422,"{$GoodsRow->title} 超过购买数量限制！");
                        break;
                    }
                }

                if ($GoodsRow->stock_num > -1) {
                    if (($GoodsRow->stock_num - $value['goods_num']) < 0) {
                        return respond(422," {$GoodsRow->title} 库存不足！");
                        break;
                    }
                }

                $GoodsRow['goods_num'] = $value['goods_num'];

                $Goods[] = $GoodsRow;

            }

            //获取店铺的活动
            $Shop  = \App\Model\Shop::where('shop_id',$shop_id)->first();

            if( $Shop->status != 1 ) {

                return respond(422,"该店铺处于休息中！");

            }

            $Shop->is_first = Order::where('user_id',$this->user_id)->count();

            $promType = (empty($Shop->is_first)) ? [1,2] : [0,1];

            $Shop->prom = PromShop::where('status',1)->where('shop_id',$shop_id)->whereIn('type',$promType)->get();

            //获取用户的收货地址 和配送信息
            $UserAddress = UserAddress::where('user_id', $this->user_id)
                                        ->where('area_id',$Shop->area_id)
                                        ->when($request->filled('address_id'), function ($query) use ($address_id ) {
                                            return $query->where('address_id',$address_id);
                                        })
                                        ->orderByDesc('is_default')
                                        ->with('delivery')
                                        ->first();

            if(empty($UserAddress)) {
                return respond(201,'请填写收货地址！');
            }

            //基础配送费
            $basic_distribution_fee = DeliveryGroup::Delivery($Shop->group_id,$UserAddress->delivery_pid,0);

            //楼层配送费
            $floor_distribution_fee = DeliveryGroup::Delivery($Shop->group_id,$UserAddress->delivery_id,$UserAddress->delivery_pid);

            //用户支付的配送费
            $Shop->custom_delivery = $basic_distribution_fee + $floor_distribution_fee;

            return respond(200,'获取成功！',[

                'shop'     => $Shop,

                'address'  => $UserAddress,

                'goods'    => $Goods

            ]);
    }

    public function orderList(Request $request)
    {
        try {

            $overdueRow = DB::table('order')
                                ->where('user_id',$this->user_id)
                                ->whereRaw('TIMESTAMPDIFF(MINUTE,created_at,now()) >= '.Cache::get('auto_cancel_time').' and order_status = 0 and pay_status = 0  and created_at is not null')
                                ->select(['order_id','created_at','order_status','pay_status','user_id'])
                                ->pluck('order_id')->toArray();

            foreach ($overdueRow as  $order_id) {
                if (!empty($order_id)) {
                    event(new \App\Events\CancelOrder($order_id,3,0));
                }
            }
        } catch (\Exception $e) {}

        $this->validate($request,[
            'page_size' => 'sometimes|present|integer',
            'order_status' => 'sometimes|present|integer|in:0,1,2,3,4,5,6'
        ], $this->message);

        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $order_status = $request->filled('order_status') ? $request->order_status : 4;

        $Order = Order::where('user_id',$this->user_id)
                        ->when($request->filled('order_status'), function ($query) use ($order_status){
                            return $query->where('order_status', $order_status);
                        })
                        ->with(['order_goods','shop','relay_info','order_ps'=>function($query){
                            return $query->select(['nickname','push_id','user_id','type','realname','mobile','headimgurl','rider_type']);
                        }])
                        ->orderByDesc('order_id')
                        ->simplePaginate($page_size);

        return respond(200,'获取成功！',$Order);
    }


    /**
     * 订单列表
     * @return [type] [description] 
     */
    public function latest_order(Request $request)
    {

        $this->validate($request,[
            'page_size'    => 'sometimes|integer'
        ],
        $this->message);

        $page_size = $request->input('page_size',15);
        $Order     = Order::where('user_id',$this->user_id)
                            ->whereDate('created_at',date('Y-m-d'))
                            ->orWhere(function ($query) {
                                $query->whereIn('order_status',[0,1,2])->where('user_id',$this->user_id)->where('created_at','<=',date('Y-m-d 23:59:59'));
                            })
                            ->with(['order_goods','shop','order_ps'=>function($query){
                                return $query->select(['user_id','headimgurl','realname','username','nickname']);
                            }])
                            ->orderByDesc('order_id')
                            ->simplePaginate($page_size);


        return respond(200,'获取成功！',$Order);
    }

    /**
     * 历史订单
     * @return [type] [description]
     */
    public function history_order(Request $request)
    {
        $this->validate($request,[
            'page_size'    => 'sometimes|integer'
        ],
        $this->message);
        $page_size = $request->input('page_size',15);
        $Order     = Order::where('user_id',$this->user_id)
                            ->whereIn('order_status',[3,4,5,6])
                            ->whereDate('created_at','<',date('Y-m-d'))
                            ->with(['order_goods','shop','order_ps'=>function($query){
                                return $query->select(['user_id','headimgurl','realname','username','nickname']);
                            }])
                            ->orderByDesc('order_id')
                            ->simplePaginate($page_size);
        return respond(200,'获取成功！',$Order);
    }

    /**
     * 取消订单
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function cancel_order(Request $request)
    {
        $this->validate($request, 
            [
                'order_id'   => 'required|integer|exists:order,order_id'
            ],
        $this->message);

        $order_id = $request->order_id;

        $Order = Order::where(['user_id'=>$this->user_id,'order_id'=>$order_id])->first();

        if (empty($Order)) {
            return respond(201,'非法操作!');
        }

        if($Order->order_status > 0) {
            return respond(422,'请联系店家!'); //店家已操作不能退款
        }

        if((boolean)event( new CancelOrder($order_id,3,0))) {
            event(new PushMessage($Order->order_id));
            return respond(200,'取消成功！');
        }
        return respond(201,'取消失败！');
    }

    /**
     * 订单详情
     * @return [type] [description]
     */
    public function order_info(Request $request)
    {

        $this->validate($request, 
            [
                'order_id'   => 'sometimes|required|integer|exists:order,order_id',
                'order_sn'   => 'sometimes|required|string|exists:order,order_sn',
            ],
        $this->message);

        if(!$request->has('order_id') && !$request->has('order_sn')) return respond(422,'查询条件不能为空！');
        $default_where = [];

        if($this->user_type == 0) $default_where['user_id'] = $this->user_id;

        foreach($request->only(['order_id', 'order_sn']) as $key => $value)
        {
            if($request->filled($key)) $default_where[$key] = $value;
        }

        $Order = Order::where($default_where)->with(['order_goods','order_ps','order_shop_prom','order_shop','relay_info','afterSale'])->first();

        $Order->is_after_sale = (!empty($Order->confirm_time) && ($Order->order_status == '4') && (time() < (strtotime($Order->confirm_time) + 86400 )))  ? true : false;

        return respond(200,'获取成功！',$Order);
    }


    /**
     * 提交评价
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function submit_evaluation(Request $request)
    {
        $this->validate($request, 
            [
                'order_id'        => 'required|integer|exists:order,order_id',
                'deliver_rank'    => 'required|integer|min:0|max:5',
                'service_rank'    => 'required|integer|min:0|max:5',
                'img'             => 'sometimes|present|string|between:6,1024',
                'content'         => 'sometimes|present|string|between:1,100',
                'goods'           => 'sometimes|array',
                'goods.*.goods_id'=> 'sometimes|integer',
                'goods.*.praise'  => 'sometimes|integer|in:0,1',
                'goods.*.stamp'   => 'sometimes|integer|in:0,1',
            ],
        $this->message);

        $comment = new Comment();
        foreach ($request->only(['order_id','img','content','deliver_rank','service_rank']) as $key => $value)
        {
            if($request->filled($key)) $comment ->$key = $value;
        }

        $default_where = [
            'user_id'  => $this->user_id,
            'order_id' => $request->order_id,
            'is_comment' => 0
        ];

        $Order = Order::where($default_where)->with('order_goods')->first();
        if (empty($Order)) return respond(201,'您已经对该订单评价过了！');

        $comment->shop_id   = $Order->shop_id;
        $comment->user_id   = $this->user_id;
        $comment->username  = empty(\Auth::user()->nickname) ? '匿名' : \Auth::user()->nickname;
        $comment->head_pic  = \Auth::user()->headimgurl;
        $comment->goods_ids = join('_',array_column($Order->order_goods->toArray(), 'goods_id'));
        $comment->ps_id     = $Order->ps_id;

        $Order->is_comment = 1;

        if($comment->save() && $Order->save()) {

            if (!empty($request->input('goods')) && is_array($request->input('goods'))) {

                foreach ($request->input('goods') as  $value) {

                    if (!empty($value['goods_id']) && (!empty($value['praise']) || !empty($value['stamp']))) {

                        if (!empty($value['praise']) && $value['praise'] == 1 ) {

                            $filled = 'praise';
                        }else{

                            $filled = 'stamp';
                        }
                        
                        OrderGoods::where('order_id', $request->order_id)->where('goods_id',$value['goods_id'])->increment($filled);

                        Goods::where('goods_id',$value['goods_id'])->increment($filled);
                    }
                }
            }
            
            $service_rank = Comment::where('shop_id',$Order->shop_id)->avg('service_rank');
            
            Shop::where('shop_id',$Order->shop_id)->update(['store_ratings'=>$service_rank?round($service_rank,1):0]);
            
            return respond(200,'提交成功！');
        }

        return respond(201,'提交失败！');
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
            'user_id'         => $this->user_id,
            'order_id'        => $request->order_id,
            'pay_status'      => 1,
            'delivery_type'   => 1,
            'order_status'    => 2,
            'shipping_status' => 0,
        ];

        $Order = Order::where($default_where)->first();

        if(empty($Order)) return respond(201,'该订单还不满足完成条件！');

        if((boolean)event(new ClearingAccount($Order))) {
            return respond(200,'操作成功！');
        }

        return respond(201,'操作失败！');
    }
}