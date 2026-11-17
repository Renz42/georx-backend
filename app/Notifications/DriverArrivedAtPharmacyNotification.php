<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverArrivedAtPharmacyNotification extends Notification
{
    use Queueable;

    public Order $order;
    public User $driver;
    public string $recipientType;

    public function __construct(Order $order, User $driver, string $recipientType = 'customer')
    {
        $this->order = $order;
        $this->driver = $driver;
        $this->recipientType = $recipientType;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $this->order->loadMissing('pharmacy');
        $driverName = $this->driver->name ?? 'Driver';
        $pharmacyName = $this->order->pharmacy?->name ?? 'the pharmacy';

        if ($this->recipientType === 'pharmacy') {
            return [
                'type' => 'delivery_update',
                'status' => 'driver_at_pharmacy',
                'title' => 'Driver Arrived at Pharmacy',
                'order_id' => (int) $this->order->id,
                'message' => "Driver {$driverName} has arrived at your pharmacy for Order #{$this->order->id}.",
                'icon' => 'fas fa-store-alt text-blue-500',
                'action_url' => '/portal/orders',
                'url' => '/portal/orders',
            ];
        }

        return [
            'type' => 'delivery_update',
            'status' => 'driver_at_pharmacy',
            'title' => 'Driver at Pharmacy',
            'order_id' => (int) $this->order->id,
            'message' => "Driver {$driverName} has arrived at {$pharmacyName} to pick up your medicine for Order #{$this->order->id}.",
            'icon' => 'fas fa-building text-blue-500',
            'action_url' => "/orders/{$this->order->id}",
            'url' => route('orders.show', $this->order->id),
        ];
    }
}
