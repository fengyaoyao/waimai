<?php 
namespace App\Http\Controllers\Delivery;

use App\Model\{User,Withdrawal,UserBill,MerBill,Area,AccountLog,Shop};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class WithdrawalController extends Controller
{

	/**
	 * 申请提现
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function apply(Request $request)
	{
		DB::beginTransaction(); //开启事务

		$user = User::where('user_id',\Auth::id())->first();

		$this->validate($request, 
			[ 
			  'client_type'   => 'required|integer|in:0,1,2',       // 客户端类型 0 买家端 1商户端 2骑手端
			  'money'         => 'required|integer|min:1',          // 提现金额
			  'type'          => 'required|integer|in:0,1',  		// 提现类型 0微信 1支付宝
			  'alipay_number' => 'sometimes|required_if:type,1',    // 支付宝提现账户
			  'shop_id'       => 'sometimes|required_if:client_type,1',    // 店铺id
			],$this->message);

		$before_money = 0;
		$money = $request->money;
		$client_type = $request->client_type;
		$Withdrawal = new Withdrawal;

		switch ($request->type) {
			case '0':
				if (empty($user->wechat_number)){
					return respond(422,'请到账号设置绑定微信!');
				}

				break;
			case '1':

				if (!$request->filled('alipay_number')){
					return respond(422,'请到账号设置绑定微信!');
				}

				break;
			case '2':
					return respond(422,'该功能暂未开通!');
				break;
		}


		$withdraw_min = Area::where('area_id',$user->area_id)->value('withdraw_min');

		if ($money < $withdraw_min) {
			return respond(422,"最小提现金额是{$withdraw_min}!");
		}

		if ($withdraw_min >= 100) {

			if (($money % 100) > 0 ){
				return respond(422,'提现金额只能是100的倍数!');
			}  
		}

		if($user->rider_money < $money){
			return respond(422,'余额不足!');
		} 

		$before_money = $user->rider_money;
		$user->rider_money = $before_money - $money;

		if ($request->type == 1 && $request->filled('alipay_number')) {
			$user->alipay_number = $request->alipay_number;
			$user->save();
		}

		$Withdrawal->money        = $money;
		$Withdrawal->type         = $request->type;
		$Withdrawal->user_id      = $user->user_id;
		$Withdrawal->area_id      = $user->area_id;
		$Withdrawal->user_type    = $user->type;
		$Withdrawal->remark       = $client_type;
		$Withdrawal->client_type  = $client_type;
		$Withdrawal->before_money = $before_money;
		$Withdrawal->bank_name    = $request->type;
		$Withdrawal->bank_card    = $request->type;
		$Withdrawal->realname     = ($user->type > 0) ? (!empty($user->realname) ? $user->realname:$user->nickname) : $user->nickname;

		$insertDefaultData = [
			'desc' => '账户余额提现',
			'money' => $money,
			'type' => 2,
			'user_id' => $user->user_id,
			'created_at' => date('Y-m-d H:i:s')
		];

	    if($Withdrawal->save() && UserBill::insert($insertDefaultData) && $user->save()) {

		    DB::commit();

		    $rider_withdrawal_amount = \App\Model\Config::where(['name'=>'rider_withdrawal_amount','inc_type'=>'basic'])->value('value');

	        if (!empty($rider_withdrawal_amount) && ($rider_withdrawal_amount > 0)  && ($money > $rider_withdrawal_amount)) {
	            return respond(200,'提现申请中!');
	        }

	        //调起提现接口
            try {
                event(new \App\Events\Withdrawal($Withdrawal->id));
            } catch (\Exception $e) {
                return respond($e->getCode(),$e->getMessage());
            }
	        
			return respond(200,'提现申请中!');
	    }

    	DB::rollBack();
	    return respond(201,'提交失败!');
	}

	/**
	 * [list 提现列表]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function list(Request $request)
	{   
		// 客户端类型 0 买家端 1商户端 2骑手端
		$this->validate($request,[
			  'client_type'   => 'required|integer|in:0,1,2',      
			  'page_size'     => 'sometimes|required|integer'
		],$this->message);

		$page_size = $request->input('page_size',15);
		$result    = Withdrawal::where('user_id',\Auth::id())->orderByDesc('id')->simplePaginate($page_size);

		foreach ($result as $key => $value) {
			if ($value->type == 2 && !empty($value->bank_card)) {
				$value->bank_card = middle_str_replace($value->bank_card);
			}
		}

		if($result)
			return respond(200,'获取成功！',$result);
		else
			return respond(201,'获取失败！');
	}
}