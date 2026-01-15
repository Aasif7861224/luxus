<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Weddingpresets.shop</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#faf7f2; font-family: Georgia, serif; }
        .nav-link { color:#000 !important; }
        .brand { font-size:22px; letter-spacing:1px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-light py-3">
    <div class="container">
        <a class="navbar-brand brand" href="<?= BASE_URL ?>">Weddingpresets.shop</a>

        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
            ☰
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>frontend/category.php?type=presets">Presets</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>frontend/category.php?type=luts">Luts</a></li>
            </ul>

            <a href="<?= BASE_URL ?>frontend/contact.php" class="btn btn-dark btn-sm">Contact Us</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
