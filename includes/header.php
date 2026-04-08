<?php
// includes/header.php
$user = currentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="role-<?= $user ? $user['role'] : 'guest' ?>">

<?php if ($user): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon">⚗</span>
        <span class="brand-name"><?= APP_NAME ?></span>
    </div>
    <div class="navbar-links">
        <?php if ($user['role'] === 'petugas'): ?>
            <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="<?= strpos($_SERVER['PHP_SELF'], '/petugas/dashboard') !== false ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= BASE_URL ?>/petugas/barang.php" class="<?= strpos($_SERVER['PHP_SELF'], '/petugas/barang') !== false ? 'active' : '' ?>">Barang</a>
            <a href="<?= BASE_URL ?>/petugas/peminjaman.php" class="<?= strpos($_SERVER['PHP_SELF'], '/petugas/peminjaman') !== false ? 'active' : '' ?>">Peminjaman</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/siswa/katalog.php" class="<?= strpos($_SERVER['PHP_SELF'], '/siswa/katalog') !== false ? 'active' : '' ?>">Katalog</a>
            <a href="<?= BASE_URL ?>/siswa/peminjaman.php" class="<?= strpos($_SERVER['PHP_SELF'], '/siswa/peminjaman') !== false ? 'active' : '' ?>">Peminjaman Saya</a>
        <?php endif; ?>
    </div>
    <div class="navbar-user">
        <div class="user-info">
            <span class="user-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></span>
            <span class="user-name"><?= sanitize($user['nama']) ?></span>
            <span class="user-role-badge"><?= $user['role'] === 'petugas' ? 'Petugas' : 'Siswa' ?></span>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-logout">Keluar</a>
    </div>
</nav>
<?php endif; ?>

<main class="main-content">
<?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
        <span class="flash-icon"><?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'error' ? '✕' : 'ℹ') ?></span>
        <?= sanitize($flash['message']) ?>
    </div>
<?php endif; ?>