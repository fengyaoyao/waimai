<?php 
namespace App\Http\Requests;
use Validator;
trait  RecruitmentsRequest
{
    /**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckParameter($request)
    {

        $rules = [
                'username'        => 'required|string|between:2,15',
                'mobile'          => 'required|regex:/^1[3456789][0-9]{9}$/',
                'area_id'         => 'required|integer',
                'type'            => 'required|in:0,1,2',
                'sex'             => 'sometimes|present|integer|in:0,1,2',
                'shop_name'       => 'sometimes|required|string|between:2,45',
                'address'         => 'sometimes|required|string|between:2,100',
        ];
        
        $message = [
                'required'    => ':attribute不能为空',
                'between'     => ':attribute长度必须:min到:max之间',
                'in'          => ':attribute格式不对',
                'integer'     => ':attribute必须是整数',
                'regex'       => ':attribute格式不正确',
                'string'      => ':attribute必须是字符串',
        ];

        //商家入驻
        if($request->type == 2)
        {
            unset($rules['area_id']);
            $rules['shop_name'] = 'required|string|between:2,45';
            $rules['address'] = 'required|string|between:2,100';
        }

        //合伙人
        if($request->type == 1)
        {
            unset($rules['area_id']);
            $rules['wechat_number'] = 'sometimes|present|string|between:2,45';
            $rules['career'] = 'sometimes|present|string|between:2,45';
            $rules['progress_area'] = 'sometimes|present|string|between:2,100';
        }

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