<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // echo json_encode(['User_id' => $_SESSION['user_id'],]);
}

// Destroy the session to log out the user
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset(); // Clear session variables
    session_destroy(); // Destroy the session

    // Optionally clear the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
}

// Return a response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'User logged out successfully'
]);
