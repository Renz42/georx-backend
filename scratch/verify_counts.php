<?php

$mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$tables = [
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
    'cart_items',
    'conversations',
    'messages',
    'reviews',
    'user_device_tokens',
    'user_favorites',
    'global_settings'
];

echo str_pad("TABLE NAME", 25) . str_pad("MYSQL", 12) . str_pad("SUPABASE PG", 12) . "STATUS\n";
echo str_repeat("-", 60) . "\n";

foreach ($tables as $table) {
    $myCount = 0;
    try {
        $myCount = $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    } catch (Exception $e) {}

    $pgCount = 0;
    try {
        $pgCount = $pgsql->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
    } catch (Exception $e) {
        $pgCount = 'N/A';
    }

    $status = ($myCount == $pgCount) ? "✅ MATCH" : "❌ MISMATCH";
    echo str_pad($table, 25) . str_pad($myCount, 12) . str_pad($pgCount, 12) . $status . "\n";
}
