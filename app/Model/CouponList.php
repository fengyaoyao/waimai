<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CouponList extends Model
{
    protected $table   = 'coupon_list';
    public $timestamps = false;

    public function goods()
    {
        return $this->hasMany('App\Model\User','uid');
    }

    public function coupon()
    {
        return $this->belongsTo('App\Model\Coupon','cid');
    }

    //查询存在的优惠卷
    public function scopeCouponExists($query,$couponids = '') {

        $pr = env('DB_PREFIX');
        $date = date('Y-m-d');
        
        // if (is_array($couponids)) {

        //     $inids = 0;

        //     if (!empty($couponids)) {
        //         $inids = join(',',$couponids);
        //     }

        //     return $query->whereRaw("{$pr}coupon_list.`status` = 0 and exists (
        //         select 1 from {$pr}coupon 
        //         where {$pr}coupon.`id` = {$pr}coupon_list.`cid` 
        //         and (
        //             {$pr}coupon.`use_type` = 0 
        //             or ({$pr}coupon.`use_type` = 1 and {$pr}coupon.`id` in ({$inids}))
        //         )and (
        //             {$pr}coupon.`status` = 0 
        //             or date(`use_end_time`) = {$date}
        //             or (DATE_SUB(CURDATE(), INTERVAL 15 DAY) <= date({$pr}coupon.`use_end_time`))
        //         )
        //     )");

        // }else{


            return $query->whereRaw("{$pr}coupon_list.`status` = 0 and exists (
                select 1 from {$pr}coupon 
                where {$pr}coupon.`id` = {$pr}coupon_list.`cid` 
            and (
                {$pr}coupon.`status` = 0 or date(`use_end_time`) = {$date}
                or DATE_SUB(CURDATE(), INTERVAL 15 DAY) <= date({$pr}coupon.`use_end_time`))
            )");
        // }
    }
}
