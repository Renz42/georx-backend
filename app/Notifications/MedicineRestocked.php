<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MedicineRestocked extends Notification
{
    use Queueable;

    protected $medicine;
    protected $pharmacy;
    protected $price;
    protected $stock;

    public function __construct($medicine, $pharmacy, $price = null, $stock = null)
    {
        $this->medicine = $medicine;
        $this->pharmacy = $pharmacy;
        $this->price = $price;
        $this->stock = $stock;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $details = [];
        if ($this->price) $details[] = 'Price: ₱' . number_format($this->price, 2);
        if ($this->stock) $details[] = 'Stock: ' . $this->stock;
        
        $msg = $this->medicine->generic_name . ' has been restocked at ' . $this->pharmacy->name . '.';
        if (!empty($details)) {
            $msg .= ' (' . implode(', ', $details) . ')';
        }

        return [
            'type' => 'stock_alert',
            'title' => 'Medicine Restocked',
            'message' => $msg,
            'pharmacy_id' => $this->pharmacy->id,
            'pharmacy_name' => $this->pharmacy->name,
            'medicine_id' => $this->medicine->id,
            'medicine_name' => $this->medicine->brand_name ?? $this->medicine->generic_name,
            'selling_price' => $this->price,
            'quantity_on_hand' => $this->stock,
            'url' => '/pharmacy/' . $this->pharmacy->id . '/medicine/' . $this->medicine->id,
            'icon' => 'fas fa-box-open text-blue-500'
        ];
    }
}