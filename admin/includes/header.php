<?php
// Luxus/admin/includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: /luxus/admin/index.php");
    exit;
}

/**
 * ✅ IMPORTANT:
 * Set this to your project folder name in htdocs.
 * If your folder name is "Luxus" (capital), still usually URL is /Luxus/ or /luxus/
 * In your case it's: /luxus/
 */
$BASE = "/LUXUS/admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Luxus Admin Panel</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color: #f5f6fa; }
        .topbar { background: #000; }
        .nav-link { font-size: 14px; }
    </style>
</head>
<body>

<!-- RESPONSIVE NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark topbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?php echo $BASE; ?>/dashboard.php">Luxus Admin</a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar"
                aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav Links -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/products/list.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/services/list.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/orders/list.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/bookings/list.php">Wedding Bookings</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo $BASE; ?>/users/list.php">Users</a></li>
            </ul>

            <!-- Right side -->
            <div class="d-flex align-items-center gap-2">
                <span class="text-white small">
                    <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                </span>
                <a class="btn btn-sm btn-outline-light" href="<?php echo $BASE; ?>/logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- PAGE CONTENT WRAPPER -->
<div class="container-fluid py-4">
