<?php 
namespace App\Http\Requests;
use Validator;
trait  ItemRequest
{
	/**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckParameter($request)
    {

        $rules = [
                'goods_id'     => 'required|integer',
                'shop_id'      => 'required|integer|exists:shops,shop_id',
                'spec_id'      => 'required|integer|exists:specs,spec_id',
                'item'         => 'required|string|between:1,20',
                'price'        => 'sometimes|present|numeric',
                'sort'         => 'sometimes|present|integer',
                'item_id'      => 'sometimes|present|integer',
        ];
       
        $message = [
                'required'    => ':attribute不能为空',
                'between'     => ':attribute长度必须:min到:max之间',
                'integer'     => ':attribute必须是整数', 
                'numeric'     => ':attribute必须是数字',
                'string'      => ':attribute必须是字符串',
                'exists'      => ':attribute该属性不存在!'
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

    /**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckSpecsParameter($request)
    {
        $rules = [
                'goods_id'         => 'required|integer',
                'shop_id'          =>'required|integer|exists:shops,shop_id',
                'name'             => 'required|string|between:1,20',
                'spec_id'          => 'sometimes|required|integer|exists:specs,spec_id',
                'select_type'      => 'sometimes|boolean',
        ];
       
        $message = [
                'required'    => ':attribute不能为空',
                'boolean'     => ':attribute格式不对',
                'integer'     => ':attribute必须是整数', 
                'string'      => ':attribute必须是字符串',
                'exists'      => ':attribute不存在!'
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