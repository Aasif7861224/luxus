<?php
require_once __DIR__ . '/includes/header.php';

/**
 * CATEGORY PAGE
 * URL examples:
 *  - /luxlut/frontend/category.php?type=presets
 *  - /luxlut/frontend/category.php?type=luts
 *  - /luxlut/frontend/category.php?type=presets&sort=low&min=199&max=999&q=soft
 */

// ---- Inputs
$type = strtolower(trim($_GET['type'] ?? 'presets'));
if (!in_array($type, ['presets', 'luts'])) $type = 'presets';

$q    = trim($_GET['q'] ?? '');
$min  = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$max  = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
$sort = strtolower(trim($_GET['sort'] ?? 'new'));

$allowedSort = ['new', 'low', 'high', 'az', 'za'];
if (!in_array($sort, $allowedSort)) $sort = 'new';

// ---- Resolve category id from DB (Preset / LUT)
$catName = ($type === 'luts') ? 'LUT' : 'Preset';
$catRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='".mysqli_real_escape_string($conn,$catName)."' LIMIT 1"));
$catId = (int)($catRow['id'] ?? 0);

// ---- Page title
$pageTitle = ($type === 'luts') ? 'LUTs' : 'Presets';

// ---- Sorting
$orderBy = "p.id DESC";
if ($sort === 'low')  $orderBy = "final_price ASC";
if ($sort === 'high') $orderBy = "final_price DESC";
if ($sort === 'az')   $orderBy = "p.title ASC";
if ($sort === 'za')   $orderBy = "p.title DESC";

// ---- Build SQL (100% DB-driven)
$conditions = [];
$params = [];
$typesStr = "";

// category filter (only if category exists; else show all active)
$conditions[] = "p.status='active'";
if ($catId > 0) {
    $conditions[] = "p.category_id=?";
    $typesStr .= "i";
    $params[] = $catId;
}

// search
if ($q !== '') {
    $conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $typesStr .= "ss";
    $like = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
}

$where = "WHERE " . implode(" AND ", $conditions);

// Price filtering uses HAVING on computed alias final_price
$havingParts = [];
if ($min !== null) { $havingParts[] = "final_price >= ?"; $typesStr .= "d"; $params[] = $min; }
if ($max !== null) { $havingParts[] = "final_price <= ?"; $typesStr .= "d"; $params[] = $max; }
$having = "";
if (!empty($havingParts)) $having = "HAVING " . implode(" AND ", $havingParts);

// SQL: latest price + thumb image via subqueries (simple and reliable)
$sql = "
SELECT
  p.id, p.title, p.description,

  (SELECT pm.file_name
   FROM product_media pm
   WHERE pm.product_id = p.id AND pm.media_type='image'
   ORDER BY pm.id DESC
   LIMIT 1) AS thumb,

  (SELECT pp.price
   FROM product_prices pp
   WHERE pp.product_id = p.id
   ORDER BY pp.id DESC
   LIMIT 1) AS price,

  (SELECT pp.discount_price
   FROM product_prices pp
   WHERE pp.product_id = p.id
   ORDER BY pp.id DESC
   LIMIT 1) AS discount_price,

  (CASE
     WHEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) IS NOT NULL
          AND (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) > 0
     THEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
     ELSE (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
   END) AS final_price

FROM products p
{$where}
{$having}
ORDER BY {$orderBy}
";

// Run prepared statement
$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $typesStr !== "") {
    mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // fallback (should not happen)
    $result = mysqli_query($conn, $sql);
}

// Image base (same as admin upload path)
$IMG_BASE = BASE_URL . "assets/uploads/products/images/";

// Build query string helper for links
function buildQuery($overrides = []) {
    $current = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($current[$k]);
        else $current[$k] = $v;
    }
    return http_build_query($current);
}
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
  .lux-card{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; overflow:hidden; transition:transform .15s ease; }
  .lux-card:hover{ transform: translateY(-2px); }
  .lux-thumb{ width:100%; aspect-ratio: 1/1; object-fit:cover; border-bottom:1px solid #e6dfd6; display:block; }
  .lux-old{ text-decoration:line-through; color:#7a7a7a; margin-right:8px; }
  .lux-bar-btn{ background:#5b5b5b; color:#fff; border:0; width:100%; padding:10px 12px; }
  .lux-bar-btn:hover{ background:#444; }
  .lux-chip{ border:1px solid #111; padding:6px 10px; border-radius:999px; font-size:12px; text-decoration:none; color:#111; }
  .lux-chip:hover{ background:#111; color:#fff; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h2 class="lux-title mb-0"><?= htmlspecialchars($pageTitle) ?></h2>
    <div class="lux-soft small">Browse & filter — everything loads from database</div>
  </div>

  <!-- Mobile Filter Button -->
  <div class="d-flex gap-2">
    <button class="btn btn-outline-dark d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
      Filters
    </button>
  </div>
</div>

<div class="row g-4">
  <!-- Desktop Sidebar -->
  <div class="col-lg-3 d-none d-lg-block">
    <div class="lux-box p-3">
      <div class="fw-semibold mb-2">Browse</div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=presets">Presets</a>
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=luts">LUTs</a>
      </div>

      <hr>

      <form method="GET" class="mt-3">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

        <div class="mb-3">
          <label class="form-label small">Search</label>
          <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search products...">
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small">Min ₹</label>
            <input type="number" name="min" class="form-control" value="<?= htmlspecialchars($min ?? '') ?>" placeholder="0">
          </div>
          <div class="col-6">
            <label class="form-label small">Max ₹</label>
            <input type="number" name="max" class="form-control" value="<?= htmlspecialchars($max ?? '') ?>" placeholder="9999">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label small">Sort</label>
          <select name="sort" class="form-select">
            <option value="new"  <?= $sort==='new'?'selected':'' ?>>Newest</option>
            <option value="low"  <?= $sort==='low'?'selected':'' ?>>Price: Low → High</option>
            <option value="high" <?= $sort==='high'?'selected':'' ?>>Price: High → Low</option>
            <option value="az"   <?= $sort==='az'?'selected':'' ?>>Title: A → Z</option>
            <option value="za"   <?= $sort==='za'?'selected':'' ?>>Title: Z → A</option>
          </select>
        </div>

        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-dark">Apply</button>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>frontend/category.php?type=<?= htmlspecialchars($type) ?>">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="col-lg-9">

    <!-- Top bar (sort + count) -->
    <div class="lux-box p-3 mb-3">
      <div class="row align-items-center gy-2">
        <div class="col-md-6">
          <div class="small lux-soft">
            Showing results for: <strong><?= htmlspecialchars($pageTitle) ?></strong>
            <?php if ($q !== ''): ?>
              • Search: <strong><?= htmlspecialchars($q) ?></strong>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-6 text-md-end">
          <form method="GET" class="d-inline-flex gap-2 align-items-center">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
            <input type="hidden" name="min" value="<?= htmlspecialchars($min ?? '') ?>">
            <input type="hidden" name="max" value="<?= htmlspecialchars($max ?? '') ?>">

            <label class="small lux-soft">Sort:</label>
            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="new"  <?= $sort==='new'?'selected':'' ?>>Newest</option>
              <option value="low"  <?= $sort==='low'?'selected':'' ?>>Price: Low → High</option>
              <option value="high" <?= $sort==='high'?'selected':'' ?>>Price: High → Low</option>
              <option value="az"   <?= $sort==='az'?'selected':'' ?>>Title: A → Z</option>
              <option value="za"   <?= $sort==='za'?'selected':'' ?>>Title: Z → A</option>
            </select>
          </form>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <?php if (!$result || mysqli_num_rows($result) == 0): ?>
        <div class="col-12">
          <div class="lux-box p-4 text-center">
            <div class="fw-semibold">No products found</div>
            <div class="lux-soft small mt-1">Try changing filters or search.</div>
          </div>
        </div>
      <?php else: ?>

        <?php while($p = mysqli_fetch_assoc($result)): ?>
          <?php
            $id = (int)$p['id'];
            $thumb = $p['thumb'] ?? '';
            $price = (float)($p['price'] ?? 0);
            $discount = (float)($p['discount_price'] ?? 0);
            $finalPrice = (float)($p['final_price'] ?? 0);
          ?>
          <div class="col-6 col-md-4 col-xl-3">
            <div class="lux-card h-100">
              <?php if (!empty($thumb)): ?>
                <img class="lux-thumb" src="<?= $IMG_BASE . htmlspecialchars($thumb) ?>" alt="product">
              <?php else: ?>
                <div class="p-4 text-center lux-soft">No Image</div>
              <?php endif; ?>

              <div class="p-3 text-center">
                <div class="fw-semibold"><?= htmlspecialchars($p['title'] ?? '-') ?></div>

                <div class="small mt-1">
                  <?php if ($discount > 0): ?>
                    <span class="lux-old">₹<?= number_format($price) ?></span>
                    <strong>₹<?= number_format($discount) ?></strong>
                  <?php else: ?>
                    <strong>₹<?= number_format($finalPrice) ?></strong>
                  <?php endif; ?>
                </div>
              </div>

              <a class="lux-bar-btn text-decoration-none text-center"
                 href="<?= BASE_URL ?>frontend/product.php?id=<?= $id ?>">
                View / Buy
              </a>
            </div>
          </div>
        <?php endwhile; ?>

      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Offcanvas Filters (Mobile) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterCanvas">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title lux-title">Filters</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="mb-3">
      <div class="fw-semibold mb-2">Browse</div>
      <div class="d-flex flex-wrap gap-2">
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=presets">Presets</a>
        <a class="lux-chip" href="<?= BASE_URL ?>frontend/category.php?type=luts">LUTs</a>
      </div>
    </div>

    <hr>

    <form method="GET">
      <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

      <div class="mb-3">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search products...">
      </div>

      <div class="row g-2">
        <div class="col-6">
          <label class="form-label small">Min ₹</label>
          <input type="number" name="min" class="form-control" value="<?= htmlspecialchars($min ?? '') ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Max ₹</label>
          <input type="number" name="max" class="form-control" value="<?= htmlspecialchars($max ?? '') ?>">
        </div>
      </div>

      <div class="mt-3">
        <label class="form-label small">Sort</label>
        <select name="sort" class="form-select">
          <option value="new"  <?= $sort==='new'?'selected':'' ?>>Newest</option>
          <option value="low"  <?= $sort==='low'?'selected':'' ?>>Price: Low → High</option>
          <option value="high" <?= $sort==='high'?'selected':'' ?>>Price: High → Low</option>
          <option value="az"   <?= $sort==='az'?'selected':'' ?>>Title: A → Z</option>
          <option value="za"   <?= $sort==='za'?'selected':'' ?>>Title: Z → A</option>
        </select>
      </div>

      <div class="d-grid gap-2 mt-3">
        <button class="btn btn-dark">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>frontend/category.php?type=<?= htmlspecialchars($type) ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>