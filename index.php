<?php
// index.php (ROOT)

// Force HTTPS later if needed
// if (!isset($_SERVER['HTTPS'])) {
//     header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
//     exit;
// }

// Load homepage
require_once __DIR__ . '/frontend/home.php';
?>