<?php
namespace App\Http\Controllers\Traits;
use Validator;
use GuzzleHttp\Client;
trait ProxyHelpers
{
    public function authenticate($request,$params)
    {
        $url = url() . '/oauth/token';

        $client = new Client();
        try {
            $respond = $client->request('POST', $url, ['form_params' => $params]);
        } catch (\Exception $exception){
            return '请求失败，服务器错误';
        }
        if ($respond->getStatusCode() !== 401)
        {
            return json_decode($respond->getBody()->getContents(), true);
        }
            return '请求失败，服务器错误';
    
    }
    
    /**
     * 刷新token
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function refresh_token($request)
    {
        $params = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $request->input('refresh_token'),
            'client_id'     => env('OAUTH_CLIENT_ID'),
            'client_secret' => env('OAUTH_CLIENT_SECRET'),
            'scope'         => env('OAUTH_SCOPE'),
        ];

        $client = new Client();

        try {

            $url = url() . '/oauth/token';

            $respond = $client->request('POST', $url, ['form_params' => $params]);

        }catch (\Exception $e){
            return '登陆过期,请重新登陆!';
        }

        if ($respond->getStatusCode() !== 401)
        {
            
            return json_decode($respond->getBody()->getContents(), true);
        }

            return '登陆过期,请重新登陆!';
    } 
}