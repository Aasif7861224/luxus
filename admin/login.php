<?php
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/helpers/app.php';

ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect_to('index.php?error=' . urlencode('Invalid session token'));
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    redirect_to('index.php?error=' . urlencode('Email and password required'));
}

$query = "SELECT * FROM admins WHERE email = ? AND status = 'active' LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$admin || !password_verify($password, $admin['password'] ?? '')) {
    redirect_to('index.php?error=' . urlencode('Invalid credentials'));
}

session_regenerate_id(true);
$_SESSION['admin_id'] = (int)$admin['id'];
$_SESSION['admin_name'] = $admin['name'] ?? 'Admin';
$_SESSION['role_id'] = (int)($admin['role_id'] ?? 0);

redirect_to('dashboard.php');
