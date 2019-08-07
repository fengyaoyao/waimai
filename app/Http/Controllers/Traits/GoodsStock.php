<?php
namespace App\Http\Controllers\Traits;

use App\Model\{OrderGoods, Goods};
use App\Exceptions\MyException;

trait GoodsStock
{
    public function changeStockNum( $order_id, $options = '') {


        if (empty($order_id) || empty($options)) {
          return false;
        }

        try {

            $pr = env('DB_PREFIX');

            switch ($options) {
                case 'increment':

                    $OrderGoods = OrderGoods::where('order_id',$order_id)->select(['goods_id','goods_num'])->get();

                    foreach ($OrderGoods as $value) {
                        Goods::where('goods_id',$value->goods_id)->where('stock_num','>=',0)->increment('stock_num',$value->goods_num);
                    }

                    break;
                case 'decrement':

                    $OrderGoods = OrderGoods::leftJoin('order','order_goods.order_id', '=','order.order_id')
                                            ->leftJoin('goods','order_goods.goods_id', '=','goods.goods_id')
                                            ->whereRaw("{$pr}order.order_id = {$order_id} and {$pr}goods.stock_num > -1")
                                            ->selectRaw("{$pr}order_goods.goods_id,{$pr}order_goods.goods_num,{$pr}goods.stock_num")
                                            ->get();

                    foreach ($OrderGoods as $value) {
                        if ($value->stock_num) {
                            Goods::where('goods_id',$value->goods_id)->decrement('stock_num',$value->goods_num);
                        }
                    }

                break;
            }
            return true;
        } catch (Exception $e) { 

            info($e->getMessage());
        }
    }
}