<?php

namespace App\Http\Controllers\Traits;



use App\Model\{AreaShift, ShiftApply, RiderAreaShift, RiderClockIn, Area};
use Illuminate\Support\Facades\Auth;
use App\Exceptions\MyException;

trait SignIn
{
    public function check($user_id,$rider_type = 0,$area_id) {

        $is_open_shift = Area::where('area_id',$area_id)->pluck('is_open_shift');

        if (!$is_open_shift) {
            throw new MyException('该区域已关闭值班！',1);
        }
        
        if (!(ShiftApply::where('user_id',$user_id)->exists())) {
            throw new MyException('你暂未申请值班！',4);
        }

        $apply_id = ShiftApply::check($user_id)->value('id');

        if (empty($apply_id)) {
            throw new MyException('不满足值班条件！',2);
        }

        $AreaShift = RiderAreaShift::meet($apply_id,$area_id,true)->first();

        if(empty($AreaShift)){
            throw new MyException('不满足打卡条件！',3);
        }


        $level = '';

        switch (date('w')) {

            case '0':

                $level = $rider_type ? $AreaShift ->j_level_weekend : $AreaShift ->q_level_weekend;

                break;

            case '6':

                $level = $rider_type ? $AreaShift ->j_level_weekend : $AreaShift ->q_level_weekend;

                break;

            default:

                $level = $rider_type ? $AreaShift ->j_level : $AreaShift ->q_level;

                break;
        }

        return [

            'level' => $level,

            'user_id' => $user_id,

            'week' => $AreaShift->week,

            'shift_id' => $AreaShift->shift_id,

            'start_time' => $AreaShift->start_time,

            'end_time' => $AreaShift->end_time
        ];
    }

    /**
     * 自动打卡
     * @return [type] [description]
     */
    public function autoExecute() {

        try {

            $user = Auth::user();
            $user_id = $user->user_id;
            $area_id =  $user->area_id;
            $rider_type = $user->rider_type;

            $Area = Area::where('area_id',$area_id)->value('is_clock_in');

            if (!empty($Area)) {

                $data = $this->check($user_id,$rider_type,$area_id);

                unset($data['start_time']);
                unset($data['end_time']);

                $RiderClockIn = RiderClockIn::where($data)->whereDate('created_at',date('Y-m-d'))->first();

                if(empty($RiderClockIn)) {

                    RiderClockIn::create(array_merge($data,['status'=>1,'call_time'=>date('H:i:s')]));

                }else{

                    $RiderClockIn->status = 1;
                    $RiderClockIn->call_time = date('H:i:s');
                    $RiderClockIn->save();
                }
            }

        } catch (\Exception $e) {
            
        }
    }
}