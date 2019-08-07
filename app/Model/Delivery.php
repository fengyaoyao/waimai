<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Delivery extends Model
{

    use SoftDeletes;
    protected $table      = 'delivery';
	protected $primaryKey = 'delivery_id';
	protected $hidden     = [
        'created_at',
        'updated_at',
        'deleted_at',
        'group_id',
        'delivery_price',
        'base_shipping_fee',
        'commission_rake'
    ];
    protected $dates = ['deleted_at'];
}