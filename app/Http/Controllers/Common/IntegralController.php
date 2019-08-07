<?php 

namespace App\Http\Controllers\Common;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\IntegralGoods;

use App\Model\IntegralGoodsCate;

use App\Model\Ad;



use Illuminate\Support\Facades\DB;



class IntegralController extends Controller

{



    /**

     * [list 积分商品列表]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function list(Request $request)

    {

        $this->validate($request,

        [

            'cate_id'     => 'sometimes|required|integer',//分类id

            'sort'        => 'sometimes|required|in:desc,asc',//综合排序

            'sales'       => 'sometimes|required|in:desc,asc',//销量排序

            'views'       => 'sometimes|required|in:desc,asc',//浏览排序

            'search_name' => 'sometimes|required|string|between:1,20',//输入搜索店铺或商品

            'page_size'   => 'sometimes|required|integer',//分页

        ],

        $this->message);



        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $search_name = $request->filled('search_name') ? $request->search_name : false;//输入搜索店铺或商品

        $cate_id = $request->filled('cate_id') ? $request->cate_id : false;//输入搜索店铺或商品



        $orderbyRaw = [];



        foreach ($request->only(['sort','sales','views']) as $key => $value) 

        {

            ($request->filled($key)) ? array_push($orderbyRaw, "{$key} {$value}") : null;

        }



        if (empty($orderbyRaw)) {

            array_push($orderbyRaw, "id desc");

        }



        $orderbyRaw = join(',', $orderbyRaw);//排序


        $pr = env('DB_PREFIX');

        $IntegralGoods = IntegralGoods::where('shelves_status',1)

                                        ->when($cate_id, function ($query) use ($cate_id) {

                                            return $query->where('cate_id',$cate_id);

                                        })

                                        ->when($search_name, function ($query) use ($search_name) {

                                            return $query->where('title','like', '%'.$search_name.'%');

                                        })
                                        ->whereExists(function ($query) use($pr) {
                                            $query->select(DB::raw(1))
                                                  ->from('coupon')
                                                  ->whereRaw("({$pr}integral_goods.flag = 1 and {$pr}integral_goods.coupon_id > 0 and {$pr}coupon.id = {$pr}integral_goods.coupon_id and {$pr}coupon.use_end_time > now()) or {$pr}integral_goods.flag = 0");
                                        })

                                        ->orderByRaw($orderbyRaw)

                                        ->simplePaginate($page_size);



        return respond(200,'获取成功！',$IntegralGoods);

    }



    /**

     * [cate 积分商品分类]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function cate(Request $request) {



        $this->validate($request,[

          'sort' => 'sometimes|required|in:desc,asc',//综合排序

          'page_size' => 'sometimes|required|integer',//分页

        ], $this->message);



        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $sort = $request->filled('sort') ? $request->sort : 'asc';



        $Cate = IntegralGoodsCate::orderBy('sort',$sort)->simplePaginate($page_size);

        

        return respond(200,'获取成功！',$Cate);

    }



    /**

     * [slideshow 轮播图]

     * @return [type] [description]

     */

    public function slideshow() {



        $Ad = Ad::where('pid',6)

                    ->where('enabled',1)

                    ->where('start_time','<=',time())

                    ->where('end_time','>',time())

                    ->orderByRaw('orderby asc,ad_id desc')

                    ->get();



        return respond(200,'获取成功！',$Ad);

    }

}