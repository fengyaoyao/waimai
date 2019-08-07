<?php
namespace App\Events;
class Withdrawal extends Event
{
    public $id;

    public function __construct($id)
    {
        $this->id = $id;
    }
}