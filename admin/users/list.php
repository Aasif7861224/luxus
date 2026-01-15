<?php
require_once "../includes/header.php";
require_once "../../backend/config/database.php";

// Search
$search = trim($_GET['search'] ?? '');
$searchLike = "%" . $search . "%";

if ($search !== '') {
    $stmt = mysqli_prepare($conn, "
        SELECT * FROM users
        WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
        ORDER BY id DESC
    ");
    mysqli_stmt_bind_param($stmt, "sss", $searchLike, $searchLike, $searchLike);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
}
?>

<h4 class="mb-4">Users</h4>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-6 col-12">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name, email, phone..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3 col-6">
                <button class="btn btn-dark w-100" type="submit">Search</button>
            </div>
            <div class="col-md-3 col-6">
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
                    <th width="60">#ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Google ID</th>
                    <th width="160">Joined</th>
                </tr>
            </thead>
            <tbody>

            <?php if (mysqli_num_rows($result) == 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No users found</td>
                </tr>
            <?php endif; ?>

            <?php while ($u = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><strong><?= htmlspecialchars($u['name'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($u['google_id'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
                </tr>
            <?php endwhile; ?>

            </tbody>
        </table>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
