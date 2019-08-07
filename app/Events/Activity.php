<?php
namespace App\Events;

class Activity extends Event
{
    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }
}