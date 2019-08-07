<?php

namespace App\Model\Buyer;



use Illuminate\Database\Eloquent\Model;

use App\Model\{UserAddress, AreaDelivery, Area, Order, DeliveryGroup};

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;







class Shop extends Model

{

    use SoftDeletes;

    

    protected $hidden = [

        'created_at',

        'updated_at',

        'deleted_at'

    ];



    protected $primaryKey = 'shop_id';

    protected $casts      = ['settlement' =>'array', 'identification_photo'=>'array'];

    // protected $appends    = ['is_first'];



    protected static function boot()

    {

        parent::boot();

        event(new \App\Events\Shop);

    }



    public  function findForId($shop_id)

    {

        return self::where('shop_id', $shop_id)->first();

    }



    public function goods()

    {

        return $this->hasMany('App\Model\Goods','shop_id');

    }



    public function user()

    {

        return $this->hasOne('App\Model\User','user_id');

    }

    

    public function area()

    {

        return $this->belongsTo('App\Model\Area','area_id');

    }

    

    public function prom()

    {

        return $this->hasMany('\App\Model\PromShop','shop_id');

    }



    public function spec()

    {

        return $this->hasMany('\App\Model\Spec','shop_id');

    }



    public function recommend_goods()

    {

        return $this->hasMany('App\Model\Goods','shop_id')->where('shop_recommend',1)->where('shelves_status',1);

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



    public function business_info()

    {

        return  $this->belongsTo('App\Model\User','user_id')->select(['user_id','nickname','push_id','type']);

    }

    

    public function shop_type()

    {

        return  $this->belongsTo('App\Model\ShopType','type_id')->select(['type_id','type_name']);

    }

    

    

    public function printer()

    {

        return $this->belongsTo('App\Model\Printer','printer_id');

    }



    public function openingTime()

    {

        return $this->hasMany('App\Model\ShopOpeningTime','shop_id','shop_id');

    }



    /**

     * [getCustomDeliveryAttribute 获取店铺基础配送费]

     * @param  [type] $value [description]

     * @return [type]        [description]

     */

    public function getCustomDeliveryAttribute($value)

    {

        if (\Auth::check()) {



            $UserAddress = UserAddress::where('user_id', \Auth::id())->where('area_id',$this->attributes['area_id'])->orderByDesc('is_default')->select('delivery_pid','delivery_id')->first();



            if (!empty( $UserAddress)) {



                //基础配送费

                $basic_distribution_fee =  DeliveryGroup::Delivery($this->attributes['group_id'],$UserAddress->delivery_pid,0);

                //楼层配送费

                $floor_distribution_fee =  DeliveryGroup::Delivery($this->attributes['group_id'],$UserAddress->delivery_id,$UserAddress->delivery_pid);

                //用户支付的配送费

                return  $basic_distribution_fee + $floor_distribution_fee;

            }

        }



        return DeliveryGroup::DefaultDelivery($this->attributes['group_id']);

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