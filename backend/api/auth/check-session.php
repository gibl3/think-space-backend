<?php
// Ensure secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start session
session_start();

// Set security headers
header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

try {
    $response = ['authenticated' => false];

    // Check if session exists and hasn't expired
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        // Set session timeout (e.g., 30 minutes)
        $session_timeout = 1800; // 30 minutes in seconds

        if (time() - $_SESSION['last_activity'] < $session_timeout) {
            // Update last activity time
            $_SESSION['last_activity'] = time();
            $response['authenticated'] = true;
        } else {
            // Session expired
            session_destroy();
            http_response_code(440); // Login timeout status
            $response['message'] = 'Session expired';
        }
    } else {
        http_response_code(401);
        $response['message'] = 'Not authenticated';
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Internal server error'
    ]);
}
