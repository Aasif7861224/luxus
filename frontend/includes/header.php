<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LuxLut — Wedding Presets & LUTs</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#f7f2ea;
      --ink:#121212;
      --muted:#6b6b6b;
      --border:#e6dfd6;
      --card:#ffffff;
    }
    body{ background:var(--bg); color:var(--ink); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto; }
    .lux-brand{ font-family:"Playfair Display", serif; letter-spacing:.4px; }
    .lux-nav{ background:rgba(247,242,234,.92); border-bottom:1px solid var(--border); backdrop-filter: blur(8px); }
    .lux-link{ color:var(--ink)!important; font-size:14px; }
    .lux-link:hover{ opacity:.7; }
    .lux-btn-outline{ border:1px solid var(--ink); color:var(--ink); background:transparent; }
    .lux-btn-outline:hover{ background:var(--ink); color:#fff; }
    .lux-container{ max-width:1200px; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg lux-nav sticky-top py-3">
  <div class="container lux-container">
    <a class="navbar-brand lux-brand fw-semibold" href="<?= BASE_URL ?>">
      LuxLut
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#luxNav"
            aria-controls="luxNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="luxNav">
      <ul class="navbar-nav mx-auto gap-lg-3">
        <li class="nav-item"><a class="nav-link lux-link" href="<?= BASE_URL ?>">Home</a></li>
        <li class="nav-item"><a class="nav-link lux-link" href="<?= BASE_URL ?>frontend/category.php?type=presets">Presets</a></li>
        <li class="nav-item"><a class="nav-link lux-link" href="<?= BASE_URL ?>frontend/category.php?type=luts">LUTs</a></li>
        <li class="nav-item"><a class="nav-link lux-link" href="<?= BASE_URL ?>frontend/contact.php">Contact</a></li>
      </ul>

      <div class="d-flex gap-2">
        <a class="btn btn-sm lux-btn-outline" href="<?= BASE_URL ?>frontend/cart.php">Cart</a>
        <a class="btn btn-sm btn-dark" href="<?= BASE_URL ?>frontend/checkout.php">Buy Now</a>
      </div>
    </div>
  </div>
</nav>

<div class="container lux-container py-4">
