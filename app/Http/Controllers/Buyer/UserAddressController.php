<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use App\Model\{UserAddress, User, Delivery};
use App\Exceptions\MyException;


class UserAddressController extends Controller
{

    use UserRequest;

    protected $user_id;

    public function __construct(Request $request)
    {
        $this->user_id = \Auth::id();
    }

    /**
     * [edit 添加收货地址]
     * @param Request $request [description]
     */
    public function edit(Request $request)
    {
        $rules = [
            'consignee'   => 'required|string|between:2,15',
            'mobile'      => 'required|regex:/^1[3456789][0-9]{9}$/',
            'delivery_id' => 'required|integer|exists:delivery,delivery_id',
            'address'     => 'required|string|between:2,45',
        ];

        if($request->filled('address_id')) {
            $rules['address_id'] = 'required|integer|exists:user_addresses,address_id';
        }

        $this->validate($request, $rules,$this->message);

        $Delivery = Delivery::find($request->delivery_id);

        $ParentDelivery = Delivery::find($Delivery->pid);

        if (empty($Delivery) && empty($ParentDelivery->area_id)) throw new MyException("服务器错误(area_id no found!)");
        
        $data = [
            'user_id'  => $this->user_id,
            'area_id'  => $ParentDelivery->area_id,
            'floor'    => $Delivery->build_name,
            'building' => $ParentDelivery->build_name,
            'delivery_id' => $Delivery->delivery_id,
            'delivery_pid' => $Delivery->pid,
        ];

        foreach ($request->only(['consignee','mobile','address']) as $key => $value) {
            if(empty($value)) continue;
            $data[$key] = $value;
        }

        if($request->filled('address_id')) {
            $Address = UserAddress::find($request->address_id);
        }else{
            $Address = new UserAddress();
        }

        foreach ($data as $key => $value)
        {
            $Address->{$key} = $value;
        }

        if($Address->save())
            return respond(200,'操作成功！',$Address);
        else
            return respond(201,'操作失败！');
    }

    /**
     * 设置默认收货地址
     * @param Request $request [description]
     */
    public function set_default_address(Request $request)
    {
            $this->validate($request, ['address_id'=>'required|integer|exists:user_addresses,address_id'],$this->message);
            $UserAddress = UserAddress::find($request->address_id);
            $UserAddress->is_default = 1;

            if($UserAddress->save()) {
                UserAddress::where('user_id', $this->user_id)->where('address_id','<>', $request->address_id)->update(['is_default' => 0]);
                return respond(200,'设置成功！',UserAddress::where('user_id', $this->user_id)->get());
            }

            return respond(201,'设置失败！');
    }
    
    /**
     * 删除收货地址
     * @param  Request $request    [description]
     * @param  [type]  $address_id [description]
     * @return [type]              [description]
     */
    public function destroy(Request $request)
    {
        $this->validate($request, ['address_id'=>'required|integer|exists:user_addresses,address_id'],$this->message);
        
        $UserAddress = UserAddress::where('address_id',$request->address_id)->where('user_id',$this->user_id)->first();
        
        if(empty($UserAddress))
        {
            return respond(201,'非法操作！');
        }

        if($UserAddress->delete())
            return respond(200,'删除成功！');
            return respond(201,'删除失败！');
    }

    /**
     * 收货地址列表
     * @param  Request $request    [description]
     * @param  [type]  $address_id [description]
     * @return [type]              [description]
     */
    public function list(Request $request)
    {
        $this->validate($request, ['area_id'=>'required|integer|exists:areas,area_id'],$this->message);

        $UserAddress = UserAddress::where('user_id',$this->user_id)->where('area_id',$request->area_id)->with('area')->get();

        return respond(200,'获取成功！',$UserAddress);
    }
}