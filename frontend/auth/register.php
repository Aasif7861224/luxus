<?php
require_once __DIR__ . '/../includes/config.php';
ensure_session_started();

$defaultRedirect = BASE_URL . 'frontend/home.php';
$redirect = safe_redirect_target($_GET['redirect'] ?? '', $defaultRedirect);

if (!empty($_SESSION['user_id'])) {
    redirect_to($redirect);
}

$flash = $_SESSION['register_flash'] ?? [];
unset($_SESSION['register_flash']);

$error = (string)($flash['error'] ?? '');
$old = $flash['old'] ?? [];
$name = (string)($old['name'] ?? '');
$email = (string)($old['email'] ?? '');
$phone = (string)($old['phone'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register | <?= h(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f6f1ea;
      --ink:#151515;
      --muted:#666;
      --border:#e6dfd6;
      --card:#fff;
    }
    body{
      min-height:100vh;
      background:
        radial-gradient(1000px 500px at 90% -10%, #fff6ea 0, rgba(255,246,234,0) 70%),
        radial-gradient(900px 450px at 10% 110%, #efe3d4 0, rgba(239,227,212,0) 70%),
        var(--bg);
      color:var(--ink);
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    .auth-shell{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      overflow:hidden;
      box-shadow:0 18px 40px rgba(0,0,0,.08);
    }
    .auth-brand{
      background:linear-gradient(165deg, #161616 0%, #2a2a2a 100%);
      color:#fff;
      padding:2rem;
      height:100%;
    }
    .lux-brand{ font-family:"Playfair Display", serif; }
    .auth-list{ margin:0; padding-left:1.1rem; }
    .auth-list li{ margin-bottom:.45rem; color:#ececec; font-size:.92rem; }
    .auth-muted{ color:#7b7b7b; }
    .auth-right{ padding:2rem; }
    .auth-card-title{ font-family:"Playfair Display", serif; margin-bottom:.35rem; }
    .auth-link{ color:#151515; text-decoration:none; font-weight:600; }
    .auth-link:hover{ text-decoration:underline; }
    .google-btn{
      border:1px solid #cfc7bd;
      background:#fff;
      color:#202124;
      font-weight:500;
    }
    .google-btn:hover{
      border-color:#b8afa4;
      background:#faf8f6;
      color:#202124;
    }
  </style>
</head>
<body>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="auth-shell">
        <div class="row g-0">
          <div class="col-lg-5 d-none d-lg-block">
            <div class="auth-brand">
              <h3 class="lux-brand mb-3"><?= h(APP_NAME) ?></h3>
              <p class="mb-4">Agar Google login use nahi karna, to yahan se manual account bana sakte ho.</p>
              <ul class="auth-list">
                <li>Email/password based secure access</li>
                <li>Phone number checkout prefill ke liye save hoga</li>
                <li>Google login future me bhi usable rahega</li>
              </ul>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="auth-right">
              <h3 class="auth-card-title">Create Account</h3>
              <p class="auth-muted mb-4">Name, email, phone aur password ke saath register karein.</p>

              <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2"><?= h($error) ?></div>
              <?php endif; ?>

              <form method="post" action="register_process.php" class="vstack gap-3 mb-3">
                <?= csrf_input() ?>
                <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

                <div>
                  <label class="form-label">Full Name</label>
                  <input type="text" name="name" class="form-control" required maxlength="100" value="<?= h($name) ?>" autocomplete="name">
                </div>

                <div>
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" required maxlength="120" value="<?= h($email) ?>" autocomplete="email">
                </div>

                <div>
                  <label class="form-label">Phone Number</label>
                  <input type="tel" name="phone" class="form-control" required maxlength="20" value="<?= h($phone) ?>" autocomplete="tel">
                </div>

                <div>
                  <label class="form-label">Password</label>
                  <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                </div>

                <div>
                  <label class="form-label">Confirm Password</label>
                  <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                </div>

                <button class="btn btn-dark w-100">Create Account</button>
              </form>

              <a href="login.php?redirect=<?= urlencode($redirect) ?>" class="btn google-btn w-100 mb-3">Continue with Google</a>

              <div class="small auth-muted">
                Already have account?
                <a class="auth-link" href="index.php?redirect=<?= urlencode($redirect) ?>">Login</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center mt-3">
        <a class="text-decoration-none auth-link fw-normal" href="<?= BASE_URL ?>frontend/home.php">&larr; Back to Home</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
