<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy;
use App\Models\Medicine;

echo "=== DB pharmacy_medicine table check ===\n";
$rows = DB::table('pharmacy_medicine')->get();
echo "Total rows in pharmacy_medicine: " . count($rows) . "\n";
foreach ($rows as $r) {
    $p = Pharmacy::find($r->pharmacy_id);
    $m = Medicine::find($r->medicine_id);
    echo "Pharm ID: {$r->pharmacy_id} ({$p?->name}) | Med ID: {$r->medicine_id} ({$m?->brand_name} / {$m?->generic_name}) | Stock: {$r->quantity_on_hand} | Avail: {$r->is_available}\n";
}

echo "\n=== All Medicines in DB ===\n";
$meds = Medicine::all();
foreach ($meds as $m) {
    echo "Med ID: {$m->id} | Brand: {$m->brand_name} | Generic: {$m->generic_name}\n";
}
