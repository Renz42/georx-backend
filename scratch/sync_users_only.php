<?php

$mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pgsql = new PDO("pgsql:host=db.aqzeibjljgvzvgpobbkx.supabase.co;port=5432;dbname=postgres;sslmode=require", "postgres", "Jake09515832123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$rows = $mysql->query("SELECT * FROM `users`")->fetchAll();

$pgsql->exec("SET session_replication_role = 'replica';");
$pgsql->exec("DELETE FROM \"users\";");

$pgColsStmt = $pgsql->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' AND table_schema = 'public'");
$pgColsStmt->execute();
$pgColsMap = [];
foreach ($pgColsStmt->fetchAll() as $colInfo) {
    $pgColsMap[$colInfo['column_name']] = strtolower($colInfo['data_type']);
}

$mysqlCols = array_keys($rows[0]);
$validCols = array_values(array_intersect($mysqlCols, array_keys($pgColsMap)));

$quotedCols = array_map(function($c) { return "\"{$c}\""; }, $validCols);
$placeholders = array_fill(0, count($validCols), '?');

$sql = "INSERT INTO \"users\" (" . implode(', ', $quotedCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
$insertStmt = $pgsql->prepare($sql);

$inserted = 0;
foreach ($rows as $row) {
    $values = [];
    foreach ($validCols as $col) {
        $val = $row[$col];
        $type = $pgColsMap[$col];

        if ($type === 'boolean') {
            $val = ($val === true || $val === 1 || $val === '1' || $val === 'true') ? 'true' : 'false';
        } elseif (in_array($type, ['integer', 'bigint', 'smallint', 'decimal', 'numeric', 'double precision', 'timestamp with time zone', 'timestamp without time zone', 'date'])) {
            if ($val === '' || $val === null) $val = null;
        }
        $values[] = $val;
    }
    $insertStmt->execute($values);
    $inserted++;
}

$pgsql->exec("SELECT setval(pg_get_serial_sequence('\"users\"', 'id'), coalesce(max(id), 1)) FROM \"users\";");
$pgsql->exec("SET session_replication_role = 'origin';");

echo "Successfully populated users table with {$inserted}/" . count($rows) . " rows!\n";
