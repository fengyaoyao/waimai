<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Award extends Model
{
	protected $table         = 'award';
	protected $primaryKey    = 'award_id';
	protected $hidden = [
        'send_type',
        'created_at',
        'updated_at'
    ];
}