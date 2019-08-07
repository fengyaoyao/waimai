<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegralGoods extends Model
{
    use SoftDeletes;

    protected $table = 'integral_goods';

    protected $hidden = ['updated_at','deleted_at'];

    protected $appends = ['candicine_url'];

	/**
	 * [getCandicineUrlAttribute 积分商品详情地址]
	 * @return [type] [description]
	 */
    public function getCandicineUrlAttribute()
    {
        return  env('BACKEND_DN').'mobile/Integral/index?id='.$this->attributes['id'];
    }
}