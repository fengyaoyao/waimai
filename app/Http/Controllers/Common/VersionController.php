<?php 
namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Version;

class VersionController extends Controller
{

	/**
	 * app 版本
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
	public function app_version(Request $request)
	{
		$this->validate($request, 
			[
				'model'         => 'required|integer|in:1,2',
				'client_side'   => 'required|integer|in:0,1,2',
			],
		$this->message);

		$Version = Version::where('status',1)->where($request->all())->first();

		return respond(200,'获取成功！',empty($Version)?[]:$Version);
	}
}