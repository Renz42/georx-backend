<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\PharmacySearchController;

$controller = new PharmacySearchController();
$request = Request::create('/api/pharmacies/nearby', 'GET', [
    'medicine' => 'Biogesic',
    'lat' => 10.6385,
    'lng' => 122.9520,
]);

$response = $controller->nearby($request);
echo "=== SEARCH API TEST RESPONSE ===\n";
echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
echo "\n";
