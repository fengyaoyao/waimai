<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Cart extends Model
{
	protected $primaryKey    = 'cart_id';
	protected $guarded       = [];
	public function goods()
    {
        return $this->belongsTo('\App\Model\Goods','goods_id');
    }
}