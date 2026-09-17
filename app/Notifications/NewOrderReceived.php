<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderReceived extends Notification
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
            'title' => 'New Order Received',
            'message' => 'Order #' . $this->order->id . ' needs your confirmation.',
            'url' => route('portal.orders', ['status' => 'pending_confirmation']),
            'icon' => 'fas fa-shopping-bag text-indigo-500'
        ];
    }
}
