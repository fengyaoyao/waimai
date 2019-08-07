<?php
namespace App\Events;

class SaveAccessToken extends Event
{
    public $data = [];
    public $user_id;

    public function __construct($data,$user_id)
    {
        $this->data    = $data;
        $this->user_id = $user_id;
    }
}