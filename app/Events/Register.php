<?php
namespace App\Events;
class Register extends Event
{
    public $user_id;
    /**
     * 注册事件
     * @param  int $user_id [用户id]
     */
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }
}