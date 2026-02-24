<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/guard.php';
ensure_session_started();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_abort(false);

    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $productId > 0) {
        $_SESSION['cart'][$productId] = (int)($_SESSION['cart'][$productId] ?? 0) + 1;
        if ($_SESSION['cart'][$productId] > 99) {
            $_SESSION['cart'][$productId] = 99;
        }
    }

    if ($action === 'remove' && $productId > 0) {
        unset($_SESSION['cart'][$productId]);
    }

    if ($action === 'update' && !empty($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (int)$qty;
            if ($pid <= 0) {
                continue;
            }
            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
            } else {
                $_SESSION['cart'][$pid] = min(99, $qty);
            }
        }
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
    }

    redirect_to(BASE_URL . 'frontend/cart.php');
}

[$items, $total] = fetch_cart_items($conn, $_SESSION['cart']);

// clean stale product IDs
foreach (array_keys($_SESSION['cart']) as $pid) {
    if (!isset($items[(int)$pid])) {
        unset($_SESSION['cart'][(int)$pid]);
    }
}

require_once __DIR__ . '/includes/header.php';
$IMG_BASE = BASE_URL . 'assets/uploads/products/images/';
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
    <div class="lux-soft small">Review products before secure checkout</div>
  </div>

  <div class="d-flex gap-2">
    <?php if (!empty($items)): ?>
      <form method="POST" class="d-inline">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-outline-secondary" onclick="return confirm('Clear entire cart?');">Clear Cart</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-outline-dark" href="<?= BASE_URL ?>frontend/category.php?type=presets">Continue Shopping</a>
  </div>
</div>

<?php if (empty($items)): ?>
  <div class="lux-box p-4 text-center">
    <div class="fw-semibold">Your cart is empty</div>
    <div class="lux-soft small mt-1">Add preset products from category pages.</div>
    <a class="btn btn-dark mt-3" href="<?= BASE_URL ?>frontend/category.php?type=presets">Shop Presets</a>
  </div>
<?php else: ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="lux-box p-3">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="update">

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr class="small lux-soft">
                <th>Item</th>
                <th class="text-center" style="width:110px;">Qty</th>
                <th class="text-end" style="width:140px;">Unit</th>
                <th class="text-end" style="width:140px;">Subtotal</th>
                <th class="text-end" style="width:90px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <div class="d-flex gap-3 align-items-center">
                      <?php if (!empty($it['after_image'])): ?>
                        <img class="lux-thumb" src="<?= $IMG_BASE . h($it['after_image']) ?>" alt="thumb">
                      <?php else: ?>
                        <div class="lux-thumb d-flex align-items-center justify-content-center lux-soft">No</div>
                      <?php endif; ?>

                      <div>
                        <div class="fw-semibold"><?= h($it['title']) ?></div>
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

                  <td class="text-end">&#8377;<?= number_format((float)$it['unit_price'], 0) ?></td>
                  <td class="text-end fw-semibold">&#8377;<?= number_format((float)$it['subtotal'], 0) ?></td>

                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger"
                            form="remove-<?= (int)$it['id'] ?>"
                            onclick="return confirm('Remove this item?');">
                      Remove
                    </button>
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

      <?php foreach ($items as $it): ?>
        <form id="remove-<?= (int)$it['id'] ?>" method="POST" class="d-none">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
        </form>
      <?php endforeach; ?>
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
        <span class="fw-semibold text-dark">&#8377;<?= number_format($total, 0) ?></span>
      </div>
      <hr>
      <a class="btn btn-dark w-100" href="<?= BASE_URL ?>frontend/checkout.php">Proceed to Checkout</a>
      <div class="small lux-soft mt-3">Checkout ke baad payment success par WhatsApp me secure link deliver hoga.</div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
