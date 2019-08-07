<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class RiderAreaShift extends Model
{
	protected $table = 'rider_area_shift';

    //根据当前时间查询符合打卡班次
    public function scopeMeet($query, $apply_id,$area_id = '',$is_times = false)
    {
        $Time = date('H:i:s');
        return $query->when($area_id, function ($query) use ($area_id) {
        	return $query->where('area_id', $area_id);
        })
        ->whereRaw("`apply_id` = {$apply_id} and `week` = date_format(now(),'%w')")
        ->when($is_times, function ($query) use ($Time) {
            return $query->whereTime('end_time','>=',$Time)->whereTime('start_time','<',$Time);
        });
    }
}