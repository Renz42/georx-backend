<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

$pharmacies = Pharmacy::where('is_active', true)->get();
echo "Active Pharmacies Count: " . $pharmacies->count() . "\n";

foreach ($pharmacies as $p) {
    echo "Pharmacy ID {$p->id}: {$p->name}\n";
    $meds = DB::table('pharmacy_medicine')
        ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
        ->where('pharmacy_medicine.pharmacy_id', $p->id)
        ->select('medicines.name as med_name', 'medicines.generic_name', 'pharmacy_medicine.quantity_on_hand', 'pharmacy_medicine.selling_price')
        ->get();
    foreach ($meds as $m) {
        echo "  - {$m->med_name} ({$m->generic_name}): Stock {$m->quantity_on_hand}, Price ₱{$m->selling_price}\n";
    }
}

// Test nearby controller query logic
$query = DB::table('pharmacies')
    ->where('is_active', true);

$search = 'Paracetamol';
$query->whereExists(function($q) use ($search) {
    $q->select(DB::raw(1))
      ->from('pharmacy_medicine')
      ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
      ->whereColumn('pharmacy_medicine.pharmacy_id', 'pharmacies.id')
      ->where(function($sub) use ($search) {
          $sub->where('medicines.name', 'LIKE', "%{$search}%")
              ->orWhere('medicines.generic_name', 'LIKE', "%{$search}%")
              ->orWhere('medicines.brand_name', 'LIKE', "%{$search}%");
      })
      ->where('pharmacy_medicine.quantity_on_hand', '>', 0);
});

$results = $query->get();
echo "\nSearch Results for 'Paracetamol': " . $results->count() . " pharmacies found\n";
foreach ($results as $r) {
    echo "Found Pharmacy: {$r->name} (ID: {$r->id})\n";
}
