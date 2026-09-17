<?php

try {
    $mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $tables = $mysql->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables in MySQL medicine_locator_db:\n\n";
    
    foreach ($tables as $t) {
        $count = $mysql->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo str_pad($t, 35) . " => " . $count . " rows\n";
    }
} catch (Exception $e) {
    echo "MySQL Error: " . $e->getMessage() . "\n";
}
