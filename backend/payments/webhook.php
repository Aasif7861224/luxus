<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/app.php';
require_once __DIR__ . '/order_delivery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (RAZORPAY_WEBHOOK_SECRET === '') {
    json_response(['success' => false, 'message' => 'Webhook secret not configured'], 500);
}

$raw = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $raw, RAZORPAY_WEBHOOK_SECRET);

if ($signature === '' || !hash_equals($expected, $signature)) {
    json_response(['success' => false, 'message' => 'Invalid webhook signature'], 403);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid JSON'], 422);
}

$event = $payload['event'] ?? '';
if (!in_array($event, ['payment.captured', 'order.paid'], true)) {
    json_response(['success' => true, 'message' => 'Event ignored']);
}

$paymentEntity = $payload['payload']['payment']['entity'] ?? [];
$orderEntity = $payload['payload']['order']['entity'] ?? [];

$razorpayOrderId = (string)($paymentEntity['order_id'] ?? ($orderEntity['id'] ?? ''));
$paymentId = (string)($paymentEntity['id'] ?? '');
$notes = $paymentEntity['notes'] ?? ($orderEntity['notes'] ?? []);
$localOrderId = (int)($notes['local_order_id'] ?? 0);

if ($localOrderId <= 0 && $razorpayOrderId !== '' && column_exists($conn, 'orders', 'razorpay_order_id')) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM orders WHERE razorpay_order_id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $razorpayOrderId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $localOrderId = (int)($row['id'] ?? 0);
}

if ($localOrderId <= 0) {
    json_response(['success' => false, 'message' => 'Local order not found'], 404);
}

$delivery = mark_order_paid_and_deliver($conn, $localOrderId, $paymentId);
json_response([
    'success' => true,
    'order_id' => $localOrderId,
    'delivery' => $delivery,
]);
