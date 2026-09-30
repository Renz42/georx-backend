<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MedicineOutOfStock extends Notification
{
    use Queueable;

    protected $medicine;
    protected $pharmacy;

    public function __construct($medicine, $pharmacy)
    {
        $this->medicine = $medicine;
        $this->pharmacy = $pharmacy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $medName = $this->medicine->brand_name ?? $this->medicine->generic_name;
        $msg = "{$medName} is now out of stock at {$this->pharmacy->name}. You will be notified when it is restocked.";

        return [
            'type' => 'stock_alert',
            'status' => 'out_of_stock',
            'title' => 'Medicine Out of Stock',
            'message' => $msg,
            'pharmacy_id' => $this->pharmacy->id,
            'pharmacy_name' => $this->pharmacy->name,
            'medicine_id' => $this->medicine->id,
            'medicine_name' => $medName,
            'url' => '/pharmacy/' . $this->pharmacy->id . '/medicine/' . $this->medicine->id,
            'icon' => 'alert-circle-outline',
        ];
    }
}
