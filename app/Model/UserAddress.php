<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{

    protected $guarded = [];
    protected $primaryKey = 'address_id';
    protected $appends = ['address_prefix'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function delivery()
    {
        return $this->belongsTo('App\Model\Delivery','delivery_id');
    }
    
    public function area()
    {
        return $this->belongsTo('App\Model\Area','area_id');
    }

    // 获取宿舍和楼层
    public function getAddressPrefixAttribute()
    {
        if (empty( $this->attributes['area_id']) || 
            empty($this->attributes['delivery_pid']) || 
            empty($this->attributes['delivery_id']) || 
            empty($this->attributes['address'] ) ) {
           return '';
        }

        $area_name = \App\Model\Area::where('area_id',$this->attributes['area_id'])->value('address');
        $build_name_one = \App\Model\Delivery::where('delivery_id',$this->attributes['delivery_pid'])->value('build_name');
        $build_name_two = \App\Model\Delivery::where('delivery_id',$this->attributes['delivery_id'])->value('build_name');
        $sroom = $this->attributes['address'];

        if (empty( $area_name) || 
            empty($build_name_one) || 
            empty($build_name_two) || 
            empty($sroom ) ) {
           return '';
        }

        return "{$area_name}{$build_name_one} {$build_name_two} {$sroom}";
    }
}