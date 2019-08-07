<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class ShiftApply extends Model
{
	protected $table = 'shift_apply';
    protected $hidden = [ 'updated_at','created_at'];

    //检查是否通过申请并且满足打卡时间
    public function scopeCheck($query, $user_id)
    {
        return $query->whereRaw("status = 1 and user_id = {$user_id}  and now() >= shift_time");
    }
}