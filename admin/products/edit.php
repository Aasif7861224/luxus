<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../backend/config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect_to('list.php');
}

$schemaError = '';
if (!column_exists($conn, 'products', 'before_image') || !column_exists($conn, 'products', 'after_image') || !table_exists($conn, 'digital_products')) {
    $schemaError = 'Database migration pending: run database/migrations/2026_02_24_presets_conversion.sql first.';
}

$ROOT = dirname(__DIR__, 2);
$IMG_DIR = $ROOT . '/assets/uploads/products/images/';
if (!is_dir($IMG_DIR)) {
    mkdir($IMG_DIR, 0755, true);
}

function admin_validate_image($file, $uploadDir, $prefix, &$errorMsg, $required = false)
{
    $maxSize = 5 * 1024 * 1024;
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $errorMsg = 'Required image missing.';
        }
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errorMsg = 'Image upload failed.';
        return '';
    }

    if (($file['size'] ?? 0) > $maxSize) {
        $errorMsg = 'Image max size is 5MB.';
        return '';
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $errorMsg = 'Only JPG, PNG, WEBP allowed.';
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) {
        $errorMsg = 'Invalid image mime type.';
        return '';
    }

    $newName = $prefix . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errorMsg = 'Failed to save uploaded file.';
        return '';
    }

    return $newName;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM products WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$product) {
    redirect_to('list.php');
}

$stmtP = mysqli_prepare($conn, 'SELECT * FROM product_prices WHERE product_id=? ORDER BY id DESC LIMIT 1');
mysqli_stmt_bind_param($stmtP, 'i', $id);
mysqli_stmt_execute($stmtP);
$currentPrice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP)) ?: ['price' => 0, 'discount_price' => 0];

$driveLink = '';
if (table_exists($conn, 'digital_products')) {
    $dStmt = mysqli_prepare($conn, 'SELECT drive_link FROM digital_products WHERE product_id=? LIMIT 1');
    mysqli_stmt_bind_param($dStmt, 'i', $id);
    mysqli_stmt_execute($dStmt);
    $driveLink = (string)(mysqli_fetch_assoc(mysqli_stmt_get_result($dStmt))['drive_link'] ?? '');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaError === '') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'delete_media') {
            $mediaId = (int)($_POST['media_id'] ?? 0);
            if ($mediaId > 0) {
                $mStmt = mysqli_prepare($conn, "SELECT id, file_name FROM product_media WHERE id=? AND product_id=? AND media_type='image' LIMIT 1");
                mysqli_stmt_bind_param($mStmt, 'ii', $mediaId, $id);
                mysqli_stmt_execute($mStmt);
                $media = mysqli_fetch_assoc(mysqli_stmt_get_result($mStmt));
                if ($media) {
                    $path = $IMG_DIR . $media['file_name'];
                    if (is_file($path)) {
                        @unlink($path);
                    }
                    $delStmt = mysqli_prepare($conn, 'DELETE FROM product_media WHERE id=? AND product_id=?');
                    mysqli_stmt_bind_param($delStmt, 'ii', $mediaId, $id);
                    mysqli_stmt_execute($delStmt);
                }
            }
            redirect_to('edit.php?id=' . $id);
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $price = (float)($_POST['price'] ?? 0);
        $discount = (float)($_POST['discount_price'] ?? 0);
        $driveLinkInput = trim($_POST['drive_link'] ?? '');

        if ($title === '' || $price <= 0 || $driveLinkInput === '') {
            $error = 'Title, price and drive link are required.';
        }

        if ($error === '' && !filter_var($driveLinkInput, FILTER_VALIDATE_URL)) {
            $error = 'Drive link must be a valid URL.';
        }

        $newBefore = '';
        $newAfter = '';
        $galleryNew = [];
        $createdFiles = [];

        if ($error === '') {
            $newBefore = admin_validate_image($_FILES['before_image'] ?? null, $IMG_DIR, 'before', $error, false);
            if ($newBefore !== '') {
                $createdFiles[] = $IMG_DIR . $newBefore;
            }
        }

        if ($error === '') {
            $newAfter = admin_validate_image($_FILES['after_image'] ?? null, $IMG_DIR, 'after', $error, false);
            if ($newAfter !== '') {
                $createdFiles[] = $IMG_DIR . $newAfter;
            }
        }

        if ($error === '' && isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'] ?? null)) {
            for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$i] ?? '',
                    'type' => $_FILES['gallery_images']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['gallery_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['gallery_images']['size'][$i] ?? 0,
                ];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $stored = admin_validate_image($file, $IMG_DIR, 'gallery', $error, false);
                if ($stored !== '') {
                    $galleryNew[] = $stored;
                    $createdFiles[] = $IMG_DIR . $stored;
                }
            }
        }

        if ($error === '') {
            $beforeToSave = $newBefore !== '' ? $newBefore : ($product['before_image'] ?? '');
            $afterToSave = $newAfter !== '' ? $newAfter : ($product['after_image'] ?? '');

            mysqli_begin_transaction($conn);
            try {
                $up = mysqli_prepare($conn, 'UPDATE products SET title=?, description=?, before_image=?, after_image=?, status=? WHERE id=?');
                mysqli_stmt_bind_param($up, 'sssssi', $title, $description, $beforeToSave, $afterToSave, $status, $id);
                mysqli_stmt_execute($up);

                $insPrice = mysqli_prepare($conn, 'INSERT INTO product_prices (product_id, price, discount_price, valid_from) VALUES (?, ?, ?, CURDATE())');
                mysqli_stmt_bind_param($insPrice, 'idd', $id, $price, $discount);
                mysqli_stmt_execute($insPrice);

                if (table_exists($conn, 'digital_products')) {
                    $existingStmt = mysqli_prepare($conn, 'SELECT id FROM digital_products WHERE product_id=? LIMIT 1');
                    mysqli_stmt_bind_param($existingStmt, 'i', $id);
                    mysqli_stmt_execute($existingStmt);
                    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));

                    if ($exists) {
                        $upDigital = mysqli_prepare($conn, 'UPDATE digital_products SET drive_link=?, is_active=1 WHERE product_id=?');
                        mysqli_stmt_bind_param($upDigital, 'si', $driveLinkInput, $id);
                        mysqli_stmt_execute($upDigital);
                    } else {
                        $insDigital = mysqli_prepare($conn, 'INSERT INTO digital_products (product_id, drive_link, is_active) VALUES (?, ?, 1)');
                        mysqli_stmt_bind_param($insDigital, 'is', $id, $driveLinkInput);
                        mysqli_stmt_execute($insDigital);
                    }
                }

                if (!empty($galleryNew)) {
                    $insGallery = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'image', ?)");
                    foreach ($galleryNew as $fileName) {
                        mysqli_stmt_bind_param($insGallery, 'is', $id, $fileName);
                        mysqli_stmt_execute($insGallery);
                    }
                }

                mysqli_commit($conn);

                if ($newBefore !== '' && !empty($product['before_image']) && is_file($IMG_DIR . $product['before_image'])) {
                    @unlink($IMG_DIR . $product['before_image']);
                }
                if ($newAfter !== '' && !empty($product['after_image']) && is_file($IMG_DIR . $product['after_image'])) {
                    @unlink($IMG_DIR . $product['after_image']);
                }

                redirect_to('edit.php?id=' . $id);
            } catch (Throwable $t) {
                mysqli_rollback($conn);
                foreach ($createdFiles as $path) {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                $error = 'Update failed. ' . $t->getMessage();
            }
        }
    }
}

$galleryStmt = mysqli_prepare($conn, "SELECT * FROM product_media WHERE product_id=? AND media_type='image' ORDER BY id DESC");
mysqli_stmt_bind_param($galleryStmt, 'i', $id);
mysqli_stmt_execute($galleryStmt);
$galleryRes = mysqli_stmt_get_result($galleryStmt);
$gallery = [];
while ($row = mysqli_fetch_assoc($galleryRes)) {
    $gallery[] = $row;
}

$IMG_BASE = APP_BASE_URL . 'assets/uploads/products/images/';
?>

<h4 class="mb-4">Edit Product</h4>

<?php if ($schemaError !== ''): ?>
  <div class="alert alert-warning"><?= h($schemaError) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data" class="vstack gap-3">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="update">

      <div>
        <label class="form-label">Product Title *</label>
        <input type="text" name="title" class="form-control" value="<?= h($product['title'] ?? '') ?>" required>
      </div>

      <div>
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= h($product['description'] ?? '') ?></textarea>
      </div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Price *</label>
          <input type="number" step="0.01" min="1" name="price" class="form-control" value="<?= h($currentPrice['price'] ?? 0) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Discount Price</label>
          <input type="number" step="0.01" min="0" name="discount_price" class="form-control" value="<?= h($currentPrice['discount_price'] ?? 0) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= ($product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div>
        <label class="form-label">Google Drive Link *</label>
        <input type="url" name="drive_link" class="form-control" required value="<?= h($driveLink) ?>">
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Replace Before Image</label>
          <input type="file" name="before_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($product['before_image'])): ?>
            <img src="<?= $IMG_BASE . h($product['before_image']) ?>" style="margin-top:8px;width:130px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
          <?php endif; ?>
        </div>

        <div class="col-md-6">
          <label class="form-label">Replace After Image</label>
          <input type="file" name="after_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($product['after_image'])): ?>
            <img src="<?= $IMG_BASE . h($product['after_image']) ?>" style="margin-top:8px;width:130px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
          <?php endif; ?>
        </div>
      </div>

      <div>
        <label class="form-label">Add Gallery Images</label>
        <input type="file" name="gallery_images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple>
      </div>

      <div>
        <button class="btn btn-dark">Update Product</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <h6 class="mb-3">Gallery Images</h6>
    <?php if (empty($gallery)): ?>
      <div class="text-muted small">No gallery images uploaded.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($gallery as $img): ?>
          <div class="col-6 col-md-3">
            <div class="border rounded-3 p-2 h-100">
              <img src="<?= $IMG_BASE . h($img['file_name']) ?>" style="width:100%;height:130px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
              <form method="POST" class="mt-2">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete_media">
                <input type="hidden" name="media_id" value="<?= (int)$img['id'] ?>">
                <button class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Delete this image?');">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
