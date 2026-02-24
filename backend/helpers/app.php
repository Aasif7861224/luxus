<?php
if (!function_exists('ensure_session_started')) {
    function ensure_session_started()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url()
    {
        if (defined('APP_BASE_URL')) {
            return APP_BASE_URL;
        }
        return '/';
    }
}

if (!function_exists('app_admin_base_url')) {
    function app_admin_base_url()
    {
        return rtrim(app_base_url(), '/') . '/admin';
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to($url)
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('safe_redirect_target')) {
    function safe_redirect_target($candidate, $default)
    {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            return $default;
        }

        if (preg_match('#^https?://#i', $candidate) || str_starts_with($candidate, '//')) {
            return $default;
        }

        $parts = parse_url($candidate);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        if ($path === '' || str_contains($path, '..')) {
            return $default;
        }

        $base = app_base_url();
        if (!str_starts_with($path, $base)) {
            return $default;
        }

        return $path . $query;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        ensure_session_started();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input()
    {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token)
    {
        ensure_session_started();
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], (string)$token);
    }
}

if (!function_exists('require_csrf_or_abort')) {
    function require_csrf_or_abort($json = false)
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (verify_csrf_token($token)) {
            return;
        }

        if ($json) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token'], 419);
        }

        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

if (!function_exists('json_response')) {
    function json_response($payload, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('column_exists')) {
    function column_exists($conn, $table, $column)
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }

        $tableEsc = '`' . $table . '`';
        $columnEsc = mysqli_real_escape_string($conn, $column);
        $sql = "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'";
        $result = mysqli_query($conn, $sql);
        $cache[$key] = $result && mysqli_num_rows($result) > 0;
        return $cache[$key];
    }
}

if (!function_exists('table_exists')) {
    function table_exists($conn, $table)
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }

        $tableEsc = mysqli_real_escape_string($conn, $table);
        $sql = "SHOW TABLES LIKE '{$tableEsc}'";
        $result = mysqli_query($conn, $sql);
        $cache[$table] = $result && mysqli_num_rows($result) > 0;
        return $cache[$table];
    }
}

if (!function_exists('normalize_phone')) {
    function normalize_phone($phone)
    {
        $defaultCountry = defined('WHATSAPP_DEFAULT_COUNTRY') ? WHATSAPP_DEFAULT_COUNTRY : '91';
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        if (!str_starts_with($digits, $defaultCountry)) {
            $digits = $defaultCountry . $digits;
        }

        return $digits;
    }
}

if (!function_exists('final_price_from_row')) {
    function final_price_from_row(array $row)
    {
        $price = (float)($row['price'] ?? 0);
        $discount = (float)($row['discount_price'] ?? 0);
        return $discount > 0 ? $discount : $price;
    }
}

if (!function_exists('fetch_cart_items')) {
    function fetch_cart_items($conn, array $cart)
    {
        $items = [];
        $total = 0.0;
        $ids = array_map('intval', array_keys($cart));
        $ids = array_values(array_filter($ids, fn($id) => $id > 0));
        if (empty($ids)) {
            return [$items, $total];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "
            SELECT
                p.id,
                p.title,
                p.before_image,
                p.after_image,
                (SELECT pp.price FROM product_prices pp WHERE pp.product_id = p.id ORDER BY pp.id DESC LIMIT 1) AS price,
                (SELECT pp.discount_price FROM product_prices pp WHERE pp.product_id = p.id ORDER BY pp.id DESC LIMIT 1) AS discount_price
            FROM products p
            WHERE p.status = 'active' AND p.id IN ($placeholders)
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [$items, $total];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$ids);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($res)) {
            $pid = (int)$row['id'];
            $qty = max(0, min(99, (int)($cart[$pid] ?? 0)));
            if ($qty <= 0) {
                continue;
            }

            $unit = final_price_from_row($row);
            $subtotal = $unit * $qty;
            $total += $subtotal;

            $row['qty'] = $qty;
            $row['unit_price'] = $unit;
            $row['subtotal'] = $subtotal;
            $items[$pid] = $row;
        }

        return [$items, $total];
    }
}

if (!function_exists('cart_session')) {
    function cart_session()
    {
        ensure_session_started();
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }
}
