<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Comment extends Model
{
	protected $table      = 'comment';
    protected $primaryKey = 'comment_id';

    public function comment_reply()
    {
        return $this->hasMany('App\Model\CommentReply','comment_id');
    }
    public function order_goods()
    {
        return $this->hasMany('App\Model\OrderGoods','order_id','order_id');
    }
    
    public function order()
    {
        return $this->belongsTo('App\Model\Order','order_id','order_id');
    }

    public function shop()
    {
        return $this->hasMany('App\Model\Shop','shop_id','shop_id');
    }
}
