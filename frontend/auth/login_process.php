<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

$defaultRedirect = BASE_URL . 'frontend/home.php';
$redirect = safe_redirect_target($_POST['redirect'] ?? '', $defaultRedirect);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(BASE_URL . 'frontend/auth/index.php?redirect=' . urlencode($redirect));
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Invalid session token') . '&redirect=' . urlencode($redirect));
}

$email = trim($_POST['email'] ?? '');
$pass = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Email & password required') . '&redirect=' . urlencode($redirect));
}

$hasPassword = column_exists($conn, 'users', 'password');
$sql = $hasPassword
    ? 'SELECT id, name, email, password FROM users WHERE email=? LIMIT 1'
    : 'SELECT id, name, email FROM users WHERE email=? LIMIT 1';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$u) {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Invalid credentials') . '&redirect=' . urlencode($redirect));
}

if (!$hasPassword) {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Password login not available, please use Google login') . '&redirect=' . urlencode($redirect));
}

$stored = $u['password'] ?? '';
$ok = false;
if ($stored !== '' && password_verify($pass, $stored)) {
    $ok = true;
} elseif ($stored !== '' && hash_equals($stored, $pass)) {
    // Legacy plain password support during migration.
    $ok = true;
}

if (!$ok) {
    redirect_to(BASE_URL . 'frontend/auth/index.php?error=' . urlencode('Invalid credentials') . '&redirect=' . urlencode($redirect));
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$u['id'];
$_SESSION['user_name'] = $u['name'] ?? 'User';
$_SESSION['user_email'] = $u['email'] ?? $email;

redirect_to($redirect);
