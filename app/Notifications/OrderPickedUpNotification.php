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
        $pharmacyName = $this->order->pharmacy->name ?? 'the pharmacy';

        return [
            'order_id' => $this->order->id,
            'message'  => "Your medicine has been picked up from {$pharmacyName} and verified by driver {$this->driver->name}.",
            'icon'     => 'fas fa-box text-teal-500',
            'url'      => "/orders/{$this->order->id}",
        ];
    }
}
