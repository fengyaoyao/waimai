<?php
namespace App\Events;

class ClearingAccount extends Event
{
    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }
}