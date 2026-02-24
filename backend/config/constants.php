<?php
require_once __DIR__ . '/env.php';

if (!defined('APP_NAME')) {
    define('APP_NAME', app_env('APP_NAME', 'LuxLut'));
}

if (!defined('APP_BASE_URL')) {
    $baseUrl = app_env('APP_BASE_URL', '/luxlut/');
    $baseUrl = '/' . trim($baseUrl, '/') . '/';
    define('APP_BASE_URL', $baseUrl);
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', app_env('GOOGLE_CLIENT_ID', ''));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', app_env('GOOGLE_CLIENT_SECRET', ''));
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', app_env('GOOGLE_REDIRECT_URI', 'http://localhost/luxlut/frontend/auth/callback.php'));
}

if (!defined('RAZORPAY_KEY_ID')) {
    define('RAZORPAY_KEY_ID', app_env('RAZORPAY_KEY_ID', ''));
}
if (!defined('RAZORPAY_KEY_SECRET')) {
    define('RAZORPAY_KEY_SECRET', app_env('RAZORPAY_KEY_SECRET', ''));
}
if (!defined('RAZORPAY_WEBHOOK_SECRET')) {
    define('RAZORPAY_WEBHOOK_SECRET', app_env('RAZORPAY_WEBHOOK_SECRET', ''));
}

if (!defined('WHATSAPP_TOKEN')) {
    define('WHATSAPP_TOKEN', app_env('WHATSAPP_TOKEN', ''));
}
if (!defined('WHATSAPP_PHONE_NUMBER_ID')) {
    define('WHATSAPP_PHONE_NUMBER_ID', app_env('WHATSAPP_PHONE_NUMBER_ID', ''));
}
if (!defined('WHATSAPP_VERIFY_TOKEN')) {
    define('WHATSAPP_VERIFY_TOKEN', app_env('WHATSAPP_VERIFY_TOKEN', ''));
}
if (!defined('WHATSAPP_DEFAULT_COUNTRY')) {
    define('WHATSAPP_DEFAULT_COUNTRY', app_env('WHATSAPP_DEFAULT_COUNTRY', '91'));
}
