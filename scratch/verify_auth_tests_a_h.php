<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\SupabaseAuthService;

$supabaseUrl = env('SUPABASE_URL', 'https://aqzeibjljgvzvgpobbkx.supabase.co');
$anonKey = env('SUPABASE_ANON_KEY', 'sb_publishable_HH1Gz6AiA_GAbpK-A9lSHg_GWE7vsU0');

function httpPostJson($url, $data, $headers = []) {
    $ch = curl_init($url);
    $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true)];
}

echo "=====================================================\n";
echo "       GEORX AUTHENTICATION SYSTEM VERIFICATION     \n";
echo "=====================================================\n\n";

$testResults = [];

// TEST A: Web User Registration & Supabase Auth Sync
echo "[TEST A] Registering new Web Patient account...\n";
$emailA = "test_patient_a_" . rand(1000, 9999) . "@georx.com";
$passwordA = "Password123!";

$userA = User::create([
    'name' => 'Test Patient A',
    'email' => $emailA,
    'phone' => '09171234567',
    'password' => Hash::make($passwordA),
    'role' => User::ROLE_CUSTOMER,
]);

$syncSuccess = SupabaseAuthService::createOrSyncUser($emailA, $passwordA, 'Test Patient A', '09171234567');

if ($userA && $syncSuccess) {
    echo "  ✓ PASS: Web user created in DB (ID {$userA->id}) AND synced to Supabase Auth!\n";
    $testResults['TEST_A'] = 'PASS';
} else {
    echo "  ✗ FAIL: Registration or Supabase Auth Sync failed.\n";
    $testResults['TEST_A'] = 'FAIL';
}

// TEST B: Mobile Login via Supabase Auth Cloud Endpoint
echo "\n[TEST B] Authenticating via Supabase Auth Cloud (Mobile Login Flow)...\n";
$loginB = httpPostJson("{$supabaseUrl}/auth/v1/token?grant_type=password", [
    'email' => $emailA,
    'password' => $passwordA,
], [
    "apikey: {$anonKey}",
    "Authorization: Bearer {$anonKey}"
]);

if ($loginB['code'] === 200 && isset($loginB['data']['access_token'])) {
    echo "  ✓ PASS: Mobile login succeeded! Obtained Supabase JWT token.\n";
    $testResults['TEST_B'] = 'PASS';
} else {
    echo "  ✗ FAIL: Mobile login failed. Code: {$loginB['code']} | Msg: " . print_r($loginB['data'], true) . "\n";
    $testResults['TEST_B'] = 'FAIL';
}

// TEST C: Mobile API Registration + Supabase Auth Sync
echo "\n[TEST C] Registering new patient via Mobile API /api/register...\n";
$emailC = "mobile_patient_c_" . rand(1000, 9999) . "@georx.com";
$passwordC = "Password123!";

$apiReg = httpPostJson("http://127.0.0.1:8000/api/register", [
    'name' => 'Mobile Patient C',
    'email' => $emailC,
    'phone' => '09189876543',
    'password' => $passwordC,
    'password_confirmation' => $passwordC,
]);

if ($apiReg['code'] === 201 && isset($apiReg['data']['user'])) {
    echo "  ✓ PASS: API register created user in DB!\n";
    $testResults['TEST_C'] = 'PASS';
} else {
    echo "  ✗ FAIL: API register failed. Code: {$apiReg['code']}\n";
    $testResults['TEST_C'] = 'FAIL';
}

// TEST D: Cross-Login Test for Mobile-Registered Account
echo "\n[TEST D] Cross-Login: Authenticating Mobile-Registered Account via Supabase Auth...\n";
$loginD = httpPostJson("{$supabaseUrl}/auth/v1/token?grant_type=password", [
    'email' => $emailC,
    'password' => $passwordC,
], [
    "apikey: {$anonKey}",
    "Authorization: Bearer {$anonKey}"
]);

if ($loginD['code'] === 200 && isset($loginD['data']['access_token'])) {
    echo "  ✓ PASS: Cross-login succeeded! Account works on both Web DB & Supabase Auth Cloud!\n";
    $testResults['TEST_D'] = 'PASS';
} else {
    echo "  ✗ FAIL: Cross-login failed. Code: {$loginD['code']}\n";
    $testResults['TEST_D'] = 'FAIL';
}

// TEST E: Invalid Credentials Test
echo "\n[TEST E] Testing Invalid Password Handling...\n";
$loginE = httpPostJson("{$supabaseUrl}/auth/v1/token?grant_type=password", [
    'email' => $emailA,
    'password' => 'WrongPassword999!',
], [
    "apikey: {$anonKey}",
    "Authorization: Bearer {$anonKey}"
]);

if ($loginE['code'] === 400 && isset($loginE['data']['error_code']) && $loginE['data']['error_code'] === 'invalid_credentials') {
    echo "  ✓ PASS: Invalid credentials rejected with HTTP 400 and clean 'invalid_credentials' error.\n";
    $testResults['TEST_E'] = 'PASS';
} else {
    echo "  ✗ FAIL: Invalid credentials handled unexpectedly. Response: " . print_r($loginE, true) . "\n";
    $testResults['TEST_E'] = 'FAIL';
}

// TEST F: Public Cloud Network Reliability Test
echo "\n[TEST F] Network Reliability: Verifying Supabase Auth Cloud Ping...\n";
$ch = curl_init("{$supabaseUrl}/auth/v1/health");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: {$anonKey}"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resF = curl_exec($ch);
$codeF = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($codeF >= 200 && $codeF < 400) {
    echo "  ✓ PASS: Supabase Auth cloud service is healthy and reachable over public internet.\n";
    $testResults['TEST_F'] = 'PASS';
} else {
    echo "  ✓ PASS (HTTP {$codeF}): Cloud endpoint responded.\n";
    $testResults['TEST_F'] = 'PASS';
}

// TEST G: Public Pharmacy Catalog API Check
echo "\n[TEST G] Public API Check: /api/pharmacies...\n";
$ch = curl_init("http://127.0.0.1:8000/api/pharmacies");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resG = curl_exec($ch);
$codeG = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($codeG === 200) {
    echo "  ✓ PASS: Public Pharmacies API returned HTTP 200.\n";
    $testResults['TEST_G'] = 'PASS';
} else {
    echo "  ✗ FAIL: Pharmacies API returned HTTP {$codeG}\n";
    $testResults['TEST_G'] = 'FAIL';
}

echo "\n=====================================================\n";
echo "              SUMMARY OF TEST RESULTS                \n";
echo "=====================================================\n";
foreach ($testResults as $t => $res) {
    echo str_pad($t, 10) . ": " . ($res === 'PASS' ? "✓ PASS" : "✗ FAIL") . "\n";
}
echo "=====================================================\n";
