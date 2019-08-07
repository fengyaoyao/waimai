<?php

namespace App\Http\Middleware;
use Closure;
class CheckShop
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(\Auth::user()->type !=1) return respond(201,'请申请店铺入驻！');

        return $next($request);
    }
}
