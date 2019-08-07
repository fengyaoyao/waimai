<?php
namespace App\Http\Middleware;

use Closure;
use App\Model\Admin;

class AuthToken
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {

        $Admin = Admin::where('api_token', '=', $request->header('api-token'))->first();

        if(empty($Admin)) 
        {
            return respond(401,'登陆过期，请重新登陆!');
        }

        $request->attributes->add(['admin' => $Admin->toArray()]);
        return $next($request);
    }
}
