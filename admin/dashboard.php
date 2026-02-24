<?php
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/includes/header.php';

$totalOrders = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM orders'))['total'] ?? 0;
$totalProducts = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM products'))['total'] ?? 0;
$totalUsers = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM users'))['total'] ?? 0;
$totalDigital = table_exists($conn, 'digital_products')
    ? (mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM digital_products'))['total'] ?? 0)
    : 0;
?>

<div class="container-fluid">
  <h4 class="mb-4">Dashboard Overview</h4>

  <div class="row g-3">
    <div class="col-lg-3 col-md-6 col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6>Total Orders</h6>
          <h3><?= (int)$totalOrders ?></h3>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6>Products</h6>
          <h3><?= (int)$totalProducts ?></h3>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6>Digital Links</h6>
          <h3><?= (int)$totalDigital ?></h3>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6>Users</h6>
          <h3><?= (int)$totalUsers ?></h3>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
