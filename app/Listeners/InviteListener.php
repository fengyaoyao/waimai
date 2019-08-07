<?php
namespace App\Listeners;

use App\Events\OtherTrigger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Model\{Order,User, Activity, InviteConf, InviteRecord, AccountLog};
use Illuminate\Support\Facades\DB;

class InviteListener
{
    public function handle(OtherTrigger $event)
    {

        $user_id = $event->user_id;
        $order_id = $event->order_id;

        try {
            //判断是否是首单
            $count = Order::where('user_id',$user_id)->where('order_status',4)->count();

            if ($count != 1) {
                return "该用户不是首单！";
            }

            DB::beginTransaction(); //开启事务

            //查询邀请记录
            $InviteRecord = InviteRecord::where(['to_user_id' => $user_id,'flag' => 0])->first();
            if (empty($InviteRecord)) {
                return "邀请记录未找到！";
            }

            //查询上级用户
            $first_leader = User::where('user_id',$InviteRecord->user_id)->first();
            if (empty($first_leader)) {
                return "上级用户未找到！";
            }

            //判断邀请有奖活动是否存在
            $Activity = Activity::where('id',$InviteRecord->activity_id)
                                ->whereRaw('`start_time` <= now() and `end_time` >= now()')
                                ->first();
            if (empty($Activity)) {
                return "邀请有奖活动未找到！";
            }

            //获取邀请有奖活动针对性配置表
            $InviteConf = InviteConf::where('id',$InviteRecord->invite_conf_id)
                                    ->where('type',$first_leader->type)
                                    ->where('status',1)
                                    ->whereRaw('`start_time` <= now() and `end_time` >= now()')
                                    ->first();
            if (empty($InviteConf)) {
                return "邀请有奖活动针对性配置未找到！";
            }

            //为邀请者返利
            $award_money = User::where('user_id',$InviteRecord->user_id)->increment('moeny',$InviteRecord->initiative_award_money);

            //邀请者返利记录
            $AccountLog = AccountLog::insert([
                                        'user_money' => $InviteRecord->initiative_award_money,
                                        'desc' => '邀请有奖',
                                        'user_id' => $InviteRecord->user_id,
                                        'order_id' => $order_id,
                                        'created_at' => date('Y-m-d H:i:s')
                                    ]);

            //跟新邀请记录
            $InviteRecord->order_id = $order_id;
            $InviteRecord->flag = 1;

            if ($InviteRecord->save() && $award_money && $AccountLog) {
                DB::commit();//提交事务
                return true;
            }

            DB::rollBack();//事务回滚
            return "邀请者返利和记录更新失败！";

        } catch (\Exception $e) {
            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
        }
    }
}