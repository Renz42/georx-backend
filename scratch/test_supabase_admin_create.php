<?php
$supabaseUrl = "https://aqzeibjljgvzvgpobbkx.supabase.co";
$serviceKey = getenv('SUPABASE_SERVICE_KEY');

function supabaseAdminPost($url, $key, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "apikey: {$key}",
        "Authorization: Bearer {$key}"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true)];
}

$email = "adminconfirmed" . rand(100,999) . "@georx.com";
$password = "Password123!";

echo "CREATE ADMIN CONFIRMED USER: {$email}\n";
$res = supabaseAdminPost("{$supabaseUrl}/auth/v1/admin/users", $serviceKey, [
    'email' => $email,
    'password' => $password,
    'email_confirm' => true,
    'user_metadata' => [
        'name' => 'Admin Confirmed Patient',
        'phone' => '09123456789'
    ]
]);

echo "Admin Create HTTP Code: {$res['code']}\n";
echo "Admin Create Response: " . print_r($res['data'], true) . "\n";

echo "TEST SIGNIN AS PUBLIC ANON KEY FOR CONFIRMED USER:\n";
$anonKey = "sb_publishable_HH1Gz6AiA_GAbpK-A9lSHg_GWE7vsU0";
$signInRes = supabaseAdminPost("{$supabaseUrl}/auth/v1/token?grant_type=password", $anonKey, [
    'email' => $email,
    'password' => $password
]);

echo "SignIn HTTP Code: {$signInRes['code']}\n";
echo "SignIn Response: " . print_r($signInRes['data'], true) . "\n";
