<?php 

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\{Order,MerBill,User,AccountLog,Shop,OrderAfterSale};
use App\Http\Controllers\Traits\RefundOrder;

class AfterSaleController extends Controller {
    use RefundOrder;

	/**
     * [agreeOrRefuseRefund 商家售后退款]
     * @param  Request $request [description]
     * @return [type]           [description]
     */

    public function agreeOrRefuseRefund(Request $request) {

        $this->validate($request, [
            'status' => 'required|integer|in:1,2',
            'id' => 'required|integer|exists:order_after_sale,id',
            'shop_id' => 'required|integer|exists:shops,shop_id',
            'order_id' => 'required|integer|exists:order,order_id',
            'refuse_cause' => 'required_if:status,2|string|between:1,100'

        ],$this->message);


        $Order = Order::where('order_id',$request->order_id)->where('order_status',4)->select(['pay_code','transaction_id','order_amount','user_money','user_id'])->first();

        if (empty($Order))  {
            return respond(201,'该订单不满足售后条件！');
        }


        DB::beginTransaction(); //开启事务

        $OrderAfterSale = OrderAfterSale::where('status',0)->where('shop_id',$request->shop_id)->where('order_id',$request->order_id)->lockForUpdate()->first();

        if (empty( $OrderAfterSale )) {
            return respond(201,'该订单不满足售后条件！');
        }

        
        $OrderAfterSale->status = $request->status;

        $OrderAfterSale->action_desc = '商家操作';


        if ($request->filled('refuse_cause')) {

            $OrderAfterSale->refuse_cause = $request->refuse_cause;
        }



        $out_refund_no = date('YmdHis').uniqid(); 



        if ($request->status == 1 && $OrderAfterSale->money > 0 )  {

            if (Shop::where('shop_id',$request->shop_id)->value('balance') < $OrderAfterSale->money) {
                return respond(201,'该店铺余额不足！');
            }

            switch ($Order->pay_code) {

                case 'alipayApp':
                case 'alipayMobile':

                    try {

                        $result = $this->alipayRefund($Order->transaction_id,$Order->order_amount,$OrderAfterSale->money,$out_refund_no);

                        if ($result === true) {
                            $OrderAfterSale->out_refund_no = $out_refund_no;
                        }
                    } catch (\Exception $e) {
                        return respond(201,$e->getMessage());
                    }

                    break;

                case 'wechatApp':
                case 'weixin':

                    try {

                        $result = $this->wechatRefund($Order->transaction_id,$Order->order_amount,$OrderAfterSale->money,$out_refund_no);
                        if ($result === true) {
                            $OrderAfterSale->out_refund_no = $out_refund_no;
                        }
                    } catch (\Exception $e) {
                        return respond(201,$e->getMessage());
                    }
                    
                    break;
                case 'deduction':
                    //退余额
                    if ($Order->user_money > 0) {

                        if ($OrderAfterSale->money > $Order->user_money) {
                            return respond(201,'售后金额不能大于支付抵扣余额！');
                        }
                        
                        $AccountLog = new AccountLog();
                        $increment_moeny = User::where('user_id',$Order->user_id)->increment('moeny',$OrderAfterSale->money);
                        $AccountLog->desc = "余额抵扣返还(售后)";
                        $AccountLog->user_money = $OrderAfterSale->money;
                        $AccountLog->user_id = $OrderAfterSale->user_id;
                        $AccountLog->order_id = $OrderAfterSale->order_id;

                        if($increment_moeny && $AccountLog->save()) {
                            // $OrderAfterSale->out_refund_no = $out_refund_no;
                        }else{
                            DB::rollBack();//事务回滚
                            return respond(201,'操作失败！');
                        }
                    }
                    
                    break;
            }



            //记录售后流水金额

            $mer_bill =  [

                'shop_id'  => $request->shop_id,

                'order_id' => $request->order_id,

                'money' => $OrderAfterSale->money,

                'desc' => '商户售后退款',

                'type' => 6,

                'created_at' => date('Y-m-d H:i:s')

            ];



            //扣除商家余额 

            //记录售后流水金额

            $change_balance = Shop::where('shop_id',$request->shop_id)->lockForUpdate()->decrement('balance',$OrderAfterSale->money);



            if (!($change_balance && MerBill::insert($mer_bill)))

            {



                DB::rollBack();//事务回滚



                return respond(201,'操作失败！');

            }

        }



        if ($OrderAfterSale->save()) 

        {

            DB::commit();//提交事务

            return respond(200,'操作成功！');

        }



        DB::rollBack();//事务回滚



        return respond(201,'操作失败！');

    }


}