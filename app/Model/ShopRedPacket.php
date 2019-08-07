<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;


class ShopRedPacket extends Model
{
    use SoftDeletes;

    protected $table = 'shop_red_packets';

	protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    protected $guarded = [];

    protected $appends = ['is_acquire'];

    public function getIsAcquireAttribute(){

        if (empty(Auth::id())) {
            return false;
        }

        $where = [
            'status' => 0,
            'user_id' => Auth::id(),
            'shop_id' => $this->attributes['shop_id'],
            'red_packet_id' => $this->attributes['id'],
            'type' => ( $this->attributes['type'] + 1),
        ];
        
        return UserRedPacket::where($where)->exists();
    }
}