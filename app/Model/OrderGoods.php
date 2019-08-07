<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class OrderGoods extends Model
{
	protected $primaryKey    = 'rec_id';
	protected $table         = 'order_goods';
    protected $hidden        = [ 'created_at', 'updated_at'];

	public function order()
    {
        return $this->belongsTo('\App\Model\Order','order_id');
    }
    public function goods()
    {
        return $this->belongsTo('\App\Model\Goods','goods_id');
    }
}