<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model

{

    protected $appends = ['last_money'];

    

    public function user()

    {

        return $this->belongsTo('App\Model\User','user_id');

    }



    public function getLastMoneyAttribute($value)

    {

        return  round($this->attributes['before_money'] - $this->attributes['money'],2);

    }



    /**

     * 设置提现账户名称

     */

    public function setBankNameAttribute($value)

    {

        switch ($value)

        {

            case '1':

                $this->attributes['bank_name'] = '支付宝';

                return '支付宝';

                break;

            case '0':

                $this->attributes['bank_name'] = '微信';

                break;

        }

    }



    /**

     * 设置提现描述

     */

    public function setRemarkAttribute($value)

    {

        switch ($value)

        {

            case '2':

                return '骑手提现';

                break;

            case '1':

                return '商户提现';

                break;

            case '0':

                return '用户提现';

                break;

        }

    }



    /**

     * 设置提现账户账号

     */

    public function setBankCardAttribute($value)

    {

        switch ($value)

        {

            case '1':

                $this->attributes['bank_card'] = \App\Model\User::where('user_id',$this->attributes['user_id'])->value('alipay_number');

                break;

            case '0':

                $this->attributes['bank_card'] = \App\Model\User::where('user_id',$this->attributes['user_id'])->value('wechat_number');

                break;

            default:

                $this->attributes['bank_card'] = $value;

                break;

        }

    }

}