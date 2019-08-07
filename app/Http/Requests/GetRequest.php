<?php 
namespace App\Http\Requests;
use Validator;
trait  GetRequest
{

    public function parameter_route($request)
    {

        $rules = [
                'username' => 'required|between:4,20',
                'password' => 'required|between:6,30'
        ];
       
        $message = [
                'required' => ':attribute不能为空',
                'between'  => ':attribute长度必须:min到:max之间',
        ];

        if($is_register)
        {
            $rules['username'] = 'required|between:4,20|unique:users';
            $message['unique'] = '该:attribute已经注册了';
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