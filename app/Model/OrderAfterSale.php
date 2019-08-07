<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class OrderAfterSale extends Model
{
    protected $table = 'order_after_sale';
    protected $hidden = [ 'deleted_at'];
    protected $appends = ['status_desc'];

    public function getStatusDescAttribute() 
    {
        $str = '';

        switch ($this->attributes['status']) {
            case '0':
                $str = '售后申请处理中';
                break;
            case '1':
                $str = '商户已同意,金额已退回您的账户';
                break;
            case '2':
                $str = '商户已拒绝,若有疑问可向平台投诉！';
                break;
        }
        
        return  $str;
    }
}