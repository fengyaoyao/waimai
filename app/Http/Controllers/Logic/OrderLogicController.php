<?php 
namespace App\Http\Controllers\Logic;
use App\Http\Controllers\Controller;
use App\Model\{Cart, Goods, Spec, SpecItem, UserAddress, PromShop, Order, OrderGoods, OrderShopProm, CouponList, AccountLog, AreaDelivery, Delivery, OrderRatio, User, Config, Area, DeliveryGroup};
use App\Model\Buyer\Shop;
use Illuminate\Support\Facades\DB;
use App\Exceptions\MyException;
class OrderLogicController extends Controller
{
    protected $user_id;//用户id
    protected $shop_id;//店铺id
    protected $goods_row;//商品信息
    protected $useraddress = [];//用户收货地址
    protected $shop;//店铺信息
    protected $shop_prom;//店铺活动
    protected $pay_code = 'weixin';//支付类型
    protected $user_note = '';//用户订单备注
    protected $remark = '';//标注信息
    protected $user_money = 0;//使用余额
    protected $integral = 0;//使用积分
    protected $total_amount = 0;//订单总价
    protected $order_amount = 0;//应付款金额
    protected $goods_price  = 0;//商品总价
    protected $order_sn = '';//订单号
    protected $default_address = '';//详细收货地址
    protected $delivery_cost = 0;//配送费
    protected $packing_expense = 0;//餐盒费
    protected $floor_amount = 0;//楼层配送费
    protected $coupon_price = 0;//优惠卷金额
    protected $integral_money = 0;//积分抵扣金额
    protected $delivery_type = 0;//配送类型
    protected $coupon;//优惠卷
    protected $shop_amount = 0;//店铺应得金额
    protected $ShopPromType2Money = 0;//店铺首单立减
    protected $ShopPromType0Money = 0;//店铺满减
    protected $platform_amount = 0;//平台收益金额
    protected $settlement= [];//购物车id
    protected $build_attr_type = 0; //建筑属性类型
    protected $order_type = 0; //订单类型
    protected $estimated_delivery = null; //客户要求送达时间
    protected $order_ratio = [];
    protected $order_id = 0;
    protected $platform_rake = 0;//平台抽成金额
    protected $shop_formula = '';
    protected $picked_up_time = null ;//客户预约自提时间
    protected $picked_up_mobile = null;//客户预约到店自提联系手机号码
    protected $picked_up_name = null;//客户预约到店自提姓名
    protected $optionStatus = []; //操作状态
    protected $pay_money = 0; //于前后端支付金额计算对比
    protected $service_charge = 0;//手续费
    protected $delivery_id = 0;//宿舍楼层id
    protected $delivery_pid = 0;//宿舍楼id
    protected $platform_assume_amount = 0;//平台承担金额
    protected $shop_assume_amount = 0;//商户承担金额
    protected $shop_pack_amount = 0;//店铺餐盒费应得
    protected $place_order_type = 0;//下单平台
    protected $discount_goods_price  = 0;//折扣商品总价
    protected $red_packet_money = 0; //红包抵扣金额
    protected $UserRedPacket = ''; //用户红包对象


    public function __construct($request)
    {
        /*********************订单初始化设置数据*********************/
        $this->user_id = \Auth::id();
        $this->setGoodsRow($request);
        $this->setAddress($request);
        $this->setDefaultAddress();
        $this->setBuildAttrType();
        $this->setShop($request);
        $this->setPayCode($request);
        $this->setPlaceOrderType($request);
        $this->setUserNote($request);
        $this->setRemark($request);
        $this->setCoupon($request);
        $this->setRedPacket($request);
        $this->setDeliveryType($request);
        $this->setUseMoney($request);
        $this->setPayMoney($request);
        $this->setUseIntegral($request);
        $this->setEstimatedDelivery($request);
        $this->setOrderSn();
        
        /*********************订单计算方法**************************/
        $this->calculatePackaging_fee();//计算餐盒费用
        $this->calculateDeliveryPrice();//计算配送费
        $this->calculateGoodsPrice();//计算商品总价
        $this->promPriority();//计算店铺活动
        $this->calculateCoupon();//计算优惠卷
        $this->calculateRedPacket();//计算红包抵扣金额
        $this->calculateTotalAmount();//计算订单总金额
        $this->calculateShopAmount();//计算商家应得金额
    }


    /**
     * 获取购物车商品及商品规格
     * @return [type] [description]
     */
    public function setGoodsRow($request)
    {
            $this->shop_id  = $request->shop_id;
            //查询商品及商品的规格
            foreach ($request->goods_row as $value)
            {
                $where = [
                    'goods_id' => $value['goods_id'],
                    'shop_id'  => $this->shop_id
                ];
                $spec_key  = empty($value['spec_key']) ? '' : $value['spec_key'];
                $Goods = Goods::where($where)->when($spec_key, function ($query) use ($spec_key) {
                                        return $query->with([
                                            'itme'=>function($query) use ($spec_key) {
                                                    return $query->whereIn('item_id',array_filter(explode('_',$spec_key)));
                                                }
                                            ]);
                                    })->first();

                if (empty($Goods)) {
                    continue;
                }

                $goods_row = $Goods->toArray();
                if (empty($goods_row)) {
                    continue;
                }
                if ($goods_row['shelves_status'] != 1) {
                    throw new MyException("{$goods_row['title']} 已下架！");
                }
                //购买数量限制
                if ($goods_row['purchase_quantity'] > 0 && ($value['goods_num'] > $goods_row['purchase_quantity'])) {
                    throw new MyException("{$goods_row['title']} 超过购买数量限制！");
                }
                if (($goods_row['stock_num'] >= 0) && (($goods_row['stock_num'] - $value['goods_num']) < 0 )) {
                    throw new MyException("{$goods_row['title']} 库存不足！");
                }
                //购买单个商品数量
                $goods_row['goods_num'] = $value['goods_num'];
                if(!empty($goods_row['itme'])) {
                    $goods_row['spec_price'] = array_sum(array_column($goods_row['itme'], 'price'));
                }else{
                    $goods_row['spec_price'] = 0;
                }

                $goods_row['is_discount'] = 0;
                $goods_row['discount_num'] = 0;

                if (!empty($value['discount_num']) && !empty($value['is_discount'])) {

                    if ($value['discount_num'] > $value['goods_num'] ) {
                        throw new MyException("折扣限制数量！");
                    }

                    if ($value['discount_num'] > $goods_row['discount_astrict'] ) {
                        throw new MyException("折扣限制数量！");
                    }

                    $goods_row['is_discount'] = $value['is_discount'];
                    $goods_row['discount_num'] = $value['discount_num'];
                }

                $this->goods_row[] = $goods_row;
            }
            if (empty($this->goods_row)) {
                throw new MyException("商品信息错误！");
            }
    }

    /**
     * 设置优惠卷
     * @return [type] [description]
     */
    public function setCoupon($request)
    {
            if(!$request->has('cid') || empty($request->input('cid'))) return true;

            $this->validate($request, ['cid' => 'required|integer'],$this->message);

            $where = [
                'uid' => $this->user_id,
                'cid' => $request->cid,
                'order_id' => 0,
                'status' => 0
            ];

            $coupon = CouponList::with('coupon')->where($where)->first();

            if(empty($coupon) || empty($coupon->coupon)) {
                throw new MyException("该优惠没有找到或者已经使用过了");
            }

            $coupon = $coupon->toArray();

            if (!empty($coupon['coupon']['days']) && $coupon['coupon']['days'] > 0) {

                $current_day = $coupon['coupon']['days'] - 1;
                $coupon['coupon']['use_start_time'] = date("Y-m-d 00:00:00",strtotime($coupon['send_time']));

                $coupon['coupon']['use_end_time'] = date("Y-m-d 23:59:59",strtotime("+{$current_day} day",strtotime($coupon['send_time'])));
            }

      
            if (empty($coupon['coupon']['days']) && $coupon['coupon']['is_forever']) {
                $coupon['coupon']['use_end_time'] = date("Y-m-d 23:59:59",strtotime("+1 year"));
            }
            
            if(time() < strtotime($coupon['coupon']['use_start_time'])) {
                throw new MyException("还未到优惠卷开始使用时间！");
            }

            if(time() > strtotime( $coupon['coupon']['use_end_time'])) {
                throw new MyException("该优惠卷已经到达使用结束时间！");
            }

            //获取用户的优惠卷信息
            $this->coupon = $coupon;
    }

    /**
     * [setRedPacket 设置红包]
     * @param [type] $request [description]
     */
    public function setRedPacket($request) {

        if ($request->filled('user_red_packet_id')) {

            $user_red_packet_id =  $request->user_red_packet_id;


            $UserRedPacket =  \App\Model\UserRedPacket::find($user_red_packet_id);

            if (empty($UserRedPacket)) {
                throw new MyException("该红包未找到！");
            }

            if ($UserRedPacket->status > 0) {
                throw new MyException("该红包已使用或已过期！");
            }
            
            if (strtotime($UserRedPacket->start_time) > time()) {
                throw new MyException("该红包已还未到使用时间！");
            }

            if (strtotime($UserRedPacket->end_time) < time()) {
                throw new MyException("该红包已到使用结束时间！");
            }

            $this->UserRedPacket = $UserRedPacket;
        }
    }

    /**
     * 根据地址获取区域和配送信息
     * @return [type] [description]
     */
    public function setAddress($request)
    {
        $this->validate($request, ['address_id'=> 'required_if:delivery_type,0|integer|exists:user_addresses,address_id'], $this->message);

        if ($request->delivery_type != 1) {

            $UserAddress = UserAddress::where('address_id',$request->address_id)
                                        ->where('user_id',$this->user_id)
                                        ->with(['delivery','area'])
                                        ->first();
            if (empty($UserAddress)) {
                throw new MyException("收货地址没有找到！");
            }else{
                $this->useraddress = $UserAddress->toArray();
            }
        }
    }

    /**
     * 获取店铺信息
     * @param Request $request [description]
     */
    public function setShop($request)
    {
        $this->shop = Shop::where('shop_id',$request->shop_id)->first()->toArray();

        if($this->shop['status'] !=1){
            throw new MyException("{$this->shop['shop_name']} 处于休息中！");
        }

        $this->settlement = $this->shop['settlement'];
    }

    /**
     * 设置支付方式
     * @param Request $request [description]
     */
    public function setPlaceOrderType($request)
    {
        if(empty($request->input('place_order_type'))) return false;

        $this->validate($request,['place_order_type'=>'required|integer|in:0,1,2'],$this->message);
        $this->place_order_type = $request->place_order_type;
    }


    /**
     * 设置支付方式
     * @param Request $request [description]
     */
    public function setPayCode($request)
    {
        if(empty($request->input('pay_code'))) return false;

        $this->validate($request,['pay_code'=>'sometimes|required|string|in:alipayMobile,weixin,other'],$this->message);
        $this->pay_code = $request->input('pay_code','weixin');
    }



    /**
     * 设置配送方式
     * @param Request $request [description]
     */
    public function setDeliveryType($request)
    {
        if(empty($request->input('delivery_type'))) {
            return false;
        }

        $this->validate($request, 
            [
                'delivery_type'    => 'required|integer|in:0,1',
                'picked_up_time'   => 'sometimes|present|date_format:Y-m-d H:i:s',
                'picked_up_name'   => 'sometimes|present|string|between:1,45',
                'picked_up_mobile' => 'required|regex:/^1[3456789][0-9]{9}$/'
            ],
        array_merge($this->message,['regex' =>'电话号码格式不正确！']));

        $this->delivery_type    = $request->delivery_type;
        
        $this->picked_up_name   = $request->filled('picked_up_name') ? $request->picked_up_name : '';

        $this->picked_up_time   = $request->filled('picked_up_time') ? $request->picked_up_time :  date('Y-m-d H:i:s',(time() + ($this->shop['sell_time'] * 60)));

        $this->picked_up_mobile = $request->picked_up_mobile;
    }

    /**
     * 设置用户备注
     * @param Request $request [description]
     */
    public function setUserNote($request)
    {
        if(empty($request->input('user_note'))) return false;

        $this->validate($request, ['user_note' => 'sometimes|required|string|max:50'],$this->message);  
        $this->user_note = $request->user_note;
    }

    /**
     * 设置标注信息
     * @param Request $request [description]
     */
    public function setRemark($request)
    {
        if(!empty($request->input('remark')))
        {
            $this->validate($request, ['remark' => 'required|string|max:50'],$this->message);
            $this->remark = $request->remark;
        }
    }

    /**
     * 设置用户使用的余额
     * @param Request $request [description]
     */
    public function setUseMoney($request)
    {
        if(empty($request->input('user_money'))) return false;

        $this->validate($request, ['user_money'=> 'required|numeric'],$this->message);

        $moeny = User::where('user_id',\Auth::id())->value('moeny');

        if($moeny < $request->input('user_money',0)) throw new MyException("你的余额不足！");

        $this->user_money = $request->input('user_money',0);
    }

    /**
     * 设置前端计算的支付金额
     * @param Request $request [description]
     */
    public function setPayMoney($request)
    {
        $this->validate($request, ['pay_money'=> 'required|numeric'],$this->message);

        $this->pay_money = round($request->input('pay_money',0),2);
    }

    /**
     * 设置用户使用的积分
     * @param Request $request [description]
     */
    public function setUseIntegral($request)
    {
      if(empty($request->input('integral'))) return false;

        $this->validate($request, [ 'integral'  => 'required|integer' ],$this->message);

        $points = User::where('user_id',\Auth::id())->value('points');

        if($points < $request->input('integral',0)) throw new MyException("你的积分不足！");

        $this->integral = $request->input('integral',0);

        $point_rate = Config::where('inc_type','basic')->where('name','point_rate')->value('value');
        $rate_value = empty($point_rate) ? 100 : $point_rate;
        $this->integral_money = empty( $this->integral) ? $this->integral : $this->integral/$rate_value;
    }


    /**
     * 设置订单号
     */
    public function setOrderSn()
    {
        $this->order_sn = date('YmdHis').mt_rand(10000,99999); 
    }

    /**
     * 设置用户详细收货地址
     */
    public function setDefaultAddress()
    {
        if (!empty($this->useraddress)) {

            $buildName = Delivery::where('delivery_id', $this->useraddress['delivery']['pid'])->value('build_name');

            $this->default_address =  $this->useraddress['area']['address'].'|-'.$buildName.'-'. $this->useraddress['delivery']['floor'].'-'. $this->useraddress['address'];
            $this->delivery_id  =  $this->useraddress['delivery']['delivery_id'];//宿舍楼层id
            $this->delivery_pid = $this->useraddress['delivery']['pid'];//宿舍楼id
        }
    }
    
    /**
     * 设置建筑属性类型
     */
    public function setBuildAttrType()
    {
        if (!empty($this->useraddress)) {
            $attr_type = Delivery::where('delivery_id', $this->useraddress['delivery']['pid'])->value('attr_type');
            $this->build_attr_type =  $attr_type ? $attr_type : 0;
        }
    }

    /**
     * [setEstimatedDelivery 设置客服要求送达时间]
     * @param [type] $request [description]
     */
    public function setEstimatedDelivery($request)
    {
        if(empty($request->input('estimated_delivery'))) return false;

        $this->validate($request, ['estimated_delivery'  => 'sometimes|date_format:Y-m-d H:i:s'],$this->message);
        
        $this->estimated_delivery = $request->estimated_delivery;
        $this->order_type = 1;
    }

    /**
     * 计算商品总金额
     * @return [type] [description]
     */
    public function calculateGoodsPrice()
    {
        $goods_price = 0;

        $discount_goods_price = 0;

        foreach ($this->goods_row as $key => $value) {

            if (empty($value)) {continue;}

            $one_goods_price = $value['spec_price'] + $value['price'];

            $total_goods_price = $one_goods_price * $value['goods_num'];

            if (($value['discount'] > 0) && ($value['discount'] <= 1) && ($value['discount_num'] > 0) ) {
                $discount_goods_price += (($one_goods_price * $value['discount_num']) - ($one_goods_price * $value['discount']) * $value['discount_num']);
            }
            
            $goods_price += $total_goods_price;
        }

        $this->goods_price = $goods_price; //商品总价
        $this->discount_goods_price = $discount_goods_price ? round($discount_goods_price,2) : 0; //商品折扣总价

    }


    /**
     * 计算餐盒费
     * @return [type] [description]
     */
    public function calculatePackaging_fee()
    {
        switch ($this->shop['packing_charges_type']) {
            case 0:
                $this->packing_expense = $this->shop['custom'];
                break;
            case 1:
                foreach ($this->goods_row as $value) {
                    $this->packing_expense += ($value['packing_expense'] * $value['goods_num']);
                }
                break;
        }
    }

    /**
     * 计算配送费
     * @return [type] [description]
     */
    public function  calculateDeliveryPrice()
    {
        if ($this->delivery_type != 1 && !empty($this->useraddress)) {

            //基础配送费
            $this->delivery_cost = DeliveryGroup::Delivery($this->shop['group_id'],$this->useraddress['delivery_pid'],0);

            //楼层配送费
            $this->floor_amount = DeliveryGroup::Delivery($this->shop['group_id'],$this->useraddress['delivery_id'],$this->useraddress['delivery_pid']);
        }
    }

    /**
     * [promPriority 活动优先权]
     * 1、首单 2、折扣 3、满减 4、满赠（与所有平级）
     * @return [type] [description]
     */
    public function promPriority()
    {
        // 判断当前用户是否存在订单
        $orderExists = Order::where('user_id',$this->user_id)->exists();

        if ($orderExists) {

            //享受折扣就不能享受满减
            if ($this->discount_goods_price == 0) {
                //执行满减
                $this->calculateShopPromType0();
            }
        }else{

            //执行首单
            $this->calculateShopPromType2();

            // 享受了首单就不能享受折扣
            if (($this->ShopPromType2Money > 0) && $this->discount_goods_price) {
                $this->discount_goods_price = 0;
                foreach ($this->goods_row as $k => $v) {
                   $this->goods_row[$k]['discount'] = 0;
                   $this->goods_row[$k]['discount_num'] = 0;
                }
            }
        }

        //执行满赠
        $this->calculateShopPromType1();
    }


    /**
     * 计算店铺满减活动
     * @return [type] [description]
     */
    public function  calculateShopPromType0()
    {
        $where = [
            ['shop_id','=', $this->shop_id],
            ['condition','<=', $this->goods_price],
            ['type','=', 0],
            ['status','=', 1],
        ];

        $prom = PromShop::where($where)->orderByDesc('condition')->first();

        $shouDan = PromShop::where(['shop_id'=>$this->shop_id,'type'=>2,'status'=>1])->orderByDesc('condition')->exists();

        if (!empty($prom->prom_id) || (!$shouDan)) {
            if (!empty($prom)) {
                $prom = $prom->toArray();
                $this->ShopPromType0Money = $prom['money'];
                $this->shop_prom[] = $prom;
            }
        }
    }

    /**
     * 计算店铺赠品活动
     * @return [type] [description]
     */
    public function  calculateShopPromType1()
    {
            $where = [
                ['shop_id','=', $this->shop_id],
                ['condition','<=', $this->goods_price],
                ['type','=', 1],
                ['status','=', 1],
            ];
            $prom = PromShop::where($where)->orderByDesc('condition')->first();
            if(!empty($prom->prom_id))
            {
                $prom = $prom->toArray();
                $this->shop_prom[] = $prom;
            }
    }

    /**
     * 计算店铺首单立减活动
     * @return [type] [description]
     */
    public function  calculateShopPromType2()
    {
        $where = [
            ['shop_id','=', $this->shop_id],
            ['type'   ,'=', 2],
            ['status' ,'=', 1],
        ];

        $prom  = PromShop::where($where)->orderByDesc('condition')->first();
        if(!empty($prom->prom_id)) {
            $prom = $prom->toArray();
            $prom['money'] = $prom['money'] > $this->goods_price ? $this->goods_price : $prom['money'];
            $this->ShopPromType2Money = $prom['money'];

            $this->shop_prom[] = $prom;
        }
    }


    /**
     * 计算优惠卷
     * @return [type] [description]
     */
    public function  calculateCoupon()
    {
        if(!empty($this->coupon))
        {
            if ($this->coupon['coupon']['money'] > $this->goods_price) {
                $this->coupon_price = $this->goods_price;
            }else{
                $this->coupon_price = $this->coupon['coupon']['money'];
            }
        }
    }
    /**
     * 计算红包抵扣金额
     * @return [type] [description]
     */
    public function  calculateRedPacket()
    {
        if(!empty($this->UserRedPacket))
        {

            if ($this->UserRedPacket->condition > 0) {
                if ($this->goods_price >= $this->UserRedPacket->condition) {
                    $this->red_packet_money = $this->UserRedPacket->money;
                }else{
                    throw new MyException("该红包不满足使用条件！");
                }
            }

            if ($this->UserRedPacket->condition == 0) {
                $this->red_packet_money = $this->UserRedPacket->money;
            }
        }
    }


    /**
     * 计算总金额
     * @return [type] [description]
     */
    public function calculateTotalAmount()
    {
        // try {
        //     info("
        //     商品价钱{$this->goods_price} 
        //     +餐盒费{$this->packing_expense} 
        //     +配送费{$this->delivery_cost} 
        //     +楼层费{$this->floor_amount} 
        //     -满减抵扣金额{$this->ShopPromType0Money}
        //     -优惠卷抵扣金额{$this->coupon_price}
        //     -积分抵扣金额{$this->integral_money}
        //     ");
        // } catch (Exception $e) { }
        //订单总价 = 商品总价+餐盒费+配送费+楼层费
        $this->total_amount = round($this->goods_price + $this->packing_expense + $this->delivery_cost + $this->floor_amount,2);

        //应付款金额 = 订单总价 - 店铺满减  - 平台优惠卷  - 红包抵扣 - 使用积分 - 使用余额
        $order_amount = bcsub($this->total_amount, ($this->discount_goods_price + array_sum([$this->ShopPromType0Money, $this->coupon_price, $this->red_packet_money, $this->integral_money])), 2);


        //店铺首单立减
        if ($this->ShopPromType2Money > 0 ) {

            if ($this->goods_price >= $this->ShopPromType2Money)
                $order_amount -= $this->ShopPromType2Money;
            else
                $order_amount -= $this->goods_price;
        }

        if ($this->user_money > 0 ) {

            if ($order_amount >= $this->user_money) {
                $order_amount -= $this->user_money;
            }else{
                $this->user_money = $this->user_money  - $order_amount;
                $order_amount -= $this->user_money;
            }
        }

        if(!empty($this->coupon) && ($this->total_amount < $this->coupon['coupon']['condition']))
        {
            unset($this->coupon);
            throw new MyException("该优惠卷不满足使用条件！");
        }

        $this->order_amount = ($order_amount > 0) ? round($order_amount,2) : 0;

        if ($this->order_amount != $this->pay_money) throw new MyException("商品价钱或优惠信息发生变化,请返回店铺重新选购！");
    }


    /**
     * 计算商家应得金额
     * @return [type] [description]
     */
    public function calculateShopAmount()
    {
        $where = [
                ['shop_id','=', $this->shop_id],
                ['type','=', 2],
                ['status','=', 1],
        ];

        $shop_amount     = $this->goods_price;
        $ratio           = $this->shop['settlement'];
        $deductionSumMoney = ($this->ShopPromType2Money + $this->ShopPromType0Money);
        $is_discounts_rake = (Area::where('area_id',$this->shop['area_id'])->value('is_discounts_rake')) ?? 0;

        //商品费应得
        if($this->goods_price > 0)
        {
            $rake = empty($this->shop['mer_rake']) ? 0 : $this->shop['mer_rake'];
            $this->order_ratio['rake'] = $rake;
            if ($is_discounts_rake) {
                $get_amount =  $rake ? round(($rake /100) *  ($this->goods_price - $deductionSumMoney) ,2) : 0; // 商家应得金额
            }else{
                $get_amount =  $rake ? round(($rake /100) *  $this->goods_price,2) : 0; // 商家应得金额
            }

            $shop_amount -= $get_amount;
            $this->shop_formula = "商品总价{$this->goods_price} - 平台抽成{$get_amount}";
        }

        //餐盒费应得
        if($this->packing_expense > 0)
        {
            $custom_ratio = empty($ratio['custom_ratio']) ? 0 : $ratio['custom_ratio'];

            $this->order_ratio['custom_ratio'] = $custom_ratio;

            $get_amount   = $custom_ratio ? round(($custom_ratio /100) * $this->packing_expense,2) : 0;
            $this->shop_pack_amount =  $get_amount;
            $shop_amount += $get_amount;

            $this->shop_formula .= " + 餐盒费{$get_amount}";
        }

        
        //配送费应得
        if($this->delivery_cost > 0)
        {
            $custom_ratio = empty($ratio['custom_delivery_ratio']) ? 0 : $ratio['custom_delivery_ratio'];

            $this->order_ratio['custom_delivery_ratio'] = $custom_ratio;

            $get_amount   = $custom_ratio ? round(($custom_ratio /100) * $this->delivery_cost,2) : 0; 
            $shop_amount += $get_amount;

            $this->shop_formula .= " + 配送费{$get_amount}";
        }

        //首单立减商家应承担百分比
        $prom  = PromShop::where($where)->orderByDesc('condition')->first();
        $count = Order::where('user_id',$this->user_id)->count();

        if(!empty($prom->prom_id) && empty($count))
        {
            $first_ratio  = empty($ratio['first_ratio']) ? 0 : $ratio['first_ratio'];
            //立减金额大于订单金额就取订单金额相反就去立减金额 
            $ratio_money  = $prom->money > $this->goods_price ? $this->goods_price : $prom->money;
            $this->order_ratio['first_ratio'] = $ratio['first_ratio'];

            $get_amount   = $first_ratio ? round(($ratio['first_ratio']/100) * $ratio_money,2) : 0;
            $shop_amount -= $get_amount;

            $this->shop_formula .= " - 首单立减承担{$get_amount}";

            $this->platform_assume_amount += ($ratio_money - $get_amount); 
            $this->shop_assume_amount += $get_amount; 
        }
    
        //满减抽成
        if($this->ShopPromType0Money > 0)
        {
            $full_ratio    = empty($ratio['full_ratio']) ? 0 : $ratio['full_ratio'];
            $this->order_ratio['full_ratio'] = $full_ratio;
            $get_amount   = $full_ratio ? round(($full_ratio /100) * $this->ShopPromType0Money,2) : 0;
            $shop_amount -= $get_amount;

            $this->shop_formula .= " - 店铺满减承担{$get_amount}";
            $this->shop_assume_amount += $get_amount; 
            $this->platform_assume_amount += ($this->ShopPromType0Money - $get_amount);
        }
        
        $pay_service = empty($ratio['pay_service']) ? 0 : $ratio['pay_service'];
        //支付手续费抽成
        if($this->order_amount > 0 && $pay_service != 0 )
        {
            $merchant_ratio = empty($ratio['merchant_ratio']) ? 0 : $ratio['merchant_ratio'];

            $this->order_ratio['merchant_ratio'] = $merchant_ratio;

            $merchant_ratio = $merchant_ratio ? $merchant_ratio / 100 : 0;

            $get_amount   = $merchant_ratio ? round($merchant_ratio * $this->order_amount,2) : 0;

            $this->service_charge = $get_amount;
            
            $shop_amount -= $get_amount;
            $this->shop_formula .= " - 支付手续费承担{$get_amount}";
        }

        //优惠卷抽成
        if(!empty($this->coupon) && $this->coupon_price > 0)
        {
            $coupon_ratio  = empty($ratio['coupon_ratio']) ? 0 : $ratio['coupon_ratio'];
            $this->order_ratio['coupon_ratio'] = $coupon_ratio;

            $get_amount  = $coupon_ratio ? round(($coupon_ratio /100) * $this->coupon_price,2) : 0;
            $shop_amount -= $get_amount;

            $this->shop_formula .= " - 优惠卷承担{$get_amount}";

            $this->platform_assume_amount += ($this->coupon_price - $get_amount); 
            $this->shop_assume_amount += $get_amount; 
        }

        //扣除商品折扣金额
        if ($this->discount_goods_price > 0) {
            $this->shop_formula .= '- 商品折扣承担 '.$this->discount_goods_price;
            $shop_amount -= $this->discount_goods_price;
        }

        //扣除店铺红包
        if (!empty($this->UserRedPacket) && ($this->UserRedPacket->type > 0)) {
            $this->shop_formula .= '- 红包抵扣承担 '.$this->UserRedPacket->money;
            $shop_amount -= $this->UserRedPacket->money;
        }

        //商户收益
        $this->shop_amount     = round($shop_amount,2);
        //平台收益
        $this->platform_amount = $this->total_amount - round($shop_amount,2); 
        //平台商品抽成金额
        if ($is_discounts_rake) {
            $this->platform_rake = ($this->order_ratio['rake'] /100) * ($this->goods_price - $deductionSumMoney)
            ;
        }else{
            $this->platform_rake = ($this->order_ratio['rake'] /100) *  $this->goods_price;
        }
    }




    /**
     * 创建订单
     * @return [type] [description]
     */
    public function createOrder()
    {
        try {
            $data = [
                'order_sn'        => $this->order_sn,
                'user_id'         => $this->user_id,
                'address'         => $this->default_address, 
                'pay_code'        => $this->pay_code,
                'goods_price'     => $this->goods_price,
                'delivery_cost'   => $this->delivery_cost,
                'packing_expense' => $this->packing_expense,
                'user_money'      => $this->user_money,
                'coupon_price'    => $this->coupon_price,
                'integral'        => $this->integral,
                'integral_money'  => $this->integral_money,
                'order_amount'    => $this->order_amount,
                'total_amount'    => $this->total_amount,
                'user_note'       => $this->user_note,
                'remark'          => $this->remark,
                'shop_id'         => $this->shop_id,
                'delivery_type'   => $this->delivery_type,
                'give_integral'   => floor($this->goods_price),
                'shop_amount'     => $this->shop_amount,
                'platform_amount' => round($this->platform_amount,2),
                'floor_amount'    => $this->floor_amount,
                'build_attr_type' => $this->build_attr_type,
                'platform_rake'   => $this->platform_rake,
                'shop_formula'    => $this->shop_formula,
                'order_type'      => $this->order_type,
                'estimated_delivery' => $this->estimated_delivery,
                'picked_up_time'     => $this->picked_up_time,
                'picked_up_mobile'   => $this->picked_up_mobile,
                'picked_up_name'     => $this->picked_up_name,
                'pay_status'         => 0,
                'delivery_id'        => $this->delivery_id,
                'delivery_pid'       => $this->delivery_pid,
                'service_charge'     => $this->service_charge,
                'shop_assume_amount' => $this->shop_assume_amount,
                'platform_assume_amount' => $this->platform_assume_amount,
                'shop_pack_amount' => $this->shop_pack_amount,
                'place_order_type' => $this->place_order_type,
                'discount_goods_price' => $this->discount_goods_price,
                'red_packet_money' => $this->red_packet_money,
            ];

            if (!empty($this->useraddress)) {

                $data['address_id'] = $this->useraddress['address_id'];
                $data['area_id']    = $this->useraddress['area_id'];
                $data['consignee']  = $this->useraddress['consignee'];
                $data['mobile']     = $this->useraddress['mobile'];
            }else{
                $data['area_id']    = $this->shop['area_id'];
            }

            $data['is_relay'] = Area::where('area_id',$data['area_id'])->value('relay');

            //应付款金额为0的情况下 改变订单状态
            if( $this->order_amount <= 0 )
            {
                $data['pay_status'] = 1;
                $data['pay_code'] = 'deduction';
                $data['pay_time'] = date('Y-m-d H:i:s');
            }

            //添加订单
            DB::beginTransaction(); //开启事务

            $Order = Order::create($data);
                  
            if(empty($Order->order_id)) {
                throw new MyException("订单提交失败!");
            }

            $this->order_id = $Order->order_id;

            /************订单创建后需要调用的方法***************/

            //添加订单商品记录
            $this->addOrderGoods();

            //添加订单所使用的活动记录
            $this->addOrderShopProm();

            //添加订单计算的折扣记录
            $this->orderRatio();

            //订单使用的余额、积分、和优惠卷操作
            $this->deductionOrChangeUser();

            if(empty($this->optionStatus)) {
                //回滚事务
                DB::rollBack();
                throw new MyException("订单提交失败!");
            }

            foreach ($this->optionStatus as $status) {

                if ($status == 0 || $status == false) {
                    DB::rollBack();
                    throw new MyException("订单提交失败!");
                }
            }
            
            //提交事务
            DB::commit();
            return $Order;

        } catch (\Exception $e) {
            throw new MyException("订单提交失败!");
        }
    }

    /**
     *  添加订单商品
     * @param [type] $order [description]
     */
    protected  function addOrderGoods()
    {
        $data = [];
        foreach ($this->goods_row as $key => $value) 
        { 
           $arr1 = [
                'order_id'      => $this->order_id,
                'goods_id'      => $value['goods_id'],
                'shop_id'       => $this->shop_id,
                'goods_name'    => $value['title'],
                'goods_num'     => $value['goods_num'],
                'goods_price'   => $value['price'],
                'goods_amount'  => $value['goods_num'] * ( $value['price'] + $value['spec_price'] ),
                'details_figure'=> $value['details_figure'],
                'discount'      => $value['discount'],
                'discount_num'  => $value['discount_num'],
                'created_at'    => date('Y-m-d H:i:s'),
            ];

            $arr2 = [
                'spec_price'     => '',
                'spec_key'       => '',
                'spec_key_name'  => ''
            ];

            if(!empty($value['itme']))
            {
                $arr2['spec_price']     = $value['spec_price'];
                $arr2['spec_key']       = join('_',array_column($value['itme'], 'item_id'));
                $arr2['spec_key_name']  = join(' ',array_column($value['itme'], 'item'));
            }

            $data[] = array_merge($arr1,$arr2);
        }
        $this->optionStatus[] = OrderGoods::insert($data);
    }

    /**
     * 添加订单店铺活动表
     * @param [type] $order_id [description]
     */
    protected function addOrderShopProm()
    {
        if(empty($this->shop_prom)) return true;
        $data = [];
        foreach ($this->shop_prom as $key => $value)
        {
            $data[] = [
                'order_id'           => $this->order_id,
                'shop_id'            => $this->shop_id,
                'prom_id'            => $value['prom_id'],
                'prom_title'         => $value['title'],
                'prom_type'          => $value['type'],
                'prom_condition'     => $value['condition'],
                'deduction_money'    => $value['money'],
                'created_at'         => date('Y-m-d H:i:s'),
            ];
        }
        $this->optionStatus[] = OrderShopProm::insert($data);
    }


    /**
     * 记录订单抽成比例
     * @return [type] [description]
     */
    public function orderRatio()
    {
        $map = [
            'order_id' => $this->order_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->optionStatus[] = OrderRatio::insert(array_merge(array_filter($this->order_ratio),$map));
    }

    /**
     * 扣除用户使用的余额、积分、和优惠卷使用状态
     * @return [type] [description]
     */
    public function deductionOrChangeUser()
    {
        if ($this->user_money > 0) {
            $this->optionStatus[] = User::where('user_id',$this->user_id)->decrement('moeny',$this->user_money);
            $this->optionStatus[] = AccountLog::insert(['user_money' => '-'."{$this->user_money}",'desc'=>'余额抵扣','user_id'=>$this->user_id,'order_id'=>$this->order_id,'created_at' => date('Y-m-d H:i:s')]);
        }

        if ($this->integral > 0) {
            $this->optionStatus[] = User::where('user_id',$this->user_id)->decrement('points',$this->integral);
            $this->optionStatus[] = AccountLog::insert(['pay_points' => '-'."{$this->integral}",'desc'=>'积分抵扣','user_id'=>$this->user_id,'order_id'=>$this->order_id,'created_at' => date('Y-m-d H:i:s')]);
        }

        if ($this->coupon_price > 0) {

            $CouponListRow = CouponList::where([
                'uid' => $this->user_id,
                'cid' => $this->coupon['coupon']['id'],
                'status' => 0,
                'order_id' => 0
            ])
            ->first();

            $CouponListRow->status = 1;
            $CouponListRow->order_id = $this->order_id;
            $CouponListRow->use_time = date('Y-m-d H:i:s');

            $this->optionStatus[] = $CouponListRow->save();
        }
        if ($this->red_packet_money > 0 && !empty($this->UserRedPacket)) {
            $this->UserRedPacket->status = 1;
            $this->UserRedPacket->order_id = $this->order_id;
            $this->UserRedPacket->use_time = date('Y-m-d H:i:s');
            $this->UserRedPacket->save();
        }
    }
}