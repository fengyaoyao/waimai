<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRedPacket extends Model
{
    use SoftDeletes;

    protected $table = 'user_red_packets';

	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    protected $guarded = [];
}