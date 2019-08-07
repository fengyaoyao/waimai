<?php 

namespace App\Http\Controllers\Shop;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\{GoodsCate,Goods,Shop};

use App\Http\Requests\GoodsCateRequest;

use Illuminate\Support\Facades\DB;





class GoodsCateController extends Controller

{

	use GoodsCateRequest;

	/**

	 * 获取当前店铺下的所有商品分类

	 * @param  Request $request [description]

	 * @param  [type]  $id      [description]

	 * @return [type]           [description]

	 */

	public function  cate(Request $request)

	{



		$this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);

		$result  = [

			'recommended' => Goods::where('shop_id',$request->shop_id)->where('shop_recommend',1)->count(),

			'sold_out'    => Goods::where('shop_id',$request->shop_id)->where('shelves_status',0)->count(),

			'putaway'     => Goods::where('shop_id',$request->shop_id)->where('shelves_status',1)->count(),

			'cate'        => GoodsCate::where('shop_id',$request->shop_id)->orderBy('sort')->withCount(['goods'=>function ($query){$query->orWhere('shelves_status',0);}])->get()

		];



		return respond(200,'获取成功！',$result);

	}



	/**

	 * 编辑分类

	 * @param  Request $request [请求]

	 * @param  [type]  $id      [商家id]

	 * @return [type]           [description]

	 */

	public function edit(Request $request)

	{

		$result_msg = $this->CheckParameter($request);



		if(!empty($result_msg)) return respond(422,$result_msg);



		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');



        if ($ShopStatus) {return respond(422,'请先关闭店铺！'); }



	

		if($request->has('cate_id')){

			$GoodsCate = GoodsCate::where('shop_id',$request->shop_id)->where('cate_id',$request->cate_id)->first();
			if (empty($GoodsCate)) {
				return respond(422,'编辑的分类和对应的店铺没有找到！');
			}

		}else{

			$GoodsCate = new GoodsCate();
		}


		

		foreach ($request->only(['cate_name','shop_id','cate_id']) as $key => $value)

		{

			$GoodsCate->$key = $value;

		}



		if($GoodsCate->save())

			return respond(200,'操作成功！',$GoodsCate);

		else

			return respond(201,'操作失败！');

	}



	/**

	 * 删除店铺商品分类

	 * @param  [type] $shop_id [商家id]

	 * @param  [type] $cate_id [分类id]

	 * @return [type]          [description]

	 */

	public function destroy(Request $request)

	{

		$this->validate(

			$request, 

			[ 'shop_id'=>'required|integer|exists:shops,shop_id',

			  'cate_id'=>'required|integer|exists:goods_cates,cate_id'

			],

			$this->message

		);



		$where = [

            'cate_id' => $request->cate_id,

            'shop_id' => $request->shop_id

        ];



		$goods_num = Goods::where($where)->count();



		if($goods_num > 0 ) return respond(201,'该分类下有商品不能删除！');



		$ShopStatus = Shop::where('shop_id',$request->shop_id)->value('status');



        if ($ShopStatus) {return respond(422,'请先关闭店铺！'); }

		



		$GoodsCate = GoodsCate::find($request->cate_id);

		if($GoodsCate->delete())

			return respond(200,'删除成功！');

		else

			return respond(201,'删除失败！');

	}



	/**

	 * [goodsCateSort 分类排序]

	 * @param  Request $request [description]

	 * @return [type]           [description]

	 */

	public function goodsCateSort(Request $request)

	{

		$this->validate($request,

		[

		  '*.sort'    => 'required|integer',

		  '*.cate_id' => 'required|integer|exists:goods_cates,cate_id',

		],

		$this->message);



		$values  = [];

		foreach ($request->all()  as $key => $value) {

			$values[] = "({$value['cate_id']},{$value['sort']})";

		}



		$str = join(',',$values);

		$pr  = env('DB_PREFIX');



		$sql = "insert into {$pr}goods_cates (cate_id,sort) values{$str} on duplicate key update sort=values(sort)";

		

		if(DB::insert($sql))

			return respond(200,'操作成功！');

		else

			return respond(201,'操作失败！');

	}

}