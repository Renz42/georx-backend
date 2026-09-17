<?php
$supabaseUrl = "https://aqzeibjljgvzvgpobbkx.supabase.co";
$anonKey = "sb_publishable_HH1Gz6AiA_GAbpK-A9lSHg_GWE7vsU0";

function supabasePost($url, $key, $data) {
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

$email = "cloudpatient" . rand(100,999) . "@georx.com";
$password = "Password123!";

echo "SIGNUP WITH REAL DOMAIN: {$email}\n";
$signUpRes = supabasePost("{$supabaseUrl}/auth/v1/signup", $anonKey, [
    'email' => $email,
    'password' => $password,
    'data' => [
        'name' => 'Cloud Patient',
        'phone' => '09123456789'
    ]
]);

echo "SignUp HTTP Code: {$signUpRes['code']}\n";
echo "SignUp Response: " . print_r($signUpRes['data'], true) . "\n";

echo "SIGNIN WITH REAL DOMAIN:\n";
$signInRes = supabasePost("{$supabaseUrl}/auth/v1/token?grant_type=password", $anonKey, [
    'email' => $email,
    'password' => $password
]);

echo "SignIn HTTP Code: {$signInRes['code']}\n";
echo "SignIn Response: " . print_r($signInRes['data'], true) . "\n";
