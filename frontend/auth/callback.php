<?php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function fail($msg) {
    http_response_code(400);
    echo "<h3>Google Login Failed</h3>";
    echo "<p>" . htmlspecialchars($msg) . "</p>";
    echo "<a href='" . htmlspecialchars(BASE_URL) . "'>Go Home</a>";
    exit;
}

// 1) Validate state
$state = $_GET['state'] ?? '';
$code  = $_GET['code'] ?? '';
$err   = $_GET['error'] ?? '';

if ($err) fail("Error: " . $err);
if (!$code) fail("Missing authorization code.");
if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    fail("Invalid state. Please try again.");
}
unset($_SESSION['oauth_state']);

// 2) Exchange code for token
$tokenUrl = "https://oauth2.googleapis.com/token";

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

if (!$tokenResp) fail("Token request failed: " . $curlErr);

$tokenJson = json_decode($tokenResp, true);
$accessToken = $tokenJson['access_token'] ?? '';
if (!$accessToken) fail("Access token missing. Response: " . $tokenResp);

// 3) Fetch user info
$userInfoUrl = "https://www.googleapis.com/oauth2/v2/userinfo";
$ch = curl_init($userInfoUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $accessToken],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$userResp = curl_exec($ch);
$curlErr2 = curl_error($ch);
curl_close($ch);

if (!$userResp) fail("User info request failed: " . $curlErr2);

$user = json_decode($userResp, true);

$googleId = $user['id'] ?? '';
$email    = $user['email'] ?? '';
$name     = $user['name'] ?? '';

if (!$googleId || !$email) fail("Google profile data missing. Response: " . $userResp);

// 4) Upsert into users table
// Assumed columns: id, name, email, phone, google_id, created_at
// If your columns differ, tell me, I’ll adjust.

$stmt = mysqli_prepare($conn, "SELECT id, name FROM users WHERE google_id=? OR email=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ss", $googleId, $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$existing = mysqli_fetch_assoc($res);

if ($existing) {
    $userId = (int)$existing['id'];

    $up = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, google_id=? WHERE id=?");
    mysqli_stmt_bind_param($up, "sssi", $name, $email, $googleId, $userId);
    mysqli_stmt_execute($up);
} else {
    $ins = mysqli_prepare($conn, "INSERT INTO users (name, email, google_id, created_at) VALUES (?, ?, ?, NOW())");
    mysqli_stmt_bind_param($ins, "sss", $name, $email, $googleId);
    mysqli_stmt_execute($ins);

    $userId = (int)mysqli_insert_id($conn);
}

// 5) Set session
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_email'] = $email;

// 6) Redirect back
$redirect = $_SESSION['login_redirect'] ?? (BASE_URL . "frontend/cart.php");
unset($_SESSION['login_redirect']);

header("Location: " . $redirect);
exit;
