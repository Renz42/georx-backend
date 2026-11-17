<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification
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
            'status' => 'delivered',
            'title' => 'Order Delivered',
            'order_id' => (int) $this->order->id,
            'message' => "Your medicine order #{$this->order->id} has been delivered successfully by driver {$this->driver->name}! Thank you for using GEORX.",
            'icon' => 'fas fa-check-circle text-emerald-500',
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
        ];
    }
}
