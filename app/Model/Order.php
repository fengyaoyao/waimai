<?php

namespace App\Model;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Cache;





class Order extends Model

{

    use SoftDeletes;

    

    protected $primaryKey    = 'order_id';

    protected $table         = 'order';

    protected $guarded       = [];

    protected $hidden        = [ 'deleted_at', 'updated_at'];

    protected $appends       = ['is_connect','shipping_fee'];





    public function order_goods()

    {

        return $this->hasMany('\App\Model\OrderGoods','order_id');

    }



    public function order_shop()

    {

        return $this->hasOne('\App\Model\Shop','shop_id','shop_id');

    }



    public function order_shop_prom()

    {

        return $this->hasMany('\App\Model\OrderShopProm','order_id');

    }



    public function order_ps()

    {

        return $this->belongsTo('\App\Model\User','ps_id','user_id');

    }



    public function area()

    {

        return $this->belongsTo('\App\Model\Area','area_id','area_id');

    }



    public function shop()

    {

        return $this->belongsTo('\App\Model\Shop','shop_id','shop_id');

    }

    

    public function user()

    {

        return $this->belongsTo('\App\Model\User','user_id','user_id');

    }



    public function ps_info()

    {

        return $this->belongsTo('\App\Model\User','ps_id','user_id')->select(['nickname','push_id','user_id','type','realname','mobile','headimgurl','rider_type']);

    }



    public function relay_info()

    {

        return $this->belongsTo('\App\Model\User','relay_id','user_id')->select(['nickname','push_id','user_id','type','realname','mobile','headimgurl','rider_type']);

    }



    public function getShopName()

    {

        return $this->belongsTo('\App\Model\Shop','shop_id','shop_id')->select(['shop_id','shop_name']);

    }



    public function order_distribution()

    {

        return $this->hasOne('\App\Model\OrderDistribution','order_id','order_id')->select(['order_id','one_formula','two_formula','one_money','two_money']);

    }

    /**
     * [afterAale 售后申请]
     * @return [type] [description]
     */
    public function afterSale()
    {
        return $this->hasOne('\App\Model\OrderAfterSale','order_id','order_id');
    }





    /**

     * [getIsConnectAttribute 配送接力是否交接]

     * @return [array]        

     */

    public function getIsConnectAttribute()

    {



        if (empty($this->attributes['relay_time'])) {

           return false;

        } else {

           return true;

        }

    }



 

    /**

     * [getDistributionStatusAttribute 订单配送类型]

     * @param  [string] $value [配送类型值]

     * @return [array]        

     */

    public function getDistributionStatusAttribute($value)

    {

        switch ($value)

        {

            case '1':

                $str = '无配送员接单';

                break;

            case '2':

                $str = '配送员长期未取餐';

                break;

            case '3':

                $str = '顾客到店自提';

                break;

            case '4':

                $str = '其他';

                break;

            default:

                $str = '骑手配送';

                break;

        }

        return  [

            'key' => $value,

            'value' => $str,

        ];

    }



    /**

     * [getDistributionStatusAttribute 谁取消订单状态]

     * @param  [string] $value [配送类型值]

     * @return [array]        

     */

    public function getWhoCancelAttribute($value)

    {

        $str = '';

        if ($this->attributes['order_status'] == 3)

        {

            switch ($value)

            {

                case '1':

                    $str = '店铺取消';

                    break;

                default:

                    $str = '用户取消';

                    break;

            }

        }



        if ($this->attributes['order_status'] == 5)

        {

            $str = '店铺拒绝';

        }
        if ($this->attributes['order_status'] == 6)

        {

            $str = '用户取消';
        }



        return  [

            'key' => $value,

            'value' => $str,

        ];

    }



    public function getBasicDistributionFeeAttribute() {



            if (empty($this->attributes['delivery_cost'])) {

                return 0;

            }

        

            if (empty($this->attributes['area_id'])) {

                return $this->attributes['delivery_cost'];

            }



            $area_id = $this->attributes['area_id'];



            $settlement = Cache::get($area_id);



            if (empty($settlement)) {

                $settlement = Area::where('area_id',$area_id)->value('settlement');

                Cache::add($area_id, $settlement, 7200);

            }



            $settlementArr = json_decode($settlement,true);



            if(!empty($settlementArr['delivery_ratio'] )) {



                return round( $this->attributes['delivery_cost'] * ($settlementArr['delivery_ratio']/100),2);

            }else{

                return $this->attributes['delivery_cost'];

            }

    }



    //获取用户付款的配送费

    public function getShippingFeeAttribute() {



        if (empty($this->attributes['delivery_cost']) || empty($this->attributes['floor_amount'])) {

            return 0;

        }

        

        return  round($this->attributes['delivery_cost'] + $this->attributes['floor_amount'],2);

    }





    //用于配送端查询

    public function scopeDistribution($query, $user_id)

    {

        return $query->whereRaw("(ps_id = {$user_id} or relay_id = {$user_id}) and order_status = 4");

    }


    //获取用户付款的配送费

    public function getDayNumAttribute($value) {

        if ($value == '0') {
            return '待接';
        }
        
        return $value;
    }

}