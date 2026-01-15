<?php

require_once "../backend/config/database.php";
require_once "includes/header.php";

// Simple auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Basic stats (simple queries)
$totalOrders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'] ?? 0;
$totalBookings  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM wedding_bookings"))['total'] ?? 0;
$totalProducts  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"))['total'] ?? 0;
$totalUsers     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard | Luxus Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f5f6fa;
        }
        .stat-card {
            border-left: 5px solid #000;
        }
    </style>
</head>

<body>

<!-- TOP NAVBAR -->
<?php require_once "includes/header.php"; ?>

<div class="container-fluid">
    <div class="row">


        <!-- MAIN CONTENT -->
        <div class="col-lg-10 col-md-9 p-4">

            <h4 class="mb-4">Dashboard Overview</h4>

            <!-- STATS -->
            <div class="row g-3">
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6>Total Orders</h6>
                            <h3><?php echo $totalOrders; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6>Wedding Bookings</h6>
                            <h3><?php echo $totalBookings; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6>Products</h6>
                            <h3><?php echo $totalProducts; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6>Users</h6>
                            <h3><?php echo $totalUsers; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            
            <?php require_once "includes/footer.php"; ?>
        </div>
    </div>
</div>

</body>
</html>
