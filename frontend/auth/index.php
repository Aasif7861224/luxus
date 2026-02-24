<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

$defaultRedirect = BASE_URL . 'frontend/home.php';
$redirect = safe_redirect_target($_GET['redirect'] ?? '', $defaultRedirect);

if (!empty($_SESSION['user_id'])) {
    redirect_to($redirect);
}

$err = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | <?= h(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h3 class="mb-1 text-center"><?= h(APP_NAME) ?> Login</h3>
          <p class="text-muted text-center mb-4">Login required to access cart & checkout</p>

          <?php if ($err !== ''): ?>
            <div class="alert alert-danger py-2"><?= h($err) ?></div>
          <?php endif; ?>

          <form method="post" action="login_process.php" class="vstack gap-3">
            <?= csrf_input() ?>
            <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

            <div>
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required autocomplete="email">
            </div>

            <div>
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>

            <button class="btn btn-dark w-100">Login</button>
            <a href="login.php?redirect=<?= urlencode($redirect) ?>" class="btn btn-outline-secondary w-100">Continue with Google</a>
          </form>
        </div>
      </div>

      <div class="text-center mt-3">
        <a class="text-decoration-none" href="<?= BASE_URL ?>frontend/home.php">&larr; Back to Home</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
