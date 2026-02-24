<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/app.php';
require_once __DIR__ . '/order_delivery.php';

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

$localOrderId = (int)($_POST['local_order_id'] ?? 0);
$paymentId = trim($_POST['razorpay_payment_id'] ?? '');
$razorpayOrderId = trim($_POST['razorpay_order_id'] ?? '');
$signature = trim($_POST['razorpay_signature'] ?? '');

if ($localOrderId <= 0 || $paymentId === '' || $razorpayOrderId === '' || $signature === '') {
    json_response(['success' => false, 'message' => 'Missing payment fields'], 422);
}

if (RAZORPAY_KEY_SECRET === '') {
    json_response(['success' => false, 'message' => 'Razorpay secret not configured'], 500);
}

$userId = (int)$_SESSION['user_id'];
$hasRazorOrderCol = column_exists($conn, 'orders', 'razorpay_order_id');
$selectSql = $hasRazorOrderCol
    ? 'SELECT id, user_id, payment_status, razorpay_order_id FROM orders WHERE id=? LIMIT 1'
    : 'SELECT id, user_id, payment_status, "" AS razorpay_order_id FROM orders WHERE id=? LIMIT 1';

$orderStmt = mysqli_prepare($conn, $selectSql);
mysqli_stmt_bind_param($orderStmt, 'i', $localOrderId);
mysqli_stmt_execute($orderStmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($orderStmt));

if (!$order) {
    json_response(['success' => false, 'message' => 'Order not found'], 404);
}

if ((int)$order['user_id'] !== $userId) {
    json_response(['success' => false, 'message' => 'Unauthorized order access'], 403);
}

if (!empty($order['razorpay_order_id']) && !hash_equals($order['razorpay_order_id'], $razorpayOrderId)) {
    json_response(['success' => false, 'message' => 'Order mismatch'], 422);
}

$payload = $razorpayOrderId . '|' . $paymentId;
$generated = hash_hmac('sha256', $payload, RAZORPAY_KEY_SECRET);
if (!hash_equals($generated, $signature)) {
    json_response(['success' => false, 'message' => 'Invalid payment signature'], 422);
}

$delivery = mark_order_paid_and_deliver($conn, $localOrderId, $paymentId);

unset($_SESSION['cart']);
$_SESSION['cart'] = [];

json_response([
    'success' => true,
    'message' => 'Payment verified',
    'delivery' => $delivery,
]);
