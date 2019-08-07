<?php 
namespace App\Http\Requests;
use Validator;
trait  GoodsCateRequest
{
	/**
     *  参数检查
     * @param  [type] $request 请求
     * @return [type]          [description]
     */
    public function CheckParameter($request)
    {

        $rules = [
                'cate_name'   => 'required|between:1,45',
				'shop_id'     => 'required|integer|exists:shops,shop_id',
                'cate_id'     => 'sometimes|required|integer|exists:goods_cates,cate_id',
        ];
       
        $message = [
                'required'    => ':attribute不能为空',
                'between'     => ':attribute长度必须:min到:max之间',
                'integer'     => ':attribute必须是整数', 
                'exists'      => ':attribute不存在',
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