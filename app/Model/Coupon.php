<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model
{
	protected $table = 'coupon';
	protected $hidden = [
        'send_start_time',
        'send_end_time',
        'send_num',
        'createnum',
        'use_num',
        'type'
    ];
    public function coupon_list()
    {
        return $this->hasMany('App\Model\CouponList','cid');
    }
}