<?php 

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Model\Shop;
use App\Model\ShopRedPacket;
use App\Model\MemberRule;
use App\Rules\ShopIsBound;



class ShopRedPacketController extends Controller
{
    protected  $user_id;
    protected  $area_id;

    public function __construct()
    {
        $user = \Auth::user();
        $this->user_id =  $user->user_id;
        $this->area_id =  $user->area_id;
    }


    /**
     * [list 红包列表]
     * @param  Request $request [description]
     * @return [type]           [description]
     */

    public function list(Request $request){
        $this->validate($request,[
                'shop_id' => ['required','integer','exists:shops,shop_id',new ShopIsBound($this->user_id)],
                'page_size' => 'sometimes|required|integer|min:1',
        ],$this->message);

        $shop_id = $request->shop_id;
        $page_size = $request->filled('page_size') ? $request->page_size : 15;

        $result = ShopRedPacket::where(['shop_id'=>$shop_id])->simplePaginate($page_size);
        return respond(200,'获取成功！',$result);

    }

    /**
     * [save 更新红包]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function save(Request $request){

        $this->validate($request,[
            'id' => 'sometimes|required|integer|exists:shop_red_packets,id',
            'shop_id' => ['required','integer','exists:shops,shop_id',new ShopIsBound($this->user_id)],
            'title' => 'required|string|between:1,65',
            'type' => 'required|integer|in:0,1',
            'money' => 'required|integer|min:0',
            'expire_days' => 'required_if:type,0|integer|min:0',
            'condition' => 'required_if:type,0|integer|min:0',
            'start_time' => 'sometimes|required_if:type,0|date_format:Y-m-d H:i:s|before_or_equal:end_time',
            'end_time' => 'sometimes|required_if:type,0|date_format:Y-m-d H:i:s|after_or_equal:start_time',
        ],$this->message);

        $shop_id = $request->shop_id;

        $id = $request->filled('id') ? $request->id : null;

        if ($request->type) {

            $MemberRule = MemberRule::where('area_id',$this->area_id)->select(['red_packet_money','status'])->first();

            if(empty($MemberRule) || ($MemberRule->status !=1) ) {
                return respond(422,'该校区暂未开通兑换红包！');
            }

            if ($request->money < $MemberRule->red_packet_money) {
                return respond(422,'面额必须大于或等于'.$MemberRule->red_packet_money);
            }
        }

        if($id) {

            $ShopRedPacket = ShopRedPacket::where('id',$id)->where('shop_id',$shop_id)->first();
            if (empty($ShopRedPacket)) {
                return respond(422,'编辑的店铺红包没有找到！');
            }

        }else{
            $ShopRedPacket = new ShopRedPacket();
        }

        foreach ($request->only(['shop_id','title','type','money','expire_days','condition','start_time','end_time']) as $key => $value)
        {
            if($request->filled($key)) {
                $ShopRedPacket->$key = $value;
            }
        }

        if($ShopRedPacket->save()) {
            return respond(200,'操作成功！',$ShopRedPacket);
        }

        return respond(201,'操作失败！');
    }

    /**
     * [destroy 删除红包]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function destroy(Request $request){
        $this->validate($request,[
                'id' => 'sometimes|required|integer|exists:shop_red_packets,id',
                'shop_id' => ['required','integer','exists:shops,shop_id',new ShopIsBound($this->user_id),
            ],
        ],$this->message);

        $shop_id = $request->shop_id;
        $id = $request->filled('id') ? $request->id : null;

        $ShopRedPacket = ShopRedPacket::where('id',$id)->where('shop_id',$shop_id)->first();

        if (empty($ShopRedPacket)) {
            return respond(422,'店铺红包没有找到！');
        }

        if($ShopRedPacket->delete()) {
            return respond(200,'删除成功！');
        }

        return respond(201,'删除失败！');
    }

    /**
     * [memberRule 兑换红包配置]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function exchangeConfig(Request $request){

        $MemberRule = MemberRule::where('area_id',$this->area_id)->select(['red_packet_money','status'])->first();

        if(empty($MemberRule)) {
            
            $MemberRule = [
                'red_packet_money' => 0,
                'status' => 0,
            ];
        }

        return respond(200,'获取成功！',$MemberRule);
    }
}