<?php

echo "==================================================\n";
echo "GEORX Phase 3: Copy MySQL Data -> Supabase Postgres\n";
echo "==================================================\n\n";

$mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "✅ Connected to MySQL and Supabase PostgreSQL!\n\n";

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

// Disable FK checks on Postgres
$pgsql->exec("SET session_replication_role = 'replica';");

foreach ($tablesToMigrate as $table) {
    // Check if table exists in MySQL
    $stmt = $mysql->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if (!$stmt->fetch()) {
        continue;
    }

    // Check if table exists in Postgres
    $stmt = $pgsql->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'");
    $stmt->execute([$table]);
    if ($stmt->fetchColumn() == 0) {
        echo str_pad($table, 25) . " => Table missing in PostgreSQL\n";
        continue;
    }

    $rows = $mysql->query("SELECT * FROM `{$table}`")->fetchAll();
    $count = count($rows);

    if ($count === 0) {
        echo str_pad($table, 25) . " => 0 rows (skipped)\n";
        continue;
    }

    // Fetch column metadata from Postgres table (column name + data type)
    $pgColsStmt = $pgsql->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'");
    $pgColsStmt->execute([$table]);
    $pgColsMap = [];
    foreach ($pgColsStmt->fetchAll() as $colInfo) {
        $pgColsMap[$colInfo['column_name']] = strtolower($colInfo['data_type']);
    }

    // Get intersection of columns that exist in BOTH MySQL row and PostgreSQL table
    $mysqlCols = array_keys($rows[0]);
    $validCols = array_values(array_intersect($mysqlCols, array_keys($pgColsMap)));

    // Truncate PG table first
    $pgsql->exec("TRUNCATE TABLE \"{$table}\" CASCADE;");

    $quotedCols = array_map(function($c) { return "\"{$c}\""; }, $validCols);
    $placeholders = array_fill(0, count($validCols), '?');

    $sql = "INSERT INTO \"{$table}\" (" . implode(', ', $quotedCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pgsql->prepare($sql);

    $inserted = 0;
    foreach ($rows as $row) {
        $values = [];
        foreach ($validCols as $col) {
            $val = $row[$col];
            $type = $pgColsMap[$col];

            // Handle boolean columns for PostgreSQL
            if ($type === 'boolean') {
                if ($val === true || $val === 1 || $val === '1' || $val === 'true' || strtolower((string)$val) === 't') {
                    $val = 'true';
                } else {
                    $val = 'false';
                }
            } 
            // Handle numeric / integer / timestamp null values
            elseif (in_array($type, ['integer', 'bigint', 'smallint', 'decimal', 'numeric', 'double precision', 'timestamp with time zone', 'timestamp without time zone', 'date'])) {
                if ($val === '' || $val === null) {
                    $val = null;
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
        // No sequence for this table
    }
}

// Re-enable FK checks
$pgsql->exec("SET session_replication_role = 'origin';");

echo "\n--- Verification Summary ---\n";
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
    echo "🎉 DATA SYNC SUCCESSFUL! All MySQL data is now live on Supabase Postgres!\n";
} else {
    echo "⚠️ DATA SYNC COMPLETED WITH SOME WARNINGS.\n";
}
echo "==================================================\n";
