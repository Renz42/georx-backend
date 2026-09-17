<?php
require __DIR__ . '/../medicine_locator_main/vendor/autoload.php';
$app = require_once __DIR__ . '/../medicine_locator_main/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$hasColumn = Schema::hasColumn('deliveries', 'verification_code');
echo "verification_code in deliveries: " . ($hasColumn ? "YES\n" : "NO\n");

if (!$hasColumn) {
    Schema::table('deliveries', function (Blueprint $table) {
        $table->string('verification_code', 10)->nullable()->after('status');
    });
    echo "Added verification_code column to deliveries table successfully!\n";
}

// Populate verification codes for existing deliveries without code
$deliveries = \App\Models\Delivery::whereNull('verification_code')->get();
foreach ($deliveries as $d) {
    $d->verification_code = (string)rand(100000, 999999);
    $d->save();
}
echo "Populated verification codes for " . $deliveries->count() . " existing deliveries.\n";
