<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? (BASE_URL . 'frontend/home.php');

if ($email === '' || $pass === '') {
    header("Location: index.php?error=" . urlencode("Email & password required") . "&redirect=" . urlencode($redirect));
    exit;
}

// Try hashed password first
$stmt = mysqli_prepare($conn, "SELECT id, name, email, password FROM users WHERE email=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$u = mysqli_fetch_assoc($res);

if (!$u) {
    header("Location: index.php?error=" . urlencode("Invalid credentials") . "&redirect=" . urlencode($redirect));
    exit;
}

$stored = $u['password'] ?? '';

// Support BOTH types:
// 1) password_hash() (recommended)
// 2) plain text (if you used old simple password)
$ok = false;
if ($stored && password_verify($pass, $stored)) {
    $ok = true;
} elseif ($stored === $pass) {
    $ok = true;
}

if (!$ok) {
    header("Location: index.php?error=" . urlencode("Invalid credentials") . "&redirect=" . urlencode($redirect));
    exit;
}

// Set session
$_SESSION['user_id'] = (int)$u['id'];
$_SESSION['user_name'] = $u['name'] ?? 'User';
$_SESSION['user_email'] = $u['email'] ?? $email;

header("Location: " . $redirect);
exit;