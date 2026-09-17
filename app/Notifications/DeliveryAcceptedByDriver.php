<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryAcceptedByDriver extends Notification
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
            'title' => 'Rider Assigned',
            'message' => 'Rider ' . $this->order->driver_name . ' is heading to your pharmacy for Order #' . $this->order->id,
            'url' => route('portal.orders', ['status' => 'accepted']),
            'icon' => 'fas fa-motorcycle text-indigo-500'
        ];
    }
}
