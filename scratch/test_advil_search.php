<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

$searchTerm = 'ADVIL';
$like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

echo "=== TESTING SEARCH FOR 'ADVIL' ===\n";

$query = Pharmacy::select('pharmacies.*')
    ->where('is_active', true)
    ->where('is_approved', true);

$query->where(function($q) use ($searchTerm, $like) {
    $q->where('name', $like, "%{$searchTerm}%")
      ->orWhere('address', $like, "%{$searchTerm}%")
      ->orWhereHas('medicines', function ($medQuery) use ($searchTerm, $like) {
          $medQuery->where(function($nameQuery) use ($searchTerm, $like) {
              $nameQuery->where('brand_name', $like, "%{$searchTerm}%")
                        ->orWhere('generic_name', $like, "%{$searchTerm}%")
                        ->orWhere('primary_use', $like, "%{$searchTerm}%")
                        ->orWhere('therapeutic_class', $like, "%{$searchTerm}%")
                        ->orWhere('drug_category', $like, "%{$searchTerm}%");
          })
          ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
          ->where('pharmacy_medicine.is_available', true);
      });
});

$pharmacies = $query->get();
echo "Pharmacies found: " . $pharmacies->count() . "\n";
foreach ($pharmacies as $p) {
    echo "ID {$p->id}: {$p->name}\n";
}

echo "\n--- Inspecting TGP Pharmacy (ID 6) Medicines in DB ---\n";
$tgpMeds = DB::table('pharmacy_medicine')
    ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
    ->where('pharmacy_medicine.pharmacy_id', 6)
    ->select('medicines.*', 'pharmacy_medicine.quantity_on_hand', 'pharmacy_medicine.is_available')
    ->get();

foreach ($tgpMeds as $m) {
    echo "Med ID {$m->id} | Brand: '{$m->brand_name}' | Generic: '{$m->generic_name}' | Stock: {$m->quantity_on_hand} | Available: {$m->is_available}\n";
}
