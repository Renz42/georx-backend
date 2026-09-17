<?php

echo "==================================================\n";
echo "GEORX Phase 3: Supabase Database Migration & Data Sync\n";
echo "==================================================\n\n";

$mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "✅ Connected to both local MySQL and Supabase PostgreSQL successfully!\n\n";

// Run Laravel Artisan migrate on pgsql connection
echo "--- Step 1: Running PostgreSQL Fresh Schema Migrations ---\n";

// Temporarily update DB env values for artisan command or run via artisan
putenv('DB_CONNECTION=pgsql');
putenv('DB_HOST=db.aqzeibjljgvzvgpobbkx.supabase.co');
putenv('DB_PORT=5432');
putenv('DB_DATABASE=postgres');
putenv('DB_USERNAME=postgres');
putenv('DB_PASSWORD=Jake09515832123');
putenv('DB_SSLMODE=require');

$_ENV['DB_CONNECTION'] = 'pgsql';
$_ENV['DB_HOST'] = 'db.aqzeibjljgvzvgpobbkx.supabase.co';
$_ENV['DB_PORT'] = '5432';
$_ENV['DB_DATABASE'] = 'postgres';
$_ENV['DB_USERNAME'] = 'postgres';
$_ENV['DB_PASSWORD'] = 'Jake09515832123';
$_ENV['DB_SSLMODE'] = 'require';

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Force default connection to pgsql
config(['database.default' => 'pgsql']);
config(['database.connections.pgsql.host' => 'db.aqzeibjljgvzvgpobbkx.supabase.co']);
config(['database.connections.pgsql.port' => '5432']);
config(['database.connections.pgsql.database' => 'postgres']);
config(['database.connections.pgsql.username' => 'postgres']);
config(['database.connections.pgsql.password' => 'Jake09515832123']);
config(['database.connections.pgsql.sslmode' => 'require']);

DB::purge('pgsql');
DB::reconnect('pgsql');

echo "Running migrate --path=database/migrations/2026_09_08_000001_pgsql_fresh_schema.php ...\n";
Artisan::call('migrate', [
    '--path' => 'database/migrations/2026_09_08_000001_pgsql_fresh_schema.php',
    '--force' => true
]);
echo Artisan::output() . "\n";

echo "Running additional PG migrations ...\n";
$otherMigrations = [
    'database/migrations/2026_09_08_000002_enable_supabase_realtime.php',
    'database/migrations/2026_09_09_000001_phase3_driver_auth.php',
    'database/migrations/2026_09_09_000002_phase4_deliveries.php',
    'database/migrations/2026_09_09_000003_phase8_supabase_realtime.php',
    'database/migrations/2026_09_15_000001_add_restock_alert_indexes.php',
    'database/migrations/2026_09_15_000002_add_performance_and_protection_indexes.php',
    'database/migrations/2026_09_15_000003_create_user_device_tokens_table.php'
];

foreach ($otherMigrations as $path) {
    if (file_exists(__DIR__ . '/../' . $path)) {
        Artisan::call('migrate', [
            '--path' => $path,
            '--force' => true
        ]);
        echo Artisan::output();
    }
}

echo "✅ Database Schema created on Supabase PostgreSQL!\n\n";

// Step 2: Copy Data from MySQL to PostgreSQL
echo "--- Step 2: Copying Data from Local MySQL to Supabase PostgreSQL ---\n";

$tablesToMigrate = [
    'users',
    'pharmacies',
    'medicines',
    'pharmacy_medicine',
    'inventory_batches',
    'driver_profiles',
    'orders',
    'order_items',
    'deliveries',
    'stock_alerts',
    'notifications',
    'search_logs',
    'audit_logs',
    'carts',
    'cart_items',
    'conversations',
    'messages',
    'reservations',
    'reviews',
    'user_device_tokens',
    'user_favorites',
    'global_settings'
];

// Disable foreign key checks on PostgreSQL during import
$pgsql->exec("SET session_replication_role = 'replica';");

foreach ($tablesToMigrate as $table) {
    // Check if table exists in MySQL
    $stmt = $mysql->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if (!$stmt->fetch()) {
        continue;
    }

    $rows = $mysql->query("SELECT * FROM `{$table}`")->fetchAll();
    $count = count($rows);

    if ($count === 0) {
        echo str_pad($table, 25) . " => 0 rows (skipped)\n";
        continue;
    }

    // Truncate PG table first
    $pgsql->exec("TRUNCATE TABLE \"{$table}\" CASCADE;");

    // Get column names
    $firstRow = $rows[0];
    $cols = array_keys($firstRow);
    $quotedCols = array_map(function($c) { return "\"{$c}\""; }, $cols);
    $placeholders = array_fill(0, count($cols), '?');

    $sql = "INSERT INTO \"{$table}\" (" . implode(', ', $quotedCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pgsql->prepare($sql);

    $inserted = 0;
    foreach ($rows as $row) {
        $values = [];
        foreach ($cols as $col) {
            $val = $row[$col];
            // Format booleans / tinyint for PostgreSQL
            if (is_numeric($val) && (str_starts_with($col, 'is_') || str_starts_with($col, 'prescription_') || str_starts_with($col, 'controlled_') || str_starts_with($col, 'has_') || $col === 'availability_status')) {
                // If destination is boolean, cast
                if ($val === '1' || $val === 1) {
                    $val = true;
                } elseif ($val === '0' || $val === 0) {
                    $val = false;
                }
            }
            $values[] = $val;
        }
        
        try {
            $insertStmt->execute($values);
            $inserted++;
        } catch (Exception $e) {
            echo "   ⚠️ Error inserting row into {$table}: " . $e->getMessage() . "\n";
        }
    }

    echo str_pad($table, 25) . " => Migrated {$inserted}/{$count} rows\n";

    // Reset sequence if table has an 'id' column
    try {
        $pgsql->exec("SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), coalesce(max(id), 1)) FROM \"{$table}\";");
    } catch (Exception $e) {
        // Table might not have standard serial id
    }
}

// Re-enable foreign key checks
$pgsql->exec("SET session_replication_role = 'origin';");

echo "\n--- Step 3: Verifying Migration Integrity ---\n";
echo str_pad("TABLE NAME", 25) . str_pad("MYSQL", 12) . str_pad("SUPABASE PG", 12) . "STATUS\n";
echo str_repeat("-", 60) . "\n";

$allSuccess = true;
foreach ($tablesToMigrate as $table) {
    $myCount = $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $pgCount = $pgsql->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();

    $status = ($myCount == $pgCount) ? "✅ MATCH" : "❌ MISMATCH";
    if ($myCount != $pgCount) $allSuccess = false;

    echo str_pad($table, 25) . str_pad($myCount, 12) . str_pad($pgCount, 12) . $status . "\n";
}

echo "\n==================================================\n";
if ($allSuccess) {
    echo "🎉 MIGRATION SUCCESSFUL! All data successfully migrated to Supabase Postgres!\n";
} else {
    echo "⚠️ MIGRATION COMPLETED WITH SOME WARNINGS/MISMATCHES.\n";
}
echo "==================================================\n";
