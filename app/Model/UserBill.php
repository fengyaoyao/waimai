<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class UserBill extends Model
{
    use SoftDeletes;
    protected $table = 'user_bill';
	protected $primaryKey = 'bill_id';
	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
    
    protected $guarded = [];

    /**
     * 用户
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo('\App\Model\User','user_id','user_id');
    }

    /**
     * 订单
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo('\App\Model\Order','order_id')
        ->select(['order_id','floor_amount','day_num','order_sn','order_amount','total_amount','horseman_amount','ps_formula','ps_id','shop_id']);
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
                $str = '配送收入';
                break;
            case '1':
                $str = '补贴收入';
                break;
            case '2':
                $str = '提现';
                break;
            case '3':
                $str = '提现失败！';
                break;
        }
        return  [
            'key' => $value,
            'value' => $str,
        ];
    }

    public function getMoneyAttribute($value)
    {
        $prefix = '';
        switch ($this->attributes['type'])
        {
            case '0':
                $prefix = '+￥';
                break;
            case '1':
                $prefix = '+￥';
                break;
            case '2':
                $prefix = '-￥';
                break;
            case '3':
                $prefix = '+￥';
                break;
        }
        return  $prefix.$value;
    }
}