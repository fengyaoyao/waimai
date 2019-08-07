<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Model\Bound;

class ShopIsBound implements Rule
{
    public $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * 判断验证规则是否通过。
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
       return Bound::where('user_id',$this->user_id)->where('shop_id',$value)->exists();
    }

    /**
     * 获取验证错误信息。
     *
     * @return string
     */
    public function message()
    {
        return '未找到绑定的店铺！';
    }
}
