<?php
// Database configuration
const DB_HOST = 'localhost';
const DB_USER = 'habit_user';
const DB_PASS = 'secure_password';
const DB_NAME = 'habit_app';
const APP_TIMEZONE = 'UTC';

if (!headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(APP_TIMEZONE);

function db(): mysqli
{
    static $conn;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        http_response_code(500);
        exit('Database connection failed');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>
