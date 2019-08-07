<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class Recruitment extends Model
{

    public function getTypeAttribute($value)
    {
        switch ($value)
        {
            case '0':
                $str = '配送人';
                break;
            case '1':
                $str = '合伙人';
                break;
            case '2':
                $str = '商家入驻';
                break;
        }
        return  [
            'key' => $value,
            'value' => $str,
        ];
    }
    public function getStatusAttribute($value)
    {
        switch ($value)
        {
            case '0':
                $str = '申请中';
                break;
            case '1':
                $str = '审核通过';
                break;
            case '2':
                $str = '审核失败';
                break;
        }
        return  [
            'key' => $value,
            'value' => $str,
        ];
    }
}