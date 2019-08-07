<?php

namespace App\Listeners;

use App\Events\Withdrawal;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Contracts\Queue\ShouldQueue;

use App\Model\{Withdrawal as ModelWithdrawal,User,UserBill,MerBill,AccountLog,Area};
use App\Exceptions\MyException;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;


class WithdrawalListener

{

    public function handle(Withdrawal $event)
    {

		$ModelWithdrawal  = ModelWithdrawal::find($event->id);//查找提现信息

		//未找到提现记录
		if (empty($ModelWithdrawal )) {
			throw new MyException("提现失败！",201);
		}

		//判断是否是实时到账
		$settlement = json_decode(Area::where('area_id',$ModelWithdrawal->area_id)->value('settlement'),true);

    	if(empty($settlement) || $settlement['withdraw_type'] != 2 ) { 
			throw new MyException("提现申请中！",200);
    	}

        try {

			//请求后台提现接口
	        $request_row = curl_request(env('BACKEND_DN')."mobile/payment/withdraw?withdraw_id={$event->id}");
	        $hander = new StreamHandler(storage_path('/logs/withdraw').date('Y-m-d').'.log');
		    $hander->setFormatter(new LineFormatter(null, null, true, true));
		    $Logger = new Logger('Withdraw');
		    $Logger->pushHandler($hander);
		    
		    if (is_array($request_row)) {
			    $Logger->info('withdraw_id:'.$event->id,$request_row);
		    }

        } catch (\Exception $e) {
        	// throw new MyException("提现申请中！",200);
        }

		//提现成功修改状态
		if (!empty($request_row['status']) && $request_row['status'] == 200) {

			$ModelWithdrawal->status = 1;
			$ModelWithdrawal->remark = '提现成功';
			$ModelWithdrawal->save();
			throw new MyException("提现成功！",200);

		}else{

			$ModelWithdrawal->status = 3;
			$ModelWithdrawal->remark = $request_row['message'];
		    $ModelWithdrawal->save();
			throw new MyException("提现申请中！",200);
		}
    }
}

