<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCustomerMessage extends Notification
{
    use Queueable;

    protected $conversation;
    protected $senderName;

    public function __construct($conversation, $senderName)
    {
        $this->conversation = $conversation;
        $this->senderName = $senderName;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'New Message',
            'message' => $this->senderName . ' sent you a message.',
            'url' => route('portal.messages', $this->conversation->id),
            'icon' => 'fas fa-envelope text-blue-500'
        ];
    }
}
