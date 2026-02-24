<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

unset(
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['login_redirect'],
    $_SESSION['oauth_state']
);

redirect_to(BASE_URL . 'frontend/home.php');
