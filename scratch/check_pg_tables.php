<?php

try {
    $pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $stmt = $pgsql->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables in Supabase Postgres:\n";
    print_r($tables);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
