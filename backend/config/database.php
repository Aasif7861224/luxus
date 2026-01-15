<?php
// database.php

$host = "localhost";
$dbname = "wedding_studio";
$username = "root";
$password = "";

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: set charset
mysqli_set_charset($conn, "utf8");
