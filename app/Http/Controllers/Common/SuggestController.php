<?php 
namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\User;
use App\Model\Complaint;


class SuggestController extends Controller
{
	/**
	 * [submit 提交建议]
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function submit(Request $request) {

		$this->validate($request, 
			[
				'client_type' => 'required|integer|in:1,2',
				'type' => 'required|integer|in:3,4,5,6,7,8',
				'content' => 'required|string|between:1,200'
			],
		$this->message);

		$request->merge(['user_id' => \Auth::id()]);

		$Complaint = new Complaint();

		foreach ($request->only(['client_type','content','picture','type','user_id']) as $key => $value)
		{
			if($request->filled($key)) $Complaint->$key = $value;
		}

		if($Complaint->save())
			return respond(200,'提交成功！',$Complaint);
		else
			return respond(201,'提交失败！');
	}
}