<?php
// config/database.php
// Roommate Expense Management System database connection configuration.
// Optimized for both InfinityFree and local development.

// InfinityFree Placeholders (Modify these with your real details)
$host = "sqlXXX.infinityfree.com";
$username = "if0_XXXXXXXX";
$password = "DATABASE_PASSWORD";
$database = "if0_XXXXXXXX_roommate";

// Prevent PHP from throwing errors if the connection fails, so we can try the fallback
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    // Local Fallback (For Wamp, Xampp, Mamp, etc.)
    $local_host = "localhost";
    $local_username = "root";
    $local_password = "";
    $local_database = "roommate_db";

    $conn = new mysqli($local_host, $local_username, $local_password, $local_database);

    if ($conn->connect_error) {
        die("Database connection failed. Both hosting credentials and local fallback failed.<br>Error: " . $conn->connect_error);
    }
}

// Enable standard error reporting after connection is established
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set UTF-8 encoding
$conn->set_charset("utf8mb4");
