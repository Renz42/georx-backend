<?php
// Test API endpoint directly using cURL

function postJson($url, $data) {
    $ch = curl_init($url);
    $payload = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Accept:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => json_decode($result, true)];
}

echo "1. TESTING API REGISTER...\n";
$regEmail = 'mobile_test_' . time() . '@example.com';
$regRes = postJson('http://127.0.0.1:8000/api/register', [
    'name' => 'Mobile Test User',
    'email' => $regEmail,
    'phone' => '09123456789',
    'password' => 'password123',
    'password_confirmation' => 'password123'
]);
echo "Register HTTP Code: " . $regRes['code'] . "\n";
echo "Register Response: " . print_r($regRes['response'], true) . "\n";

echo "2. TESTING API LOGIN WITH NEW USER...\n";
$loginRes = postJson('http://127.0.0.1:8000/api/login', [
    'email' => $regEmail,
    'password' => 'password123',
    'role' => 'customer'
]);
echo "Login HTTP Code: " . $loginRes['code'] . "\n";
echo "Login Response: " . print_r($loginRes['response'], true) . "\n";
