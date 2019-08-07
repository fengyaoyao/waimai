<?php
namespace App\Events;

class CancelOrder extends Event
{
    public $order_id;
    public $order_status;
    public $type;

    public function __construct($order_id, $order_status = 3 ,$type = 1)
    {
        $this->order_id     = $order_id;
        $this->order_status = $order_status;
        $this->type         = $type;
    }
}