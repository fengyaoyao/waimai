<?php 
namespace App\Http\Controllers\Common;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Order;
class OrderController extends Controller
{
    protected $user_id;
    protected $user_type;


    public function __construct(Request $request)
    {
        $this->user_id = \Auth::id();
        $this->user_type = \Auth::user()->type;

    }

    /**
     * 订单详情
     * @return [type] [description]
     */
    public function order_info(Request $request)
    {

        $this->validate($request, 
            [
                'order_id'   => 'sometimes|required|integer|exists:order,order_id',
                'order_sn'   => 'sometimes|required|string|exists:order,order_sn',
            ],
        $this->message);

        if(!$request->has('order_id') && !$request->has('order_sn')) return respond(422,'查询条件不能为空！');

        foreach($request->only(['order_id', 'order_sn']) as $key => $value)
        {
            if($request->filled($key)) $default_where[$key] = $value;
        }

        $Order  = Order::where($default_where)->with(['order_goods','order_ps','order_shop_prom','order_shop','relay_info','order_distribution','afterSale','area'=>
                        function($query){
                            return $query->select(['area_id','process_date','part_time_delivery','full_time_delivery']);
                        }])->first();
        
        if(empty($Order->shop_id)) return respond(201,'获取失败！');

        $Order->user_cont  = Order::where('shop_id',$Order->shop_id)->where('user_id',$Order->user_id)->where('created_at','<=',$Order->created_at)->count();

        $Order->is_after_sale = (!empty($Order->confirm_time) && ($Order->order_status == '4') && (time() < (strtotime($Order->confirm_time) + 86400 )))  ? true : false;


        return respond(200,'获取成功！',$Order);
    }
}