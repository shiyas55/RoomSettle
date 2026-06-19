<?php
// logout.php
// Safe session termination

require_once 'includes/auth.php';

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session on server
session_destroy();

// Start a brief fresh session to pass a success flash message
session_start();
set_flash_message('success', 'You have been successfully logged out.');

header("Location: login.php");
exit;
