<?php
namespace App\Events;
class CalculateBrokerage extends Event
{
    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }
}