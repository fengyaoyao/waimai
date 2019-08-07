<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    
    protected $primaryKey = 'role_id';
    protected $table      = 'admin_role';
    protected $casts      = ['home_menu' => 'array'];
    public $timestamps    = false;
}
