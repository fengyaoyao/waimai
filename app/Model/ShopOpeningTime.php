<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ShopOpeningTime extends Model
{
	protected $table      = 'shop_opening_times';
    protected $primaryKey = 'time_id';
	protected $hidden     = ['updated_at','created_at','deleted_at'];
}