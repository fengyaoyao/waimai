<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ShopGroup extends Model

{

	protected $table = 'shop_group';


    protected $hidden     = ['settlement', 'updated_at','created_at'];

	

}