<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class AwardList extends Model
{
	protected $table = 'award_list';

	protected $hidden = [
        'money',
        'updated_at',
        'flag'
    ];

    public function ps_info()
    {
        return $this->belongsTo('\App\Model\User','user_id','user_id')->select(['nickname','user_id','realname','mobile','headimgurl']);
    }
}