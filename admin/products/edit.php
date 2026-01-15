<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

$id = $_GET['id'];

$product = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM products WHERE id = '$id'"
));

$currentPrice = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM product_prices WHERE product_id='$id' ORDER BY id DESC LIMIT 1"
));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $title    = $_POST['title'];
    $status   = $_POST['status'];
    $price    = $_POST['price'];
    $discount = $_POST['discount_price'];

    mysqli_query($conn, "
        UPDATE products SET title='$title', status='$status'
        WHERE id='$id'
    ");

    mysqli_query($conn, "
        INSERT INTO product_prices (product_id, price, discount_price, valid_from)
        VALUES ('$id', '$price', '$discount', CURDATE())
    ");

    header("Location: list.php");
    exit;
}
?>

<h4 class="mb-4">Edit Product</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">

            <div class="mb-3">
                <label>Product Title</label>
                <input type="text" name="title" class="form-control"
                       value="<?= htmlspecialchars($product['title']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $product['status']=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $product['status']=='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Price</label>
                    <input type="number" name="price" class="form-control"
                           value="<?= $currentPrice['price'] ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label>Discount Price</label>
                    <input type="number" name="discount_price" class="form-control"
                           value="<?= $currentPrice['discount_price'] ?>">
                </div>
            </div>

            <button class="btn btn-dark">Update</button>
            <a href="list.php" class="btn btn-secondary">Back</a>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
