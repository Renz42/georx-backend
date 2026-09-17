<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverAssignedNotification extends Notification
{
    use Queueable;

    public Order $order;
    public User $driver;
    public string $recipientType; // 'customer' or 'pharmacy'

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, User $driver, string $recipientType = 'customer')
    {
        $this->order = $order;
        $this->driver = $driver;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $driverName = $this->driver->name;
        $driverPhone = $this->driver->phone ?? 'N/A';
        $driverProfile = $this->driver->driverProfile;
        $vehicleInfo = $driverProfile ? trim(($driverProfile->vehicle_make ?? '') . ' ' . ($driverProfile->vehicle_model ?? '')) : 'Motorcycle';

        if ($this->recipientType === 'pharmacy') {
            return [
                'order_id' => $this->order->id,
                'message'  => "Driver {$driverName} ({$driverPhone}) has accepted delivery for Order #{$this->order->id} and is heading to your pharmacy.",
                'icon'     => 'fas fa-motorcycle text-indigo-500',
                'url'      => '/portal/orders',
            ];
        }

        return [
            'order_id' => $this->order->id,
            'message'  => "Driver {$driverName} ({$vehicleInfo}) has accepted your delivery order #{$this->order->id}!",
            'icon'     => 'fas fa-truck text-emerald-500',
            'url'      => "/orders/{$this->order->id}",
        ];
    }
}
