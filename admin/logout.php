<?php
require_once __DIR__ . '/../backend/helpers/app.php';
ensure_session_started();

session_unset();
session_destroy();

redirect_to('index.php');
