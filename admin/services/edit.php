<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: list.php");
    exit;
}

// Fetch service
$serviceStmt = mysqli_prepare($conn, "SELECT * FROM services WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($serviceStmt, "i", $id);
mysqli_stmt_execute($serviceStmt);
$serviceRes = mysqli_stmt_get_result($serviceStmt);
$service = mysqli_fetch_assoc($serviceRes);

if (!$service) {
    header("Location: list.php");
    exit;
}

// Fetch latest price
$priceStmt = mysqli_prepare($conn, "SELECT * FROM service_prices WHERE service_id = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($priceStmt, "i", $id);
mysqli_stmt_execute($priceStmt);
$priceRes = mysqli_stmt_get_result($priceStmt);
$currentPrice = mysqli_fetch_assoc($priceRes);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $deliveryDays = (int)($_POST['delivery_days'] ?? 0);
    $status       = $_POST['status'] ?? 'active';

    $price        = (float)($_POST['price'] ?? 0);
    $discount     = (float)($_POST['discount_price'] ?? 0);

    if ($name !== '' && $deliveryDays > 0 && $price > 0) {

        // Update service
        $updateStmt = mysqli_prepare($conn, "UPDATE services SET name=?, description=?, delivery_days=?, status=? WHERE id=?");
        mysqli_stmt_bind_param($updateStmt, "ssisi", $name, $description, $deliveryDays, $status, $id);
        mysqli_stmt_execute($updateStmt);

        // Insert new price record (price history)
        $insertPrice = mysqli_prepare($conn, "INSERT INTO service_prices (service_id, price, discount_price, valid_from) VALUES (?, ?, ?, CURDATE())");
        mysqli_stmt_bind_param($insertPrice, "idd", $id, $price, $discount);
        mysqli_stmt_execute($insertPrice);

        header("Location: list.php");
        exit;
    }
}
?>

<h4 class="mb-4">Edit Service</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Service Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($service['name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Delivery Days</label>
                    <input type="number" name="delivery_days" class="form-control" min="1"
                           value="<?= (int)$service['delivery_days'] ?>" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" min="1"
                           value="<?= $currentPrice['price'] ?? 0 ?>" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Discount Price</label>
                    <input type="number" name="discount_price" class="form-control" min="0"
                           value="<?= $currentPrice['discount_price'] ?? 0 ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $service['status']=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $service['status']=='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <button class="btn btn-dark">Update</button>
            <a href="list.php" class="btn btn-secondary">Back</a>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
