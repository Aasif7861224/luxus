<?php
session_start();
require_once "../backend/config/database.php";

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    header("Location: index.php?error=1");
    exit;
}

// Fetch admin
$query = "SELECT * FROM admins WHERE email = ? AND status = 'active' LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

// Verify password
if ($admin && password_verify($password, $admin['password'])) {

    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['role_id']    = $admin['role_id'];

    header("Location: dashboard.php");
    exit;

} else {
    header("Location: index.php?error=1");
    exit;
}
?>