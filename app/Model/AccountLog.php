<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AccountLog extends Model
{

	protected $table      = 'account_log';
	protected $primaryKey = 'log_id';
	protected $guarded    = [];
	protected $hidden     = ['frozen_money', 'updated_at', 'deleted_at'];    
}