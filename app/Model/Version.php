<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Version extends Model
{
    protected $hidden = [
        'deleted_at',
        'updated_at',
        'status'
    ];

	public function getClientSideAttribute($value)
    {

        $str = '';
        switch ($value)
        {
            case '0':
                $str = '买家端';
                break;
            case '1':
                $str = '商家端';
                break;
            case '2':
                $str = '骑手端';
                break;
        }
        return   $str;
    }

    public function getModelAttribute($value)
    {
        $str = '';
        switch ($value)
        {
            case '1':
                $str = 'Android';
                break;
            case '2':
                $str = 'IOS';
                break;
        }
        return  $str;
    }
}
