<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class AreaShift extends Model
{
	protected $table       = 'area_shift';
	protected $primaryKey  = 'shift_id';

	//查询符合打卡班次
    public function scopeShift($query, $shift_id)
    {
        $Time = date('H:i:s');
        return $query->where('shift_id',$shift_id)->whereTime('end_time','>=',$Time)->whereTime('start_time','<',$Time);
    }
}