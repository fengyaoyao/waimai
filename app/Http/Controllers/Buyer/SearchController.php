<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Buyer\Shop;
use App\Model\Area;
use App\Model\GoodsHasArea;
use App\Model\Search as ModelSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(Request $request)
    {
        if (in_array($request->getRequestUri(),['/buyer/search' ]) && $request->header('Authorization')) {
            $this->middleware('auth');
        }
    }
    
    /**
     * 搜索店铺列表
     * @param  [type] $area_id [description]
     * @return [type]          [description]
     */
    public function search(Request $request)
    {

        $this->validate($request,
        [
            'area_id'        => 'required|integer|exists:areas,area_id',
            'is_new'         => 'sometimes|boolean',//新店
            'type_id'        => 'sometimes|integer',//店铺类型
            'sort'           => 'sometimes|in:desc,asc',//综合排序
            'avg_minute'     => 'sometimes|in:desc,asc',//速度最快
            'store_ratings'  => 'sometimes|in:desc,asc',//评价排序
            'sales'          => 'sometimes|in:desc,asc',//销量排序
            'search_name'    => 'sometimes|string|between:1,20',//输入搜索店铺或商品
            'page_size'      => 'sometimes|integer',//分页
        ],
        $this->message);

        $request->merge(['status'=>'desc']);//默认店铺营业的倒序

        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $search_name = $request->filled('search_name') ? $request->search_name : false;//输入搜索店铺或商品

        $area_id = $request->area_id;//区域id

        $select_where = array_filter([
            'area_id' => $area_id,
            'is_new'  => $request->filled('is_new') ? $request->is_new : '',//新店
            'type_id' => $request->filled('type_id') ? $request->type_id : '',//店铺类型id 
        ]);

        $select_where['is_lock'] = 0;

        $orderbyRaw = [];

        $request->filled('sort') ? $request->merge(['sort'=>'asc']) : '';//综合排序

        foreach ($request->only(['status','sort','sales','avg_minute','store_ratings']) as $key => $value) 
        {
            if($request->filled($key)) {
                array_push($orderbyRaw, "{$key} {$value}");
            } 
        }

        $orderbyRaw = join(',',$orderbyRaw);//排序
        $shops = [];
        $goods = [];

        if ($search_name) {
            
            $GoodsHasArea = GoodsHasArea::where('area_id',$area_id)->where('title', 'like', '%'.$search_name.'%')->orWhere('shop_name','like', '%'.$search_name.'%')->pluck('goods_id','shop_id')->toArray();

            $goods = GoodsHasArea::where('area_id',$area_id)->where('title', 'like', '%'.$search_name.'%')->pluck('goods_id')->toArray();

            if (empty($GoodsHasArea)) {
                return respond(200,'获取成功！',(object)[]); 
            }

            $shops = array_keys($GoodsHasArea);
        }

        $Shop = Shop::where($select_where)
                    ->when($shops, function ($query) use ($shops) {
                        return $query->whereIn('shop_id',$shops);
                    })
                    ->when($goods, function ($query) use ($goods) {
                        return $query->with(['goods'=>function($query) use ($goods) {
                            return $query->whereIn('goods_id',$goods)
                                        ->select(['goods_id','shop_id','title','price','details_figure','sort','praise','sold_num'])
                                        ->orderByDesc('sort');
                        }]);
                    })
                    ->with(['prom'=>function($query) { 
                        return $query->where('status',1)->orderByRaw('field(type,2,0,1)');
                    },'redPacket'])
                    ->orderByRaw($orderbyRaw)
                    ->simplePaginate($page_size);

        foreach ($Shop as $value) {
            $process_date = Area::where('area_id',$value->area_id)->value('process_date');
            if (empty($value->avg_minute)) {
                $value->avg_minute = $value->sell_time + $process_date;
            }else{
                $value->avg_minute = $value->avg_minute + $process_date;
            }
        }

        if (empty($Shop)) {
            $Shop = (object)[];
        }

        return respond(200,'获取成功！',$Shop);
    }

    /**
     * [hotSearch 热门搜索词]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function hotSearchWord(Request $request)
    {
        $this->validate($request, [
            'area_id'=>'sometimes|required|integer|exists:areas,area_id',
        ],$this->message);

        $area_id   = $request->input('area_id');
    
        $Search = ModelSearch::when($area_id , function ($query) use ($area_id) {
                    return $query->where('area_id', $area_id);
                  })->where('status',1)->orderByDesc('sort')->get();
        
        return respond(200,'获取成功！',$Search);
    }
}