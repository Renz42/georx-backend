<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "--- CHECKING CUSTOMER USERS IN SUPABASE DB ---\n";
$users = User::where('role', User::ROLE_CUSTOMER)->get();
echo "Total customers found: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Name: {$u->name} | Phone: {$u->phone}\n";
}

echo "\n--- TESTING PASSWORD CHECK FOR RECENT REGISTRATION ---\n";
$testEmail = 'patient@example.com'; // or whatever email
$user = User::where('email', $testEmail)->first();
if ($user) {
    echo "Found user {$user->email}\n";
} else {
    echo "User {$testEmail} not found.\n";
}
