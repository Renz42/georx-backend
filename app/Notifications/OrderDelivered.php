<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderDelivered extends Notification
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
            'title' => 'Order Delivered',
            'message' => 'Your order #' . $this->order->id . ' has been delivered. Thank you!',
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-check-circle text-emerald-500'
        ];
    }
}
