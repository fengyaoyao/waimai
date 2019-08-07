<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Bound extends Model
{
	protected $table = 'bounds';

    protected $primaryKey = 'bound_id';
	
	protected $hidden = [
        'created_at',
        'updated_at'
    ];
}