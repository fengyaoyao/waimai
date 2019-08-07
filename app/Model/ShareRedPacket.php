<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class ShareRedPacket extends Model
{
	protected $table = 'share_red_packet';
    protected $hidden = [ 'deleted_at', 'updated_at','created_at'];
}