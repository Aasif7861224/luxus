<?php
require_once __DIR__ . '/env.php';

$host = app_env('DB_HOST', 'localhost');
$dbname = app_env('DB_NAME', 'wedding_studio');
$username = app_env('DB_USER', 'root');
$password = app_env('DB_PASS', '');
$port = (int)app_env('DB_PORT', 3306);

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    $debug = app_env('APP_DEBUG', '0') === '1';
    if ($debug) {
        die('Database connection failed: ' . mysqli_connect_error());
    }
    http_response_code(500);
    die('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');

