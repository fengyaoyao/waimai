<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    
    protected $primaryKey = 'admin_id';
    protected $table      = 'admin';
    protected $casts      = ['area_id' => 'array','user_id'=>'array'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'created_at',
        'updated_at'
    ];

    public function findForPassport($username)
    {
        return self::where('user_name', $username)->first();
    }

    public function role()
    {
        return $this->belongsTo('App\Model\AdminRole','role_id');
    }
}
