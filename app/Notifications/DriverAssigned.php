<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverAssigned extends Notification
{
    use Queueable;

    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Driver Assigned',
            'message' => $this->order->driver_name . ' is on the way to pick up your order.',
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-motorcycle text-blue-500'
        ];
    }
}
