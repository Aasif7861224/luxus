<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../backend/config/database.php';

$hasDigital = table_exists($conn, 'digital_products');
$query = "
SELECT p.id, p.title, p.status, p.before_image, p.after_image, c.name AS category,
  (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price,
  (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price
";
if ($hasDigital) {
    $query .= ", dp.drive_link";
}
$query .= "
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
";
if ($hasDigital) {
    $query .= 'LEFT JOIN digital_products dp ON dp.product_id = p.id';
}
$query .= ' ORDER BY p.id DESC';

$result = mysqli_query($conn, $query);
?>

<h4 class="mb-4">Products</h4>
<a href="add.php" class="btn btn-dark mb-3">+ Add Product</a>

<div class="card shadow-sm">
  <div class="card-body table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th width="70">ID</th>
          <th width="110">Preview</th>
          <th>Title</th>
          <th>Category</th>
          <th>Price</th>
          <th>Drive Link</th>
          <th>Status</th>
          <th width="120">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$result || mysqli_num_rows($result) === 0): ?>
          <tr><td colspan="8" class="text-center text-muted">No products found</td></tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php
              $final = ((float)$row['discount_price'] > 0) ? (float)$row['discount_price'] : (float)$row['price'];
              $after = $row['after_image'] ?? '';
            ?>
            <tr>
              <td><?= (int)$row['id'] ?></td>
              <td>
                <?php if ($after !== ''): ?>
                  <img src="<?= APP_BASE_URL ?>assets/uploads/products/images/<?= h($after) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                <?php else: ?>
                  <span class="text-muted small">No image</span>
                <?php endif; ?>
              </td>
              <td><?= h($row['title']) ?></td>
              <td><?= h($row['category'] ?? '-') ?></td>
              <td>&#8377;<?= number_format($final, 0) ?></td>
              <td>
                <?php if (!empty($row['drive_link'])): ?>
                  <span class="badge bg-success">Added</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Missing</span>
                <?php endif; ?>
              </td>
              <td><?= ucfirst((string)$row['status']) ?></td>
              <td><a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary">Edit</a></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
