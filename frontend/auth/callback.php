<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

function oauth_fail($msg)
{
    $url = BASE_URL . 'frontend/auth/index.php?error=' . urlencode($msg);
    redirect_to($url);
}

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$err = $_GET['error'] ?? '';

if ($err !== '') {
    oauth_fail('Google login cancelled');
}
if ($code === '') {
    oauth_fail('Missing authorization code');
}
if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    oauth_fail('Invalid OAuth state');
}
unset($_SESSION['oauth_state']);

$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$tokenResp = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if (!$tokenResp) {
    oauth_fail('Token request failed: ' . $curlErr);
}

$tokenJson = json_decode($tokenResp, true);
$accessToken = $tokenJson['access_token'] ?? '';
if ($accessToken === '') {
    oauth_fail('Access token missing');
}

$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$userResp = curl_exec($ch);
$curlErr2 = curl_error($ch);
curl_close($ch);

if (!$userResp) {
    oauth_fail('Profile request failed: ' . $curlErr2);
}

$user = json_decode($userResp, true);
$googleId = $user['id'] ?? '';
$email = $user['email'] ?? '';
$name = $user['name'] ?? 'User';

if ($googleId === '' || $email === '') {
    oauth_fail('Google profile data missing');
}

$hasPassword = column_exists($conn, 'users', 'password');

$stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE google_id=? OR email=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $googleId, $email);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($existing) {
    $userId = (int)$existing['id'];

    $up = mysqli_prepare($conn, 'UPDATE users SET name=?, email=?, google_id=? WHERE id=?');
    mysqli_stmt_bind_param($up, 'sssi', $name, $email, $googleId, $userId);
    mysqli_stmt_execute($up);
} else {
    if ($hasPassword) {
        $ins = mysqli_prepare($conn, 'INSERT INTO users (name, email, password, google_id, created_at) VALUES (?, ?, NULL, ?, NOW())');
    } else {
        $ins = mysqli_prepare($conn, 'INSERT INTO users (name, email, google_id, created_at) VALUES (?, ?, ?, NOW())');
    }
    mysqli_stmt_bind_param($ins, 'sss', $name, $email, $googleId);
    mysqli_stmt_execute($ins);
    $userId = (int)mysqli_insert_id($conn);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_email'] = $email;

$defaultRedirect = BASE_URL . 'frontend/cart.php';
$redirect = safe_redirect_target($_SESSION['login_redirect'] ?? '', $defaultRedirect);
unset($_SESSION['login_redirect']);

redirect_to($redirect);
