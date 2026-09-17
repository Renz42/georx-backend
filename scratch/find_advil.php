<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ALL MEDICINES IN DATABASE CONTAINING 'advil' OR 'ibuprofen' ===\n";

$meds = DB::table('medicines')
    ->where('brand_name', 'LIKE', '%advil%')
    ->orWhere('generic_name', 'LIKE', '%ibuprofen%')
    ->orWhere('brand_name', 'LIKE', '%ibuprofen%')
    ->get();

foreach ($meds as $m) {
    echo "Med ID: {$m->id} | Brand: '{$m->brand_name}' | Generic: '{$m->generic_name}'\n";
    $pms = DB::table('pharmacy_medicine')->where('medicine_id', $m->id)->get();
    foreach ($pms as $pm) {
        echo "   -> Pharmacy ID: {$pm->pharmacy_id} | Stock: {$pm->quantity_on_hand} | Price: ₱{$pm->selling_price} | Avail: {$pm->is_available}\n";
    }
}
