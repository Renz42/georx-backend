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
        $this->order->loadMissing('pharmacy');
        $pharmacyName = $this->order->pharmacy?->name ?? 'the pharmacy';

        return [
            'type' => 'review_prompt',
            'status' => 'delivered',
            'title' => 'Order Delivered! Rate Your Experience',
            'message' => "Your order #{$this->order->id} from {$pharmacyName} has been delivered. Tap to submit your star rating and feedback.",
            'order_id' => (int) $this->order->id,
            'url' => route('orders.show', $this->order->id),
            'action_url' => "/orders/{$this->order->id}",
        ];
    }
}
