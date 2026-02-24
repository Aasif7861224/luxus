<?php
require_once __DIR__ . '/includes/header.php';

$catPreset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='Preset' ORDER BY id ASC LIMIT 1"));
$catLut = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='LUT' ORDER BY id ASC LIMIT 1"));

$presetId = (int)($catPreset['id'] ?? 0);
$lutId = (int)($catLut['id'] ?? 0);

$schemaReady = column_exists($conn, 'products', 'before_image') && column_exists($conn, 'products', 'after_image');
$schemaError = '';

$hero = null;
$presetsQ = false;
$lutsQ = false;

if ($schemaReady) {
    $heroQ = mysqli_query($conn, "
        SELECT p.id, p.title, p.description, p.before_image, p.after_image,
          (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price,
          (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price
        FROM products p
        WHERE p.status='active'
        ORDER BY p.id DESC
        LIMIT 1
    ");
    if ($heroQ) {
        $hero = mysqli_fetch_assoc($heroQ) ?: null;
    }

    $presetsQ = mysqli_query($conn, "
        SELECT p.id, p.title, p.before_image, p.after_image,
          (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price,
          (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price
        FROM products p
        WHERE p.status='active' " . ($presetId > 0 ? "AND p.category_id=" . $presetId : "") . "
        ORDER BY p.id DESC
        LIMIT 6
    ");

    $lutsQ = mysqli_query($conn, "
        SELECT p.id, p.title, p.before_image, p.after_image,
          (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price,
          (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price
        FROM products p
        WHERE p.status='active' " . ($lutId > 0 ? "AND p.category_id=" . $lutId : "") . "
        ORDER BY p.id DESC
        LIMIT 6
    ");
} else {
    $schemaError = 'Database migration pending. Please run the migration SQL once.';
}

$IMG_BASE = BASE_URL . 'assets/uploads/products/images/';

function home_final_price($price, $discount)
{
    $price = (float)$price;
    $discount = (float)$discount;
    return $discount > 0 ? $discount : $price;
}
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-card{ border:1px solid #e6dfd6; border-radius:14px; overflow:hidden; background:#fff; height:100%; }
  .lux-bar-btn{ background:#171717; color:#fff; border:0; width:100%; padding:10px 12px; text-decoration:none; display:block; text-align:center; }
  .lux-bar-btn:hover{ background:#000; color:#fff; }
  .lux-price del{ color:#7a7a7a; margin-right:8px; }

  .ba-wrap{ position:relative; width:100%; aspect-ratio:1/1; overflow:hidden; background:#ece5dc; }
  .ba-wrap img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; pointer-events:none; }
  .ba-after-wrap{ position:absolute; inset:0 auto 0 0; width:50%; overflow:hidden; border-right:2px solid rgba(255,255,255,.9); }
  .ba-slider{ position:absolute; left:12px; right:12px; bottom:10px; z-index:5; width:calc(100% - 24px); }
  .ba-label{ position:absolute; top:10px; font-size:11px; letter-spacing:.4px; background:#111; color:#fff; padding:2px 8px; border-radius:999px; z-index:5; }
  .ba-label.before{ left:10px; }
  .ba-label.after{ right:10px; }

  .hero-box{ border:1px solid #e6dfd6; border-radius:16px; background:#fff; }
</style>

<?php if ($schemaError !== ''): ?>
  <div class="alert alert-warning"><?= h($schemaError) ?></div>
<?php endif; ?>

<div class="row g-4 align-items-center mb-5">
  <div class="col-lg-6">
    <h1 class="lux-title display-5 mb-3">Premium Wedding Presets & LUTs</h1>
    <p class="lux-soft mb-4">
      Sell-ready digital preset store experience with clean preview cards, quick checkout, and WhatsApp delivery.
    </p>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=presets">Shop Presets</a>
      <a class="btn btn-outline-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=luts">Shop LUTs</a>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="hero-box p-3">
      <?php if (!empty($hero) && !empty($hero['before_image']) && !empty($hero['after_image'])): ?>
        <div class="ba-wrap" data-compare>
          <span class="ba-label before">Before</span>
          <span class="ba-label after">After</span>
          <img src="<?= $IMG_BASE . h($hero['before_image']) ?>" alt="Before">
          <div class="ba-after-wrap">
            <img src="<?= $IMG_BASE . h($hero['after_image']) ?>" alt="After">
          </div>
          <input type="range" class="ba-slider" min="0" max="100" value="50" aria-label="Compare before and after">
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div>
            <div class="fw-semibold"><?= h($hero['title']) ?></div>
            <div class="small lux-price">
              <?php if ((float)$hero['discount_price'] > 0): ?>
                <del>&#8377;<?= number_format((float)$hero['price'], 0) ?></del>
                <strong>&#8377;<?= number_format((float)$hero['discount_price'], 0) ?></strong>
              <?php else: ?>
                <strong>&#8377;<?= number_format((float)$hero['price'], 0) ?></strong>
              <?php endif; ?>
            </div>
          </div>
          <a class="btn btn-dark btn-sm" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$hero['id'] ?>">View</a>
        </div>
      <?php else: ?>
        <div class="text-center py-5 lux-soft">Upload before/after images from Admin to show hero preview.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="text-center mb-4">
  <h2 class="lux-title">Featured Presets</h2>
  <div class="lux-soft">Before/After preview se client ko instant idea milega.</div>
</div>

<div class="row g-4 mb-5">
  <?php if ($presetsQ && mysqli_num_rows($presetsQ) > 0): ?>
    <?php while ($p = mysqli_fetch_assoc($presetsQ)): ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="lux-card">
          <?php if (!empty($p['before_image']) && !empty($p['after_image'])): ?>
            <div class="ba-wrap" data-compare>
              <span class="ba-label before">Before</span>
              <span class="ba-label after">After</span>
              <img src="<?= $IMG_BASE . h($p['before_image']) ?>" alt="Before">
              <div class="ba-after-wrap"><img src="<?= $IMG_BASE . h($p['after_image']) ?>" alt="After"></div>
              <input type="range" class="ba-slider" min="0" max="100" value="50">
            </div>
          <?php else: ?>
            <div class="p-5 text-center lux-soft">Before/After not uploaded</div>
          <?php endif; ?>

          <div class="p-3 text-center">
            <div class="fw-semibold"><?= h($p['title']) ?></div>
            <div class="small lux-price mt-1">
              <?php if ((float)$p['discount_price'] > 0): ?>
                <del>&#8377;<?= number_format((float)$p['price'], 0) ?></del>
                <strong>&#8377;<?= number_format((float)$p['discount_price'], 0) ?></strong>
              <?php else: ?>
                <strong>&#8377;<?= number_format(home_final_price($p['price'], $p['discount_price']), 0) ?></strong>
              <?php endif; ?>
            </div>
          </div>

          <a class="lux-bar-btn" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$p['id'] ?>">View Preset</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="col-12"><div class="alert alert-light border">No presets available.</div></div>
  <?php endif; ?>
</div>

<div class="text-center mb-5">
  <a class="btn btn-outline-dark px-4" href="<?= BASE_URL ?>frontend/category.php?type=presets">View All Presets</a>
</div>

<div class="text-center mb-4">
  <h2 class="lux-title">Featured LUTs</h2>
  <div class="lux-soft">Video color grade LUT packs with instant before/after impact.</div>
</div>

<div class="row g-4">
  <?php if ($lutsQ && mysqli_num_rows($lutsQ) > 0): ?>
    <?php while ($l = mysqli_fetch_assoc($lutsQ)): ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="lux-card">
          <?php if (!empty($l['before_image']) && !empty($l['after_image'])): ?>
            <div class="ba-wrap" data-compare>
              <span class="ba-label before">Before</span>
              <span class="ba-label after">After</span>
              <img src="<?= $IMG_BASE . h($l['before_image']) ?>" alt="Before">
              <div class="ba-after-wrap"><img src="<?= $IMG_BASE . h($l['after_image']) ?>" alt="After"></div>
              <input type="range" class="ba-slider" min="0" max="100" value="50">
            </div>
          <?php else: ?>
            <div class="p-5 text-center lux-soft">Before/After not uploaded</div>
          <?php endif; ?>

          <div class="p-3 text-center">
            <div class="fw-semibold"><?= h($l['title']) ?></div>
            <div class="small lux-price mt-1">
              <?php if ((float)$l['discount_price'] > 0): ?>
                <del>&#8377;<?= number_format((float)$l['price'], 0) ?></del>
                <strong>&#8377;<?= number_format((float)$l['discount_price'], 0) ?></strong>
              <?php else: ?>
                <strong>&#8377;<?= number_format(home_final_price($l['price'], $l['discount_price']), 0) ?></strong>
              <?php endif; ?>
            </div>
          </div>

          <a class="lux-bar-btn" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$l['id'] ?>">View LUT</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="col-12"><div class="alert alert-light border">No LUTs available.</div></div>
  <?php endif; ?>
</div>

<script>
(function(){
  document.querySelectorAll('[data-compare]').forEach(function(box){
    var slider = box.querySelector('.ba-slider');
    var afterWrap = box.querySelector('.ba-after-wrap');
    if (!slider || !afterWrap) return;
    var apply = function(){ afterWrap.style.width = slider.value + '%'; };
    slider.addEventListener('input', apply);
    apply();
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
