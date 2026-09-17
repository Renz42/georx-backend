<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $lat = 10.6385;
    $lng = 122.9566;

    $results = DB::table('pharmacies')
        ->select('id', 'name', 'latitude', 'longitude')
        ->selectRaw('( 6371 * acos( LEAST(1.0, GREATEST(-1.0, cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) )) ) ) AS distance', [$lat, $lng, $lat])
        ->orderBy('distance')
        ->get();

    echo "Haversine Query Success on Supabase Postgres!\n";
    echo "Found " . count($results) . " pharmacies near center:\n\n";
    foreach ($results as $p) {
        echo "- {$p->name} => " . round($p->distance, 2) . " km away\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
