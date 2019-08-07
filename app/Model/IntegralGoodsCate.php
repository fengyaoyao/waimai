<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegralGoodsCate extends Model
{
    use SoftDeletes;
    
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    // protected $table = 'integral_goods_cates';

}