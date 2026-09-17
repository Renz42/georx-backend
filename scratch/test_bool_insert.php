<?php

$pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Get columns of medicines table
$stmt = $pgsql->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'medicines'");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($cols);
