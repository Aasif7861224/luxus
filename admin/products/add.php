<?php
require_once "../includes/header.php";
require_once "../../backend/config/database.php";

$error = "";

// categories (Preset, LUT etc.) - excluding Service
$categories = mysqli_query($conn, "SELECT * FROM categories WHERE name != 'Service' ORDER BY id ASC");

function ensureDir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function uploadMultipleFiles($files, $uploadDir, $allowedExts, $prefix) {
    $storedNames = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $storedNames;
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);

    $price       = (float)($_POST['price'] ?? 0);
    $discount    = (float)($_POST['discount_price'] ?? 0);

    if ($title === "" || $category_id <= 0 || $price <= 0) {
        $error = "Please fill required fields (Title, Category, Price).";
    }

    if ($error === "") {

        // 1) Insert product
        $stmt = mysqli_prepare($conn, "
            INSERT INTO products (category_id, title, description, status)
            VALUES (?, ?, ?, 'active')
        ");
        mysqli_stmt_bind_param($stmt, "iss", $category_id, $title, $description);
        mysqli_stmt_execute($stmt);

        $product_id = mysqli_insert_id($conn);

        // 2) Insert price history
        $stmt2 = mysqli_prepare($conn, "
            INSERT INTO product_prices (product_id, price, discount_price, valid_from)
            VALUES (?, ?, ?, CURDATE())
        ");
        mysqli_stmt_bind_param($stmt2, "idd", $product_id, $price, $discount);
        mysqli_stmt_execute($stmt2);

        // 3) Upload folders
        $root = dirname(__DIR__, 2); // points to /LUXUS/
        $imgDir = $root . "/assets/uploads/products/images/";
        $vidDir = $root . "/assets/uploads/products/videos/";
        ensureDir($imgDir);
        ensureDir($vidDir);

        // 4) Upload multiple images
        $imageNames = uploadMultipleFiles(
            $_FILES['images'] ?? [],
            $imgDir,
            ['jpg','jpeg','png','webp'],
            "img"
        );

        // 5) Upload multiple videos
        $videoNames = uploadMultipleFiles(
            $_FILES['videos'] ?? [],
            $vidDir,
            ['mp4','webm','mov'],
            "vid"
        );

        // 6) Insert into product_media
        if (!empty($imageNames)) {
            $ins = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'image', ?)");
            foreach ($imageNames as $img) {
                mysqli_stmt_bind_param($ins, "is", $product_id, $img);
                mysqli_stmt_execute($ins);
            }
        }

        if (!empty($videoNames)) {
            $ins2 = mysqli_prepare($conn, "INSERT INTO product_media (product_id, media_type, file_name) VALUES (?, 'video', ?)");
            foreach ($videoNames as $vid) {
                mysqli_stmt_bind_param($ins2, "is", $product_id, $vid);
                mysqli_stmt_execute($ins2);
            }
        }

        header("Location: list.php");
        exit;
    }
}
?>

<h4 class="mb-4">Add Product</h4>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label">Product Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Write product details..."></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select name="category_id" class="form-control" required>
                    <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price *</label>
                    <input type="number" name="price" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Discount Price</label>
                    <input type="number" name="discount_price" class="form-control">
                </div>
            </div>

            <hr class="my-4">

            <!-- MULTIPLE IMAGES -->
            <div class="mb-3">
                <label class="form-label">Upload Images (multiple)</label>
                <input type="file" name="images[]" id="imagesInput" class="form-control"
                       accept=".jpg,.jpeg,.png,.webp" multiple>

                <div class="mt-3 d-flex flex-wrap gap-2" id="imagesPreview"></div>
                <div class="form-text">Allowed: JPG/JPEG/PNG/WEBP</div>
            </div>

            <!-- MULTIPLE VIDEOS -->
            <div class="mb-3">
                <label class="form-label">Upload Videos (multiple)</label>
                <input type="file" name="videos[]" id="videosInput" class="form-control"
                       accept=".mp4,.webm,.mov" multiple>

                <div class="mt-3 d-flex flex-wrap gap-2" id="videosPreview"></div>
                <div class="form-text">Allowed: MP4/WEBM/MOV</div>
            </div>

            <button class="btn btn-dark">Save Product</button>
            <a href="list.php" class="btn btn-secondary">Back</a>

        </form>
    </div>
</div>

<script>
/* Image previews */
document.getElementById('imagesInput')?.addEventListener('change', function (e) {
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

/* Video previews */
document.getElementById('videosInput')?.addEventListener('change', function (e) {
    const wrap = document.getElementById('videosPreview');
    wrap.innerHTML = "";

    const files = Array.from(e.target.files || []);
    files.forEach(file => {
        const url = URL.createObjectURL(file);

        const video = document.createElement('video');
        video.src = url;
        video.controls = true;
        video.muted = true;
        video.style.width = "220px";
        video.style.height = "140px";
        video.style.objectFit = "cover";
        video.style.borderRadius = "10px";
        video.style.border = "1px solid #ddd";

        wrap.appendChild(video);
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
