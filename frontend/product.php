<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . BASE_URL);
    exit;
}

// --- Fetch product basic info
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);

if (!$product) {
    // show nice not found
    ?>
    <div class="my-5 text-center">
        <h2 class="lux-title">Product not found</h2>
        <p class="text-muted">This product is not available.</p>
        <a class="btn btn-dark" href="<?= BASE_URL ?>frontend/category.php?type=presets">Back to Shop</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// --- Latest price row
$stmtP = mysqli_prepare($conn, "SELECT price, discount_price FROM product_prices WHERE product_id=? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmtP, "i", $id);
mysqli_stmt_execute($stmtP);
$resP = mysqli_stmt_get_result($stmtP);
$priceRow = mysqli_fetch_assoc($resP);

$price = (float)($priceRow['price'] ?? 0);
$discount = (float)($priceRow['discount_price'] ?? 0);
$finalPrice = ($discount > 0) ? $discount : $price;

// --- Media (images + videos)
$mediaStmt = mysqli_prepare($conn, "SELECT id, media_type, file_name FROM product_media WHERE product_id=? ORDER BY id DESC");
mysqli_stmt_bind_param($mediaStmt, "i", $id);
mysqli_stmt_execute($mediaStmt);
$mediaRes = mysqli_stmt_get_result($mediaStmt);

$images = [];
$videos = [];
while ($m = mysqli_fetch_assoc($mediaRes)) {
    if ($m['media_type'] === 'image') $images[] = $m;
    if ($m['media_type'] === 'video') $videos[] = $m;
}

// Bases (same as admin upload folders)
$IMG_BASE = BASE_URL . "assets/uploads/products/images/";
$VID_BASE = BASE_URL . "assets/uploads/products/videos/";

// --- For breadcrumb/category display (optional)
$catName = "";
if (!empty($product['category_id'])) {
    $cid = (int)$product['category_id'];
    $cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM categories WHERE id=$cid LIMIT 1"));
    $catName = $cat['name'] ?? "";
}
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
  .lux-thumb-mini{ width:70px; height:70px; object-fit:cover; border-radius:12px; border:1px solid #e6dfd6; cursor:pointer; }
  .lux-thumb-mini.active{ outline:2px solid #111; }
  .lux-hero-media{
    width:100%;
    border-radius:16px;
    border:1px solid #e6dfd6;
    background:#fff;
    overflow:hidden;
  }
  .lux-hero-media img, .lux-hero-media video{
    width:100%;
    height:520px;
    object-fit:cover;
    display:block;
  }
  @media (max-width: 991px){
    .lux-hero-media img, .lux-hero-media video{ height:380px; }
  }
  @media (max-width: 575px){
    .lux-hero-media img, .lux-hero-media video{ height:300px; }
  }
  .lux-price del{ color:#7a7a7a; margin-right:10px; }
  .lux-btn-wide{ width:100%; padding:12px 14px; border-radius:12px; }
  .lux-pill{ border:1px solid #111; border-radius:999px; padding:6px 10px; font-size:12px; }
  .lux-section{ margin-top:28px; }
</style>

<!-- Breadcrumb -->
<div class="mb-3 small lux-soft">
  <a href="<?= BASE_URL ?>" class="text-decoration-none lux-soft">Home</a>
  <span class="mx-1">/</span>
  <a href="<?= BASE_URL ?>frontend/category.php?type=<?= (strtolower($catName)==='lut'?'luts':'presets') ?>" class="text-decoration-none lux-soft">
    <?= htmlspecialchars($catName ?: "Shop") ?>
  </a>
  <span class="mx-1">/</span>
  <span class="text-dark"><?= htmlspecialchars($product['title']) ?></span>
</div>

<div class="row g-4">

  <!-- LEFT: GALLERY -->
  <div class="col-lg-7">

    <?php
      // Choose default hero media: first image if exists, else first video
      $defaultType = 'image';
      $defaultSrc = '';
      if (!empty($images)) {
          $defaultType = 'image';
          $defaultSrc = $IMG_BASE . $images[0]['file_name'];
      } elseif (!empty($videos)) {
          $defaultType = 'video';
          $defaultSrc = $VID_BASE . $videos[0]['file_name'];
      }
    ?>

    <div class="lux-hero-media mb-3" id="heroBox">
      <?php if ($defaultSrc): ?>
        <?php if ($defaultType === 'image'): ?>
          <img id="heroMedia" src="<?= htmlspecialchars($defaultSrc) ?>" alt="product">
        <?php else: ?>
          <video id="heroMedia" src="<?= htmlspecialchars($defaultSrc) ?>" controls playsinline></video>
        <?php endif; ?>
      <?php else: ?>
        <div class="p-5 text-center lux-soft">No media uploaded for this product.</div>
      <?php endif; ?>
    </div>

    <!-- Thumbnails (Images + Videos) -->
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($images as $idx => $img): ?>
        <img
          class="lux-thumb-mini <?= $idx===0 && $defaultType==='image' ? 'active':'' ?>"
          src="<?= $IMG_BASE . htmlspecialchars($img['file_name']) ?>"
          data-type="image"
          data-src="<?= $IMG_BASE . htmlspecialchars($img['file_name']) ?>"
          alt="thumb">
      <?php endforeach; ?>

      <?php foreach ($videos as $v): ?>
        <div
          class="lux-thumb-mini d-flex align-items-center justify-content-center bg-dark text-white"
          style="position:relative;"
          data-type="video"
          data-src="<?= $VID_BASE . htmlspecialchars($v['file_name']) ?>"
          title="Video">
          ▶
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Extra info / trust row -->
    <div class="lux-section">
      <div class="lux-box p-3">
        <div class="d-flex flex-wrap gap-2">
          <span class="lux-pill">Instant Access</span>
          <span class="lux-pill">High Quality</span>
          <span class="lux-pill">Support on WhatsApp</span>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: DETAILS -->
  <div class="col-lg-5">
    <div class="lux-box p-4">
      <h2 class="lux-title mb-2"><?= htmlspecialchars($product['title']) ?></h2>
      <?php if ($catName): ?>
        <div class="small lux-soft mb-2">Category: <strong><?= htmlspecialchars($catName) ?></strong></div>
      <?php endif; ?>

      <div class="mt-2 lux-price">
        <?php if ($discount > 0): ?>
          <del>₹<?= number_format($price) ?></del>
          <span class="fs-4 fw-bold">₹<?= number_format($discount) ?></span>
          <span class="badge bg-success ms-2">Discount</span>
        <?php else: ?>
          <span class="fs-4 fw-bold">₹<?= number_format($finalPrice) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($product['description'])): ?>
        <p class="lux-soft mt-3 mb-0"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
      <?php else: ?>
        <p class="lux-soft mt-3 mb-0">No description added yet.</p>
      <?php endif; ?>

      <hr class="my-4">

      <!-- Buy actions -->
      <div class="d-grid gap-2">
        <a class="btn btn-dark lux-btn-wide"
           href="<?= BASE_URL ?>frontend/checkout.php?product_id=<?= $id ?>">
          Buy Now
        </a>

        <a class="btn btn-outline-dark lux-btn-wide"
           href="<?= BASE_URL ?>frontend/cart.php?add=<?= $id ?>">
          Add to Cart
        </a>

        <?php
          // WhatsApp click-to-chat fallback (automation later)
          $waText = "Hello LuxLut! I want to buy: " . ($product['title'] ?? '') . " | Price: ₹" . number_format($finalPrice) . " | Product ID: " . $id;
          $waLink = "https://wa.me/9028795006?text=" . urlencode($waText);
        ?>
        <a class="btn btn-outline-secondary lux-btn-wide" target="_blank" href="<?= $waLink ?>">
          WhatsApp Order (MVP)
        </a>
      </div>

      <div class="small lux-soft mt-3">
        Note: Login will be required only at checkout (Google login), as you wanted.
      </div>
    </div>

    <!-- Related products (same category) -->
    <div class="lux-section lux-box p-3">
      <div class="fw-semibold mb-2">Related Products</div>
      <div class="row g-3">
        <?php
          $related = mysqli_query($conn, "
            SELECT p.id, p.title,
              (SELECT pm.file_name FROM product_media pm WHERE pm.product_id=p.id AND pm.media_type='image' ORDER BY pm.id DESC LIMIT 1) AS img,
              (SELECT price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS price,
              (SELECT discount_price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS discount
            FROM products p
            WHERE p.status='active'
              AND p.category_id=" . (int)$product['category_id'] . "
              AND p.id <> $id
            ORDER BY p.id DESC
            LIMIT 4
          ");
          while($r = mysqli_fetch_assoc($related)):
            $rFinal = ((float)($r['discount'] ?? 0) > 0) ? (float)$r['discount'] : (float)($r['price'] ?? 0);
        ?>
          <div class="col-6">
            <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$r['id'] ?>">
              <div class="border rounded-3 p-2 h-100" style="border-color:#e6dfd6!important;">
                <?php if (!empty($r['img'])): ?>
                  <img src="<?= $IMG_BASE . htmlspecialchars($r['img']) ?>" style="width:100%; height:120px; object-fit:cover; border-radius:10px; border:1px solid #e6dfd6;">
                <?php else: ?>
                  <div class="text-center lux-soft" style="height:120px; display:flex; align-items:center; justify-content:center;">No Image</div>
                <?php endif; ?>

                <div class="mt-2 small fw-semibold text-truncate"><?= htmlspecialchars($r['title']) ?></div>
                <div class="small lux-soft">₹<?= number_format($rFinal) ?></div>
              </div>
            </a>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

  </div>
</div>

<script>
// Gallery switching (images + videos)
(function(){
  const thumbs = document.querySelectorAll('.lux-thumb-mini');
  if (!thumbs.length) return;

  function setActive(el){
    thumbs.forEach(t => t.classList.remove('active'));
    if (el && el.classList) el.classList.add('active');
  }

  function render(type, src){
    const heroBox = document.getElementById('heroBox');
    heroBox.innerHTML = '';

    if(type === 'video'){
      const v = document.createElement('video');
      v.src = src;
      v.controls = true;
      v.playsInline = true;
      v.style.width = '100%';
      v.style.height = '520px';
      v.style.objectFit = 'cover';
      v.style.display = 'block';
      v.style.borderRadius = '16px';
      v.style.border = '1px solid #e6dfd6';
      heroBox.appendChild(v);
    } else {
      const img = document.createElement('img');
      img.src = src;
      img.alt = 'product';
      img.style.width = '100%';
      img.style.height = '520px';
      img.style.objectFit = 'cover';
      img.style.display = 'block';
      img.style.borderRadius = '16px';
      img.style.border = '1px solid #e6dfd6';
      heroBox.appendChild(img);
    }
  }

  thumbs.forEach(t => {
    t.addEventListener('click', () => {
      const type = t.getAttribute('data-type');
      const src = t.getAttribute('data-src');
      setActive(t);
      render(type, src);
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>