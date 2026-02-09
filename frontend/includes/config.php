<?php
define('BASE_URL', '/luxlut/');

require_once __DIR__ . '/../../backend/config/database.php';

/* Google OAuth */
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_NEW_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/luxlut/frontend/auth/callback.php');
?>