<?php
require_once "../includes/header.php";
require_once "../../backend/config/database.php";

function ensureDir($path) {
    if (!is_dir($path)) mkdir($path, 0777, true);
}

function uploadMultipleFiles($files, $uploadDir, $allowedExts, $prefix) {
    $storedNames = [];

    if (!isset($files['name']) || !is_array($files['name'])) return $storedNames;

    for ($i = 0; $i < count($files['name']); $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

        $original = $files['name'][$i];
        $tmpName  = $files['tmp_name'][$i];

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) continue;

        $newName = $prefix . "_" . time() . "_" . rand(1000,9999) . "." . $ext;
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($tmpName, $target)) {
            $storedNames[] = $newName;
        }
    }
    return $storedNames;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php"); exit; }

// Paths
$ROOT = dirname(__DIR__, 2); // /LUXUS
$IMG_DIR = $ROOT . "/assets/uploads/products/images/";
$VID_DIR = $ROOT . "/assets/uploads/products/videos/";
ensureDir($IMG_DIR);
ensureDir($VID_DIR);

// Browser paths (for preview in HTML)
$IMG_BASE = "/LUXUS/assets/uploads/products/images/";
$VID_BASE = "/LUXUS/assets/uploads/products/videos/";

// ---------------------------
// Handle delete media
// ---------------------------
if (isset($_GET['delete_media'])) {
    $media_id = (int)$_GET['delete_media'];

    // fetch media info
    $st = mysqli_prepare($conn, "SELECT id, media_type, file_name FROM product_media WHERE id=? AND product_id=? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $media_id, $id);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $media = mysqli_fetch_assoc($rs);

    if ($media) {
        // delete file
        $filePath = ($media['media_type'] === 'image') ? ($IMG_DIR . $media['file_name']) : ($VID_DIR . $media['file_name']);
        if (file_exists($filePath)) @unlink($filePath);

        // delete db row
        $del = mysqli_prepare($conn, "DELETE FROM product_media WHERE id=? AND product_id=?");
        mysqli_stmt_bind_param($del, "ii", $media_id, $id);
        mysqli_stmt_execute($del);
    }

    header("Location: edit.php?id=" . $id);
    exit;
}

// ---------------------------
// Fetch product
// ---------------------------
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);

if (!$product) { header("Location: list.php"); exit; }

// Latest price
$stmtP = mysqli_prepare($conn, "SELECT * FROM product_prices WHERE product_id=? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmtP, "i", $id);
mysqli_stmt_execute($stmtP);
$resP = mysqli_stmt_get_result($stmtP);
$currentPrice = mysqli_fetch_assoc($resP);

// Existing media
$mediaStmt = mysqli_prepare($conn, "SELECT * FROM product_media WHERE product_id=? ORDER BY id DESC");
mysqli_stmt_bind_param($mediaStmt, "i", $id);
mysqli_stmt_execute($mediaStmt);
$mediaRes = mysqli_stmt_get_result($mediaStmt);

$images = [];
$videos = [];
while ($m = mysqli_fetch_assoc($mediaRes)) {
    if ($m['media_type'] === 'image') $images[] = $m;
    if ($m['media_type'] === 'video') $videos[] = $m;
}

$error = "";

// ---------------------------
// Handle update + add new media
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'active';

    $price       = (float)($_POST['price'] ?? 0);
    $discount    = (float)($_POST['discount_price'] ?? 0);

    if ($title === "" || $price <= 0) {
        $error = "Title and Price are required.";
    }

    if ($error === "") {

        // Update product
        $up = mysqli_prepare($conn, "UPDATE products SET title=?, description=?, status=? WHERE id=?");
        mysqli_stmt_bind_param($up, "sssi", $title, $description, $status, $id);
        mysqli_stmt_execute($up);

        // Insert new price history row
        $insPrice = mysqli_prepare($conn, "
            INSERT INTO product_prices (product_id, price, discount_price, valid_from)
            VALUES (?, ?, ?, CURDATE())
        ");
        mysqli_stmt_bind_param($insPrice, "idd", $id, $price, $discount);
        mysqli_stmt_execute($insPrice);

        // Upload new images/videos
        $newImages = uploadMultipleFiles($_FILES['images'] ?? [], $IMG_DIR, ['jpg','jpeg','png','webp'], "img");
        $newVideos = uploadMultipleFiles($_FILES['videos'] ?? [], $VID_DIR, ['mp4','webm','mov'], "vid");

        // Insert media rows
        if (!empty($newImages)) {
            $insM = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'image', ?)");
            foreach ($newImages as $img) {
                mysqli_stmt_bind_param($insM, "is", $id, $img);
                mysqli_stmt_execute($insM);
            }
        }

        if (!empty($newVideos)) {
            $insV = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'video', ?)");
            foreach ($newVideos as $vid) {
                mysqli_stmt_bind_param($insV, "is", $id, $vid);
                mysqli_stmt_execute($insV);
            }
        }

        header("Location: edit.php?id=" . $id);
        exit;
    }
}
?>

<h4 class="mb-4">Edit Product</h4>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label">Product Title *</label>
                <input type="text" name="title" class="form-control"
                       value="<?= htmlspecialchars($product['title'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"
                          placeholder="Write product details..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= ($product['status']=='active')?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= ($product['status']=='inactive')?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price *</label>
                    <input type="number" name="price" class="form-control"
                           value="<?= htmlspecialchars($currentPrice['price'] ?? 0) ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Discount Price</label>
                    <input type="number" name="discount_price" class="form-control"
                           value="<?= htmlspecialchars($currentPrice['discount_price'] ?? 0) ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label">Add More Images (multiple)</label>
                    <input type="file" name="images[]" id="imagesInput" class="form-control"
                           accept=".jpg,.jpeg,.png,.webp" multiple>
                    <div class="mt-2 d-flex flex-wrap gap-2" id="imagesPreview"></div>
                </div>

                <div class="col-lg-6">
                    <label class="form-label">Add More Videos (multiple)</label>
                    <input type="file" name="videos[]" id="videosInput" class="form-control"
                           accept=".mp4,.webm,.mov" multiple>
                    <div class="mt-2 d-flex flex-wrap gap-2" id="videosPreview"></div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-dark">Update</button>
                <a href="list.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

<!-- Existing Gallery -->
<div class="row g-4">
    <div class="col-12">
        <h5 class="mb-2">Existing Gallery</h5>
        <p class="text-muted small mb-3">Images and videos already uploaded for this product.</p>
    </div>

    <!-- Images -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Images</h6>

                <?php if (empty($images)): ?>
                    <div class="text-muted small">No images uploaded.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($images as $img): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="border rounded-3 p-2 h-100">
                                    <img
                                        src="<?= $IMG_BASE . htmlspecialchars($img['file_name']) ?>"
                                        alt="image"
                                        style="width:100%; height:160px; object-fit:cover; border-radius:10px; border:1px solid #ddd;"
                                    >
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="small text-muted text-truncate" style="max-width:140px;">
                                            <?= htmlspecialchars($img['file_name']) ?>
                                        </span>
                                        <a class="btn btn-sm btn-outline-danger"
                                           href="edit.php?id=<?= $id ?>&delete_media=<?= (int)$img['id'] ?>"
                                           onclick="return confirm('Delete this image?');">
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Videos -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Videos</h6>

                <?php if (empty($videos)): ?>
                    <div class="text-muted small">No videos uploaded.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($videos as $vid): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="border rounded-3 p-2 h-100">
                                    <video
                                        src="<?= $VID_BASE . htmlspecialchars($vid['file_name']) ?>"
                                        controls
                                        style="width:100%; height:200px; object-fit:cover; border-radius:10px; border:1px solid #ddd;">
                                    </video>

                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="small text-muted text-truncate" style="max-width:180px;">
                                            <?= htmlspecialchars($vid['file_name']) ?>
                                        </span>
                                        <a class="btn btn-sm btn-outline-danger"
                                           href="edit.php?id=<?= $id ?>&delete_media=<?= (int)$vid['id'] ?>"
                                           onclick="return confirm('Delete this video?');">
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
// Images preview
document.getElementById('imagesInput')?.addEventListener('change', function(e){
    const wrap = document.getElementById('imagesPreview');
    wrap.innerHTML = "";
    const files = Array.from(e.target.files || []);
    files.forEach(file => {
        const url = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.src = url;
        img.style.width = "110px";
        img.style.height = "110px";
        img.style.objectFit = "cover";
        img.style.borderRadius = "10px";
        img.style.border = "1px solid #ddd";
        wrap.appendChild(img);
    });
});

// Videos preview
document.getElementById('videosInput')?.addEventListener('change', function(e){
    const wrap = document.getElementById('videosPreview');
    wrap.innerHTML = "";
    const files = Array.from(e.target.files || []);
    files.forEach(file => {
        const url = URL.createObjectURL(file);
        const v = document.createElement('video');
        v.src = url;
        v.controls = true;
        v.muted = true;
        v.style.width = "220px";
        v.style.height = "140px";
        v.style.objectFit = "cover";
        v.style.borderRadius = "10px";
        v.style.border = "1px solid #ddd";
        wrap.appendChild(v);
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
