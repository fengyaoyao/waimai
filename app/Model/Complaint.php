<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Complaint extends Model
{
	public function order()
    {
        return $this->belongsTo('App\Model\Order','order_id','order_id');
    }
}