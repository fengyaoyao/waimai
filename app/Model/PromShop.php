<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PromShop extends Model
{
	protected $table      = 'prom_shop';
	protected $primaryKey = 'prom_id';

    protected static function boot()
    {
        parent::boot();

        $Prom0 = DB::table('prom_shop')->where('status',1)->where('type',0)->get();
        $map   = [[],[]];

        foreach ($Prom0  as $key => $value)
        {
            $time = time();
        	if(strtotime($value->start_time) < $time && strtotime($value->end_time) > $time)
        	{
        		if(!$value->status ) $map[1][] = $value->prom_id;
        	}else{
        		if($value->status ) $map[0][] = $value->prom_id;
        	}
        }

        foreach ($map as $key => $value) 
        {
        	if(empty($value)) continue;
	        DB::table('prom_shop')->whereIn('prom_id',$value)->update(['status'=>$key]);
        }
    }

    public function getTitleAttribute($value) {
        try {
            switch ($this->attributes['type']) {
                case '0':
                    return '满'.$this->attributes['condition'].'减'.$this->attributes['money'];
                    break;

                case '1':
                    return '满'.$this->attributes['condition'].'赠'.str_replace('满'.$this->attributes['condition'].'赠','',$this->attributes['title']);
                    break;

                case '2':
                    return '首单立减'.$this->attributes['money'];
                    break;
            }

        } catch (\Exception $e) {
            return $value;
        }
    }
}