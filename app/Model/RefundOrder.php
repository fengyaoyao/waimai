<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundOrder extends Model
{
    use SoftDeletes;

    protected $table      = 'refund_order';
	protected $primaryKey = 'refund_id';
	protected $hidden     = ['updated_at','deleted_at'];
    protected $guarded    = [];

    /**
     * 用户
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo('\App\Model\User','user_id');
    }

    /**
     * 订单
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo('\App\Model\Order','order_id');
    }

    /**
     * [getDistributionStatusAttribute 谁取消订单状态]
     * @param  [string] $value [配送类型值]
     * @return [array]        
     */
    public function getTypeAttribute($value)
    {

        $str = '';
        switch ($value)
        {
            case '0':
                $str = '用户提交退款';
                break;
            case '1':
                $str = '商家提交退款';
                break;
        }
        return  [
            'key' => $value,
            'value' => $str,
        ];
    }
}