<?php 
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Spec,SpecItem,Goods,Shop};
use App\Http\Requests\ItemRequest;

class SpecsController extends Controller
{
	use ItemRequest;

	/**
	 * 编辑商品属性
	 * @param  Request $request [description]
	 * @param  [type]  $id      [description]
	 * @return [type]           [description]
	 */
	public function edit(Request $request)
	{
		$result_msg = $this->CheckSpecsParameter($request);
		if(!empty($result_msg)) return respond(422,$result_msg);

		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');

        if ($ShopStatus) { return respond(422,'请先关闭店铺！'); }

		if($request->has('spec_id'))
		{
			$Spec = Spec::find($request->spec_id);
		}else{
			$Spec = new Spec();
		}

		foreach ($request->only(['goods_id','shop_id','name','spec_id','select_type']) as $key => $value)
		{
			if($request->filled($key)) $Spec->$key = $value;
		}

		if($Spec->save())
			return respond(200,'操作成功！',$Spec);
			return respond(201,'操作失败！');
	}


	/**
	 * 删除商品属性
	 * @return [type] [description]
	 */
	function destroy(Request $request)
	{
		$where = [
            'spec_id'        => $request->spec_id,
            'shop_id'        => $request->shop_id,
        ]; 

		$this->validate(
			$request,
			[
				'spec_id' => 'required|integer|exists:specs,spec_id',
				'shop_id' => 'required|integer|exists:shops,shop_id',
			],
		$this->message);

		$SpecItem = SpecItem::where($where)->count();
		
		if($SpecItem) return respond(201,'该属性下存在规格不能删除！');

		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');

        if ($ShopStatus) { return respond(422,'请先关闭店铺！');}

		if(Spec::where($where)->delete())
			return respond(200,'删除成功！');
		else
			return respond(201,'删除失败！');
	}
}