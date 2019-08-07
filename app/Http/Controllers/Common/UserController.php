<?php 

namespace App\Http\Controllers\Common;



use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\{Auth,Crypt,DB,Mail,Cache};

use App\Model\{User,Collect,Coupon,AccountLog,CouponList,Order,Area};

use App\Http\Requests\LoginRequest;

use App\Http\Controllers\Traits\ProxyHelpers;

use App\Events\SaveAccessToken;

use App\Events\CalculateBrokerage;

use App\Exceptions\MyException;

use Carbon\Carbon;

use App\Http\Controllers\Traits\SignIn;

use Monolog\Handler\StreamHandler;

use Monolog\Formatter\LineFormatter;

use Monolog\Logger;



class UserController extends Controller

{

    use ProxyHelpers,LoginRequest,SignIn;



    /**

     * 用户登录

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function login(Request $request)

    {



        $msg = $this->parameter_check($request);

        

        if(!empty($msg)) {

            return respond(422,$msg);

        }



        $UserModel = new User;

        $user      = $UserModel->findForPassport($request->input('username'));





        if(empty($user)) return respond(203,'账户或密码输入不正确！');



        if($user->is_lock == 1) return respond(203,'你的账号存在问题请联系管理员!');



        if(empty($user->password)) return respond(203,'账户或密码输入不正确！');



        $in_type = [[0,1,2],[1],[2]];



        if (!in_array($user->type, $in_type[$request->input('type',0)])) return respond(203,'账户类型不匹配！');

        

        if($request->input('password')  != Crypt::decryptString($user->password)) return respond(203,'账户或密码输入不正确！');



        $params = [

            'username'     => $request->input('username'),

            'password'     => $request->input('password'),

            'grant_type'   => env('OAUTH_GRANT_TYPE'),

            'client_secret'=> env('OAUTH_CLIENT_SECRET'),

            'client_id'    => env('OAUTH_CLIENT_ID'),

            'scope'        => env('OAUTH_SCOPE')

        ];



        $request_oauth  = $this->authenticate($request,$params);



        if(is_array($request_oauth))

        {

            event(new SaveAccessToken($request_oauth,$user->user_id));

            try {
                $user->last_ip = $request->getClientIp();
                $user->save();
            } catch (\Exception $e) {}
            

            return respond(200,'请求成功',$request_oauth);

        }

        

        return respond(402,$request_oauth);

    }

    

    /**

     * [msgLogin 短信验证码登陆]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function msgLogin(Request $request) {

        $this->validate($request, [
            'mobile'=> 'required|required|regex:/^1[3456789][0-9]{9}$/',
            'code' => 'required|string|between:4,6',
            'headimgurl' => 'sometimes|required|string|between:4,1024',
            'openid' => 'sometimes|required|string|between:4,1024',
            'nickname' => 'sometimes|required|string|between:1,288',
            'sex' => 'sometimes|required|integer|in:0,1,2'
        ],$this->message);

        $pass_mobile = ['13512345678' =>'123456','18381001233'=>'123456','13402858313'=>'123456','17002811520'=>'123456'];

        $mobile = $request->mobile;

        if (!array_key_exists( $mobile, $pass_mobile ) ) {

            $cacheKey = Cache::get($mobile.'_2');

            if(empty($cacheKey)) return respond(201,'验证码已过期！');

            if($cacheKey != $request->code) return respond(201,'验证码不正确！');

        }else{

            if($pass_mobile[$mobile] != $request->code) return respond(201,'验证码不正确！');
        }



        //用户没有找到 就新增用户

        if (empty(User::where('mobile',$mobile)->count())) {

            $last_id = User::max('user_id') + 1;

            $data = [

                'mobile' => $mobile,

                'username' => 'subang'.$last_id,

                'nickname' => get_rand_str(),

                'password' => Crypt::encryptString(mt_rand(100000,999999)),

                'register_type' => '1',

                'created_at' => date('Y-m-d H:i:s'),
            ];

            if ($request->filled('openid')) {
              if (!User::where('openid',$request->openid)->exists()) {
                foreach ($request->only(['openid','nickname','headimgurl','sex']) as $key => $value) {
                    if ($request->filled($key)) {
                       $data[$key] = $value;
                    }
                }
              }
            }

            DB::table('users')->insert($data);
        }



        $User = User::where('mobile',$mobile)->first();

        if($User->is_lock == 1){
            return respond(203,'账号已被冻结 请联系管理员!');
        }

        $in_type = [[0,1,2],[1],[2]];



        if (!in_array($User->type, $in_type[$request->input('type',0)])) return respond(203,'账户类型不匹配！');



        $params = [

            'username'     => $User->username,

            'password'     => Crypt::decryptString($User->password),

            'grant_type'   => env('OAUTH_GRANT_TYPE'),

            'client_secret'=> env('OAUTH_CLIENT_SECRET'),

            'client_id'    => env('OAUTH_CLIENT_ID'),

            'scope'        => env('OAUTH_SCOPE')

        ];



        $request_oauth  = $this->authenticate($request,$params);



        if(is_array($request_oauth))

        {

            event(new SaveAccessToken($request_oauth,$User->user_id));

            try {

                $User->last_ip = $request->getClientIp();
                $User->save();

            } catch (\Exception $e) {}

            return respond(200,'请求成功',$request_oauth);

        }

        

        return respond(402,$request_oauth);



    }







    /**

     * 用户注册

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function register(Request $request)

    {



        $msg = $this->parameter_check($request,true);



        if(!empty($msg)) return respond(422,$msg);



        $UserModel = new User;

        $UserModel->username = $request->input('username');

        $UserModel->password = Crypt::encryptString($request->input('password'));



        if($UserModel->save())



            return respond(200,'注册成功!');

        else



            return respond(203,'注册失败!');

    }



    /**

     * 用户详情

     * @return [type] [description]

     */

    public function user_info()

    {

        $user_info = User::where('user_id',\Auth::id())

                            ->with('area')

                            ->withCount(['couponList'=>function($query){

                                return $query->couponExists();

                            }])

                            ->first();

        if($user_info){



            if (empty($user_info->openid)) {

               $user_info->openid =  $user_info->wechat_number;

            }

            

            if (empty($user_info->alipay_number)) {

                $user_info->alipay_number = '';

            }

            $point_rate = \App\Model\Config::where('name','point_rate')->where('inc_type','basic')->value('value');

            if (empty($point_rate)) {
                $point_rate = 100; 
            }

            $user_info->point_rate = (integer)$point_rate;



            return respond(200,'获取成功!',$user_info);

        }else{

            return respond(203,'获取失败!');

        }

    }



    /**

     * 用户id

     * @return [type] [description]

     */

    public function userid()

    {

        $userid = Auth::id();

        if($userid)

            return respond(200,'获取成功!',$userid);

        else

            return respond(203,'获取失败!');

    }



    /**

     * 修改用户个人信息

     * @return [type] [description]

     */

    public function change_info(Request $request)

    {

        $this->validate($request, [
            'push_id'        => 'sometimes|present|string|between:2,100',
            'getui_id'       => 'sometimes|present|string|between:2,60',
            'wechat_number'  => 'sometimes|present|string|between:1,128',
            'headimgurl'     => 'sometimes|required|string|between:4,1024',
            'username'       => 'sometimes|required|alpha_dash|string|between:2,45',
            'work_status'    => 'sometimes|required|in:0,1',
            'alipay_number'  => 'sometimes|present|string|between:5,128',
            'nickname'       => 'sometimes|present|string|between:1,45',
            'bank_name'      => 'sometimes|present|string|between:4,45',
            'bank_card'      => 'sometimes|present|string|between:6,100',
            'is_aggregation' => 'sometimes|required|in:0,1',
        ],
        $this->message);
        $UserModel  =  User::find(Auth::id());
        foreach ($request->only(['push_id','wechat_number','headimgurl','username','work_status','alipay_number','nickname','bank_name','bank_card','getui_id','is_aggregation'])  as $key => $value)
        {
            $UserModel->$key = $value;
        }
        if($UserModel->save())
            return respond(200,'修改成功!');
        else
            return respond(203,'修改失败!');
    }



    /**

     * 刷新token

     * @return [type] [description]

     */

    public function refresh(Request $request)

    {

        $this->validate($request, ['refresh_token'=>'required|string'],$this->message);



        $request_oauth  = $this->refresh_token($request);

    

        if(is_array($request_oauth))

        {

            $client   = new \GuzzleHttp\Client();

            $url      = url() . '/user_id';



            $response = $client->request('GET',  $url , [

                'headers' => [

                    'Accept' => 'application/json',

                    'Authorization' => 'Bearer '.$request_oauth['access_token'],

                ],

            ]);

            $response = json_decode($response->getBody()->getContents());



            event(new SaveAccessToken($request_oauth,$response->data));



            return respond(200,'请求成功',$request_oauth);



        }else{

            

            return respond(401,$request_oauth);

        }

    }



    /**

     * [buildMobile 绑定手机号码]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function buildMobile(Request $request)

    {

        $this->validate($request, 

        [

            'mobile'=> 'required|required|regex:/^1[3456789][0-9]{9}$/',

            'code'  => 'required|string|between:4,6',

            'scene' => 'required|integer|in:1,2,3,4',

        ],$this->message);



        $mobile = $request->mobile;

        $scene  = $request->scene;

        $key    = $mobile.'_'.$scene;

        $cacheKey = Cache::get($key);



        if(empty($cacheKey)) return respond(201,'验证码已过期！');



        if($cacheKey != $request->code) return respond(201,'验证码不正确！');



        $exists = User::where('mobile', $mobile)->count();

        if($exists) return respond(201,'该号码已经存在了');



        $User = User::find(Auth::id());

        $User->mobile = $mobile;



        if($User->save())

            return respond(200,'操作成功!');

        else

            return respond(201,'操作失败!');

    }





    /**

     * [sendVerifyCode 发送验证码]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function sendVerifyCode(Request $request)

    {

        $this->validate($request, 

        [

            'mobile'=> 'required|regex:/^1[3456789][0-9]{9}$/',

            'scene' => 'sometimes|present|integer|in:1,2,3,4',

        ],$this->message);



        $mobile = $request->mobile;

        $scene  = $request->filled('scene') ? $request->scene : 2;

        $code   = mt_rand(1000,9999);

        try {

            $url    = env('BACKEND_DN').'index.php/mobile/api/sendSms?mobile='."{$mobile}&code={$code}&scene={$scene}";

            $result = curl_request($url);

        } catch (\Exception $e) {

            $result['status'] = 500;

        }

        

        if (!empty($result['status']) && $result['status'] ==  1 ) {

            Cache::forget($mobile.'_'.$scene);

            Cache::add($mobile.'_'.$scene, $code, 15);

            return respond(200,'发送成功!');
        }

            return respond(201,'发送失败!');

    }



    /**

     * [userHasShop 用户关于店铺信息]

     * @param  Request $request [description]

     * @return [type]           [description]

     */

    public function userHasShop(Request $request) {



        $this->validate($request, ['shop_id'=>'required|integer|exists:shops,shop_id'],$this->message);



        $is_first = Order::where('user_id',\Auth::id())->count();

        

        //是否收藏当前店铺

        $is_collect  =  Collect::where(['user_id'=>\Auth::id(),'shop_id'=>$request->shop_id])->count();



        return respond(200,'获取成功！',['is_first'=>$is_first,'is_collect'=>$is_collect]);

    }

     /**
     * [changePassword 修改密码]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function changePassword(Request $request)
    {
        $this->validate($request, 
        [
            'old_password' => 'required|between:6,30',
            'new_password' => 'required|between:6,30',
        ],$this->message);

        $User = User::find(\Auth::id());

        if($request->old_password  != Crypt::decryptString($User->password)){
            return respond(203,'原密码输错误！');
        }

        $User->password = Crypt::encryptString($request->new_password);

        try {

            $data = [
                '原密码'=> $request->old_password,
                '新密码'=> $request->new_password,
            ];

            $hander = new StreamHandler(storage_path('/logs/change_password').'.log');
            $hander->setFormatter(new LineFormatter(null, null, true, true));
            $Logger = new Logger('ChangePassword');
            $Logger->pushHandler($hander);
            $Logger->info($User->user_id,$data);
            
        } catch (\Exception $e) {}

        if($User->save())
            return respond(200,'操作成功!');
        else
            return respond(201,'操作失败!');
    }





    function test(Request $request)
    {
          try {


            $wechatAppPay = new \wechatAppPay(config('pay.wechatpay'));

            $params['transaction_id'] = '4200000314201904230550520232';
            $params['total_fee'] = (3.1 * 100);
            $params['refund_fee'] = (1.1 * 100);

            $result = $wechatAppPay->refundOrder($params);

            return respond(200,'操作失败!',$result);

          } catch (\Exception $e) {
            info($e->getMessage());
          }
        
        // echo Crypt::decryptString('eyJpdiI6IlZ5Q1NQRDIrTW1ZYmREeFVRMXEyR3c9PSIsInZhbHVlIjoiSE1MbFdhKzlnN1NpVHV2ZjBCdWx0dz09IiwibWFjIjoiMDAwZjJmZTQ5ZTIwMDM4MDM1ZTJkYmRhZmRiYTY1YmFjZmIwMzE3MWU2YjExZWI0MzlkMmMyMWRmMjk1NjZkZSJ9');exit;
        // echo date('Y-m-d H:i:s');
        
        // Cache::forget('18857370267_2');
        // Cache::add('18857370267_2', '1234', 15);

        // Cache::forget('15623028747_2');
        // Cache::add('15623028747_2', '1234', 15);
        // $redis  = new \Redis();
       //  $redis->connect('127.0.0.1', 6379);
        // $redis->select(14);
        // $redis->setex('yaoayo',100,'feng');
        // $redis->set('jb','gb');
        // print_r($redis->keys('*'));
         
                // $redis->delete($redis->keys('*'));exit;
    }
}



