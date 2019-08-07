<?php 
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class FileController extends Controller
{
	/**
	 * 文件上传
	 * @param  Request $request [description]
	 * @return [type]           [description]
	 */
    public function upload(Request $request)
    {
        if($request->isMethod('post'))
        {

            $file = $request->file('picture');
            // 文件是否上传成功
            if ($file->isValid())
            {
                // 获取文件相关信息
                // $originalName = $file->getClientOriginalName(); // 文件原名
                // $realPath     = $file->getRealPath();   //临时文件的绝对路径
                // $type         = $file->getClientMimeType();     // image/jpeg

                $ext        = $file->getClientOriginalExtension();     // 扩展名
                $filename   = date('YmdHis') . '-' . uniqid() . '.' . $ext;
                $bool       = $file->move(storage_path('app/public/'.date('Y-m-d')), $filename);
                $data       = [
                    'host'  => url(),
                    'directory_name' => 'storage/',
                    'src'            => date('Y-m-d').'/'.$filename
                ];
                if($bool)
                return respond(200,'上传成功！',$data);
            }
        }
                return respond(201,'上传失败！');
    }
}
