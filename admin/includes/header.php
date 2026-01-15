<?php
// header.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}
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
        body {
            background-color: #f5f6fa;
        }
        .sidebar {
            min-height: 100vh;
            background: #111;
        }
        .sidebar a {
            color: #ddd;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            font-size: 14px;
        }
        .sidebar a:hover {
            background: #222;
            color: #fff;
        }
        .topbar {
            background: #000;
        }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark topbar px-3">
    <span class="navbar-brand">Luxus Admin</span>
    <div class="text-white small">
        <?php echo htmlspecialchars($_SESSION['admin_name']); ?> |
        <a href="../logout.php" class="text-danger text-decoration-none">Logout</a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
