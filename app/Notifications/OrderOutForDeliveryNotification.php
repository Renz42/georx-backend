<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderOutForDeliveryNotification extends Notification
{
    use Queueable;

    public Order $order;
    public User $driver;

    public function __construct(Order $order, User $driver)
    {
        $this->order = $order;
        $this->driver = $driver;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'delivery_update',
            'status' => 'out_for_delivery',
            'title' => 'Out for Delivery',
            'order_id' => (int) $this->order->id,
            'message' => "Driver {$this->driver->name} is now out for delivery heading to your location for Order #{$this->order->id}.",
            'icon' => 'fas fa-shipping-fast text-blue-500',
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
        ];
    }
}
