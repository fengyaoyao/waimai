<?php 
namespace App\Http\Requests;
use Validator;
trait  LoginRequest
{
    /**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function parameter_check($request,$is_register = false,$filter = false)
    {

        $rules = [
                'username' => 'required|between:4,20',
                'password' => 'required|between:6,30',
                'type'     => 'sometimes|present|in:0,1,2'
        ];
       
        $message = [
                'required' => ':attribute不能为空',
                'between'  => ':attribute长度必须:min到:max之间',
                'in'       => '账户类型不对！',
        ];

        if($is_register)
        {
            $rules['username'] = 'required|between:4,20|unique:users';
            $message['unique'] = '该:attribute已经注册了';
        }

        foreach ($rules as $key => $value)
        {
            if(!array_key_exists($key, $request->all()))
            {
                unset($rules[$key]);
            }
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