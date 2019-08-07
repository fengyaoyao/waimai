<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Collect extends Model
{
	protected $primaryKey = 'collect_id';
	protected $guarded = [];

	public function shop()
    {
        return $this->belongsTo('\App\Model\Shop','shop_id');
    }
}