<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$passwordsToTest = ['password', 'password123', '12345678', 'admin123', 'secret'];

$driverEmails = [
    'driver.approved@test.georx',
    'phase910_driver_a@georx.test',
    'john@gmail.com'
];

foreach ($driverEmails as $email) {
    $user = User::where('email', $email)->first();
    if (!$user) continue;

    echo "Driver Email: {$email} ({$user->name})\n";
    foreach ($passwordsToTest as $pass) {
        if (Hash::check($pass, $user->password)) {
            echo "  --> MATCHED PASSWORD: '{$pass}'\n";
            break;
        }
    }
}
