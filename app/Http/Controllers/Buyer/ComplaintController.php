<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Complaint;

class ComplaintController extends Controller
{
	/**
	 * 用户投诉
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function complaint(Request $request)
	{
		$request->merge(['user_id' => \Auth::id()]);

		$rules = [
                'user_id'         => 'required|integer',
                'username'        => 'required|string|between:2,20',
                'mobile'          => 'required|regex:/^1[3456789][0-9]{9}$/',
                'order_id'        => 'required|integer|exists:order,order_id',
                'content'         => 'sometimes|required|string|between:2,1024',
                'picture'         => 'sometimes|present|between:2,1024',
                'type'            => 'required|integer|in:0,1,2'
        ];

        if ($request->type == 2) {
            unset($rules['order_id']);
            
            $count = Complaint::where('user_id',\Auth::id())->whereDate('created_at',date('Y-m-d'))->count();

            if ( $count > 5) {
	        	return respond(422,'感谢你的反馈！');	
            }
        }
        
		$this->validate($request,$rules, $this->message);

		$order_id =$request->input('order_id','');


        if ($request->type != 2 && Complaint::where('user_id',\Auth::id())->where('order_id',$order_id)->exists()) {
        	return respond(422,'你已经提交过了！');
        }

		$Complaint = new Complaint();

		foreach ($request->only(['user_id','username','mobile','order_id','content','picture','type']) as $key => $value)
		{
			 if($request->filled($key)) $Complaint->$key = $value;
		}

		if($Complaint->save())
			return respond(200,'提交成功！',$Complaint);
		else
			return respond(201,'提交失败！');
	}

	/**
	 * 获取当前用户所有的投诉内容列表
	 * @return [type] [description]
	 */
	public function list(Request $request)
	{

		$this->validate($request,['page_size' => 'sometimes|present|integer'], $this->message);

		if ($request->filled('page_size')) {

	        $page_size = $request->page_size;

            $result = Complaint::where('user_id',\Auth::id())->where('client_type',0)->with('order')->orderByDesc('created_at')->simplePaginate($page_size);

		}else{

			$result = Complaint::where('user_id',\Auth::id())->where('client_type',0)->with('order')->orderByDesc('created_at')->get();
		}

		return respond(200,'获取成功！',$result);
	}

	
    public function orderList()
    {

        $Order = \App\Model\Order::where('user_id', \Auth::id())
				        ->whereRaw("date(`created_at`) >=  DATE_SUB(CURDATE(), INTERVAL 7 DAY)")
                        ->with(['order_goods','shop','relay_info','order_ps'=>function($query){
                            return $query->select(['nickname','push_id','user_id','type','realname','mobile','headimgurl','rider_type']);
                        }])
                        ->orderByDesc('order_id')
                        ->get();

        return respond(200,'获取成功！',$Order);
    }
}