<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InviteRecord extends Model
{
    protected $table = 'invite_record';
	protected $hidden = ['updated_at', 'deleted_at'];
}