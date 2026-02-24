<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../backend/config/database.php';

$schemaError = '';
if (!column_exists($conn, 'products', 'before_image') || !column_exists($conn, 'products', 'after_image') || !table_exists($conn, 'digital_products')) {
    $schemaError = 'Database migration pending: run database/migrations/2026_02_24_presets_conversion.sql first.';
}

$error = '';

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE name IN ('Preset','LUT') ORDER BY id ASC");

function ensure_dir_secure($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function validate_and_store_image($file, $uploadDir, $prefix, &$errorMsg, $required = true)
{
    $maxSize = 5 * 1024 * 1024;
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $errorMsg = 'Before/After image is required.';
        }
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errorMsg = 'File upload failed.';
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
        $errorMsg = 'Invalid image type.';
        return '';
    }

    $name = $prefix . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    $target = $uploadDir . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errorMsg = 'Failed to move uploaded file.';
        return '';
    }

    return $name;
}

function store_gallery_images($files, $uploadDir, &$errorMsg)
{
    $stored = [];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $stored;
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        $file = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $name = validate_and_store_image($file, $uploadDir, 'gallery', $errorMsg, false);
        if ($name === '') {
            continue;
        }
        $stored[] = $name;
    }

    return $stored;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaError === '') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $discount = (float)($_POST['discount_price'] ?? 0);
    $driveLink = trim($_POST['drive_link'] ?? '');

    if ($error === '' && ($title === '' || $categoryId <= 0 || $price <= 0 || $driveLink === '')) {
        $error = 'Title, category, price and drive link are required.';
    }

    if ($error === '' && !filter_var($driveLink, FILTER_VALIDATE_URL)) {
        $error = 'Drive link must be a valid URL.';
    }

    $root = dirname(__DIR__, 2);
    $imgDir = $root . '/assets/uploads/products/images/';
    ensure_dir_secure($imgDir);

    $createdFiles = [];

    if ($error === '') {
        $before = validate_and_store_image($_FILES['before_image'] ?? null, $imgDir, 'before', $error, true);
        if ($before !== '') {
            $createdFiles[] = $imgDir . $before;
        }
    }

    if ($error === '') {
        $after = validate_and_store_image($_FILES['after_image'] ?? null, $imgDir, 'after', $error, true);
        if ($after !== '') {
            $createdFiles[] = $imgDir . $after;
        }
    }

    if ($error === '') {
        $gallery = store_gallery_images($_FILES['gallery_images'] ?? [], $imgDir, $error);
        foreach ($gallery as $g) {
            $createdFiles[] = $imgDir . $g;
        }

        mysqli_begin_transaction($conn);
        try {
            $insProduct = mysqli_prepare($conn, '
                INSERT INTO products (category_id, title, description, before_image, after_image, status)
                VALUES (?, ?, ?, ?, ?, "active")
            ');
            mysqli_stmt_bind_param($insProduct, 'issss', $categoryId, $title, $description, $before, $after);
            mysqli_stmt_execute($insProduct);
            $productId = (int)mysqli_insert_id($conn);

            $insPrice = mysqli_prepare($conn, '
                INSERT INTO product_prices (product_id, price, discount_price, valid_from)
                VALUES (?, ?, ?, CURDATE())
            ');
            mysqli_stmt_bind_param($insPrice, 'idd', $productId, $price, $discount);
            mysqli_stmt_execute($insPrice);

            $insDigital = mysqli_prepare($conn, '
                INSERT INTO digital_products (product_id, drive_link, is_active)
                VALUES (?, ?, 1)
            ');
            mysqli_stmt_bind_param($insDigital, 'is', $productId, $driveLink);
            mysqli_stmt_execute($insDigital);

            if (!empty($gallery)) {
                $insGallery = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'image', ?)");
                foreach ($gallery as $fileName) {
                    mysqli_stmt_bind_param($insGallery, 'is', $productId, $fileName);
                    mysqli_stmt_execute($insGallery);
                }
            }

            mysqli_commit($conn);
            redirect_to('list.php');
        } catch (Throwable $t) {
            mysqli_rollback($conn);
            foreach ($createdFiles as $filePath) {
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $error = 'Unable to save product. ' . $t->getMessage();
        }
    }
}
?>

<h4 class="mb-4">Add Product</h4>

<?php if ($schemaError !== ''): ?>
  <div class="alert alert-warning"><?= h($schemaError) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data" class="vstack gap-3">
      <?= csrf_input() ?>

      <div>
        <label class="form-label">Product Title *</label>
        <input type="text" name="title" class="form-control" required>
      </div>

      <div>
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
      </div>

      <div>
        <label class="form-label">Category *</label>
        <select name="category_id" class="form-control" required>
          <?php while ($c = mysqli_fetch_assoc($categories)): ?>
            <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Price *</label>
          <input type="number" min="1" step="0.01" name="price" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Discount Price</label>
          <input type="number" min="0" step="0.01" name="discount_price" class="form-control">
        </div>
      </div>

      <div>
        <label class="form-label">Google Drive Link *</label>
        <input type="url" name="drive_link" class="form-control" placeholder="https://drive.google.com/..." required>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Before Image *</label>
          <input type="file" name="before_image" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">After Image *</label>
          <input type="file" name="after_image" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
        </div>
      </div>

      <div>
        <label class="form-label">Gallery Images (optional, multiple)</label>
        <input type="file" name="gallery_images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple>
        <div class="form-text">Max 5MB per image</div>
      </div>

      <div>
        <button class="btn btn-dark">Save Product</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
