<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TGP PHARMACY (ID 6) INVENTORY ITEMS ===\n";

$items = DB::table('pharmacy_medicine')
    ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
    ->where('pharmacy_medicine.pharmacy_id', 6)
    ->select(
        'pharmacy_medicine.id as pm_id',
        'medicines.id as med_id',
        'medicines.brand_name',
        'medicines.generic_name',
        'pharmacy_medicine.selling_price',
        'pharmacy_medicine.quantity_on_hand',
        'pharmacy_medicine.is_available',
        'pharmacy_medicine.updated_at'
    )
    ->get();

foreach ($items as $item) {
    echo "PM ID: {$item->pm_id} | Med ID: {$item->med_id} | Brand: '{$item->brand_name}' | Generic: '{$item->generic_name}' | Price: ₱{$item->selling_price} | Stock: {$item->quantity_on_hand} | Avail: {$item->is_available}\n";
}
