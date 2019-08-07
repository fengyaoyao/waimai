<?php 
namespace App\Http\Requests;
use Validator;
trait  PromRequest
{
	/**
     *  参数检查
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function CheckParameter($request)
    {

        $rules = [
                'type0.*.shop_id'         => 'required|integer|exists:shops,shop_id',
                'type0.*.prom_id'         => 'sometimes|required|integer',
                'type0.*.title'           => 'required|string|between:2,45',
                'type0.*.money'           => 'required|numeric',
                'type0.*.condition'       => 'required|numeric',
                'type0.*.start_time'      => 'required|date_format:Y-m-d H:i:s',
                'type0.*.end_time'        => 'required|date_format:Y-m-d H:i:s',
                'type0.*.status'          => 'required|in:0,1',
                'type0.*.type'            => 'required|in:0',

                'type1.*.shop_id'         => 'required|integer|exists:shops,shop_id',
                'type1.*.title'           => 'required|string|between:2,45',
                'type1.*.money'           => 'required|numeric',
                'type1.*.prom_id'         => 'sometimes|required|integer',
                'type1.*.type'            => 'required|in:1',
                'type1.*.condition'       => 'required||numeric',
                'type1.*.status'          => 'required|integer|in:0,1',

                'type2.*.shop_id'         => 'required|integer|exists:shops,shop_id',
                'type2.*.money'           => 'required|numeric',
                'type2.*.prom_id'         => 'sometimes|required|integer',
                'type2.*.type'            => 'required|in:2',
                'type2.*.status'          => 'required|integer|in:0,1',
        ];
        
        $message = [
                'required'    => ':attribute不能为空',
                'between'     => ':attribute长度必须:min到:max之间',
                'date_format' => ':attribute格式不对',
                'integer'     => ':attribute必须是整数',
                'numeric'     => ':attribute必须是数字字符串',
                'in'          => ':attribute内容不匹配',
                'regex'       => ':attribute格式不正确',
                'string'      => ':attribute必须是字符串',
                'exists'      => ':attribute不存在',
        ];

        // if($request->has('prom_id'))
        // {

        //     foreach ($rules as $key => $value)
        //     {
        //         $rules[$key] = 'sometimes|'. $value;
        //     }

        //     $rules['prom_id'] = 'required|integer|exists:prom_shop,prom_id';
        // }

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
