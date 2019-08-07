<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberRule extends Model
{
	use SoftDeletes;
	
    protected $table = 'member_rule';
	protected $hidden = ['updated_at', 'deleted_at'];
}