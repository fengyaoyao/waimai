<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Goods extends Model
{
    use SoftDeletes;
	protected $primaryKey = 'goods_id';
	protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    protected $dates   = ['deleted_at'];
    
    protected $appends = ['is_show','favorable'];

    public function findForId($shop_id)
    {
        return self::where('shop_id', $shop_id)->first();
    }

    public function user()
    {
        return $this->hasOne('\App\Model','user_id');
    }

    public function itme()
    {
        return $this->hasMany('\App\Model\SpecItem','goods_id');
    }

    public function spec()
    {
        return $this->hasMany('\App\Model\Spec', 'goods_id');
    }

    public function itmeForSpec()
    {
        return $this->hasMany('\App\Model\Spec', 'goods_id')->with('item');
    }

    //此字段用于前端展示
    public function getIsShowAttribute()
    {
        return  false;
    }

    //好评率
    public function getFavorableAttribute()
    {
        if(!empty($this->attributes['stamp'])) {

            if( $this->attributes['stamp'] > 0) {
                return  round($this->attributes['stamp'] / $this->attributes['sold_num'] * 100);
            }
        } 
        return 100;
    }
}