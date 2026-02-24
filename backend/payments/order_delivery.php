<?php
require_once __DIR__ . '/../helpers/app.php';
require_once __DIR__ . '/../integrations/whatsapp_client.php';

if (!function_exists('delivery_log')) {
    function delivery_log($conn, $orderId, $status, $payload, $channel = 'whatsapp')
    {
        if (!table_exists($conn, 'delivery_logs')) {
            return;
        }

        $payloadText = is_string($payload) ? $payload : json_encode($payload);
        $stmt = mysqli_prepare($conn, 'INSERT INTO delivery_logs (order_id, channel, status, response_payload, sent_at) VALUES (?, ?, ?, ?, NOW())');
        mysqli_stmt_bind_param($stmt, 'isss', $orderId, $channel, $status, $payloadText);
        mysqli_stmt_execute($stmt);
    }
}

if (!function_exists('already_delivered_whatsapp')) {
    function already_delivered_whatsapp($conn, $orderId)
    {
        if (!table_exists($conn, 'delivery_logs')) {
            return false;
        }

        $stmt = mysqli_prepare($conn, "SELECT id FROM delivery_logs WHERE order_id=? AND channel='whatsapp' AND status='sent' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('deliver_order_links_via_whatsapp')) {
    function deliver_order_links_via_whatsapp($conn, $orderId)
    {
        if (already_delivered_whatsapp($conn, $orderId)) {
            delivery_log($conn, $orderId, 'skipped', 'Already delivered');
            return [
                'success' => true,
                'status' => 'skipped',
            ];
        }

        $hasCustomerPhone = column_exists($conn, 'orders', 'customer_phone');
        $phoneSql = $hasCustomerPhone ? 'o.customer_phone' : 'u.phone';
        $stmt = mysqli_prepare($conn, "
            SELECT o.id, o.total_amount, {$phoneSql} AS customer_phone, u.name
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$order) {
            delivery_log($conn, $orderId, 'failed', 'Order not found');
            return ['success' => false, 'status' => 'failed', 'message' => 'Order not found'];
        }

        $itemStmt = mysqli_prepare($conn, "
            SELECT p.title, oi.final_price, dp.drive_link
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.item_id
            LEFT JOIN digital_products dp ON dp.product_id = p.id AND dp.is_active = 1
            WHERE oi.order_id = ?
        ");
        mysqli_stmt_bind_param($itemStmt, 'i', $orderId);
        mysqli_stmt_execute($itemStmt);
        $itemsRes = mysqli_stmt_get_result($itemStmt);

        $lines = [];
        $count = 1;
        while ($it = mysqli_fetch_assoc($itemsRes)) {
            $title = $it['title'] ?: 'Preset';
            $link = trim((string)($it['drive_link'] ?? ''));
            if ($link === '') {
                continue;
            }
            $lines[] = $count . '. ' . $title . ': ' . $link;
            $count++;
        }

        if (empty($lines)) {
            delivery_log($conn, $orderId, 'failed', 'No drive link configured for order items');
            return ['success' => false, 'status' => 'failed', 'message' => 'No drive links configured'];
        }

        $message = "Thank you for purchasing " . APP_NAME . " Preset ✅\n";
        $message .= "Amount: ₹" . number_format((float)$order['total_amount'], 0) . "\n";
        $message .= "Order ID: #" . (int)$order['id'] . "\n\n";
        $message .= "Download Links:\n" . implode("\n", $lines);

        $send = whatsapp_send_text($order['customer_phone'] ?? '', $message);
        if (!empty($send['success'])) {
            delivery_log($conn, $orderId, 'sent', $send['response']);
            return ['success' => true, 'status' => 'sent'];
        }

        delivery_log($conn, $orderId, 'failed', $send['response'] ?? 'Unknown delivery error');
        return ['success' => false, 'status' => 'failed', 'message' => $send['response'] ?? 'Delivery failed'];
    }
}

if (!function_exists('mark_order_paid_and_deliver')) {
    function mark_order_paid_and_deliver($conn, $orderId, $razorpayPaymentId = '')
    {
        $updates = ['payment_status = ?'];
        $params = ['paid'];
        $types = 's';

        if (column_exists($conn, 'orders', 'order_status')) {
            $updates[] = 'order_status = ?';
            $params[] = 'paid';
            $types .= 's';
        }

        if (column_exists($conn, 'orders', 'razorpay_payment_id')) {
            $updates[] = 'razorpay_payment_id = ?';
            $params[] = $razorpayPaymentId;
            $types .= 's';
        }

        if (column_exists($conn, 'orders', 'paid_at')) {
            $updates[] = 'paid_at = NOW()';
        }

        $sql = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $types .= 'i';
        $params[] = (int)$orderId;

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);

        return deliver_order_links_via_whatsapp($conn, (int)$orderId);
    }
}
