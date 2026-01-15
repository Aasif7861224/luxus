<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

// ---------------------------
// Update Order Status (POST)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $order_id       = (int)($_POST['order_id'] ?? 0);
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $order_status   = $_POST['order_status'] ?? 'pending';

    if ($order_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE orders SET payment_status=?, order_status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $payment_status, $order_status, $order_id);
        mysqli_stmt_execute($stmt);
    }

    header("Location: list.php");
    exit;
}

// ---------------------------
// Fetch Orders
// ---------------------------
$ordersQuery = "
SELECT o.*, u.name AS user_name, u.email AS user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
ORDER BY o.id DESC
";
$ordersRes = mysqli_query($conn, $ordersQuery);

// Status options
$orderStatusOptions = [
    'pending' => 'Pending',
    'payment_received' => 'Payment Received',
    'assigned_to_editor' => 'Assigned to Editor',
    'editing' => 'Editing',
    'review' => 'Review',
    'delivered' => 'Delivered',
    'closed' => 'Closed'
];

$paymentStatusOptions = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'failed' => 'Failed'
];

// Helper: get item name (product/service)
function getItemName($conn, $item_id, $item_type) {
    if ($item_type === 'product') {
        $stmt = mysqli_prepare($conn, "SELECT title FROM products WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $item_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        return $row['title'] ?? 'Unknown Product';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT name FROM services WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $item_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        return $row['name'] ?? 'Unknown Service';
    }
}
?>

<h4 class="mb-4">Orders</h4>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th width="60">#ID</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Items</th>
                    <th>Payment</th>
                    <th>Order Status</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>

            <?php if (mysqli_num_rows($ordersRes) == 0): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No orders found</td>
                </tr>
            <?php endif; ?>

            <?php while ($o = mysqli_fetch_assoc($ordersRes)): ?>
                <?php
                    $orderId = (int)$o['id'];

                    // Fetch order items
                    $itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = $orderId");
                    $itemsHtml = "";

                    while ($it = mysqli_fetch_assoc($itemsRes)) {
                        $name = getItemName($conn, (int)$it['item_id'], $it['item_type']);
                        $itemsHtml .= "<div class='small'>
                            • ".htmlspecialchars($name)." 
                            <span class='text-muted'>(₹".htmlspecialchars($it['final_price']).")</span>
                        </div>";
                    }
                    if ($itemsHtml === "") $itemsHtml = "<span class='text-muted small'>No items</span>";
                ?>

                <tr>
                    <td><?= $orderId ?></td>

                    <td>
                        <div><strong><?= htmlspecialchars($o['user_name'] ?? 'Guest') ?></strong></div>
                        <div class="text-muted small"><?= htmlspecialchars($o['user_email'] ?? '-') ?></div>
                    </td>

                    <td><?= ucfirst($o['order_type']) ?></td>

                    <td>₹<?= htmlspecialchars($o['total_amount']) ?></td>

                    <td><?= $itemsHtml ?></td>

                    <td>
                        <span class="badge bg-<?= $o['payment_status']=='paid'?'success':($o['payment_status']=='failed'?'danger':'warning') ?>">
                            <?= strtoupper($o['payment_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge bg-dark">
                            <?= $orderStatusOptions[$o['order_status']] ?? $o['order_status'] ?>
                        </span>
                    </td>

                    <td>
                        <!-- Update form -->
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?= $orderId ?>">
                            Update
                        </button>
                    </td>
                </tr>

                <!-- Modal -->
                <div class="modal fade" id="updateModal<?= $orderId ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">

                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Order #<?= $orderId ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="update_status" value="1">

                                    <div class="mb-3">
                                        <label class="form-label">Payment Status</label>
                                        <select name="payment_status" class="form-control">
                                            <?php foreach ($paymentStatusOptions as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= $o['payment_status']==$val?'selected':'' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Order Status</label>
                                        <select name="order_status" class="form-control">
                                            <?php foreach ($orderStatusOptions as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= $o['order_status']==$val?'selected':'' ?>>
                                                    <?= $label ?>
                                                </option>
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

            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS (Modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once "../includes/footer.php"; ?>
