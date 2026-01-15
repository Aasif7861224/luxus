<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $deliveryDays = (int)($_POST['delivery_days'] ?? 0);
    $price        = (float)($_POST['price'] ?? 0);
    $discount     = (float)($_POST['discount_price'] ?? 0);

    if ($name !== '' && $deliveryDays > 0 && $price > 0) {

        // Insert service
        $stmt = mysqli_prepare($conn, "INSERT INTO services (name, description, delivery_days, status) VALUES (?, ?, ?, 'active')");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $description, $deliveryDays);
        mysqli_stmt_execute($stmt);

        $service_id = mysqli_insert_id($conn);

        // Insert service price history (valid_from today)
        $stmt2 = mysqli_prepare($conn, "INSERT INTO service_prices (service_id, price, discount_price, valid_from) VALUES (?, ?, ?, CURDATE())");
        mysqli_stmt_bind_param($stmt2, "idd", $service_id, $price, $discount);
        mysqli_stmt_execute($stmt2);

        header("Location: list.php");
        exit;
    }
}
?>

<h4 class="mb-4">Add Service</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Service Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Wedding Photo Editing" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Short details..."></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Delivery Days</label>
                    <input type="number" name="delivery_days" class="form-control" min="1" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" min="1" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Discount Price</label>
                    <input type="number" name="discount_price" class="form-control" min="0">
                </div>
            </div>

            <button class="btn btn-dark">Save Service</button>
            <a href="list.php" class="btn btn-secondary">Back</a>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
