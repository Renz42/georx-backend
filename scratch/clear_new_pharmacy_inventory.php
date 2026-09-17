<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

echo "=== CLEARING SEEDED INVENTORY FOR NEWLY REGISTERED PHARMACIES ===\n";

// Clear test inventory for newly registered pharmacies (ID > 6)
$newPharmacies = Pharmacy::where('id', '>', 6)->get();

foreach ($newPharmacies as $p) {
    $deleted = DB::table('pharmacy_medicine')->where('pharmacy_id', $p->id)->delete();
    echo "Cleared {$deleted} test inventory items for Pharmacy ID: {$p->id} ({$p->name})\n";
}

echo "COMPLETED: Newly registered pharmacies now have 0 items in inventory until added via the dashboard.\n";
