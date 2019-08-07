<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerBill extends Model
{
    use SoftDeletes;

    protected $table = 'mer_bill';
	protected $primaryKey = 'bill_id';
	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
    
    protected $guarded       = [];
    /**
     * 用户
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo('\App\Model\Shop','shop_id');
    }
     /**
     *商户
     * @return [type] [description]
     */
    public function shop()
    {
        return $this->belongsTo('\App\Model\Shop','shop_id');
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
            case '1':
                $str = '订单收入';
                break;
            case '2':
                $str = '取消订单';
                break;
            case '3':
                $str = '补贴';
                break;
            case '4':
                $str = '提现';
                break;
            case '5':
                $str = '拒绝提现！';
                break;
        }
        return  [
            'key' => $value,
            'value' => $str,
        ];
    }
}