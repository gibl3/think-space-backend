<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_samesite', 'Lax');

// Start a PHP session
session_start();

error_log("=== New verification attempt ==="); // This will mark each new request
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the JSON payload from the request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if idToken exists in the JSON payload
if (!isset($data['idToken'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No token provided',
        'received_data' => $data
    ]);
    exit;
}

// Get the idToken from the JSON payload
$idToken = $data['idToken'];
// echo json_encode($idToken); // debug

$verifiedUser = verifyFirebaseToken($idToken);

if ($verifiedUser === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Store user info in session
$_SESSION['user_id'] = $verifiedUser['uid'];
$_SESSION['user_email'] = $verifiedUser['email'];
$_SESSION['last_activity'] = time();

// Respond with success
// TODO: Remove user info from response before production
// TODO: Create a standardized response format
echo json_encode([
    'status' => 'success',
    'message' => 'Login successful',
    'user' => $verifiedUser['email']
]);

function getFirebaseKeys()
{
    // Full path to the cache file
    $cacheFilePath = __DIR__ . '/firebase_keys_cache.json';
    $cacheExpiry = 3600; // 1 hour
    $cacheData = null;

    // Check if cache file exists and is valid
    if (file_exists($cacheFilePath)) {
        $cacheData = json_decode(file_get_contents($cacheFilePath), true);

        if ($cacheData && isset($cacheData['expiry']) && time() < $cacheData['expiry']) {
            return $cacheData['keys'];
        }
    }

    // Fetch new keys if cache is invalid or missing
    $keysUrl = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    $response = file_get_contents($keysUrl);
    if ($response === false) {
        throw new Exception('Failed to fetch Firebase public keys');
    }

    $keys = json_decode($response, true);

    // Save to cache file
    $cacheData = [
        'keys' => $keys,
        'expiry' => time() + $cacheExpiry
    ];
    file_put_contents($cacheFilePath, json_encode($cacheData));

    return $keys;
}

function verifyFirebaseToken($idToken)
{
    try {
        $projectId = 'thinkspace-8a3a2';
        $keys = getFirebaseKeys();

        error_log("=== Token Verification Details ===");
        error_log("Server time: " . date('Y-m-d H:i:s'));

        // Add leeway to JWT validation
        JWT::$leeway = 60; // 60 seconds of leeway

        foreach ($keys as $key => $publicKey) {
            error_log("Trying key: $key");
            try {
                $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));

                error_log("Token decoded successfully!");
                error_log("Token claims: " . json_encode([
                    'aud' => $decoded->aud,
                    'iss' => $decoded->iss,
                    'exp' => date('Y-m-d H:i:s', $decoded->exp),
                    'iat' => date('Y-m-d H:i:s', $decoded->iat)
                ]));

                if ($decoded->aud !== $projectId) {
                    error_log("Audience mismatch. Expected: $projectId, Got: {$decoded->aud}");
                    continue;
                }

                if ($decoded->iss !== "https://securetoken.google.com/$projectId") {
                    error_log("Issuer mismatch. Expected: https://securetoken.google.com/$projectId, Got: {$decoded->iss}");
                    continue;
                }

                return [
                    'uid' => $decoded->sub,
                    'email' => $decoded->email,
                ];
            } catch (Exception $e) {
                error_log("Failed with key $key: " . $e->getMessage());
            }
        }
        throw new Exception('Token verification failed for all keys');
    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        return false;
    }
}
