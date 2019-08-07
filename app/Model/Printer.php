<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Printer extends Model
{
	protected $table      = 'printer';
	protected $primaryKey = 'printer_id';
    protected $hidden     = ['addtime'];

    public function getTypeAttribute($value)
    {
        $str = '';
        switch ($value)
        {
            case '0':
                $str = 'Default';
                break;
            case '1':
                $str = 'A';
                break;
            case '2':
                $str = 'B';
                break;
            case '3':
                $str = 'C';
                break;

        }
        return  "迪速帮打印机{$str}型";
    }
}