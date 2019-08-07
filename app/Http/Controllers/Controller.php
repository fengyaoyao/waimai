<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{

	protected  $message = [
                'in'          => ':attribute参数不对！',
                'boolean'     => ':attribute格式不对',
                'integer'     => ':attribute必须是整数',
                'string'      => ':attribute必须是字符串',
                'between'     => ':attribute长度必须:min到:max之间',
                'required'    => ':attribute不能为空',
                'array'       => ':attribute必须是数组',
                'exists'      => ':attribute不存在!',
                'date_format' => ':attribute格式不对',
                'json'        => ':attribute必须是json字符串',
                'regex'       => ':attribute格式不正确！',
                'size'        => ':attribute格式大小不正确！',
                'numeric'     => ':attribute必须是数字！',
                'required_with'=>':attribute不能为空',
                'required_if'  =>':attribute不能为空',
    ];

    /**
     * 请求数据验证错误返回
     * @param Request $request
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws ValidationException
     */
/*    protected function throwValidationException(Request $request, $validator)
    {
        $response = [
        	 'status'  => 422,
	         'message' => $validator->errors()->first(),
	         'data'    => ''
        ];
        throw new ValidationException($validator, $response);
    }*/
}
