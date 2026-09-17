<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewPrompt extends Notification
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'review_prompt',
            'title' => 'Order Delivered! Rate Your Experience',
            'message' => "Your order #{$this->order->id} from {$this->order->pharmacy->name} has been delivered. Tap to submit your star rating and feedback.",
            'order_id' => $this->order->id,
            'action_url' => "/orders/{$this->order->id}",
        ];
    }
}
