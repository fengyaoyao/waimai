<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class SpecItem extends Model
{
	protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $primaryKey = 'item_id';
    
	public function spec()
	{
		return $this->belongsTo('\App\Model\Spec', 'spec_id')->where('item','<>','');
	}
}