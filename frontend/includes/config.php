<?php
// frontend/includes/config.php

define('BASE_URL', '/LUXUS/');  // folder same hona chahiye

require_once __DIR__ . '/../../backend/config/database.php';

// ===== GOOGLE OAUTH =====
define('GOOGLE_CLIENT_ID', 'PASTE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'PASTE_CLIENT_SECRET_HERE');

// Must match Google console redirect URI exactly:
define('GOOGLE_REDIRECT_URI', 'http://localhost/LUXUS/frontend/auth/callback.php');
