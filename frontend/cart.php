<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Cart in session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // product_id => qty
}

// --- Helpers
function cartAdd($id, $qty = 1) {
    $id = (int)$id;
    $qty = (int)$qty;
    if ($id <= 0) return;
    if ($qty <= 0) $qty = 1;

    if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
    $_SESSION['cart'][$id] += $qty;

    if ($_SESSION['cart'][$id] > 99) $_SESSION['cart'][$id] = 99; // safety cap
}

function cartRemove($id) {
    $id = (int)$id;
    if ($id <= 0) return;
    unset($_SESSION['cart'][$id]);
}

function cartSetQty($id, $qty) {
    $id = (int)$id;
    $qty = (int)$qty;
    if ($id <= 0) return;

    if ($qty <= 0) {
        unset($_SESSION['cart'][$id]);
    } else {
        if ($qty > 99) $qty = 99;
        $_SESSION['cart'][$id] = $qty;
    }
}

// --- Actions (GET based simple MVP)
if (isset($_GET['add'])) {
    cartAdd($_GET['add'], 1);
    header("Location: cart.php");
    exit;
}

if (isset($_GET['remove'])) {
    cartRemove($_GET['remove']);
    header("Location: cart.php");
    exit;
}

// qty update via GET: cart.php?qty[12]=2&qty[5]=1
if (isset($_GET['qty']) && is_array($_GET['qty'])) {
    foreach ($_GET['qty'] as $pid => $q) {
        cartSetQty($pid, $q);
    }
    header("Location: cart.php");
    exit;
}

// Clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit;
}

// --- Fetch cart products from DB
$cartIds = array_keys($_SESSION['cart']);
$items = [];
$total = 0.0;

$IMG_BASE = BASE_URL . "assets/uploads/products/images/";

if (!empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $types = str_repeat('i', count($cartIds));

    $sql = "
    SELECT
      p.id, p.title,

      (SELECT pm.file_name
       FROM product_media pm
       WHERE pm.product_id=p.id AND pm.media_type='image'
       ORDER BY pm.id DESC LIMIT 1) AS thumb,

      (SELECT pp.price
       FROM product_prices pp
       WHERE pp.product_id=p.id
       ORDER BY pp.id DESC LIMIT 1) AS price,

      (SELECT pp.discount_price
       FROM product_prices pp
       WHERE pp.product_id=p.id
       ORDER BY pp.id DESC LIMIT 1) AS discount_price,

      (CASE
        WHEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) IS NOT NULL
             AND (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1) > 0
        THEN (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
        ELSE (SELECT pp.price FROM product_prices pp WHERE pp.product_id=p.id ORDER BY pp.id DESC LIMIT 1)
      END) AS final_price

    FROM products p
    WHERE p.status='active'
      AND p.id IN ($placeholders)
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$cartIds);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        $pid = (int)$row['id'];
        $qty = (int)($_SESSION['cart'][$pid] ?? 0);
        if ($qty <= 0) continue;

        $unit = (float)($row['final_price'] ?? 0);
        $sub = $unit * $qty;
        $total += $sub;

        $row['qty'] = $qty;
        $row['unit_price'] = $unit;
        $row['subtotal'] = $sub;
        $items[$pid] = $row;
    }

    // If some cart items are inactive/deleted, remove them
    foreach ($cartIds as $pid) {
        if (!isset($items[(int)$pid])) {
            unset($_SESSION['cart'][(int)$pid]);
        }
    }
}
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
  .lux-thumb{ width:70px; height:70px; object-fit:cover; border-radius:12px; border:1px solid #e6dfd6; }
  .lux-qty{ width:84px; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h2 class="lux-title mb-0">Your Cart</h2>
    <div class="lux-soft small">Review items before checkout</div>
  </div>

  <div class="d-flex gap-2">
    <?php if (!empty($items)): ?>
      <a class="btn btn-outline-secondary" href="cart.php?clear=1" onclick="return confirm('Clear entire cart?');">Clear Cart</a>
    <?php endif; ?>
    <a class="btn btn-outline-dark" href="<?= BASE_URL ?>frontend/category.php?type=presets">Continue Shopping</a>
  </div>
</div>

<?php if (empty($items)): ?>
  <div class="lux-box p-4 text-center">
    <div class="fw-semibold">Your cart is empty</div>
    <div class="lux-soft small mt-1">Add some products from Presets or LUTs.</div>
    <a class="btn btn-dark mt-3" href="<?= BASE_URL ?>frontend/category.php?type=presets">Shop Presets</a>
  </div>
<?php else: ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="lux-box p-3">
      <form method="GET" action="cart.php">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr class="small lux-soft">
                <th>Item</th>
                <th class="text-center" style="width:110px;">Qty</th>
                <th class="text-end" style="width:140px;">Unit</th>
                <th class="text-end" style="width:140px;">Subtotal</th>
                <th class="text-end" style="width:70px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <div class="d-flex gap-3 align-items-center">
                      <?php if (!empty($it['thumb'])): ?>
                        <img class="lux-thumb" src="<?= $IMG_BASE . htmlspecialchars($it['thumb']) ?>" alt="thumb">
                      <?php else: ?>
                        <div class="lux-thumb d-flex align-items-center justify-content-center lux-soft">No</div>
                      <?php endif; ?>

                      <div>
                        <div class="fw-semibold"><?= htmlspecialchars($it['title']) ?></div>
                        <a class="small text-decoration-none lux-soft" href="<?= BASE_URL ?>frontend/product.php?id=<?= (int)$it['id'] ?>">View product</a>
                      </div>
                    </div>
                  </td>

                  <td class="text-center">
                    <input class="form-control form-control-sm lux-qty mx-auto"
                           type="number" min="0" max="99"
                           name="qty[<?= (int)$it['id'] ?>]"
                           value="<?= (int)$it['qty'] ?>">
                    <div class="small lux-soft mt-1">0 = remove</div>
                  </td>

                  <td class="text-end">
                    ₹<?= number_format((float)$it['unit_price'], 0) ?>
                  </td>

                  <td class="text-end fw-semibold">
                    ₹<?= number_format((float)$it['subtotal'], 0) ?>
                  </td>

                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-danger"
                       href="cart.php?remove=<?= (int)$it['id'] ?>"
                       onclick="return confirm('Remove this item?');">
                      ✕
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-dark" type="submit">Update Cart</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="lux-box p-3">
      <div class="fw-semibold mb-2">Order Summary</div>

      <div class="d-flex justify-content-between lux-soft small">
        <span>Items</span>
        <span><?= array_sum(array_column($items, 'qty')) ?></span>
      </div>

      <div class="d-flex justify-content-between lux-soft small mt-2">
        <span>Total</span>
        <span class="fw-semibold text-dark">₹<?= number_format($total, 0) ?></span>
      </div>

      <hr>

      <a class="btn btn-dark w-100" href="<?= BASE_URL ?>frontend/checkout.php">
        Proceed to Checkout
      </a>

      <?php
        // WhatsApp MVP fallback
        $waText = "Hello LuxLut! I want to checkout these items:\n";
        foreach ($items as $it) {
            $waText .= "- " . ($it['title'] ?? '') . " (Qty: " . (int)$it['qty'] . ")\n";
        }
        $waText .= "Total: ₹" . number_format($total, 0);
        $waLink = "https://wa.me/918898476480?text=" . urlencode($waText);
      ?>
      <a class="btn btn-outline-secondary w-100 mt-2" target="_blank" href="<?= $waLink ?>">
        WhatsApp Order (MVP)
      </a>

      <div class="small lux-soft mt-3">
        Note: Google Login will be required only at checkout.
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>