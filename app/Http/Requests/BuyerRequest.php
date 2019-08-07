<?php 
namespace App\Http\Requests;
use Validator;
trait  BuyerRequest
{
	/**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckSearchParameter($request)
    {

        $rules = [
                'area_id'         => 'sometimes|integer',
                'is_new'          => 'sometimes|boolean',
                'sort'            => 'sometimes|in:desc,asc',
                'store_ratings'   => 'sometimes|in:desc,asc',
                'sales'           => 'sometimes|in:desc,asc',
                'search_name'     => 'sometimes|string|between:1,20',
        ];
       
        $message = [
                'in'          => ':attribute参数不对！',
                'boolean'     => ':attribute格式不对',
                'integer'     => ':attribute必须是整数',
                'string'      => ':attribute必须是字符串',
                'between'     => ':attribute长度必须:min到:max之间'
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