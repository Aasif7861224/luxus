<?php
require_once __DIR__ . '/includes/header.php';

// HERO PRODUCT (example: first active preset)
$hero = mysqli_query($conn,"
    SELECT p.id, p.title, pm.file_path
    FROM products p
    JOIN product_media pm ON pm.product_id = p.id AND pm.media_type='image'
    WHERE p.status='active'
    ORDER BY p.id DESC
    LIMIT 1
");
$heroProduct = mysqli_fetch_assoc($hero);
?>

<!-- HERO SECTION -->
<div class="container my-5">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="display-6"><?= htmlspecialchars($heroProduct['title'] ?? 'Wedding Presets') ?></h1>
            <p class="text-muted">Professional presets crafted for weddings.</p>
            <a href="<?= BASE_URL ?>frontend/product.php?id=<?= $heroProduct['id'] ?>" class="btn btn-dark">
                Shop Now
            </a>
        </div>
        <div class="col-md-6">
            <?php if ($heroProduct): ?>
                <img src="<?= BASE_URL ?>frontend/assets/uploads/<?= $heroProduct['file_path'] ?>"
                     class="img-fluid rounded">
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PRESETS GRID -->
<div class="container my-5">
    <h2 class="text-center mb-4">TMC PRESETS</h2>

    <div class="row g-4">
        <?php
        $products = mysqli_query($conn,"
            SELECT p.id, p.title,
            (SELECT price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) price,
            (SELECT discount_price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) discount,
            (SELECT file_path FROM product_media WHERE product_id=p.id AND media_type='image' LIMIT 1) image
            FROM products p
            WHERE p.status='active'
        ");

        while($p = mysqli_fetch_assoc($products)):
        ?>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <img src="<?= BASE_URL ?>frontend/assets/uploads/<?= $p['image'] ?>" class="card-img-top">
                <div class="card-body text-center">
                    <h6><?= htmlspecialchars($p['title']) ?></h6>
                    <del class="text-muted">₹<?= number_format($p['price']) ?></del>
                    <strong> ₹<?= number_format($p['discount']) ?></strong>
                    <br>
                    <a href="<?= BASE_URL ?>frontend/product.php?id=<?= $p['id'] ?>" class="btn btn-outline-dark btn-sm mt-2">
                        Buy Now
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
