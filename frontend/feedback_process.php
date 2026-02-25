<?php
require_once __DIR__ . '/includes/config.php';
ensure_session_started();

$feedbackUrl = BASE_URL . 'frontend/feedback.php';

$fail = function ($message, array $old = []) use ($feedbackUrl) {
    $_SESSION['feedback_flash'] = [
        'error' => $message,
        'old' => $old,
    ];
    redirect_to($feedbackUrl);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to($feedbackUrl);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $fail('Invalid session token. Please try again.');
}

$name = trim((string)($_POST['name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phoneInput = trim((string)($_POST['phone'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$old = [
    'name' => $name,
    'email' => $email,
    'phone' => $phoneInput,
    'message' => $message,
];

$nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
if ($name === '' || $nameLen < 2) {
    $fail('Please enter a valid name.', $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fail('Please enter a valid email address.', $old);
}

$phoneDigits = preg_replace('/\D+/', '', $phoneInput);
if ($phoneDigits === '' || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    $fail('Please enter a valid phone number.', $old);
}

$messageLen = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
if ($message === '' || $messageLen < 5) {
    $fail('Please share a meaningful feedback message.', $old);
}

if (!table_exists($conn, 'feedback')) {
    $fail('Feedback storage is not ready. Please run latest migration first.', $old);
}

$phone = normalize_phone($phoneInput);
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$hasUserId = column_exists($conn, 'feedback', 'user_id');
$hasStatus = column_exists($conn, 'feedback', 'status');

if ($hasUserId && $hasStatus && $userId > 0) {
    $sql = 'INSERT INTO feedback (user_id, name, email, phone, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $fail('Unable to save feedback right now.', $old);
    }
    $status = 'new';
    mysqli_stmt_bind_param($stmt, 'isssss', $userId, $name, $email, $phone, $message, $status);
} elseif ($hasStatus) {
    $sql = 'INSERT INTO feedback (name, email, phone, message, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $fail('Unable to save feedback right now.', $old);
    }
    $status = 'new';
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $phone, $message, $status);
} elseif ($hasUserId && $userId > 0) {
    $sql = 'INSERT INTO feedback (user_id, name, email, phone, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $fail('Unable to save feedback right now.', $old);
    }
    mysqli_stmt_bind_param($stmt, 'issss', $userId, $name, $email, $phone, $message);
} else {
    $sql = 'INSERT INTO feedback (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, NOW())';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $fail('Unable to save feedback right now.', $old);
    }
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $message);
}

if (!mysqli_stmt_execute($stmt)) {
    $fail('Unable to save feedback right now.', $old);
}

$_SESSION['feedback_flash'] = [
    'success' => 'Thank you! Your feedback has been submitted.',
];
redirect_to($feedbackUrl);
