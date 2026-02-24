<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../backend/config/database.php';

$orderStatusOptions = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'payment_received' => 'Payment Received',
    'assigned_to_editor' => 'Assigned',
    'editing' => 'Editing',
    'review' => 'Review',
    'delivered' => 'Delivered',
    'closed' => 'Closed',
];
$paymentStatusOptions = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'failed' => 'Failed',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_to('list.php');
    }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $paymentStatus = $_POST['payment_status'] ?? 'pending';
    $orderStatus = $_POST['order_status'] ?? 'pending';

    if ($orderId > 0 && isset($paymentStatusOptions[$paymentStatus]) && isset($orderStatusOptions[$orderStatus])) {
        $stmt = mysqli_prepare($conn, 'UPDATE orders SET payment_status=?, order_status=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssi', $paymentStatus, $orderStatus, $orderId);
        mysqli_stmt_execute($stmt);
    }

    redirect_to('list.php');
}

$hasCustomerPhone = column_exists($conn, 'orders', 'customer_phone');
$phoneSql = $hasCustomerPhone ? 'COALESCE(o.customer_phone, u.phone)' : 'u.phone';
$ordersQuery = "
    SELECT o.*, u.name AS user_name, u.email AS user_email, {$phoneSql} AS customer_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
";
$ordersRes = mysqli_query($conn, $ordersQuery);
?>

<h4 class="mb-4">Orders</h4>

<div class="card shadow-sm">
  <div class="card-body table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th width="70">#ID</th>
          <th>User</th>
          <th>Phone</th>
          <th>Total</th>
          <th>Items</th>
          <th>Payment</th>
          <th>Order Status</th>
          <th width="120">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$ordersRes || mysqli_num_rows($ordersRes) === 0): ?>
        <tr><td colspan="8" class="text-center text-muted">No orders found</td></tr>
      <?php else: ?>
        <?php while ($o = mysqli_fetch_assoc($ordersRes)): ?>
          <?php
            $orderId = (int)$o['id'];

            $itemsStmt = mysqli_prepare($conn, '
                SELECT oi.final_price, p.title
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.item_id
                WHERE oi.order_id = ?
            ');
            mysqli_stmt_bind_param($itemsStmt, 'i', $orderId);
            mysqli_stmt_execute($itemsStmt);
            $itemsRes = mysqli_stmt_get_result($itemsStmt);

            $itemsHtml = '';
            while ($it = mysqli_fetch_assoc($itemsRes)) {
                $itemsHtml .= '<div class="small">- ' . h($it['title'] ?? 'Product') . ' <span class="text-muted">(' . '&#8377;' . number_format((float)$it['final_price'], 0) . ')</span></div>';
            }
            if ($itemsHtml === '') {
                $itemsHtml = '<span class="text-muted small">No items</span>';
            }

            $paymentBadge = $o['payment_status'] === 'paid' ? 'success' : ($o['payment_status'] === 'failed' ? 'danger' : 'warning');
          ?>
          <tr>
            <td><?= $orderId ?></td>
            <td>
              <div><strong><?= h($o['user_name'] ?? 'Guest') ?></strong></div>
              <div class="text-muted small"><?= h($o['user_email'] ?? '-') ?></div>
            </td>
            <td><?= h($o['customer_phone'] ?? '-') ?></td>
            <td>&#8377;<?= number_format((float)$o['total_amount'], 0) ?></td>
            <td><?= $itemsHtml ?></td>
            <td><span class="badge bg-<?= $paymentBadge ?>"><?= strtoupper((string)$o['payment_status']) ?></span></td>
            <td><span class="badge bg-dark"><?= h($orderStatusOptions[$o['order_status']] ?? (string)$o['order_status']) ?></span></td>
            <td>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?= $orderId ?>">Update</button>
            </td>
          </tr>

          <div class="modal fade" id="updateModal<?= $orderId ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header">
                    <h5 class="modal-title">Update Order #<?= $orderId ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">

                    <div class="mb-3">
                      <label class="form-label">Payment Status</label>
                      <select name="payment_status" class="form-control">
                        <?php foreach ($paymentStatusOptions as $val => $label): ?>
                          <option value="<?= h($val) ?>" <?= ($o['payment_status'] ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Order Status</label>
                      <select name="order_status" class="form-control">
                        <?php foreach ($orderStatusOptions as $val => $label): ?>
                          <option value="<?= h($val) ?>" <?= ($o['order_status'] ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="modal-footer">
                    <button class="btn btn-dark" type="submit">Save</button>
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
