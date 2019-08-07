<?php 
namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Activity;

class ActivityController extends Controller
{
    /**
     * 活动详情
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function info(Request $request)
    {
        $this->validate($request, ['activity_id'=>'required|integer|exists:activity,id'],$this->message);

        $Activity = Activity::where(['id'=>$request->activity_id])->select(['name','desc','img'])->first();

        $url =  env('BACKEND_DN').'mobile/Invite/index?id='.$request->activity_id;

        return respond(200,'获取成功！',['activity'=>$Activity,'url'=>$url]);
    }

    public function staticShare(Request $request) {

        $this->validate($request, ['area_id' => 'required|integer|exists:areas,area_id'],$this->message);

        $Activity = Activity::where('area_id',$request->area_id)->where('type',2)->select(['name','desc','img','status'])->first();

        if (!empty($Activity)) {

            $Activity->info_url =  env('BACKEND_DN')."mobile/Invite/index";

        }else{

            $Activity['info_url'] =  env('BACKEND_DN')."mobile/activity/empty.html";
            $Activity['name'] = "";
            $Activity['desc'] = "";
            $Activity['img']  = "";
            $Activity['status']  = 0;
        }
        
        return respond(200,'获取成功！',$Activity);
    }
}