<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$hasColumn = Schema::hasColumn('orders', 'customer_confirmed_delivery');
echo "customer_confirmed_delivery in orders: " . ($hasColumn ? "YES\n" : "NO\n");

if (!$hasColumn) {
    Schema::table('orders', function (Blueprint $table) {
        $table->boolean('customer_confirmed_delivery')->default(false)->after('status');
        $table->timestamp('customer_confirmed_at')->nullable()->after('customer_confirmed_delivery');
    });
    echo "Added customer_confirmed_delivery & customer_confirmed_at columns to orders table!\n";
}

if (!Schema::hasColumn('deliveries', 'customer_confirmed_at')) {
    Schema::table('deliveries', function (Blueprint $table) {
        $table->timestamp('customer_confirmed_at')->nullable()->after('delivered_at');
    });
    echo "Added customer_confirmed_at column to deliveries table!\n";
}
