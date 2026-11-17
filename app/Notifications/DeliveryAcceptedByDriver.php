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
        $driverName = $this->order->driver_name ?: 'A courier rider';

        return [
            'type' => 'delivery_update',
            'status' => 'driver_assigned',
            'title' => 'Rider Assigned',
            'order_id' => (int) $this->order->id,
            'message' => 'Rider ' . $driverName . ' is heading to your pharmacy for Order #' . $this->order->id,
            'url' => route('portal.orders', ['status' => 'accepted']),
            'icon' => 'fas fa-motorcycle text-indigo-500',
        ];
    }
}
