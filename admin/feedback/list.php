<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../backend/config/database.php';

$allowedStatuses = ['new', 'reviewed'];
$hasStatus = table_exists($conn, 'feedback') && column_exists($conn, 'feedback', 'status');
$hasUserId = table_exists($conn, 'feedback') && column_exists($conn, 'feedback', 'user_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnSearch = trim((string)($_POST['return_search'] ?? ''));
    $returnStatus = (string)($_POST['return_status'] ?? '');
    if (!in_array($returnStatus, $allowedStatuses, true)) {
        $returnStatus = '';
    }

    $redirect = 'list.php';
    $query = [];
    if ($returnSearch !== '') {
        $query['search'] = $returnSearch;
    }
    if ($returnStatus !== '') {
        $query['status'] = $returnStatus;
    }
    if (!empty($query)) {
        $redirect .= '?' . http_build_query($query);
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['feedback_admin_flash'] = ['error' => 'Invalid session token.'];
        redirect_to($redirect);
    }

    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'mark_reviewed' && $id > 0 && $hasStatus) {
        $status = 'reviewed';
        $stmt = mysqli_prepare($conn, 'UPDATE feedback SET status=? WHERE id=? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $status, $id);
            mysqli_stmt_execute($stmt);
            $_SESSION['feedback_admin_flash'] = ['success' => 'Feedback marked as reviewed.'];
        } else {
            $_SESSION['feedback_admin_flash'] = ['error' => 'Unable to update feedback status.'];
        }
    }

    redirect_to($redirect);
}

$flash = $_SESSION['feedback_admin_flash'] ?? [];
unset($_SESSION['feedback_admin_flash']);

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!table_exists($conn, 'feedback')) {
    ?>
    <h4 class="mb-4">Feedback</h4>
    <div class="alert alert-warning">Feedback table not found. Please run latest migration first.</div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$userColumn = $hasUserId ? 'user_id' : 'NULL AS user_id';

if ($search !== '' && $statusFilter !== '' && $hasStatus) {
    $like = '%' . $search . '%';
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, ' . $userColumn . ', name, email, phone, message, status, created_at
         FROM feedback
         WHERE status=? AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
         ORDER BY id DESC'
    );
    mysqli_stmt_bind_param($stmt, 'ssss', $statusFilter, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif ($search !== '') {
    $like = '%' . $search . '%';
    $select = $hasStatus
        ? 'SELECT id, ' . $userColumn . ', name, email, phone, message, status, created_at FROM feedback'
        : 'SELECT id, ' . $userColumn . ', name, email, phone, message, "new" AS status, created_at FROM feedback';
    $stmt = mysqli_prepare(
        $conn,
        $select . ' WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC'
    );
    mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif ($statusFilter !== '' && $hasStatus) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, ' . $userColumn . ', name, email, phone, message, status, created_at FROM feedback WHERE status=? ORDER BY id DESC'
    );
    mysqli_stmt_bind_param($stmt, 's', $statusFilter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $select = $hasStatus
        ? 'SELECT id, ' . $userColumn . ', name, email, phone, message, status, created_at FROM feedback'
        : 'SELECT id, ' . $userColumn . ', name, email, phone, message, "new" AS status, created_at FROM feedback';
    $result = mysqli_query($conn, $select . ' ORDER BY id DESC');
}
?>

<h4 class="mb-4">Feedback</h4>

<?php if (!empty($flash['success'])): ?>
  <div class="alert alert-success py-2"><?= h($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
  <div class="alert alert-danger py-2"><?= h($flash['error']) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2" method="GET">
      <div class="col-md-5 col-12">
        <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone..." value="<?= h($search) ?>">
      </div>
      <div class="col-md-3 col-12">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
          <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
        </select>
      </div>
      <div class="col-md-2 col-6">
        <button class="btn btn-dark w-100" type="submit">Filter</button>
      </div>
      <div class="col-md-2 col-6">
        <a class="btn btn-secondary w-100" href="list.php">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th width="70">#ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <?php if ($hasUserId): ?>
            <th width="90">User ID</th>
          <?php endif; ?>
          <th>Message</th>
          <th width="110">Status</th>
          <th width="170">Created</th>
          <th width="130">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$result || mysqli_num_rows($result) === 0): ?>
        <tr>
          <td colspan="<?= $hasUserId ? '9' : '8' ?>" class="text-center text-muted">No feedback found</td>
        </tr>
      <?php else: ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <?php
            $status = (string)($row['status'] ?? 'new');
            $badge = $status === 'reviewed' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
            $preview = (string)($row['message'] ?? '');
            $previewLen = function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview);
            if ($previewLen > 90) {
                $preview = function_exists('mb_substr') ? mb_substr($preview, 0, 90) : substr($preview, 0, 90);
                $preview .= '...';
            }
          ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= h($row['name'] ?? '-') ?></td>
            <td><?= h($row['email'] ?? '-') ?></td>
            <td><?= h($row['phone'] ?? '-') ?></td>
            <?php if ($hasUserId): ?>
              <td><?= !empty($row['user_id']) ? (int)$row['user_id'] : '-' ?></td>
            <?php endif; ?>
            <td><?= h($preview) ?></td>
            <td><span class="badge <?= $badge ?>"><?= h(ucfirst($status)) ?></span></td>
            <td><?= h($row['created_at'] ?? '-') ?></td>
            <td>
              <?php if ($hasStatus && $status !== 'reviewed'): ?>
                <form method="POST">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="mark_reviewed">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="return_search" value="<?= h($search) ?>">
                  <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
                  <button class="btn btn-sm btn-outline-dark w-100">Mark Reviewed</button>
                </form>
              <?php else: ?>
                <span class="text-muted small">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
