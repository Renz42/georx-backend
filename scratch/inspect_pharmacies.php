<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

$pharmacies = Pharmacy::all();
echo "Total Pharmacies: " . $pharmacies->count() . "\n";
foreach ($pharmacies as $p) {
    $count = DB::table('pharmacy_medicine')->where('pharmacy_id', $p->id)->count();
    echo "ID {$p->id} | {$p->name} | Address: {$p->address} | Inventory Rows: {$count}\n";
}
