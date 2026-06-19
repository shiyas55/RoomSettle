<?php
// includes/auth.php
// Authentication & Security Helper Module

// Start session securely if not already started
function start_session_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session cookies
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        // If site is HTTPS, enforce secure cookie
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Set SameSite attribute to Lax for CSRF protection
        if (PHP_VERSION_ID >= 70300) {
            session_start([
                'cookie_samesite' => 'Lax'
            ]);
        } else {
            session_start();
        }
    }
}

start_session_safe();

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

// Enforce login on private pages
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// Enforce admin access on admin-only actions
function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['error_message'] = "Unauthorized access. Administrator privileges required.";
        header("Location: dashboard.php");
        exit;
    }
}

// Generate CSRF Token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Output hidden CSRF input field
function csrf_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Validate CSRF from POST requests
function check_csrf_post() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($token)) {
            die("CSRF validation failed. Invalid request token.");
        }
    }
}

// Sanitize user inputs
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

// Flash messages helpers
function set_flash_message($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function display_flash_messages() {
    $types = ['success', 'error', 'warning', 'info'];
    $output = '';
    foreach ($types as $type) {
        $key = 'flash_' . $type;
        if (isset($_SESSION[$key])) {
            $alert_class = ($type === 'error') ? 'danger' : $type;
            $output .= '<div class="alert alert-' . $alert_class . ' alert-dismissible fade show" role="alert">';
            $output .= $_SESSION[$key];
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $output;
}
