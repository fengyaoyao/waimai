<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgencyBill extends Model
{
    use SoftDeletes;
    protected $table = 'agency_bill';
	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
    
    protected $guarded = [];

    /**
     * 订单
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo('\App\Model\Order','order_id')->select(['order_id','order_sn']);
    }
}