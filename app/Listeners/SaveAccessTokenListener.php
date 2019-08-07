<?php
namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\SaveAccessToken;
use App\Model\User;

class SaveAccessTokenListener
{
    public function handle(SaveAccessToken $event)
    {
    	try {
    		
		    $User =  User::find($event->user_id);
		    if ($User) {
			  	$User->token_type   = $event->data['token_type'];
			  	$User->expiry_time  = date('Y-m-d H:i:s',time() + $event->data['expires_in'] - 300);
			  	$User->access_token = $event->data['access_token'];
			    $User->save();
		    }
		} catch (\Exception $e) {
    		info([ 
                'msg'  => $e->getMessage(), 
                'file' => $e->getFile(), 
                'line' => $e->getLine() 
            ]);
    	}
    }
}
