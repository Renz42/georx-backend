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
            'type' => 'delivery_update',
            'status' => 'arrived_at_dropoff',
            'title' => 'Driver Arrived',
            'message' => 'Your driver has arrived at the drop-off location for Order #' . $this->order->id . '.',
            'order_id' => (int) $this->order->id,
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-map-marker-alt text-emerald-500',
        ];
    }
}
