<?php
// Phase 3 test seeder — creates test driver accounts for authentication testing.
// Run with: php artisan tinker --execute="require 'database/seeders/phase3_driver_test.php';"

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\DriverProfile;

echo "\n=== GEORX Phase 3 — Driver Auth Test Seeder ===\n\n";

// Clean up previous test users
User::where('email', 'like', '%@test.georx')->delete();

// 1. APPROVED DRIVER
$approved = User::create([
    'name'           => 'Test Driver Approved',
    'email'          => 'driver.approved@test.georx',
    'phone'          => '09171234001',
    'password'       => bcrypt('password123'),
    'role'           => 'driver',
    'account_status' => 'approved',
]);
DriverProfile::create([
    'user_id'        => $approved->id,
    'vehicle_type'   => 'motorcycle',
    'vehicle_make'   => 'Honda',
    'vehicle_model'  => 'Click 125i',
    'plate_number'   => 'TEST-001',
    'account_status' => 'approved',
    'is_online'      => false,
    'is_available'   => true,
]);
echo "[OK] Approved Driver:  driver.approved@test.georx / password123\n";

// 2. PENDING DRIVER
$pending = User::create([
    'name'           => 'Test Driver Pending',
    'email'          => 'driver.pending@test.georx',
    'phone'          => '09171234002',
    'password'       => bcrypt('password123'),
    'role'           => 'driver',
    'account_status' => 'pending_review',
]);
DriverProfile::create([
    'user_id'        => $pending->id,
    'vehicle_type'   => 'bicycle',
    'account_status' => 'pending_review',
    'is_online'      => false,
    'is_available'   => true,
]);
echo "[OK] Pending Driver:   driver.pending@test.georx / password123\n";

// 3. SUSPENDED DRIVER
$suspended = User::create([
    'name'           => 'Test Driver Suspended',
    'email'          => 'driver.suspended@test.georx',
    'phone'          => '09171234003',
    'password'       => bcrypt('password123'),
    'role'           => 'driver',
    'account_status' => 'suspended',
]);
DriverProfile::create([
    'user_id'        => $suspended->id,
    'vehicle_type'   => 'car',
    'account_status' => 'suspended',
    'is_online'      => false,
    'is_available'   => true,
]);
echo "[OK] Suspended Driver: driver.suspended@test.georx / password123\n";

echo "\n=== Seeder complete. ===\n";
echo "Use these credentials to test login at /driver/login\n\n";
