<?php 

namespace App\Http\Controllers\Common;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Http\Requests\RecruitmentsRequest;

use App\Model\Recruitment;



class RecruitmentController extends Controller

{



	use RecruitmentsRequest;

	/**

	 * [index 平台招聘]

	 * @param  Request $equest [description]

	 * @return [type]          [description]

	 */

	public function index(Request $request)

	{



		$result_msg = $this->CheckParameter($request);



		if(!empty($result_msg)) {

			return respond(422,$result_msg);

		}



		if(Recruitment::where(['mobile'=>$request->mobile, 'status' => 0])->exists()){

			return respond(422,'你已经提交过了');

		}



		$Recruitment = new Recruitment();

		

		foreach ($request->only(['username','mobile','area_id','type','shop_name','address','user_id','sex','wechat_number','career','progress_area']) as $key => $value)

		{

			if($request->filled($key)){

				$Recruitment->$key = $value;

			}

		}



		if($Recruitment->save())

			return respond(200,'提交成功！');

		else

			return respond(201,'提交失败！');

	}

}