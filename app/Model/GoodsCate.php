<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class GoodsCate extends Model
{

    protected $primaryKey = 'cate_id';
    protected $hidden     = [ 'deleted_at', 'updated_at','created_at'];


    /**
     * 关联商品
     * @return [type]
     */
    public function goods()
    {
        return $this->hasMany('App\Model\Goods','cate_id')->where('shelves_status',1)->orderBy('sort');
    }
}