<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

$defaultRedirect = BASE_URL . 'frontend/home.php';
$candidateRedirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? '');
$redirect = safe_redirect_target($candidateRedirect, $defaultRedirect);
$registerUrl = BASE_URL . 'frontend/auth/register.php?redirect=' . urlencode($redirect);

$fail = function ($message, array $old = []) use ($registerUrl) {
    $_SESSION['register_flash'] = [
        'error' => $message,
        'old' => $old,
    ];
    redirect_to($registerUrl);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail('Invalid request.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $fail('Invalid session token.');
}

$name = trim((string)($_POST['name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phoneInput = trim((string)($_POST['phone'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

$old = [
    'name' => $name,
    'email' => $email,
    'phone' => $phoneInput,
];

$nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
if ($name === '' || $nameLen < 2) {
    $fail('Please enter a valid name.', $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fail('Please enter a valid email address.', $old);
}

$phoneDigits = preg_replace('/\D+/', '', $phoneInput);
if ($phoneDigits === '' || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    $fail('Please enter a valid phone number.', $old);
}

if (strlen($password) < 8) {
    $fail('Password must be at least 8 characters.', $old);
}
if (!hash_equals($password, $confirm)) {
    $fail('Password and confirm password do not match.', $old);
}

if (!column_exists($conn, 'users', 'password')) {
    $fail('Registration is not available right now. Please run latest migration.', $old);
}

$check = mysqli_prepare($conn, 'SELECT id FROM users WHERE email=? LIMIT 1');
if (!$check) {
    $fail('Unable to process request right now.', $old);
}
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
$exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
if ($exists) {
    $fail('Account with this email already exists. Please login.', $old);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    $fail('Unable to secure your password right now.', $old);
}

$normalizedPhone = normalize_phone($phoneInput);
$insert = mysqli_prepare(
    $conn,
    'INSERT INTO users (name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())'
);
if (!$insert) {
    $fail('Unable to create account right now.', $old);
}
mysqli_stmt_bind_param($insert, 'ssss', $name, $email, $passwordHash, $normalizedPhone);
if (!mysqli_stmt_execute($insert)) {
    $fail('Unable to create account right now.', $old);
}

unset($_SESSION['register_flash']);
redirect_to(BASE_URL . 'frontend/auth/index.php?success=registered&redirect=' . urlencode($redirect));
