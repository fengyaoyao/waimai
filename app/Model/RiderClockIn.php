<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class RiderClockIn extends Model
{
	protected $table = 'rider_clock_in';
    protected $hidden = ['updated_at'];
    protected $guarded  = [];
}