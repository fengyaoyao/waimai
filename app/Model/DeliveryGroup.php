<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryGroup extends Model
{
    public $timestamps = false;
    protected $table = 'delivery_group';

	//获取一条默认
    public function scopeDefaultDelivery($query, $group_id)
    {
        return $query->where('group_id', $group_id)->where('delivery_pid', 0)->value('delivery_price') ?? 0;
    }

	//根据店铺分组id和用户收货楼层、楼 查询基础配送费或楼层费
    public function scopeDelivery($query, $group_id, $delivery_id,$delivery_pid = 0)
    {
        return $query->where('group_id', $group_id)->where('delivery_id',$delivery_id)->where('delivery_pid',$delivery_pid)->value('delivery_price') ?? 0;
    }
}