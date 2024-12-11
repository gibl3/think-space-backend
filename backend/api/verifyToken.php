<?php
// Start a PHP session
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code("Request method: " . 200);
    exit;
}

// Specifically parse JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);
// echo json_encode($data);

// Check if idToken exists in the JSON payload
if (!isset($data['idToken'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No token provided',
        'received_data' => $data
    ]);
    exit;
}

$idToken = $data['idToken'];
// echo json_encode($idToken);

$verifiedUser = verifyFirebaseToken($idToken);

if ($verifiedUser === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Store user info in session
$_SESSION['user_id'] = $verifiedUser['uid'];
$_SESSION['user_email'] = $verifiedUser['email'];

// Respond with success
echo json_encode([
    'status' => 'success',
    'message' => 'Login successful',
    'user' => $verifiedUser
]);


function verifyFirebaseToken($idToken)
{
    try {
        $projectId = 'thinkspace-8a3a2';

        // Use a file-based cache instead of static variables
        $cacheFile = sys_get_temp_dir() . '/firebase_keys.cache';
        $keys = null;

        // If file doesn't exist, $keys remains null
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $keys = json_decode(file_get_contents($cacheFile), true);
        }   

        // If $keys is null (either file doesn't exist or is expired)
        if ($keys === null) {
            $keysUrl = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
            $keys = json_decode(file_get_contents($keysUrl), true);
            // This creates or overwrites the cache file automatically
            file_put_contents($cacheFile, json_encode($keys));
        }

        // Try each key until we find the right one
        foreach ($keys as $key => $publicKey) {
            try {
                $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));

                if (
                    $decoded->aud !== $projectId ||
                    $decoded->iss !== "https://securetoken.google.com/$projectId"
                ) {
                    continue;
                }

                return [
                    'uid' => $decoded->sub,
                    'email' => $decoded->email,
                ];
            } catch (Exception $e) {
                continue; // Try next key
            }
        }
        throw new Exception('Token verification failed for all keys');
    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        return false;
    }
}
