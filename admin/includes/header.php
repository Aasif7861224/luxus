<?php
require_once __DIR__ . '/../../backend/config/constants.php';
require_once __DIR__ . '/../../backend/helpers/app.php';

ensure_session_started();

if (empty($_SESSION['admin_id'])) {
    redirect_to(rtrim(APP_BASE_URL, '/') . '/admin/index.php');
}

$BASE = rtrim(APP_BASE_URL, '/') . '/admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= h(APP_NAME) ?> Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f6fa; }
        .topbar { background: #111; }
        .nav-link { font-size: 14px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark topbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= $BASE ?>/dashboard.php"><?= h(APP_NAME) ?> Admin</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar"
                aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/products/list.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/orders/list.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/users/list.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/feedback/list.php">Feedback</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <span class="text-white small"><?= h($_SESSION['admin_name'] ?? 'Admin') ?></span>
                <a class="btn btn-sm btn-outline-light" href="<?= $BASE ?>/logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">
