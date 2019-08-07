<?php 

namespace App\Http\Controllers\Shop;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\{User,Withdrawal,MerBill,Area,Shop, Bound};
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller

{
    /**

     * [apply 申请提现]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function apply(Request $request) {


        $this->validate($request, 
            [ 
              'type' => 'required|integer|in:0,1,2',         // 提现类型 0微信 1支付宝 2银行卡
              'alipay_number' => 'required_if:type,1|between:1,100',    // 支付宝提现账户
              'shop_id' => 'sometimes|required|integer|exists:shops,shop_id',
              'money' => 'required_with:shop_id|numeric'
            ],
        $this->message);

        try {

            $user = User::where('user_id',\Auth::id())->select(['realname','nickname','alipay_number','bank_name','bank_card','user_id','is_withdraw_all','area_id','withdraw_auth','wechat_number'])->first();

            if (empty($user)) {
                return respond(422,'非法操作!');
            }

            if($user->withdraw_auth != 1) return respond(422,'提现权限不够!');

            $shop_id = $request->input('shop_id');

            $bank_card = '';

            $bank_name = '';

            $realname = !empty($user->realname) ? $user->realname : $user->nickname;



            switch ($request->type) {

                case '0':

                        if (empty($user->wechat_number)) {

                            return respond(422,'请先绑定微信账号!');
                        }

                        $bank_name = '微信';

                        $bank_card = $user->wechat_number;
                    break;

                case '1':

                        $bank_name = '支付宝';

                        $bank_card = $request->alipay_number;

                    break;

                case '2':

                        $bank_name = $user->bank_name;

                        $bank_card = $user->bank_card;

                        if (empty($bank_card)) {

                            return respond(422,'请先绑定银行卡!');
                        }

                    break;
            }



            $shop_ids = Bound::where('user_id',$user->user_id)
                                        ->when($request->filled('shop_id'), function ($query) use ($shop_id) {
                                            return $query->where('shop_id', $shop_id);
                                        })
                                        ->select('shop_id')
                                        ->pluck('shop_id')
                                        ->toArray();


            if(empty($shop_ids)) return respond(422,'没有找到你管理的店铺！');



            $Shop = Shop::whereIn('shop_id',$shop_ids)->select(['shop_id','balance','settlement'])->get();



            if ($request->filled('shop_id') &&  $request->filled('money')) {



                if($Shop->pluck('balance')->sum() < $request->money) return respond(422,'余额不足!');



                if($Shop[0]->settlement['withdraw_multiple'] == 1 && !empty($request->money % 100))  return respond(422,'提现金额只能是100的倍数!');

                

            }else{

                if ($user->is_withdraw_all == 0)  return respond(422,'该账户不支持一键提现!');

                if ($Shop->pluck('balance')->sum() <= 0) return respond(422,'余额不足!');

            }

            

            $withdrawalInsertData = [];

            $merBillInsertData = [];



            foreach ($Shop as $key => $value) {



                if ($value->balance <= 0) continue;



                $before_money = $value->balance;

                $money = ($request->filled('shop_id') && $request->filled('money') )?  $request->money : $value->balance;



                $withdrawalInsertData[] = [

                    'shop_id'      => $value->shop_id,

                    'money'        => $money,

                    'type'         => $request->type,

                    'user_id'      => $user->user_id,

                    'area_id'      => $user->area_id,

                    'remark'       => '商户提现',

                    'user_type'    => 1,

                    'client_type'  => 1,

                    'before_money' => $before_money,

                    'bank_name'    => $bank_name,

                    'bank_card'    => $bank_card,

                    'realname'     => $realname,

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

        } catch (\Exception $e) {

            info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);

            return respond(500,$e->getMessage());
        }

    }

}