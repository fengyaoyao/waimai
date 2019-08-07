<?php 
namespace App\Http\Controllers\Buyer;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use App\Model\{CouponList, AccountLog, IntegralExchange, IntegralGoods, User, Coupon};
use Illuminate\Support\Facades\DB;


class ExchangeController extends Controller
{

    protected $user_id;

    public function __construct()
    {
        $this->user_id = \Auth::id();
    }

    /**
     * [exchange 积分商品兑换]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function exchange(Request $request) {

        $this->validate($request, 
        [
            'integral_goods_id' => 'required|integer|exists:integral_goods,id',
            'flag' => 'required|integer|in:0,1',
            'type' => 'sometimes|present|integer|in:0,1',
            'mobile' => 'required_if:flag,0|regex:/^1[3456789][0-9]{9}$/',
            'consignee' => 'required_if:flag,0|string|between:1,45',
            'address' => 'required_if:flag,0|string|between:3,128'
        ],
        $this->message);

        try {

            DB::beginTransaction(); //开启事务

            $IntegralGoods = IntegralGoods::where(['id'=>$request->integral_goods_id,'shelves_status'=>1])->lockForUpdate()->first();

            if (empty($IntegralGoods )) {
                throw new Exception("该商品未找到或已下架!");
            }

            if (($IntegralGoods->sales + 1) > $IntegralGoods->create_num) {
                throw new Exception("该商品已经兑换完了!");
            }

            if ($IntegralGoods->flag != $request->flag) {
                throw new Exception("标记类型不符合该商品!");
            }

            $points = User::where('user_id',$this->user_id)->value('points');
   
            if ($IntegralGoods->conversion_integral > $points ) {
                throw new Exception("你的积分不足!");
            }

            //积分商品销量加1
            $IntegralGoods->sales = $IntegralGoods->sales + 1;


            //积分商品兑换记录
            $IntegralExchange = new IntegralExchange();
            $IntegralExchange->user_id = $this->user_id;
            $IntegralExchange->use_integral = $IntegralGoods->conversion_integral;
            $IntegralExchange->status = 1;

            //判断是否是虚拟产品并且优惠卷id 不为空的
            if ($IntegralGoods->flag && !empty($IntegralGoods->coupon_id)) {

                $Coupon = Coupon::where('id',$IntegralGoods->coupon_id)->where('status',0)->first();

                if(empty($Coupon)){
                    throw new Exception("该优惠卷没有找到!");
                }

                if(time() > strtotime($Coupon->use_end_time)) {
                    throw new Exception("该优惠卷已经过期了!");
                }

                $IntegralExchange->shipping_status = 2;
            }

            foreach($request->only(['integral_goods_id', 'type','mobile','consignee','address']) as $key => $value){
                if ($request->filled($key)) {
                    $IntegralExchange->$key = $value;
                }
            }
            
            //扣除用户抵扣的积分
            $changePoints = User::where('user_id',$this->user_id)->decrement('points',$IntegralGoods->conversion_integral);

            //记录积分使用记录
            $AccountLog = AccountLog::insert(['pay_points' => '-'."{$IntegralGoods->conversion_integral}",'desc'=>'积分兑换','user_id'=>$this->user_id,'created_at' => date('Y-m-d H:i:s')]);


            if ($IntegralExchange->save() && $changePoints && $AccountLog && $IntegralGoods->save()) {

                //为用户记录优惠卷信息
                if ($IntegralGoods->flag && !empty($IntegralGoods->coupon_id)) {
                    CouponList::insert([
                        'cid' => $IntegralGoods->coupon_id,
                        'type' => 5,
                        'uid' => $this->user_id,
                        'send_time' => date('Y-m-d H:i:s'),
                    ]);
                }

                DB::commit();//提交事务
                return respond(200,'兑换成功!');
            }

            DB::rollBack();//事务回滚
            return respond(201,'兑换失败!');

        } catch (Exception $e) {

            DB::rollBack();//事务回滚
            return respond(201,$e->getMessage());
        }
    }

    /**
     * [exchangeList 积分商品兑换记录列表]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function exchangeList(Request $request) {

        $this->validate($request,['page_size' => 'sometimes|required|integer'], $this->message);

        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $IntegralExchange = IntegralExchange::where('user_id',$this->user_id)->with('integral_goods')->orderByDesc('id')->simplePaginate($page_size);

        return respond(200,'获取成功！',$IntegralExchange);
    }
}