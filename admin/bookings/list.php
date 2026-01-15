<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

// ---------------------------
// Update Booking Status (POST)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {

    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $status     = $_POST['status'] ?? 'pending';

    if ($booking_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE wedding_bookings SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);
        mysqli_stmt_execute($stmt);
    }

    header("Location: list.php");
    exit;
}

// ---------------------------
// Fetch Bookings
// ---------------------------
$query = "
SELECT wb.*, 
       u.name  AS user_name, 
       u.email AS user_email,
       s.name  AS service_name
FROM wedding_bookings wb
LEFT JOIN users u ON wb.user_id = u.id
LEFT JOIN services s ON wb.service_id = s.id
ORDER BY wb.id DESC
";
$result = mysqli_query($conn, $query);

$statusOptions = [
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed'
];
?>

<h4 class="mb-4">Wedding Bookings</h4>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th width="60">#ID</th>
                    <th>User</th>
                    <th>Service</th>
                    <th>Wedding Date</th>
                    <th>City</th>
                    <th>Event Type</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>

            <?php if (mysqli_num_rows($result) == 0): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">No bookings found</td>
                </tr>
            <?php endif; ?>

            <?php while ($b = mysqli_fetch_assoc($result)): ?>
                <?php
                    $bookingId = (int)$b['id'];

                    $badge = "warning";
                    if ($b['status'] === 'confirmed') $badge = "primary";
                    if ($b['status'] === 'completed') $badge = "success";

                    $notes = trim($b['notes'] ?? '');
                    $notesShort = $notes ? (strlen($notes) > 40 ? substr($notes, 0, 40) . "..." : $notes) : "-";
                ?>

                <tr>
                    <td><?= $bookingId ?></td>

                    <td>
                        <div><strong><?= htmlspecialchars($b['user_name'] ?? 'Guest') ?></strong></div>
                        <div class="text-muted small"><?= htmlspecialchars($b['user_email'] ?? '-') ?></div>
                    </td>

                    <td><?= htmlspecialchars($b['service_name'] ?? 'Unknown') ?></td>

                    <td><?= htmlspecialchars($b['wedding_date']) ?></td>

                    <td><?= htmlspecialchars($b['city'] ?? '-') ?></td>

                    <td><?= htmlspecialchars($b['event_type'] ?? '-') ?></td>

                    <td title="<?= htmlspecialchars($notes) ?>"><?= htmlspecialchars($notesShort) ?></td>

                    <td>
                        <span class="badge bg-<?= $badge ?>">
                            <?= strtoupper($b['status']) ?>
                        </span>
                    </td>

                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal<?= $bookingId ?>">
                            Update
                        </button>
                    </td>
                </tr>

                <!-- Modal -->
                <div class="modal fade" id="bookingModal<?= $bookingId ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">

                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Booking #<?= $bookingId ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                    <input type="hidden" name="update_booking" value="1">

                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control">
                                            <?php foreach ($statusOptions as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= $b['status']==$val?'selected':'' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="small text-muted">
                                        <div><strong>User:</strong> <?= htmlspecialchars($b['user_name'] ?? '-') ?></div>
                                        <div><strong>Service:</strong> <?= htmlspecialchars($b['service_name'] ?? '-') ?></div>
                                        <div><strong>Wedding Date:</strong> <?= htmlspecialchars($b['wedding_date']) ?></div>
                                        <div><strong>City:</strong> <?= htmlspecialchars($b['city'] ?? '-') ?></div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button class="btn btn-dark" type="submit">Save</button>
                                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            <?php endwhile; ?>

            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS (Modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once "../includes/footer.php"; ?>
