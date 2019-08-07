<?php 
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class RechargeController extends Controller
{
    protected $user_id;
    public function __construct(){
        $user = Auth::user();
        $this->user_id = $user->user_id;
    }
}