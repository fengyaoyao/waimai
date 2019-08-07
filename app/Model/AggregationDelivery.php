<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AggregationDelivery extends Model
{
    use SoftDeletes;

    protected $table = 'aggregation_delivery';

	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
    
    protected $guarded = [];
}