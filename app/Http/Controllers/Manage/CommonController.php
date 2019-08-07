<?php 
namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\{Admin,User,Area,Shop,Order};

class CommonController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
       $this->request  = $request;
    }

    /**
     * 登陆
     * @return [type] [description]
     */
    public function login()
    {
        $this->validate($this->request, 
            [
                'user_name'      => 'required|string|between:4,45|exists:admin,user_name',
                'password'       => 'required|string|between:6,45',
            ],
            array_merge($this->message,['exists'=>'账户或密码输入不正确！'])
        );

        $adminModel = new Admin;
        $admin      = $adminModel->findForPassport($this->request->user_name);


        if (empty($admin)) 
        {
            return respond(422,'账户或密码输入不正确！!');
        } 

        if (!(md5(env('AUTH_CODE').$this->request->password) == $admin->password))
        {
            return respond(422,'账户或密码输入不正确！');
        }

        if ($admin->role_id != 1) { //用于判断超级管理人员

                if (empty($admin->area_id)) {
                    return respond(422,'该账户未绑定区域！');
                }

                if (empty($admin->shop_ids)) {
                    return respond(422,'该账户未绑定店铺！');
                }
        }

        $admin->api_token = sha1(time());
        $admin->save();

        return respond(200,'登陆成功',$admin);
    }

    /**
     * 区域列表
     * @return [type] [description]
     */
    function area_list()
    {
        return respond(200,'获取成功！', Area::orderBy('created_at')->get());
    }
}