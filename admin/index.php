<?php
require_once __DIR__ . '/../backend/config/constants.php';
require_once __DIR__ . '/../backend/helpers/app.php';

ensure_session_started();
if (!empty($_SESSION['admin_id'])) {
    redirect_to('dashboard.php');
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login | <?= h(APP_NAME) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-lg-4 col-md-6 col-12">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <h4 class="text-center mb-3">Admin Login</h4>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger text-center"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" class="vstack gap-3">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-dark w-100">Login</button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 text-muted small"><?= h(APP_NAME) ?> Admin Panel</p>
        </div>
    </div>
</div>
</body>
</html>
