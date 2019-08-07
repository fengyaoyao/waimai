<?php 
namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Goods,Spec,OrderGoods,SpecItem};

class GoodsController extends Controller
{

	/**
	 * 商品详情
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function goods_info(Request $request)
	{
		$this->validate($request, ['goods_id'=>'required|integer|exists:goods,goods_id'],$this->message);
		$where  = ['goods_id'=> $request->goods_id];
		$Goods  = Goods::find($request->goods_id);
		$Spec   = Spec::where($where)->with('item')->get();
		$data   = [
			'goods_info' => $Goods,
			'spec'       => $Spec,
		];
		return respond(200,'获取成功！',$data);
	}

	/**
	 * [goodsFormat 店铺所有商品数据格式化]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function goodsFormat(Request $request) {

		$this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);

		$Goods = Goods::where(['shop_id'=>$request->shop_id,'shelves_status'=>1])->get();

		$Item  = SpecItem::where('shop_id',$request->shop_id)->get();

		return respond(200,'获取成功！',['goods'=>$Goods,'item'=>$Item]);
	}
}