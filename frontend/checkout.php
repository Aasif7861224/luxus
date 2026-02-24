<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/guard.php';
ensure_session_started();

$buyNowProductId = (int)($_GET['product_id'] ?? 0);
if ($buyNowProductId > 0) {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (!isset($_SESSION['cart'][$buyNowProductId])) {
        $_SESSION['cart'][$buyNowProductId] = 1;
    }
}

[$items, $total] = fetch_cart_items($conn, $_SESSION['cart'] ?? []);
if (empty($items)) {
    redirect_to(BASE_URL . 'frontend/cart.php');
}

$userId = (int)$_SESSION['user_id'];
$userStmt = mysqli_prepare($conn, 'SELECT name, email, phone FROM users WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($userStmt, 'i', $userId);
mysqli_stmt_execute($userStmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt)) ?: [];

$phonePrefill = $userRow['phone'] ?? '';
$successOrderId = (int)($_GET['order_id'] ?? 0);
$success = isset($_GET['success']) && (int)$_GET['success'] === 1;

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .lux-title{ font-family:"Playfair Display", serif; }
  .lux-soft{ color:#6b6b6b; }
  .lux-box{ background:#fff; border:1px solid #e6dfd6; border-radius:14px; }
</style>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="lux-box p-4">
      <h2 class="lux-title mb-1">Secure Checkout</h2>
      <p class="lux-soft mb-4">Payment success ke baad download link WhatsApp pe send hoga.</p>

      <?php if ($success): ?>
        <div class="alert alert-success">
          Payment successful. Order #<?= (int)$successOrderId ?> placed and delivery is being processed on WhatsApp.
        </div>
      <?php endif; ?>

      <form id="checkoutForm" class="vstack gap-3" onsubmit="return false;">
        <?= csrf_input() ?>

        <div>
          <label class="form-label">Phone Number</label>
          <input type="tel" class="form-control" id="phone" name="phone" required value="<?= h($phonePrefill) ?>" placeholder="10-digit phone number">
          <div class="form-text">Isi number pe WhatsApp delivery aayegi.</div>
        </div>

        <button id="payButton" class="btn btn-dark">Pay with Razorpay</button>
        <div id="checkoutMsg" class="small"></div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="lux-box p-3">
      <div class="fw-semibold mb-3">Order Summary</div>
      <?php foreach ($items as $item): ?>
        <div class="d-flex justify-content-between small mb-2">
          <span><?= h($item['title']) ?> x <?= (int)$item['qty'] ?></span>
          <span>&#8377;<?= number_format((float)$item['subtotal'], 0) ?></span>
        </div>
      <?php endforeach; ?>
      <hr>
      <div class="d-flex justify-content-between fw-semibold">
        <span>Total</span>
        <span>&#8377;<?= number_format($total, 0) ?></span>
      </div>
    </div>
  </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
  var payButton = document.getElementById('payButton');
  var msg = document.getElementById('checkoutMsg');
  var phone = document.getElementById('phone');
  var csrfToken = document.querySelector('input[name="csrf_token"]').value;

  function setMessage(text, isError) {
    msg.textContent = text;
    msg.className = isError ? 'small text-danger mt-2' : 'small text-success mt-2';
  }

  function verifyPayment(payload, localOrderId) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('local_order_id', localOrderId);
    body.set('razorpay_payment_id', payload.razorpay_payment_id || '');
    body.set('razorpay_order_id', payload.razorpay_order_id || '');
    body.set('razorpay_signature', payload.razorpay_signature || '');

    return fetch('<?= BASE_URL ?>backend/payments/verify_signature.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function(r){ return r.json(); });
  }

  payButton.addEventListener('click', function(){
    setMessage('', false);

    if (!phone.value.trim()) {
      setMessage('Phone number required', true);
      return;
    }

    payButton.disabled = true;
    payButton.textContent = 'Creating order...';

    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('phone', phone.value.trim());

    fetch('<?= BASE_URL ?>backend/payments/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
    .then(function(resp){ return resp.json(); })
    .then(function(data){
      if (!data.success) {
        throw new Error(data.message || 'Unable to create order');
      }

      var options = {
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        name: data.name,
        description: data.description,
        order_id: data.razorpay_order_id,
        prefill: data.prefill || {},
        notes: data.notes || {},
        theme: { color: '#111111' },
        handler: function(response){
          payButton.textContent = 'Verifying payment...';
          verifyPayment(response, data.local_order_id)
            .then(function(verifyRes){
              if (!verifyRes.success) {
                throw new Error(verifyRes.message || 'Payment verification failed');
              }
              window.location.href = '<?= BASE_URL ?>frontend/checkout.php?success=1&order_id=' + encodeURIComponent(data.local_order_id);
            })
            .catch(function(err){
              payButton.disabled = false;
              payButton.textContent = 'Pay with Razorpay';
              setMessage(err.message || 'Verification failed', true);
            });
        },
        modal: {
          ondismiss: function(){
            payButton.disabled = false;
            payButton.textContent = 'Pay with Razorpay';
          }
        }
      };

      var rzp = new Razorpay(options);
      rzp.on('payment.failed', function (response){
        setMessage((response.error && response.error.description) ? response.error.description : 'Payment failed', true);
        payButton.disabled = false;
        payButton.textContent = 'Pay with Razorpay';
      });

      payButton.textContent = 'Opening payment...';
      rzp.open();
    })
    .catch(function(err){
      setMessage(err.message || 'Checkout failed', true);
      payButton.disabled = false;
      payButton.textContent = 'Pay with Razorpay';
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
