<?php

$hosts = [
    'db.aqzeibjljgvzvgpobbkx.supabase.co',
    'aws-0-ap-southeast-1.pooler.supabase.com'
];
$port = 5432;
$db = 'postgres';
$user = 'postgres';
$pass = 'Jake09515832123';

foreach ($hosts as $host) {
    echo "Testing connection to {$host}:{$port}...\n";
    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);
        echo "SUCCESS! Connected to {$host}!\n";
        $stmt = $pdo->query("SELECT version();");
        echo "Postgres Version: " . $stmt->fetchColumn() . "\n";
        break;
    } catch (Exception $e) {
        echo "FAILED on {$host}: " . $e->getMessage() . "\n";
    }
}
