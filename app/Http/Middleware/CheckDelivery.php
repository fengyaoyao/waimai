<?php

namespace App\Http\Middleware;
use Closure;
class CheckDelivery
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
        if(\Auth::user()->type !=2) return respond(201,'请申请配送人员！');
        return $next($request);
    }
}
