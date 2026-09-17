<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverArrived extends Notification
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
            'title' => 'Driver Arrived',
            'message' => 'Your driver has arrived at the drop-off location.',
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-map-marker-alt text-emerald-500'
        ];
    }
}
