<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InviteConf extends Model
{
    protected $table = 'invite_conf';
	protected $hidden = ['updated_at', 'deleted_at'];
}