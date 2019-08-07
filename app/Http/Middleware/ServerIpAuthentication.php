<?php

namespace App\Http\Middleware;



use Closure;

use Illuminate\Support\Facades\Log;


class ServerIpAuthentication

{

    /**

     * [handle description]

     * @param  [type]  $request [description]

     * @param  Closure $next    [description]

     * @return [type]           [description]

     */

    public function handle($request, Closure $next)

    {

        // $ip = $request->getClientIp();

        // $all = $request->all();

        // $header = $request->header();


        // Log::channel('server')->info($request->getClientIp(),$request->header());

        // $ips = ['127.0.0.1','119.3.91.126'];//IP白名单

        // if(!in_array( $request->getClientIp(),  $ips)) {

        //     return respond(401,'拒绝访问!');

        // }

        return $next($request);

    }

}

