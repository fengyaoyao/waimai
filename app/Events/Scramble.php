<?php
namespace App\Events;
class Scramble extends Event
{
    public $order_id;

    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }
}