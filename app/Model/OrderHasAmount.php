<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class OrderHasAmount extends Model
{
	protected $primaryKey    = 'order_id';
	protected $table         = 'order_has_amount';
}