<?php 
namespace App\Http\Requests;
use Validator;
trait  UserRequest
{
    /**
     *  投诉参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckParameterComplaint($request)
    {

        $rules = [
                'user_id'         => 'required|integer',
                'username'        => 'required|string|between:2,20',
                'mobile'          => 'required|regex:/^1[3456789][0-9]{9}$/',
                'order_id'        => 'required|integer|exists:order,order_id',
                'content'         => 'sometimes|required|string|between:2,1024',
                'picture'         => 'sometimes|present|between:2,1024',
                'type'            => 'required|integer|in:0,1,2'
        ];
        
        $message = [
                'required'    => ':attribute不能为空',
                'between'     => ':attribute长度必须:min到:max之间',
                'integer'     => ':attribute必须是整数',
                'regex'       => ':attribute格式不正确',
                'string'      => ':attribute必须是字符串',
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