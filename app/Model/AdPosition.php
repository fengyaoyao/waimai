<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class AdPosition extends Model
{
	protected $primaryKey = 'position_id';
	protected $table = 'ad_position';

    public function Ad()
    {
        return $this->hasMany('App\Model\Ad','pid');
    }
}