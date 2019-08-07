<?php 
namespace App\Http\Requests;
use Validator;
use Illuminate\Validation\Rule;

trait CartRequest
{
    /**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckParameter($request)
    {

        $rules = [
                'goods_id'     => 'required|integer|exists:goods,goods_id',
                'shop_id'      => 'required|integer|exists:shops,shop_id',
                'goods_num'    => 'required|integer',
                'cart_id'      => 'sometimes|required|integer|exists:carts,cart_id',
                'spec_key'     => 'sometimes|required|array',
                'spec_key.*'   => 'sometimes|required|integer',
        ];
       
        $message = [
                'required'    => ':attribute不能为空',
                'integer'     => ':attribute必须是整数', 
                'array'       => ':attribute必须是数组',
                'exists'      => ':attribute值不存在',
        ];

        $validator = Validator::make($request->all(),$rules,$message);

        if($validator->fails())
        {
            $msg = '';
            
            foreach ($validator->errors()->all() as $message)
            {
                $msg = $message;break;
            }
            return $msg;
        }
    }

}