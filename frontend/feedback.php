<?php
require_once __DIR__ . '/includes/config.php';
ensure_session_started();

$flash = $_SESSION['feedback_flash'] ?? [];
unset($_SESSION['feedback_flash']);

$error = (string)($flash['error'] ?? '');
$success = (string)($flash['success'] ?? '');
$old = $flash['old'] ?? [];

$prefill = [
    'name' => (string)($_SESSION['user_name'] ?? ''),
    'email' => (string)($_SESSION['user_email'] ?? ''),
    'phone' => '',
    'message' => '',
];

if (!empty($_SESSION['user_id']) && column_exists($conn, 'users', 'phone')) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, 'SELECT phone FROM users WHERE id=? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $prefill['phone'] = (string)($row['phone'] ?? '');
    }
}

$formData = [
    'name' => (string)($old['name'] ?? $prefill['name']),
    'email' => (string)($old['email'] ?? $prefill['email']),
    'phone' => (string)($old['phone'] ?? $prefill['phone']),
    'message' => (string)($old['message'] ?? $prefill['message']),
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="bg-white border rounded-4 p-4" style="border-color:#e6dfd6!important;">
      <h2 class="lux-title mb-2">Feedback</h2>
      <p class="text-muted mb-4">Apna experience share karein. Team review karke improve karegi.</p>

      <?php if ($success !== ''): ?>
        <div class="alert alert-success py-2"><?= h($success) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>frontend/feedback_process.php" class="vstack gap-3">
        <?= csrf_input() ?>

        <div>
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" maxlength="100" required value="<?= h($formData['name']) ?>" autocomplete="name">
        </div>

        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" maxlength="120" required value="<?= h($formData['email']) ?>" autocomplete="email">
        </div>

        <div>
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" maxlength="20" required value="<?= h($formData['phone']) ?>" autocomplete="tel">
        </div>

        <div>
          <label class="form-label">Message</label>
          <textarea name="message" class="form-control" rows="5" maxlength="2000" required><?= h($formData['message']) ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-dark">Submit Feedback</button>
          <a class="btn btn-outline-dark" href="<?= BASE_URL ?>frontend/contact.php">Back to Contact</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
