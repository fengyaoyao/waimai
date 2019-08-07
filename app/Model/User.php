<?php
namespace App\Model;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Crypt;


class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, Authenticatable, Authorizable;
    
    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email'
    ];
    // public $timestamps = true;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'first_leader',
        'frozen_money',
        'access_token',
        'password',
        'created_at',
        'updated_at'
    ];

    public function findForPassport($username)
    {
        // //匹配手机号码
        // if(preg_match("/^1[3456789][0-9]{9}$/",$username)) {

        //     return self::whereNotNull('mobile')->where('mobile','<>','')->where('mobile', $username)->first();
        // //匹配邮箱
        // }elseif (preg_match("/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2})?/i",$username)) {

        //     return self::whereNotNull('email')->where('email','<>','')->where('email', $username)->first();
        // }else{

            return self::whereNotNull('username')->where('username','<>','')->where('username', $username)->first();
        // }
    }

    public function validateForPassportPasswordGrant($password)
    {
        return $password == Crypt::decryptString($this->password);
    }

    public function area()
    {
        return $this->belongsTo('App\Model\Area','area_id');
    }

    public function address()
    {
        return $this->hasMany('App\Model\UserAddress','user_id')->select(['address_id','user_id','area_id','consignee','floor','building','address','mobile','delivery_id','delivery_pid']);
    }

    public function DefualtAddress()
    {
        return $this->hasOne('App\Model\UserAddress','user_id')->select(['address_id','user_id','area_id','consignee','floor','building','address','mobile','delivery_id','delivery_pid'])->where('is_default',1);
    }

    public function couponList()
    {
        return $this->hasMany('App\Model\CouponList','uid','user_id');//->where('status',0)->select(['cid'])->with('coupon');
    }

    public function order()
    {
        return $this->hasMany('App\Model\Order','user_id','user_id');
    }

    public function order_ps()
    {
        return $this->hasMany('App\Model\Order','ps_id','user_id');
    }

    public function shops()
    {
        return $this->belongsToMany('App\Model\Shop','bounds','user_id','shop_id');
    }
}