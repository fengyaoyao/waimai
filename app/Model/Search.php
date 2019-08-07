<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Search extends Model
{
	protected $table  = 'search';
	protected $hidden = ['updated_at','created_at'];

}