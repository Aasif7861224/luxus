<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    $redirect = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'frontend/cart.php');
    header("Location: " . BASE_URL . "frontend/auth/index.php?redirect=" . urlencode($redirect));
    exit;
}