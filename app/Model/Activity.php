<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Activity extends Model
{
	protected $table = 'activity';
	protected $hidden = ['updated_at', 'created_at'];    
}