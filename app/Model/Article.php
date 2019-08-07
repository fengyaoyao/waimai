<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Article extends Model

{

	protected $table         = 'article';

	protected $primaryKey    = 'article_id';

    protected $appends = ['jump_url'];


    public function getJumpUrlAttribute()
    {
    	return  env('BACKEND_DN').'Mobile/Article/index/cat_id/'. $this->attributes['cat_id'] . '/area_id/' . $this->attributes['area_id'];
    }
}