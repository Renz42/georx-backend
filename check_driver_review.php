<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$hasColumn = Schema::hasColumn('reviews', 'driver_id');
echo "driver_id in reviews: " . ($hasColumn ? "YES\n" : "NO\n");

if (!$hasColumn) {
    Schema::table('reviews', function (Blueprint $table) {
        $table->foreignId('driver_id')->nullable()->after('pharmacy_id')->constrained('users')->nullOnDelete();
    });
    echo "Added driver_id column to reviews table successfully!\n";
}
