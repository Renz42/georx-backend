<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$drivers = User::whereIn('role', ['delivery_partner', 'driver'])->get();

echo "=== DRIVER PROFILE ACCOUNT STATUSES ===\n\n";

foreach ($drivers as $d) {
    $p = $d->driverProfile;
    $status = $p ? $p->account_status : 'NO PROFILE RECORD';
    echo "ID:     {$d->id}\n";
    echo "Name:   {$d->name}\n";
    echo "Email:  {$d->email}\n";
    echo "Status: {$status}\n";
    echo "-----------------------------------------\n";
}
