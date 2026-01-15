<?php
require_once "../config/database.php";

// Test query
$result = mysqli_query($conn, "SELECT * FROM admins");

if ($result) {
    echo "Database connected successfully";
}
