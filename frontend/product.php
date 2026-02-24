<?php
require_once __DIR__ . '/includes/config.php';
ensure_session_started();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect_to(BASE_URL);
}

if (!empty($_SESSION['user_id']) && isset($_GET['add_after_login']) && (int)$_GET['add_after_login'] === 1) {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$id] = (int)($_SESSION['cart'][$id] ?? 0) + 1;
    if ($_SESSION['cart'][$id] > 99) {
        $_SESSION['cart'][$id] = 99;
    }
    redirect_to(BASE_URL . 'frontend/cart.php');
}

require_once __DIR__ . '/includes/header.php';

$stmt = mysqli_prepare($conn, 'SELECT * FROM products WHERE id=? AND status=? LIMIT 1');
$status = 'active';
mysqli_stmt_bind_param($stmt, 'is', $id, $status);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);

if (!$product) {
    ?>
    <div class="my-5 text-center">
      <h2 class="lux-title">Product not found</h2>
      <p class="text-muted">This product is not available now.</p>
      <a class="btn btn-dark" href="<?= BASE_URL ?>frontend/category.php?type=presets">Back to Shop</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$stmtP = mysqli_prepare($conn, 'SELECT price, discount_price FROM product_prices WHERE product_id=? ORDER BY id DESC LIMIT 1');
mysqli_stmt_bind_param($stmtP, 'i', $id);
mysqli_stmt_execute($stmtP);
$priceRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP)) ?: ['price' => 0, 'discount_price' => 0];

$price = (float)($priceRow['price'] ?? 0);
$discount = (float)($priceRow['discount_price'] ?? 0);
$finalPrice = $discount > 0 ? $discount : $price;

$galleryStmt = mysqli_prepare($conn, "SELECT id, file_name FROM product_media WHERE product_id=? AND media_type='image' ORDER BY id DESC");
mysqli_stmt_bind_param($galleryStmt, 'i', $id);
mysqli_stmt_execute($galleryStmt);
$galleryRes = mysqli_stmt_get_result($galleryStmt);
$gallery = [];
while ($g = mysqli_fetch_assoc($galleryRes)) {
    $gallery[] = $g;
}

$catName = '';
if (!empty($product['category_id'])) {
    $cid = (int)$product['category_id'];
    $catStmt = mysqli_prepare($conn, 'SELECT name FROM categories WHERE id=? LIMIT 1');
    mysqli_stmt_bind_param($catStmt, 'i', $cid);
    mysqli_stmt_execute($catStmt);
    $catName = (string)(mysqli_fetch_assoc(mysqli_stmt_get_result($catStmt))['name'] ?? '');
}

$IMG_BASE = BASE_URL . 'assets/uploads/products/images/';
$beforeImage = $product['before_image'] ?? '';
$afterImage = $product['after_image'] ?? '';
$isLoggedIn = !empty($_SESSION['user_id']);
$loginReturn = BASE_URL . 'frontend/product.php?id=' . $id . '&add_after_login=1';
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
  .lux-pill{ border:1px solid #111; border-radius:999px; padding:6px 10px; font-size:12px; }

  .ba-wrap{ position:relative; width:100%; aspect-ratio:4/3; overflow:hidden; border-radius:16px; background:#ece5dc; }
  .ba-wrap img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; pointer-events:none; }
  .ba-after-wrap{ position:absolute; inset:0 auto 0 0; width:50%; overflow:hidden; border-right:2px solid rgba(255,255,255,.9); }
  .ba-slider{ position:absolute; left:16px; right:16px; bottom:12px; z-index:5; width:calc(100% - 32px); }
  .ba-label{ position:absolute; top:12px; font-size:11px; letter-spacing:.4px; background:#111; color:#fff; padding:2px 8px; border-radius:999px; z-index:5; }
  .ba-label.before{ left:12px; }
  .ba-label.after{ right:12px; }

  .gallery-thumb{ width:88px; height:88px; object-fit:cover; border-radius:10px; border:1px solid #e6dfd6; cursor:pointer; }
  .gallery-thumb.active{ outline:2px solid #111; }
</style>

<div class="mb-3 small lux-soft">
  <a href="<?= BASE_URL ?>" class="text-decoration-none lux-soft">Home</a>
  <span class="mx-1">/</span>
  <a href="<?= BASE_URL ?>frontend/category.php?type=<?= strtolower($catName) === 'lut' ? 'luts' : 'presets' ?>" class="text-decoration-none lux-soft">
    <?= h($catName ?: 'Shop') ?>
  </a>
  <span class="mx-1">/</span>
  <span class="text-dark"><?= h($product['title']) ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <?php if ($beforeImage !== '' && $afterImage !== ''): ?>
      <div class="ba-wrap mb-3" data-compare>
        <span class="ba-label before">Before</span>
        <span class="ba-label after">After</span>
        <img id="beforePreview" src="<?= $IMG_BASE . h($beforeImage) ?>" alt="Before">
        <div class="ba-after-wrap" id="afterWrap"><img id="afterPreview" src="<?= $IMG_BASE . h($afterImage) ?>" alt="After"></div>
        <input type="range" class="ba-slider" id="compareSlider" min="0" max="100" value="50">
      </div>
    <?php else: ?>
      <div class="lux-box p-5 text-center lux-soft mb-3">Before/After images not uploaded yet.</div>
    <?php endif; ?>

    <?php if (!empty($gallery)): ?>
      <div class="lux-box p-3">
        <div class="fw-semibold mb-2">Gallery</div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($gallery as $idx => $img): ?>
            <img src="<?= $IMG_BASE . h($img['file_name']) ?>" class="gallery-thumb <?= $idx === 0 ? 'active' : '' ?>" data-gallery-thumb>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="lux-box p-4">
      <h2 class="lux-title mb-2"><?= h($product['title']) ?></h2>
      <?php if ($catName !== ''): ?>
        <div class="small lux-soft mb-2">Category: <strong><?= h($catName) ?></strong></div>
      <?php endif; ?>

      <div class="mt-2">
        <?php if ($discount > 0): ?>
          <span class="text-muted" style="text-decoration:line-through;">&#8377;<?= number_format($price, 0) ?></span>
          <span class="fs-4 fw-bold ms-2">&#8377;<?= number_format($discount, 0) ?></span>
          <span class="badge bg-success ms-2">Discount</span>
        <?php else: ?>
          <span class="fs-4 fw-bold">&#8377;<?= number_format($finalPrice, 0) ?></span>
        <?php endif; ?>
      </div>

      <p class="lux-soft mt-3 mb-0"><?= nl2br(h($product['description'] ?? 'No description added yet.')) ?></p>

      <hr class="my-4">

      <?php if ($isLoggedIn): ?>
        <form method="POST" action="<?= BASE_URL ?>frontend/cart.php" class="d-grid gap-2">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$id ?>">
          <button class="btn btn-dark w-100">Add to Cart</button>
          <a class="btn btn-outline-dark w-100" href="<?= BASE_URL ?>frontend/checkout.php?product_id=<?= (int)$id ?>">Buy Now</a>
        </form>
      <?php else: ?>
        <a class="btn btn-dark w-100" href="<?= BASE_URL ?>frontend/auth/index.php?redirect=<?= urlencode($loginReturn) ?>">Login to Add to Cart</a>
      <?php endif; ?>

      <div class="small lux-soft mt-3">Download link payment success ke baad WhatsApp pe send hoga.</div>
    </div>

    <div class="lux-box p-3 mt-3">
      <div class="d-flex flex-wrap gap-2">
        <span class="lux-pill">Secure Delivery</span>
        <span class="lux-pill">Instant Access</span>
        <span class="lux-pill">Support on WhatsApp</span>
      </div>
    </div>
  </div>
</div>

<?php
$related = mysqli_query($conn, "
  SELECT p.id, p.title, p.before_image, p.after_image,
    (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price,
    (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price
  FROM products p
  WHERE p.status='active'
    AND p.category_id=" . (int)$product['category_id'] . "
    AND p.id <> " . (int)$id . "
  ORDER BY p.id DESC
  LIMIT 4
");
?>

<div class="lux-box p-3 mt-4">
  <div class="fw-semibold mb-3">Related Products</div>
  <div class="row g-3">
    <?php if ($related && mysqli_num_rows($related) > 0): ?>
      <?php while ($r = mysqli_fetch_assoc($related)): ?>
        <?php $rFinal = ((float)$r['discount_price'] > 0) ? (float)$r['discount_price'] : (float)$r['price']; ?>
        <div class="col-6 col-md-3">
          <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$r['id'] ?>">
            <div class="border rounded-3 p-2 h-100" style="border-color:#e6dfd6!important;">
              <?php if (!empty($r['after_image'])): ?>
                <img src="<?= $IMG_BASE . h($r['after_image']) ?>" style="width:100%; height:120px; object-fit:cover; border-radius:10px; border:1px solid #e6dfd6;">
              <?php else: ?>
                <div class="text-center lux-soft" style="height:120px; display:flex; align-items:center; justify-content:center;">No Image</div>
              <?php endif; ?>

              <div class="mt-2 small fw-semibold text-truncate"><?= h($r['title']) ?></div>
              <div class="small lux-soft">&#8377;<?= number_format($rFinal, 0) ?></div>
            </div>
          </a>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12"><div class="small text-muted">No related products yet.</div></div>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  var slider = document.getElementById('compareSlider');
  var afterWrap = document.getElementById('afterWrap');
  if (slider && afterWrap) {
    var apply = function(){ afterWrap.style.width = slider.value + '%'; };
    slider.addEventListener('input', apply);
    apply();
  }

  var thumbs = document.querySelectorAll('[data-gallery-thumb]');
  var beforePreview = document.getElementById('beforePreview');
  var afterPreview = document.getElementById('afterPreview');
  if (thumbs.length && beforePreview && afterPreview) {
    thumbs.forEach(function(t){
      t.addEventListener('click', function(){
        thumbs.forEach(function(x){ x.classList.remove('active'); });
        t.classList.add('active');

        // For gallery click, show selected image as AFTER frame while keeping before fixed.
        afterPreview.src = t.src;
      });
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
