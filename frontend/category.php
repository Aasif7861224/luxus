<?php
require_once __DIR__ . '/includes/header.php';

$type = strtolower(trim($_GET['type'] ?? 'presets'));
if (!in_array($type, ['presets', 'luts'], true)) {
    $type = 'presets';
}

$q = trim($_GET['q'] ?? '');
$min = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$max = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
$sort = strtolower(trim($_GET['sort'] ?? 'new'));
$allowedSort = ['new', 'low', 'high', 'az', 'za'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'new';
}

$catName = $type === 'luts' ? 'LUT' : 'Preset';
$catStmt = mysqli_prepare($conn, 'SELECT id FROM categories WHERE name=? ORDER BY id ASC LIMIT 1');
mysqli_stmt_bind_param($catStmt, 's', $catName);
mysqli_stmt_execute($catStmt);
$catRes = mysqli_stmt_get_result($catStmt);
$catRow = mysqli_fetch_assoc($catRes);
$catId = (int)($catRow['id'] ?? 0);

$orderBy = 'p.id DESC';
if ($sort === 'low') { $orderBy = 'final_price ASC'; }
if ($sort === 'high') { $orderBy = 'final_price DESC'; }
if ($sort === 'az') { $orderBy = 'p.title ASC'; }
if ($sort === 'za') { $orderBy = 'p.title DESC'; }

$conditions = ["p.status='active'"];
$params = [];
$types = '';

if ($catId > 0) {
    $conditions[] = 'p.category_id=?';
    $types .= 'i';
    $params[] = $catId;
}

if ($q !== '') {
    $conditions[] = '(p.title LIKE ? OR p.description LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$havingParts = [];
if ($min !== null) {
    $havingParts[] = 'final_price >= ?';
    $types .= 'd';
    $params[] = $min;
}
if ($max !== null) {
    $havingParts[] = 'final_price <= ?';
    $types .= 'd';
    $params[] = $max;
}

$whereSql = 'WHERE ' . implode(' AND ', $conditions);
$havingSql = empty($havingParts) ? '' : ('HAVING ' . implode(' AND ', $havingParts));

$sql = "
SELECT
  p.id,
  p.title,
  p.description,
  p.before_image,
  p.after_image,
  (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS price,
  (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price,
  (CASE
    WHEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) IS NOT NULL
         AND (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) > 0
    THEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
    ELSE (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
  END) AS final_price
FROM products p
{$whereSql}
{$havingSql}
ORDER BY {$orderBy}
";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$pageTitle = $type === 'luts' ? 'LUTs' : 'Presets';
$IMG_BASE = BASE_URL . 'assets/uploads/products/images/';
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
  .lux-card{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; overflow:hidden; height:100%; }
  .lux-chip{ border:1px solid #111; padding:6px 10px; border-radius:999px; font-size:12px; text-decoration:none; color:#111; }
  .lux-chip:hover{ background:#111; color:#fff; }
  .lux-bar-btn{ background:#171717; color:#fff; border:0; width:100%; padding:10px 12px; display:block; text-align:center; text-decoration:none; }
  .lux-bar-btn:hover{ background:#000; color:#fff; }
  .lux-old{ text-decoration:line-through; color:#7a7a7a; margin-right:8px; }

  .ba-wrap{ position:relative; width:100%; aspect-ratio:1/1; overflow:hidden; background:#ece5dc; }
  .ba-wrap img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; pointer-events:none; }
  .ba-after-wrap{ position:absolute; inset:0 auto 0 0; width:50%; overflow:hidden; border-right:2px solid rgba(255,255,255,.9); }
  .ba-slider{ position:absolute; left:12px; right:12px; bottom:10px; z-index:5; width:calc(100% - 24px); }
  .ba-label{ position:absolute; top:10px; font-size:11px; letter-spacing:.4px; background:#111; color:#fff; padding:2px 8px; border-radius:999px; z-index:5; }
  .ba-label.before{ left:10px; }
  .ba-label.after{ right:10px; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h2 class="lux-title mb-0"><?= h($pageTitle) ?></h2>
    <div class="lux-soft small">Before/After based preset discovery</div>
  </div>
  <button class="btn btn-outline-dark d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">Filters</button>
</div>

<div class="row g-4">
  <div class="col-lg-3 d-none d-lg-block">
    <div class="lux-box p-3">
      <div class="fw-semibold mb-2">Browse</div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=presets">Presets</a>
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=luts">LUTs</a>
      </div>
      <hr>
      <form method="GET" class="mt-2">
        <input type="hidden" name="type" value="<?= h($type) ?>">
        <div class="mb-3">
          <label class="form-label small">Search</label>
          <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Search presets...">
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small">Min &#8377;</label>
            <input type="number" name="min" class="form-control" value="<?= h($min ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small">Max &#8377;</label>
            <input type="number" name="max" class="form-control" value="<?= h($max ?? '') ?>">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label small">Sort</label>
          <select name="sort" class="form-select">
            <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
            <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Price Low to High</option>
            <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price High to Low</option>
            <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A to Z</option>
            <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>Z to A</option>
          </select>
        </div>

        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-dark">Apply</button>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>frontend/category.php?type=<?= h($type) ?>">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="row g-4">
      <?php if (!$result || mysqli_num_rows($result) === 0): ?>
        <div class="col-12">
          <div class="lux-box p-4 text-center">
            <div class="fw-semibold">No products found</div>
            <div class="small lux-soft mt-1">Try changing search/filter values.</div>
          </div>
        </div>
      <?php else: ?>
        <?php while ($p = mysqli_fetch_assoc($result)): ?>
          <div class="col-12 col-sm-6 col-xl-4">
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
                <div class="small mt-1">
                  <?php if ((float)$p['discount_price'] > 0): ?>
                    <span class="lux-old">&#8377;<?= number_format((float)$p['price'], 0) ?></span>
                    <strong>&#8377;<?= number_format((float)$p['discount_price'], 0) ?></strong>
                  <?php else: ?>
                    <strong>&#8377;<?= number_format((float)$p['final_price'], 0) ?></strong>
                  <?php endif; ?>
                </div>
              </div>

              <a class="lux-bar-btn" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$p['id'] ?>">View / Buy</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="filterCanvas">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Filters</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <form method="GET">
      <input type="hidden" name="type" value="<?= h($type) ?>">
      <div class="mb-3">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control" value="<?= h($q) ?>">
      </div>
      <div class="row g-2">
        <div class="col-6"><input type="number" class="form-control" name="min" value="<?= h($min ?? '') ?>" placeholder="Min"></div>
        <div class="col-6"><input type="number" class="form-control" name="max" value="<?= h($max ?? '') ?>" placeholder="Max"></div>
      </div>
      <div class="mt-3">
        <select name="sort" class="form-select">
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
          <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Price Low to High</option>
          <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price High to Low</option>
          <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A to Z</option>
          <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>Z to A</option>
        </select>
      </div>
      <div class="d-grid gap-2 mt-3">
        <button class="btn btn-dark">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>frontend/category.php?type=<?= h($type) ?>">Reset</a>
      </div>
    </form>
  </div>
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
