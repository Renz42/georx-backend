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
            'order_id' => $this->order->id,
            'message'  => "Driver {$this->driver->name} is now out for delivery heading to your location for Order #{$this->order->id}.",
            'icon'     => 'fas fa-shipping-fast text-blue-500',
            'url'      => "/orders/{$this->order->id}",
        ];
    }
}
