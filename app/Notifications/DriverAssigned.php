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
        $driverName = $this->order->driver_name ?: 'A courier rider';

        return [
            'type' => 'delivery_update',
            'status' => 'driver_assigned',
            'title' => 'Driver Assigned',
            'message' => $driverName . ' is on the way to pick up your order #' . $this->order->id . '.',
            'order_id' => (int) $this->order->id,
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-motorcycle text-blue-500',
        ];
    }
}
