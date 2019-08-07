<?php 
namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Admin,User,UserBill,MerBill,Area,AccountLog, Shop};
use App\Model\Manage\Withdrawal;
use Illuminate\Support\Facades\DB;


class WithdrawalController extends Controller
{
	protected $admin;
	protected $areas = [];
	protected $shops = [];

	public function __construct(Request $request)
    {
        $this->admin    = $request->get('admin');

		if ($this->admin['role_id'] == 1 ) {
		    $this->areas = Area::select('area_id')->pluck('area_id')->toArray();
		}else{
		    $this->areas = $this->admin['area_id'];
		}


		if($this->admin['role_id'] == 1) {
		    $this->shops = Shop::pluck('shop_id')->toArray();
		}else{
		    if (!empty($this->admin['shop_ids'])) {
		       $this->shops = Shop::select('shop_id')->whereIn('shop_id',explode(',', $this->admin['shop_ids']))->pluck('shop_id')->toArray();
		    }else{
		       $this->shops = Shop::whereIn('area_id',$this->areas)->pluck('shop_id')->toArray();
		    }
		}
    }

    /**
	 * [withdrawalNeedData 商户提现页面所需数据]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function withdrawalNeedData(Request $request)
	{
		$result   = [];
		if(!empty($this->admin['shop_ids'])) {
			$result   = Shop::whereIn('shop_id', $this->shops)->select(['shop_id','balance','shop_name','logo'])->get();
		}

		$data = [
			'total_balance' => !empty($result) ? round($result->sum('balance'),2) : 0,
			'shops' => $result,
			'alipay_number' => $this->admin['alipay_number']
		];
		return respond(200,'获取成功！',$data);
	}


	 /**
     * [apply 申请提现]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function apply(Request $request) {

        try {
            $this->validate($request, 
                [ 
                  'type'          => 'required|integer|in:0,1,2',         // 提现类型 0微信 1支付宝
                  'alipay_number' => 'required_if:type,1|between:3,128',    // 支付宝提现账户
                  'shop_id'       => 'sometimes|required|integer|exists:shops,shop_id',
                  'money'         => 'required_with:shop_id|integer|min:1'
                ],
            $this->message);

            if ($request->type == 0) return respond(422,'微信提现暂未开通!');

            if ($request->type == 0 && empty($this->admin['openid'])) return respond(422,'请先联系客户绑定微信!');

            $shop_ids = [];

            if ($request->filled('shop_id')) {

	            $Shop = Shop::where('shop_id', $request->shop_id)->get();

                if($Shop->pluck('balance')->sum() < $request->input('money')) return respond(422,'商户账户余额不足!');

                array_push($shop_ids, $request->shop_id);

            }else{

	            $Shop = Shop::whereIn('shop_id', $this->shops)->get();

                if($Shop->pluck('balance')->sum() <= 0) return respond(422,'商户账户余额不足!');

				$shop_ids = $this->shops;
            }

            $withdrawalInsertData = [];
            $merBillInsertData = [];


            $bank_card = '';
            $bank_name = '';

            switch ($request->type) {
                case '0':
                        return respond(422,'微信提现暂未开通!');
                        $bank_name = '微信';
                        $bank_card = $this->admin['openid'];
                        if (empty($this->admin['openid'])) {
                            return respond(422,'请先绑定微信!');
                        }
                    break;
                case '1':
                        $bank_name = '支付宝';
                        $bank_card = $request->alipay_number;
                    break;
                case '2':
                        $bank_name =$this->admin['bank_name'];
                        $bank_card =$this->admin['bank_card'];
                        if (empty($this->admin['bank_card'])) {
                            return respond(422,'请先绑定银行卡!');
                        }
                    break;
            }

            foreach ($Shop as $value) {

                if ($value->balance <= 0) continue;
                $before_money = $value->balance;
                $money = $request->filled('shop_id') ? $request->money : $value->balance;

                $withdrawalInsertData[] = [
                    'shop_id'      => $value->shop_id,
                    'money'        => $money,
                    'type'         => $request->type,
                    'area_id'      => $value->area_id,
                    'user_type'    => 1,
                    'remark'       => '管理人员提现！',
                    'client_type'  => 3,
                    'before_money' => $before_money,
                    'bank_name'    => $bank_name,
                    'bank_card'    => $bank_card,
                    'realname'     => $this->admin['true_name'],
                    'admin_id'     => $this->admin['admin_id'],
                    'created_at'   => date('Y-m-d H:i:s')
                ];

                $merBillInsertData[] = [
                    'shop_id'=> $value->shop_id,
                    'money'=> $money,
                    'type'=> 4,
                    'desc'=>'账户余额提现',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            DB::beginTransaction(); //开启事务

            $updateBalance = $request->filled('shop_id') ? $Shop->pluck('balance')->sum() - $request->money : 0;

            if(Shop::whereIn('shop_id',$shop_ids)->update(['balance'=>$updateBalance]) && MerBill::insert($merBillInsertData) && Withdrawal::insert($withdrawalInsertData)) {
                DB::commit(); //提交事务
                return respond(200,'提交成功!');
            }

            DB::rollBack();//回滚事务
            return respond(203,'提交失败!');

        } catch (Exception $e) {
            return respond(500,'服务器错误！');
        }
    }
}