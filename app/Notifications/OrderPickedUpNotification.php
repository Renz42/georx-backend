<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPickedUpNotification extends Notification
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
        $this->order->loadMissing('pharmacy');
        $pharmacyName = $this->order->pharmacy?->name ?? 'the pharmacy';

        return [
            'type' => 'delivery_update',
            'status' => 'picked_up',
            'title' => 'Order Picked Up',
            'order_id' => (int) $this->order->id,
            'message' => "Your medicine has been picked up from {$pharmacyName} and verified by driver {$this->driver->name}.",
            'icon' => 'fas fa-box text-teal-500',
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
        ];
    }
}
