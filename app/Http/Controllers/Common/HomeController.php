<?php 
namespace App\Http\Controllers\Common;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Area,User,Ad,Article,ShopType,AdPosition,Config,Goods,ShopOpeningTime,UserHasArea, AreaShift, RiderAreaShift, RiderClockIn};
use App\Model\Buyer\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Traits\SignIn;


class HomeController extends Controller
{
    use SignIn;

    public function index(Request $request)
    {

        $this->validate($request, ['area_id'=>'required|integer|exists:areas,area_id'],$this->message);

        $area_id = $request->area_id;

        if ($request->filled('user_id')) {
            $map = ['user_id'=>$request->user_id,'area_id'=>$area_id];
            if (!(UserHasArea::where($map)->exists())) {
                UserHasArea::create($map);
            }
        }

        //banner位置
        $position_id = AdPosition::where('area_id',$area_id)->where('is_open',1)->value('position_id');

        //跟据位置获取banner
        $Banner = Ad::where('pid',$position_id??0)->where('enabled',1)->where('start_time','<=',time())->where('end_time','>',time())->orderBy('orderby')->orderByDesc('ad_id')->get();

        //店铺类型
        $Shop     = Shop::where('area_id',$area_id)->groupBy('type_id')->having('area_id','=',$area_id)->get();
        $ShopType = [];
        if (!empty($Shop)) {
            $ShopType = ShopType::whereIn('type_id',array_column($Shop->toArray(), 'type_id'))->orderByDesc('sort')->get();
        }

        //推荐店铺
        $recommend = Shop::where('is_recommend',1)->where('is_lock',0)->where('area_id',$area_id)->orderByDesc('status')->get();

        $Ad = Ad::where('pid',5)->where('enabled',1)->where('start_time','<=',time())->where('end_time','>',time())->select(['ad_name','ad_code','ad_link'])->orderBy('orderby')->first();

        $data = [
            'banner'    => $Banner,
            'notice'    => Article::where('cat_id',4)->where('area_id',$area_id)->where('is_open',1)->orderByDesc('article_id')->get(),//系统和区域公告
            'shop_type' => $ShopType,
            'recommend_shop' => $recommend,
            'ad'=> empty($Ad)? ['ad_name'=>'','ad_code'=>'','ad_link'=>''] : $Ad
        ];

        return respond(200,'获取成功！',$data);
    }

    /**
     * [businessHours 获取店铺营业时间段]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function businessHours(Request $request)
    {
        $this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);

        $Shop = \App\Model\Shop::where('shop_id',$request->shop_id)
                                ->select(['shop_id','area_id','sell_time'])
                                ->with(['area'=>function($query){return $query->select(['area_id','process_date']);}])
                                ->first();
        if (!empty($Shop)) {
            $need_minute = $Shop->sell_time;
            $need_minute += empty($Shop->area) ? 10 : $Shop->area['process_date'];
        }else{
            $need_minute = 30;
        }

        $can_time   = time() + $need_minute * 60;

        $ShopOpeningTime = ShopOpeningTime::where('shop_id',$request->shop_id)
                                            ->whereTime('end_time','>=',date('H:i:s', $can_time))
                                            ->select(['start_time','end_time'])
                                            ->orderBy('end_time')->get()
                                            ->toArray();

        $is_exists = \App\Model\ShopOpeningTime::where('shop_id',$request->shop_id)->count();
        
        if (empty($ShopOpeningTime) && empty($is_exists)) array_push($ShopOpeningTime, ['start_time'=>'08:00:00','end_time'=>'22:00:00']);

        $data = [];
        $result = [];

        foreach (array_column($ShopOpeningTime, 'end_time') as $key => $value) {

            if (empty($value) || empty($ShopOpeningTime[$key]['start_time'])) continue;

            $start_time = strtotime(date($ShopOpeningTime[$key]['start_time']));

            //end_time 时间戳
            $end_time = strtotime(date($value));

            //剩余多少分钟
            $remain_minute = ceil((($end_time-($can_time))/60)/10);

            for ($i=0; $i < $remain_minute; $i++) {

                $m = $i*10;
                $option_time = strtotime("-{$m} minute",$end_time);

                if ($option_time < $start_time ) continue;

                $data[date('H', $option_time)][] = date('i', $option_time);;
            }
        }

        if (!empty($data) && ksort($data)) {

            foreach ($data as $k => $v) {
                $v = array_unique($v);
                sort($v);
                $result[] = ['hour'=> $k, 'minute'=> $v];
            }
        }

        return respond(200,'获取成功!',$result);
    }

    /**
     * 数据初始化接口
     * @return [type] [description]
     */
    public function init()
    {
        /***********配置信息********************************/
        $data = Config::whereIn('name',['delivery_cost','packing_expense','delivery_duration','point_rate','auto_cancel_time'])->get();

        foreach ($data as $v) {
            if (!Cache::has($v->name)) {
               Cache::add($v->name,$v->value,7200);
            }
        }
        /***********配置信息********************************/

        /***********商品自动上下架程序开始*******************/
        try {
            $Goods = Goods::where('auto_shelves',1)->get();

            $ids0  = []; $ids1  = []; $now   = date("Y-m-d");

            foreach ($Goods as $key => $value) {
                $format        = "{$now} H:i:s";
                $shelves_start = strtotime(date($format,strtotime($value->shelves_start)));
                $shelves_end   = strtotime(date($format,strtotime($value->shelves_end)));

                if( time() > $shelves_start && time() < $shelves_end )
                    $ids1[] = $value->goods_id;
                else
                    $ids0[] = $value->goods_id;
            }

            if(!empty($ids1)) Goods::whereIn('goods_id',$ids1)->update(['shelves_status' => 1]);
            if(!empty($ids0)) Goods::whereIn('goods_id',$ids0)->update(['shelves_status' => 0]);
        } catch (\Exception $e) {}
        /***********商品自动上下架程序结束*******************/

        /***********店铺自动营业****************************/
        try {

            $is_exists = ShopOpeningTime::groupBy('shop_id')->get()->pluck('shop_id')->toArray();

            $off = []; $on = [];

            foreach ($is_exists as $shop_id) {
              $count = ShopOpeningTime::where('shop_id',$shop_id)->whereTime('start_time','<',date('H:i:s'))->whereTime('end_time','>',date('H:i:s'))->count();
              $count ? array_push($off, $shop_id) : array_push($on, $shop_id);
            }

            if(!empty($off)) DB::table('shops')->whereIn('shop_id',$off)->where('is_timing',1)->update(['status'=>1]);
            if(!empty($on)) DB::table('shops')->whereIn('shop_id',$on)->where('is_timing',1)->update(['status'=>0]);

        } catch (\Exception $e) {}
        /***********店铺自动营业****************************/


        /**********优惠卷状态维护***************************/
        try {

            $Coupon = DB::table('coupon')->where('is_forever',0)->select(['id','use_start_time','use_end_time','status'])->get();

            $couponStatus = [[],[]];

            foreach ($Coupon  as $value)
            {
                $time = time();
                if(strtotime($value->use_start_time) < $time && strtotime($value->use_end_time) > $time)
                {
                    if ($value->status) {
                        $couponStatus[0][] = $value->id;
                    } 
                }else{

                    if (!$value->status) {
                        $couponStatus[1][] = $value->id;
                    } 
                }
            }

            if (!empty($couponStatus[0]) || !empty($couponStatus[1])) {
                foreach ($couponStatus as $key => $value) 
                {
                    if(empty($value)) continue;
                    DB::table('coupon')->where('is_forever',0)->whereIn('id',$value)->update(['status'=>$key]);
                }
            }
        } catch (\Exception $e) {}
        /**********优惠卷状态维护****************************/

        /**********自动取消15分钟内未付款的订单***************/
        // try {

        //     $overdueRow = DB::table('order')
        //                     ->whereRaw('TIMESTAMPDIFF(MINUTE,created_at,now()) >= '.Cache::get('auto_cancel_time').' and order_status = 0 and pay_status = 0 and deleted_at is null')
        //                     ->select(['order_id'])
        //                     ->pluck('order_id')
        //                     ->toArray();

        //     foreach ($overdueRow as  $order_id) {

        //         if (!empty($order_id)) {
        //             event(new \App\Events\CancelOrder($order_id,3,0));
        //         }
        //     }

        // } catch (\Exception $e) {}
        /**********自动取消15分钟内未付款的订单***************/

        try {

            $Area = Area::where('is_open_shift',1)->pluck('area_id')->toArray();
            if ($Area) {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->select(14);
                // $redis->delete($redis->keys('*'));exit;
                $pr = env('DB_PREFIX');
                $w = date('w');
                $area_ids = join(',',$Area);
                $ShiftApply = DB::select("select 
                    {$pr}shift_apply.id,
                    {$pr}shift_apply.user_id,
                    {$pr}users.rider_type,
                    {$pr}users.area_id
                 from {$pr}shift_apply,{$pr}users
                 where {$pr}shift_apply.`user_id` = {$pr}users.`user_id`
                 and {$pr}shift_apply.`status` = 1 
                 and {$pr}shift_apply.`shift_time` is not null 
                 and now() >= {$pr}shift_apply.`shift_time`
                 and {$pr}users.`area_id` in ({$area_ids})
                 ");

                foreach ($ShiftApply as $stdClass) {

                    $RiderAreaShift = RiderAreaShift::meet($stdClass->id,$stdClass->area_id)->get()->toArray();

                    if (empty($RiderAreaShift)) { continue; }

                    foreach ($RiderAreaShift as $va) {

                        $cacheKey = $stdClass->user_id .'_'.$va['shift_id'];

                        if ($redis->exists($cacheKey)) { continue; }

                        $level = '';

                        switch ($w) {
                            case '0':
                                $level = $stdClass->rider_type ? $va['j_level_weekend'] : $va['q_level_weekend'];
                                break;
                            case '6':
                                $level = $stdClass->rider_type ? $va['j_level_weekend'] : $va['q_level_weekend'];
                                break;
                            default:
                                $level = $stdClass->rider_type ? $va['j_level'] : $va['q_level'];
                                break;
                        }

                        $checkInData = [
                            'user_id' => $stdClass->user_id,
                            'shift_id' => $va['shift_id'],
                            'level' => $level,
                            'week' => $w
                        ];

                        if(!RiderClockIn::where($checkInData)->whereDate('created_at',date('Y-m-d'))->exists()){

                            $check_in_id = RiderClockIn::insertGetId(array_merge($checkInData,['created_at' => date('Y-m-d '.$va['start_time'])]));

                            if ($check_in_id) {
                                $seconds = strtotime(date('Y-m-d 23:59:59',time())) - time() ;
                                $redis->setex($cacheKey,$seconds,$check_in_id);
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {}


        if($data)
            return respond(200,'获取成功！',$data);
        else
            return respond(201,'获取失败！');
    }

    /**
     * [start_page 启动页]
     * @return [type] [description]
     */
    public function start_page() {

    }
}