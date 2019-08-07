<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Model\ShopRedPacket;
use App\Model\UserRedPacket;
use App\Model\MemberRedPacket;
use App\Model\ExchangeRedPacket;
use App\Model\Shop;
use App\Model\MemberRule;
use App\Model\Recharge;


class RedPacketController extends Controller
{

    protected $user_id;

    public function __construct(){
        $user = Auth::user();
        $this->user_id = $user->user_id;

        try {
            DB::update("update wm_user_red_packets set status = 2 where user_id = ? and end_time <= now()", [$this->user_id]);
        } catch (\Exception $e) {
            
        }
    }

    /**
     * [exchange 通用红包兑换店铺红包]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function exchange(Request $request) {

        $this->validate($request,[
            'user_red_packet_id' => 'required','integer','exists:user_red_packets,id',
            'shop_red_packet_id' => 'required','integer','exists:shop_red_packets,id',
        ],$this->message);


        $ShopRedPacket = ShopRedPacket::where(['id'=>$request->shop_red_packet_id,'type'=>1])
                            ->select(['id as red_packet_id','title','shop_id','type','money','expire_days','condition'])
                            ->first();

        if (empty($ShopRedPacket)) {
            return respond(201,'店铺兑换红包没有找到！');
        }
        
        if ($ShopRedPacket->expire_days) {
            $current_day = $ShopRedPacket->expire_days - 1;
            $start_time =  date("Y-m-d 00:00:00");
            $ShopRedPacket->start_time = $start_time;
            $ShopRedPacket->end_time = date("Y-m-d 23:59:59",strtotime("+{$current_day} day",strtotime($start_time)));
        }

        $ShopRedPacket->type +=1; 
        $data = $ShopRedPacket->only(['red_packet_id','title','shop_id','type','money','expire_days','condition']);

        //开启事务
        DB::beginTransaction();

        $MemberRedPacketRecord = UserRedPacket::where(['id'=>$request->user_red_packet_id,'type'=>0,'status'=>0])->first();

        if (empty($MemberRedPacketRecord)) {
            return respond(201,'会员红包没有找到！');
        }

        $data['user_id'] = $this->user_id;
        $data['area_id'] = Shop::where('shop_id',$data['shop_id'])->value('area_id');
        $data['start_time'] = $MemberRedPacketRecord->start_time;
        $data['end_time'] = $MemberRedPacketRecord->end_time;

        $UserRedPacket = UserRedPacket::where(['user_id'=>$this->user_id,'red_packet_id'=>$request->shop_red_packet_id,'type'=>2,'status'=>0])->withTrashed()->first();

        if (!empty($UserRedPacket)) {
            $UserRedPacket->restore();
        }else{
            
            $UserRedPacket = null;
            $UserRedPacket = UserRedPacket::create($data);
        }

        $ExchangeRedPacket = [
            'form_id' => $request->user_red_packet_id,
            'to_id' => $UserRedPacket->id,
            'user_id' => $this->user_id,
            'created_at' => date("Y-m-d H:i:s")
        ];
        
        $exchange_id = ExchangeRedPacket::insertGetId($ExchangeRedPacket);

        if ($exchange_id && $MemberRedPacketRecord->delete() && UserRedPacket::where('id', $UserRedPacket->id)->update(['exchange_id'=>$exchange_id])) {

            //提交事务
            DB::commit();
            return respond(200,'兑换成功！',$UserRedPacket);
        }

        //事务回滚
        DB::rollBack();

        return respond(201,'兑换失败！');
    }

    /**
     * [cancelExchange 取消兑换]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function cancelExchange(Request $request) {

        $this->validate($request,[
            'exchange_id' => 'required|integer|exists:exchange_red_packets,id'
        ],$this->message);

        $ExchangeRedPacket = ExchangeRedPacket::where(['id'=>$request->exchange_id,'user_id'=>$this->user_id])->first();

        if (empty($ExchangeRedPacket)) {
            return respond(201,'没有找到红包兑换记录!');
        }

        list($form_id,$to_id) = array_values($ExchangeRedPacket->only(['form_id','to_id']));

        $ExchangeRedPacket = [
            'form_id' => $to_id,
            'to_id' => $form_id,
            'user_id' => $this->user_id,
            'created_at' => date("Y-m-d H:i:s")
        ];

        //开启事务
        DB::beginTransaction();

        if (ExchangeRedPacket::insert($ExchangeRedPacket) && UserRedPacket::where('id',$to_id)->delete() && DB::update('update wm_user_red_packets set deleted_at = null where id = ?', [$form_id])) {

            //提交事务
            DB::commit();
            return respond(200,'操作成功！');
        }

        //事务回滚
        DB::rollBack();
        return respond(201,'操作失败！');

    }


    /**
     * [acquisition 获取店铺红包]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function acquisition(Request $request) {

        $this->validate($request,[
                'shop_red_packet_id' => 'required|integer|exists:shop_red_packets,id'
        ],$this->message);

        $ShopRedPacket = ShopRedPacket::where(['id'=>$request->shop_red_packet_id,'type'=>0])
                                        ->select(['id as red_packet_id','title','shop_id','type','money','expire_days','condition','start_time','end_time'])
                                        ->first();
        if (empty($ShopRedPacket)) {
            return respond(201,'领取失败！');
        }

        if ($ShopRedPacket->expire_days) {
            $current_day = $ShopRedPacket->expire_days - 1;
            $start_time =  date("Y-m-d 00:00:00");
            $ShopRedPacket->start_time = $start_time;
            $ShopRedPacket->end_time = date("Y-m-d 23:59:59",strtotime("+{$current_day} day",strtotime($start_time)));
        }

        $ShopRedPacket->type +=1; 
        $data = $ShopRedPacket->only(['red_packet_id','title','shop_id','type','money','expire_days','condition','start_time','end_time']);

        if (UserRedPacket::where(['user_id'=>$this->user_id,'red_packet_id'=>$data['red_packet_id'],'type'=>$data['type'],'status'=>0])->exists()) {
            return respond(201,'您已经领取过了！');
        }

        $data['user_id'] = $this->user_id;
        $data['area_id'] = Shop::where('shop_id',$data['shop_id'])->value('area_id');

        $UserRedPacket = UserRedPacket::create($data);
        if (!empty($UserRedPacket)) {
            return respond(200,'领取成功！',$UserRedPacket);
        }

        return respond(201,'领取失败！');
    }

    /**
     * [list 红包列表]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function list(Request $request) {
        $this->validate($request,[
            'page_size' => 'sometimes|required|integer|min:1',
            'shop_id' => 'sometimes|required|integer|exists:shops,shop_id',
            'status' => 'sometimes|required|integer|in:0,1,2',
        ],$this->message);

        $page_size = $request->filled('page_size') ? $request->page_size : 15;
        $shop_id = $request->filled('shop_id') ? $request->shop_id : null;
        $status = $request->filled('status') ? $request->status : null;

        $UserRedPacket = UserRedPacket::where('user_id',$this->user_id)
                                        ->when($shop_id, function ($query) use( $shop_id) {
                                            return $query->whereRaw('((type <> 0 and shop_id = ?) or (type =0 and shop_id = 0))',[$shop_id]);
                                        })
                                        ->when($request->filled('status'), function ($query) use( $status) {
                                            return $query->where('status',$status);
                                        })
                                        ->orderByDesc('created_at')
                                        ->simplePaginate($page_size);

        return respond(200, '获取成功！',$UserRedPacket);
    }

    /**
     * [usable 获取店铺可以领取和兑换的红包]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function usable(Request $request){

        $this->validate($request,[
            'shop_id' => 'required|integer|exists:shops,shop_id',
            'total_amount' => 'required|numeric'
        ],$this->message);

        $ShopRedPacket = DB::select("select * from wm_shop_red_packets where ((`type` = 0 and `condition` >= :condition) or `type` = 1 ) and `shop_id` = :shop_id and `end_time` > :end_time and `deleted_at` is null", ['shop_id' => $request->shop_id,'condition'=>$request->total_amount,'end_time'=>date('Y-m-d H:i:s')]); 

        foreach ($ShopRedPacket as $key => $value) {
            if (UserRedPacket::where('type',($value->type + 1))->where(['user_id'=>$this->user_id,'status'=>0,'shop_id'=>$value->shop_id,'red_packet_id'=>$value->id])->exists()) {
                unset($ShopRedPacket[$key]);
            }
        }

        return respond(200, '获取成功！',$ShopRedPacket);
    }


    /**
     * [member 是否开通会员 和 会员红包总金额]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function member(Request $request) {
        $this->validate($request,[
            'area_id' => 'required|integer|exists:areas,area_id',
        ],$this->message);

        $data = [
            // 根据充值记录判断是否是会员
            'is_member' => Recharge::where(['user_id'=>$this->user_id, 'area_id'=>$request->area_id, 'pay_status'=>1])->whereRaw('etime > now()')->exists(),
            'total_money' => 0
        ];

        // 获取会员红包总金额
        $MemberRule = MemberRule::where(['area_id'=>$request->area_id, 'status'=>1])->first();
        if (!empty($MemberRule)) {
            $data['total_money'] = $MemberRule->red_packet_num * $MemberRule->red_packet_money;
        }
        return respond(200, '获取成功！',$data);
    }
}