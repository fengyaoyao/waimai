<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class ShopType extends Model
{
	protected $table = 'shop_type';
	protected $primaryKey = 'type_id';
    protected $hidden     = [ 'deleted_at', 'updated_at','created_at'];
	
}