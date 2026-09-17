<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pharmacy;

$p = Pharmacy::where('name', 'LIKE', '%Grace%')->first();
if ($p) {
    echo "Found Pharmacy:\n";
    echo "ID: {$p->id}\n";
    echo "Name: {$p->name}\n";
    echo "Address: {$p->address}\n";
    echo "Latitude: {$p->latitude}\n";
    echo "Longitude: {$p->longitude}\n";
    echo "Is Active: {$p->is_active}\n";
    echo "Is Approved: {$p->is_approved}\n";
} else {
    echo "Grace Pharmacy not found!\n";
}
