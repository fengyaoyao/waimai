<?php 
namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Admin,AgencyBill};
use App\Model\Manage\Withdrawal;
use Illuminate\Support\Facades\DB;


class AgentController extends Controller
{
	protected $admin;

	public function __construct(Request $request)
    {
        $this->admin = $request->get('admin');
    }


	 /**
     * [apply 申请提现]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function withdrawalApply(Request $request) {

        $this->validate($request, [ 
            'type'          => 'required|integer|in:0,1,2', // 提现类型 0微信 1支付宝
            'alipay_number' => 'required_if:type,1|between:3,128',  // 支付宝提现账户
            'money'         => 'required|numeric'
        ],$this->message);

        try {

            if ($request->type == 0) {
                return respond(422,'微信提现暂未开通!');
            }

            if (($request->type == 0) && empty($this->admin['openid'])) {
                return respond(422,'请先联系客户绑定微信!');
            }

            if(($this->admin['money'] <= 0 ) || ($this->admin['money'] < $request->money)) {
                return respond(422,'商户账户余额不足!');
            }

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

            $withdrawalInsertData = [
                'money'        => $request->money,
                'type'         => $request->type,
                'user_type'    => 3,
                'remark'       => '代理人员提现！',
                'client_type'  => 3,
                'before_money' => $this->admin['money'],
                'bank_name'    => $bank_name,
                'bank_card'    => $bank_card,
                'realname'     => $this->admin['true_name'],
                'admin_id'     => $this->admin['admin_id'],
                'created_at'   => date('Y-m-d H:i:s')
            ];

            $agencyBillInsertData = [
              'money' => '-'.$request->money,
              'admin_id' => $this->admin['admin_id'],
              'desc' => '账户余额提现',
              'type' => 4,
              'created_at' => date('Y-m-d H:i:s')
            ];
        
            DB::beginTransaction(); //开启事务

            $UpdateAdminMoney = Admin::where('admin_id',$this->admin['admin_id'])->decrement('money',$request->money);

            if( $UpdateAdminMoney && AgencyBill::insert($agencyBillInsertData) && Withdrawal::insert($withdrawalInsertData)) 
            {
                DB::commit(); //提交事务
                return respond(200,'提交成功!');
            }

            DB::rollBack();//回滚事务
            return respond(203,'提交失败!');

        } catch (\Exception $e) {
            return respond(500,'服务器错误！');
        }
    }



    /**
     * [agencyBill 代理收支记录]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function agencyBill(Request $request)
    {
        $this->validate( $request,[
            'page_size'  => 'sometimes|required|integer',
            'type' =>  'sometimes|required|integer',
        ],$this->message);

        $type = $request->filled('type') ? $request->type : null;
        $page_size = $request->filled('page_size') ? $request->page_size : 15;
        $agencyBill = AgencyBill::where('admin_id',$this->admin['admin_id'])
                                ->with('order')
                                ->when($type, function ($query) use ($type) {
                                    return $query->where('type',$type);
                                })
                                ->orderByDesc('created_at')
                                ->simplePaginate($page_size);
        if($agencyBill) {
            return respond(200, '获取成功！',$agencyBill);
        }else{
            return respond(201, '获取失败！');
        }
    }


    /**
     * [buildWithdrawalAccount 绑定提现账号]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    // public function buildWithdrawalAccount(Request $request)
    // {

    //     $this->validate($request, [ 
    //         'type' => 'required|integer|in:1,2,3',         // 提现类型 1微信 2支付宝 3银行卡
    //         'openid' => 'required_if:type,1|between:3,128', //微信账户
    //         'alipay_number' => 'required_if:type,2|string|between:3,128', //支付宝账户
    //         'bank_name' => 'required_if:type,3|string|between:4,15', //银行名称
    //         'bank_card' => 'required_if:type,3|string|between:12,32', //银行卡号
    //     ],$this->message);
    // }
}