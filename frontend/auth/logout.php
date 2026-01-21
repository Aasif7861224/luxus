<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
header("Location: " . BASE_URL . "frontend/home.php");
exit;