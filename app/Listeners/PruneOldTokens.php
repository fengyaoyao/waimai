<?php

namespace App\Listeners;

use Laravel\Passport\Events\RefreshTokenCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class PruneOldTokens
{

    public function handle(RefreshTokenCreated $event)
    {
    	// $user_id = DB::table('oauth_access_tokens')->where('id',$event->accessTokenId)->value('user_id');

    }
}
