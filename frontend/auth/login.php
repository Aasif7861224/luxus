<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

$defaultRedirect = BASE_URL . 'frontend/cart.php';
$redirect = safe_redirect_target($_GET['redirect'] ?? '', $defaultRedirect);
$_SESSION['login_redirect'] = $redirect;

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Google login is not configured') . '&redirect=' . urlencode($redirect));
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

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
redirect_to($authUrl);
