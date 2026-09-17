<?php

$mysql = new PDO("mysql:host=127.0.0.1;dbname=medicine_locator_db", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$tables = $mysql->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $t) {
    if (in_array($t, ['migrations', 'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'password_reset_tokens', 'sessions'])) {
        continue;
    }
    echo "=========================================\n";
    echo "TABLE: {$t}\n";
    echo "=========================================\n";
    $createSql = $mysql->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC)['Create Table'];
    echo $createSql . "\n\n";
}
