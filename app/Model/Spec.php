<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Spec extends Model
{
    protected $primaryKey = 'spec_id';
	
	protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    
	function item()
	{
		return $this->hasMany('\App\Model\SpecItem','spec_id');
	}
}