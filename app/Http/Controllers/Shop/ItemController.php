<?php 

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{Spec,SpecItem,SpecGoodsPrice,SpecImage,Goods,Shop};

use App\Http\Requests\ItemRequest;



class ItemController extends Controller

{

	use ItemRequest;





	/**

	 * 编辑商品规格

	 * @param  Request $request [description]

	 * @param  [type]  $id      [description]

	 * @return [type]           [description]

	 */

	public function edit(Request $request)

	{



		$result_msg = $this->CheckParameter($request);



		if(!empty($result_msg)) return respond(422,$result_msg);



		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');



        if ($ShopStatus) { return respond(422,'请先关闭店铺！'); }



		if($request->filled('item_id')) {

			$SpecItem = SpecItem::where('item_id',$request->input('item_id'))->where('shop_id',$request->shop_id)->first();
			if (empty($SpecItem)) {
				return respond(422,'编辑的规格没有找到');
			}

		}else{

			$SpecItem = new SpecItem();

		}

		

		foreach ($request->only(['goods_id','shop_id','spec_id','item','price','sort','item_id']) as $key => $value)

		{

		

			if($request->filled($key)){



				$SpecItem->$key = $value;

			}

		}

	

		if($SpecItem->save())

			return respond(200,'操作成功！',$SpecItem);

		else

			return respond(201,'操作失败！');

	}



	/**

	 * 删除规格

	 * @return [type] [description]

	 */

	function destroy(Request $request)

	{

		$where = [

            'item_id'        => $request->item_id,

            'shop_id'        => $request->shop_id,

        ]; 



		$this->validate(

			$request,

			[

				'item_id' => 'required|integer|exists:spec_items,item_id',

				'shop_id' => 'required|integer|exists:shops,shop_id',

			],

		$this->message);



		$SpecItem = SpecItem::where($where)->first();

		

		if(empty($SpecItem)) return respond(201,'非法操作！');



		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');



        if ($ShopStatus) {return respond(422,'请先关闭店铺！');}



		if($SpecItem->delete())

			return respond(200,'删除成功！');

		else

			return respond(201,'删除失败！');

	}

}