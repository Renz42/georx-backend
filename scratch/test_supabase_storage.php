<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;

try {
    $storage = new SupabaseStorageService();
    
    // Create temporary file
    $tmpPath = sys_get_temp_dir() . '/test_logo.png';
    file_put_contents($tmpPath, 'GEORX_DUMMY_IMAGE_DATA_123');

    $file = new UploadedFile($tmpPath, 'test_logo.png', 'image/png', null, true);

    $publicUrl = $storage->upload($file, SupabaseStorageService::BUCKET_PHARMACY_LOGOS, 'test');
    
    echo "✅ Supabase Storage Upload Success!\n";
    echo "Public URL: {$publicUrl}\n";
} catch (Exception $e) {
    echo "❌ Storage Upload Error: " . $e->getMessage() . "\n";
}
