<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRedPacket extends Model
{
    use SoftDeletes;

    protected $table = 'exchange_red_packets';

	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    protected $guarded = [];
}