<?php
require_once __DIR__ . '/config.php';
ensure_session_started();

if (empty($_SESSION['user_id'])) {
    $default = BASE_URL . 'frontend/cart.php';
    $current = $_SERVER['REQUEST_URI'] ?? $default;
    $safe = safe_redirect_target($current, $default);
    redirect_to(BASE_URL . 'frontend/auth/index.php?redirect=' . urlencode($safe));
}
