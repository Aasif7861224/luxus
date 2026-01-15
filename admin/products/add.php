<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE name != 'Service'");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $title       = $_POST['title'];
    $category_id = $_POST['category_id'];
    $price       = $_POST['price'];
    $discount    = $_POST['discount_price'];

    mysqli_query($conn, "
        INSERT INTO products (category_id, title, status)
        VALUES ('$category_id', '$title', 'active')
    ");

    $product_id = mysqli_insert_id($conn);

    mysqli_query($conn, "
        INSERT INTO product_prices (product_id, price, discount_price, valid_from)
        VALUES ('$product_id', '$price', '$discount', CURDATE())
    ");

    header("Location: list.php");
    exit;
}
?>

<h4 class="mb-4">Add Product</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label>Product Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Category</label>
                <select name="category_id" class="form-control" required>
                    <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Price</label>
                    <input type="number" name="price" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label>Discount Price</label>
                    <input type="number" name="discount_price" class="form-control">
                </div>
            </div>

            <button class="btn btn-dark">Save Product</button>
            <a href="list.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
