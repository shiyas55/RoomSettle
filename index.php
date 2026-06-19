<?php
// index.php
// Main landing router

require_once 'includes/auth.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
