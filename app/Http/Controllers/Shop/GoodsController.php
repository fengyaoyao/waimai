<?php 

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{Goods,SpecItem,Spec,SpecGoodsPrice,Shop};

use App\Http\Requests\GoodsRequest;



class GoodsController extends Controller

{

    use GoodsRequest;



    /**

     * 搜索当前店铺下的商品

     * @param  Request $request [description]

     * @param  [type]  $id      [description]

     * @return [type]           [description]

     */

    public function search_goods(Request $request)

    {

        $page_size = $request->filled('page_size') ? $request->page_size:100;

        $result_msg = $this->CheckSearchParameter($request);

        if(!empty($result_msg)) return respond(422,$result_msg);

        $except = [];
        
        foreach($request->only(['shop_recommend', 'shelves_status','cate_id','shop_id']) as $key => $value)
        {
            if($request->filled($key)){
                $except[$key] = $value;
            }
        }

        $result = Goods::where($except)->simplePaginate($page_size);
        return respond(200,'获取成功！',$result);

    }



    /**

     * 编辑商品

     * @param  Request $request [description]

     * @param  [type]  $id      [description]

     * @return [type]           [description]

     */

    public function edit_goods(Request $request)
    {

        $result_msg = $this->CheckParameterGoods($request);

        if(!empty($result_msg)) {
            return respond(422,$result_msg);
        } 



        if($request->filled('goods_id') && $request->filled('shop_id')){

            $Good = Goods::where('goods_id',$request->goods_id)->where('shop_id',$request->shop_id)->first();
            if (empty( $Good)) {
                return respond(422,'编辑的商品没有找到！');
            }

        }else{

            $Good = new Goods();

        }


        foreach ($request->only(['title','price','cate_id','sort','intro','shelves_status','details_figure','shelves_start','shelves_end','shop_recommend','auto_shelves','shop_id','packing_expense','units','is_required','purchase_quantity','discount','discount_astrict']) as $key => $value)

        {

            if($request->filled($key)){

                $Good->$key = $value;

            }

        }



        if($Good->save()){
            return respond(200,'操作成功！',$Good);
        }else{

            return respond(201,'操作失败！');
        }

    }



    /**

     * 删除商品

     * @param  Request $request [description]

     * @param  [type]  $goods_id      [description]

     * @return [type]           [description]

     */

    public function destroy(Request $request)

    {



        $this->validate($request,

        [

            'goods_id' =>'required|integer|exists:goods,goods_id',

            'shop_id' => 'required|integer|exists:shops,shop_id',

        ]

        ,$this->message);



        $Good = Goods::where('goods_id',$request->goods_id)->where('shop_id',$request->shop_id)->first();



        if (empty($Good)) {

            return respond(201,'非法操作');

        }



        if($Good->shelves_status == 1){

            return respond(201,'该商品没有下下架不能删除！');

        }



        $ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');



        if ($ShopStatus) {return respond(422,'请先关闭店铺！');}





        if($Good->delete())

            return respond(200,'操作成功！');

        else

            return respond(201,'操作失败！');

    }

}