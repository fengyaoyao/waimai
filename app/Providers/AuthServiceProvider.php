<?php
namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Dusterio\LumenPassport\LumenPassport;
use Carbon\Carbon;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application. 
     *
     * @return void
     */
    public function boot()
    {
        LumenPassport::routes($this->app);
        LumenPassport::tokensExpireIn(Carbon::now()->addDays(7)); //addHours addDays
    }
}
