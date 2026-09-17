<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LowStockTriggered extends Notification
{
    use Queueable;

    public string $medicineName;
    public int $currentStock;
    public int $reorderLevel;

    public function __construct(string $medicineName, int $currentStock, int $reorderLevel)
    {
        $this->medicineName = $medicineName;
        $this->currentStock = $currentStock;
        $this->reorderLevel = $reorderLevel;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'low_stock_warning',
            'title' => 'Low Stock Warning: ' . $this->medicineName,
            'message' => "Stock for {$this->medicineName} has dropped to {$this->currentStock} (Reorder Level: {$this->reorderLevel}).",
            'medicine_name' => $this->medicineName,
            'current_stock' => $this->currentStock,
            'reorder_level' => $this->reorderLevel,
            'action_url' => '/portal/inventory',
        ];
    }
}
