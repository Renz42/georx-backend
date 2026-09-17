<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\PharmacySearchController;
use Illuminate\Http\Request;

$controller = new PharmacySearchController();
$request = Request::create('/api/pharmacies/nearby', 'GET', ['medicine' => 'ADVIL']);

$response = $controller->nearby($request);
$data = json_decode($response->getContent(), true);

echo "API Response Count: " . ($data['count'] ?? 0) . "\n";
echo "Pharmacies returned: " . count($data['pharmacies'] ?? []) . "\n";

foreach ($data['pharmacies'] ?? [] as $p) {
    echo "Pharmacy: {$p['name']} (ID {$p['id']})\n";
    echo "  Medicines array count: " . count($p['medicines'] ?? []) . "\n";
    foreach ($p['medicines'] ?? [] as $m) {
        echo "   - Med: Brand='{$m['brand_name']}', Generic='{$m['generic_name']}', Stock=" . ($m['pivot']['quantity_on_hand'] ?? 'N/A') . "\n";
    }
}
