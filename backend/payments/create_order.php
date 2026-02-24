<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/app.php';

ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token'], 419);
}

if (empty($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'Login required'], 401);
}

$phoneInput = trim($_POST['phone'] ?? '');
$phoneDigits = preg_replace('/\D+/', '', $phoneInput);
if (strlen($phoneDigits) < 10) {
    json_response(['success' => false, 'message' => 'Valid phone number required'], 422);
}

$customerPhone = normalize_phone($phoneInput);

[$items, $total] = fetch_cart_items($conn, $_SESSION['cart'] ?? []);
if (empty($items) || $total <= 0) {
    json_response(['success' => false, 'message' => 'Cart is empty'], 422);
}

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    json_response(['success' => false, 'message' => 'Razorpay keys are not configured'], 500);
}

$userId = (int)$_SESSION['user_id'];

mysqli_begin_transaction($conn);
try {
    $orderColumns = ['user_id', 'order_type', 'total_amount', 'payment_status', 'order_status'];
    $orderValues = ['?', '?', '?', '?', '?'];
    $types = 'isdss';
    $params = [$userId, 'product', $total, 'pending', 'pending'];

    if (column_exists($conn, 'orders', 'customer_phone')) {
        $orderColumns[] = 'customer_phone';
        $orderValues[] = '?';
        $types .= 's';
        $params[] = $customerPhone;
    }

    $sql = 'INSERT INTO orders (' . implode(',', $orderColumns) . ') VALUES (' . implode(',', $orderValues) . ')';
    $orderStmt = mysqli_prepare($conn, $sql);
    if (!$orderStmt) {
        throw new RuntimeException('Failed to prepare local order insert');
    }
    mysqli_stmt_bind_param($orderStmt, $types, ...$params);
    if (!mysqli_stmt_execute($orderStmt)) {
        throw new RuntimeException('Failed to create local order');
    }
    $localOrderId = (int)mysqli_insert_id($conn);

    $itemStmt = mysqli_prepare($conn, '
        INSERT INTO order_items (order_id, item_id, item_type, price, discount_price, final_price)
        VALUES (?, ?, "product", ?, ?, ?)
    ');
    if (!$itemStmt) {
        throw new RuntimeException('Failed to prepare order items insert');
    }
    foreach ($items as $item) {
        $pid = (int)$item['id'];
        $price = (float)($item['price'] ?? $item['unit_price']);
        $discount = (float)($item['discount_price'] ?? 0);
        $final = (float)$item['unit_price'];
        $qty = (int)$item['qty'];

        for ($i = 0; $i < $qty; $i++) {
            mysqli_stmt_bind_param($itemStmt, 'iiddd', $localOrderId, $pid, $price, $discount, $final);
            if (!mysqli_stmt_execute($itemStmt)) {
                throw new RuntimeException('Failed to insert order item');
            }
        }
    }

    if (column_exists($conn, 'users', 'phone')) {
        $upUser = mysqli_prepare($conn, 'UPDATE users SET phone=? WHERE id=?');
        if (!$upUser) {
            throw new RuntimeException('Failed to prepare phone update');
        }
        mysqli_stmt_bind_param($upUser, 'si', $customerPhone, $userId);
        if (!mysqli_stmt_execute($upUser)) {
            throw new RuntimeException('Failed to update customer phone');
        }
    }

    $amountPaise = (int)round($total * 100);
    $razorPayload = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => 'luxlut_' . $localOrderId,
        'notes' => [
            'local_order_id' => (string)$localOrderId,
            'user_id' => (string)$userId,
        ],
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($razorPayload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Razorpay order failed: ' . ($curlErr ?: (string)$resp));
    }

    $json = json_decode($resp, true);
    $razorOrderId = $json['id'] ?? '';
    if ($razorOrderId === '') {
        throw new RuntimeException('Invalid Razorpay order response');
    }

    if (column_exists($conn, 'orders', 'razorpay_order_id')) {
        $upOrder = mysqli_prepare($conn, 'UPDATE orders SET razorpay_order_id=? WHERE id=?');
        if (!$upOrder) {
            throw new RuntimeException('Failed to prepare Razorpay order update');
        }
        mysqli_stmt_bind_param($upOrder, 'si', $razorOrderId, $localOrderId);
        if (!mysqli_stmt_execute($upOrder)) {
            throw new RuntimeException('Failed to save Razorpay order ID');
        }
    }

    mysqli_commit($conn);

    json_response([
        'success' => true,
        'local_order_id' => $localOrderId,
        'razorpay_order_id' => $razorOrderId,
        'amount' => $amountPaise,
        'currency' => 'INR',
        'key_id' => RAZORPAY_KEY_ID,
        'name' => APP_NAME,
        'description' => 'Preset Purchase #' . $localOrderId,
        'prefill' => [
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'contact' => $customerPhone,
        ],
        'notes' => [
            'local_order_id' => (string)$localOrderId,
        ],
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
