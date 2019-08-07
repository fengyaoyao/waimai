<?php

namespace App\Model;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;



class Shop extends Model

{

    use SoftDeletes;

    

    protected $hidden = [

        'created_at',

        'updated_at',

        'deleted_at'

    ];



    protected $primaryKey = 'shop_id';

    protected $casts = ['settlement' => 'array','identification_photo'=>'array'];



    public  function findForId($shop_id)

    {

        return self::where('shop_id', $shop_id)->first();

    }



    public function goods()

    {

        return $this->hasMany('App\Model\Goods','shop_id');

    }


    public function search_goods()

    {

        return $this->belongsTo('App\Model\Goods','shop_id');

    }



    public function user()

    {

        return $this->hasOne('App\Model\User','user_id');

    }

    

    public function prom()

    {

        return $this->hasMany('\App\Model\PromShop','shop_id');

    }



    public function area()

    {

        return $this->belongsTo('App\Model\Area','area_id');

    }



    public function spec()

    {

        return $this->hasMany('\App\Model\Spec','shop_id');

    }



    public function recommend_goods()

    {

        return $this->hasMany('App\Model\Goods','shop_id');

    }



    public function cate()

    {

        return $this->hasMany('App\Model\GoodsCate','shop_id');

    }



    public function comment()

    {

        return $this->hasMany('App\Model\Comment','shop_id');

    }



    public function order()

    {

        return $this->hasMany('App\Model\Order','shop_id');

    }

    public function users()

    {

        return $this->belongsTo('App\Model\User','user_id');

    }



    public function business()

    {

        return $this->belongsToMany('App\Model\User','bounds','shop_id','user_id');

    }



    public function printer()

    {

        return $this->belongsTo('App\Model\Printer','printer_id');

    }



    public function openingTime()

    {

        return $this->hasMany('App\Model\ShopOpeningTime','shop_id','shop_id');

    }

    public function getIdentificationPhotoAttribute($value){

        if (empty($value)) {
            return [];
        }else{
            return json_decode($value,true);
        }
    }

    public function getTagsAttribute($value) {
      
        return preg_replace("/\s/is", "", $value);
    }

    public function redPacket()
    {
        return $this->hasMany('App\Model\ShopRedPacket','shop_id');
    }
}