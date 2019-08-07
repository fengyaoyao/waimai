<?php 

namespace App\Http\Requests;

use Validator;

trait  GoodsRequest

{

	/**

     *  参数检查

     * @param  [type] $request [description]

     * @return [type]          [description]

     */

    public function CheckParameterGoods($request)

    {



        $rules = [



                'title'            => 'required|between:2,45',

				'price'            => 'required|numeric',

				'cate_id'          => 'required|integer',

                'sort'             => 'integer',

                'intro'            => 'between:1,30',

				'shelves_status'   => 'boolean',

				'details_figure'   => 'string',

				'shelves_start'    => 'date_format:Y-m-d H:i:s',

				'shelves_end'      => 'date_format:Y-m-d H:i:s',

				'shop_recommend'   => 'boolean',

				'auto_shelves'     => 'boolean',

                'packing_expense'  => 'sometimes|present|numeric',

                'units'            => 'sometimes|present|string|between:1,45',

                'shop_id'          => 'required|integer|exists:shops,shop_id',

                'purchase_quantity'=> 'sometimes|present|numeric|min:0',

                'is_required'      => 'sometimes|present|numeric|between:0,1',

                'discount'         => 'sometimes|present|numeric|between:0,1',

                'discount_astrict' => 'sometimes|present|numeric|min:1',

        ];

       

        $message = [

                'required'    => ':attribute不能为空',

                'between'     => ':attribute长度必须:min到:max之间',

                'date_format' => ':attribute时间格式不对',

                'boolean'     => ':attribute格式不对',

                'integer'     => ':attribute必须是整数', 

                'numeric'     => ':attribute必须是数字',

                'string'      => ':attribute必须是字符串',

                'exists'      => ':attribute该商品没有找到！',



        ];



        //修改则不必须填写

        if($request->has('goods_id'))

        {

            $rules['price']    =  'sometimes|required|numeric';

            $rules['cate_id']  =  'sometimes|required|integer';

            $rules['goods_id'] =  'required|integer|exists:goods,goods_id';

            $rules['title']    =  'sometimes|required|between:2,45';

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



    /**

     *  参数检查

     * @param  [type] $request 请求

     * @return [type]          [description]

     */

    public function CheckSearchParameter($request)

    {

        $rules = [

                'shop_recommend'   => 'sometimes|required|in:0,1',

                'shelves_status'   => 'sometimes|required|in:0,1,2',

                'cate_id'          => 'sometimes|integer',

                'shop_id'          => 'required|integer|exists:shops,shop_id',

                'page_size'       => 'sometimes|required|integer'

        ];

    

        $message = [

                'required'    => ':attribute不能为空',

                'between'     => ':attribute长度必须:min到:max之间',

                'integer'     => ':attribute必须是整数',

                'in'          => ':attribute不在范围内！', 

                'exists'      => ':attribute该店铺没有找到！',

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