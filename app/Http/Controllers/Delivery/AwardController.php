<?php 
namespace App\Http\Controllers\Delivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Order,Area,User,UserBill,AwardList,Activity};
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Exceptions\MyException;


class AwardController extends Controller
{
    protected $user_id;
    protected $area_id;
    protected $user;

    public function __construct() {
        $this->user =  \Auth::user();
        $this->user_id = $this->user->user_id;
        $this->area_id =  $this->user->area_id;
    }


    /**
     * [awardMain 奖励主页]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function awardMain() {

        $explain = '配送员红包奖励';

        $order_num = $this->getTodayOrderNum();

        $Activity = $this->getActivity();

        $activity_id = empty($Activity->id) ? 0 : $Activity->id;

        $AwardList = AwardList::whereDate('created_at',date('Y-m-d'))
                                ->where('activity_id','>',0)
                                ->where('activity_id',$activity_id)
                                ->with('ps_info')
                                ->orderBy('id','asc')
                                ->get();

        $is_receive = ($order_num >= $Activity->condition && !($this->ToDayIsAlreadyAwardList($Activity->id))) ? true : false;

        return respond(200,'获取成功！',[
            'explain' =>  $explain,
            'award' =>  $Activity,
            'user_list' => $AwardList,
            'order_num' => $order_num,
            'is_receive' => $is_receive
        ]);
    }

    /**
     * [visibleAward 奖励是否显示]
     * @return [type] [description]
     */
    public function visibleAward() {


        $Activity = Activity::where(['area_id'=>$this->area_id,'status'=>1,'type'=>1])->whereRaw('`start_time` <= now() and `end_time` >= now()')->first();

        if (!empty($Activity)) {
            return respond(200,'获取成功！',['visible'=> true]);
        }
        
        return respond(200,'获取成功！',['visible'=> false]);
    }

    /**
     * [receive 领取奖励]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function receive() {

        DB::beginTransaction(); //开启事务

        try {

            $Activity = $this->getActivity();

            if ($this->getTodayOrderNum() < $Activity->condition) {
                return respond(201,'单量不满足领取条件！');
            }

            if ($this->ToDayIsAlreadyAwardList($Activity->id)) {
                return respond(201,'不能重复领取奖励！');
            }


            $ToDayGetNum = AwardList::where('activity_id',$Activity->id)
                                    ->whereDate('created_at',date('Y-m-d'))
                                    ->count();

            if (($ToDayGetNum + 1) > $Activity->send_num ) {
                return respond(201,'来晚了！奖励已被领取完了！');
            }

            $inserAwardList = [
                'user_id' => $this->user_id,
                'money'  => $Activity->money,
                'activity_id' => $Activity->id,
                'flag' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $inserUserBill = [
                'user_id'    => $this->user_id,
                'money'      => $Activity->money,
                'desc'       => '配送奖励',
                'type'       => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $User = User::where('user_id',$this->user_id)->lockForUpdate()->increment('rider_money',$Activity->money);

            if ($User && UserBill::insert($inserUserBill) 
                && AwardList::insert($inserAwardList) 
                && Activity::where('id', $Activity->id)->increment('get_num')) {

                DB::commit();
                return respond(200,'领取成功！');
            }

            DB::rollBack();
            return respond(201,'领取失败！');
        } catch (\Exception $e) {
            DB::rollBack();
            return respond(201,'领取失败！');
        }
    }

    /**
     * [ToDayIsAlreadyAwardList 判断领取记录是否存在]
     * @param [type] $activity_id [description]
     */
    private function ToDayIsAlreadyAwardList($activity_id) {

        return AwardList::whereDate('created_at',date('Y-m-d'))
                            ->where('activity_id','>',0)
                            ->where('activity_id',$activity_id)
                            ->where('user_id',$this->user_id)
                            ->exists();
    }

    /**
     * [getTodayOrderNum 获取当天完成的订单量]
     * @return [type] [description]
     */
    private function getTodayOrderNum() {

        return Order::where('order_status',4)
                    ->where('area_id',$this->area_id)
                    ->whereDate('created_at',date('Y-m-d'))
                    ->where(function ($query){
                        $query->where('ps_id',$this->user_id)->orWhere('relay_id',$this->user_id);
                    })
                    ->count();
    }

    /**
     * [getActivity 获取奖励数据]
     * @return [type] [description]
     */
    private function getActivity() {

        $Activity = Activity::where(['area_id'=>$this->area_id,'status'=>1,'type'=>1])
                            ->whereRaw('`start_time` <= now() and `end_time` >= now()')
                            ->first();
        if ( empty($Activity) ) { throw new MyException("活动未找到！");}

        return $Activity;
    }
}