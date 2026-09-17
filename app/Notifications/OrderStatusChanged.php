<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    protected $order;
    protected $statusMessage;
    protected $icon;

    public function __construct($order, $statusMessage, $icon = 'fas fa-info-circle text-blue-500')
    {
        $this->order = $order;
        $this->statusMessage = $statusMessage;
        $this->icon = $icon;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Order Update #' . $this->order->id,
            'message' => $this->statusMessage,
            'url' => route('orders.show', $this->order->id),
            'icon' => $this->icon
        ];
    }
}
