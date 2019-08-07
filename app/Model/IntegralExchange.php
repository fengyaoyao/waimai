<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegralExchange extends Model
{
    protected $table = 'integral_exchange';
    
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function integral_goods()
    {
        return $this->belongsTo('\App\Model\IntegralGoods','integral_goods_id');
    }
}