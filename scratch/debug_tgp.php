<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;
use App\Services\GeofenceService;

$tgp = Pharmacy::find(6);
echo "TGP Pharmacy (ID 6):\n";
echo "  Name: {$tgp->name}\n";
echo "  Lat: {$tgp->latitude}\n";
echo "  Lng: {$tgp->longitude}\n";
echo "  is_active: {$tgp->is_active}\n";
echo "  is_approved: {$tgp->is_approved}\n";

$inside = GeofenceService::isInsideAlijis((float)$tgp->latitude, (float)$tgp->longitude);
echo "  isInsideAlijis: " . ($inside ? "TRUE" : "FALSE") . "\n";
