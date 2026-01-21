<?php
require_once __DIR__ . '/includes/header.php';

// 1) Find category IDs for Preset & LUT (from your categories table)
$catPreset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='Preset' LIMIT 1"));
$catLut    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='LUT' LIMIT 1"));

$presetId = (int)($catPreset['id'] ?? 0);
$lutId    = (int)($catLut['id'] ?? 0);

// 2) HERO: pick latest active product with at least 1 image
$heroQ = mysqli_query($conn, "
  SELECT p.id, p.title,
    (SELECT pm.file_name FROM product_media pm WHERE pm.product_id=p.id AND pm.media_type='image' ORDER BY pm.id DESC LIMIT 1) AS hero_image,
    (SELECT price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS price,
    (SELECT discount_price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS discount
  FROM products p
  WHERE p.status='active'
  ORDER BY p.id DESC
  LIMIT 1
");
$hero = mysqli_fetch_assoc($heroQ);

// 3) Featured Presets (4)
$presetsQ = mysqli_query($conn, "
  SELECT p.id, p.title,
    (SELECT pm.file_name FROM product_media pm WHERE pm.product_id=p.id AND pm.media_type='image' ORDER BY pm.id DESC LIMIT 1) AS img,
    (SELECT price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS price,
    (SELECT discount_price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS discount
  FROM products p
  WHERE p.status='active' AND p.category_id = $presetId
  ORDER BY p.id DESC
  LIMIT 4
");

// 4) Featured LUTs (4)
$lutsQ = mysqli_query($conn, "
  SELECT p.id, p.title,
    (SELECT pm.file_name FROM product_media pm WHERE pm.product_id=p.id AND pm.media_type='image' ORDER BY pm.id DESC LIMIT 1) AS img,
    (SELECT price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS price,
    (SELECT discount_price FROM product_prices WHERE product_id=p.id ORDER BY id DESC LIMIT 1) AS discount
  FROM products p
  WHERE p.status='active' AND p.category_id = $lutId
  ORDER BY p.id DESC
  LIMIT 4
");

// Image base (same folders as admin uploads)
$IMG_BASE = BASE_URL . "assets/uploads/products/images/";
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-hero-card{ border:1px solid #e6dfd6; background:#fff; }
  .lux-card{ border:1px solid #e6dfd6; background:#fff; transition:transform .15s ease; }
  .lux-card:hover{ transform: translateY(-2px); }
  .lux-thumb{ width:100%; aspect-ratio: 1/1; object-fit:cover; border-bottom:1px solid #e6dfd6; }
  .lux-price del{ color:#7a7a7a; margin-right:8px; }
  .lux-bar-btn{ background:#5b5b5b; color:#fff; border:0; width:100%; padding:10px 12px; }
  .lux-bar-btn:hover{ background:#444; }
</style>

<!-- HERO -->
<div class="row g-4 align-items-center mb-5">
  <div class="col-lg-6">
    <h1 class="lux-title display-5 mb-3"><?= htmlspecialchars($hero['title'] ?? 'LuxLut Presets & LUTs') ?></h1>
    <p class="text-muted mb-4">
      Browse premium presets and LUTs crafted for wedding photographers and editors. Clean, modern, and responsive on every device.
    </p>

    <?php if (!empty($hero['id'])): ?>
      <a class="btn btn-dark px-4" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$hero['id'] ?>">Shop Now</a>
      <a class="btn btn-outline-dark px-4 ms-2" href="<?= BASE_URL ?>frontend/category.php?type=presets">Explore Presets</a>
    <?php else: ?>
      <a class="btn btn-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=presets">Explore</a>
    <?php endif; ?>
  </div>

  <div class="col-lg-6">
    <div class="lux-hero-card p-3 rounded-3">
      <?php if (!empty($hero['hero_image'])): ?>
        <img src="<?= $IMG_BASE . htmlspecialchars($hero['hero_image']) ?>" class="img-fluid rounded-3" alt="hero">
      <?php else: ?>
        <div class="text-muted text-center py-5">No product image found. Please upload product images from Admin.</div>
      <?php endif; ?>

      <?php if (!empty($hero['id'])): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="lux-price small">
            <?php if (!empty($hero['discount']) && $hero['discount'] > 0): ?>
              <del>₹<?= number_format((float)$hero['price']) ?></del>
              <strong>₹<?= number_format((float)$hero['discount']) ?></strong>
            <?php else: ?>
              <strong>₹<?= number_format((float)($hero['price'] ?? 0)) ?></strong>
            <?php endif; ?>
          </div>
          <a class="btn btn-sm btn-dark" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$hero['id'] ?>">View</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- PRESETS -->
<div class="text-center mb-4">
  <h2 class="lux-title">PRESETS</h2>
  <div class="text-muted">Premium wedding presets for fast & consistent edits</div>
</div>

<div class="row g-4 mb-5">
  <?php while($p = mysqli_fetch_assoc($presetsQ)): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="lux-card h-100">
        <?php if (!empty($p['img'])): ?>
          <img class="lux-thumb" src="<?= $IMG_BASE . htmlspecialchars($p['img']) ?>" alt="preset">
        <?php else: ?>
          <div class="p-4 text-center text-muted">No Image</div>
        <?php endif; ?>

        <div class="p-3 text-center">
          <div class="fw-semibold"><?= htmlspecialchars($p['title']) ?></div>

          <div class="lux-price small mt-1">
            <?php if (!empty($p['discount']) && $p['discount'] > 0): ?>
              <del>₹<?= number_format((float)$p['price']) ?></del>
              <strong>₹<?= number_format((float)$p['discount']) ?></strong>
            <?php else: ?>
              <strong>₹<?= number_format((float)($p['price'] ?? 0)) ?></strong>
            <?php endif; ?>
          </div>
        </div>

        <a class="lux-bar-btn text-decoration-none text-center" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$p['id'] ?>">
          Buy Now
        </a>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<div class="text-center mb-5">
  <a class="btn btn-outline-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=presets">View All Presets</a>
</div>

<!-- LUTS -->
<div class="text-center mb-4">
  <h2 class="lux-title">LUTs</h2>
  <div class="text-muted">Cinematic LUTs for wedding videos and reels</div>
</div>

<div class="row g-4 mb-5">
  <?php while($l = mysqli_fetch_assoc($lutsQ)): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="lux-card h-100">
        <?php if (!empty($l['img'])): ?>
          <img class="lux-thumb" src="<?= $IMG_BASE . htmlspecialchars($l['img']) ?>" alt="lut">
        <?php else: ?>
          <div class="p-4 text-center text-muted">No Image</div>
        <?php endif; ?>

        <div class="p-3 text-center">
          <div class="fw-semibold"><?= htmlspecialchars($l['title']) ?></div>

          <div class="lux-price small mt-1">
            <?php if (!empty($l['discount']) && $l['discount'] > 0): ?>
              <del>₹<?= number_format((float)$l['price']) ?></del>
              <strong>₹<?= number_format((float)$l['discount']) ?></strong>
            <?php else: ?>
              <strong>₹<?= number_format((float)($l['price'] ?? 0)) ?></strong>
            <?php endif; ?>
          </div>
        </div>

        <a class="lux-bar-btn text-decoration-none text-center" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$l['id'] ?>">
          Buy Now
        </a>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<div class="text-center">
  <a class="btn btn-outline-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=luts">View All LUTs</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
