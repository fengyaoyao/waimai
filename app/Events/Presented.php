<?php
namespace App\Events;

class Presented extends Event
{
    public $order_id;

    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }
}