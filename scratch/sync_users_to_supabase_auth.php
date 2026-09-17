<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL', 'https://aqzeibjljgvzvgpobbkx.supabase.co');
$serviceKey = config('services.supabase.service_key') ?: env('SUPABASE_SERVICE_KEY');

function createOrUpdateSupabaseAuthUser($supabaseUrl, $serviceKey, $email, $password = null, $name = '', $phone = '') {
    $url = "{$supabaseUrl}/auth/v1/admin/users";
    
    // First, try to create
    $data = [
        'email' => $email,
        'email_confirm' => true,
        'user_metadata' => [
            'name' => $name,
            'phone' => $phone
        ]
    ];
    if ($password) {
        $data['password'] = $password;
    } else {
        $data['password'] = 'Password123!'; // Default fallback password if unreadable
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "apikey: {$serviceKey}",
        "Authorization: Bearer {$serviceKey}"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'response' => json_decode($res, true)];
}

echo "--- SYNCING EXISTING USERS TO SUPABASE AUTH ---\n";
$users = User::all();
echo "Found " . $users->count() . " users in public.users.\n";

foreach ($users as $u) {
    echo "Syncing User ID: {$u->id} ({$u->email})... ";
    // Default known password for seeded/test accounts if password hash is non-reversible, or password123
    $res = createOrUpdateSupabaseAuthUser($supabaseUrl, $serviceKey, $u->email, 'password123', $u->name, $u->phone ?? '');
    if ($res['code'] == 200 || $res['code'] == 201) {
        echo "CREATED/SYNCED (HTTP {$res['code']})\n";
    } else {
        $msg = $res['response']['msg'] ?? json_encode($res['response']);
        echo "NOTE: {$msg} (HTTP {$res['code']})\n";
    }
}
echo "Sync Completed!\n";
