<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockWarning extends Notification
{
    use Queueable;

    protected $medicine;
    protected $currentQty;
    protected $reorderLevel;

    public function __construct($medicine, $currentQty, $reorderLevel)
    {
        $this->medicine = $medicine;
        $this->currentQty = $currentQty;
        $this->reorderLevel = $reorderLevel;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Low Stock Warning',
            'message' => $this->medicine->brand_name . ' (' . $this->medicine->generic_name . ') is running low. Current stock: ' . $this->currentQty . ' (Reorder level: ' . $this->reorderLevel . ')',
            'url' => route('portal.inventory'),
            'icon' => 'fas fa-exclamation-triangle text-amber-500'
        ];
    }
}
