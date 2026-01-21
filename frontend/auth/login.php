<?php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$redirect = $_GET['redirect'] ?? (BASE_URL . "frontend/cart.php");
$_SESSION['login_redirect'] = $redirect;

// CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Google OAuth authorization URL
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'offline',
    'prompt' => 'select_account',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header("Location: " . $authUrl);
exit;
