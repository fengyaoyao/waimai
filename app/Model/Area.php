<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Area extends Model
{
	use SoftDeletes;

	protected $primaryKey = 'area_id';
	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}