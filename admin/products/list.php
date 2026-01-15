<?php
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
require_once "../../backend/config/database.php";

$query = "
SELECT p.id, p.title, p.status, c.name AS category
FROM products p
JOIN categories c ON p.category_id = c.id
ORDER BY p.id DESC
";
$result = mysqli_query($conn, $query);
?>

<h4 class="mb-4">Products</h4>

<a href="add.php" class="btn btn-dark mb-3">+ Add Product</a>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['category'] ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                    <td>
                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
