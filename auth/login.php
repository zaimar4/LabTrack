<?php
// auth/login.php
require_once __DIR__ . '/../config/app.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(BASE_URL . ($role === 'petugas' ? '/petugas/dashboard.php' : '/siswa/katalog.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token tidak valid, coba refresh halaman.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) $errors[] = 'Email wajib diisi.';
        if (empty($password)) $errors[] = 'Password wajib diisi.';

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, nama, email, password, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_nama']  = $user['nama'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];

                setFlash('success', 'Selamat datang, ' . $user['nama'] . '!');
                redirect(BASE_URL . ($user['role'] === 'petugas' ? '/petugas/dashboard.php' : '/siswa/katalog.php'));
            } else {
                $errors[] = 'Email atau password salah.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="brand-icon">⚗</span>
            <h1><?= APP_NAME ?></h1>
            <p><?= APP_SUBTITLE ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error" style="margin-bottom:1.25rem">
                <span class="flash-icon">✕</span>
                <?= implode('<br>', array_map('sanitize', $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="nama@sekolah.sch.id"
                    value="<?= sanitize($_POST['email'] ?? '') ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:0.5rem">
                Masuk ke Sistem
            </button>
        </form>

        <div style="text-align:center;margin:1.25rem 0;position:relative;color:var(--text-muted);font-size:0.8rem">
            <span style="position:relative;z-index:1;background:var(--bg-card);padding:0 0.75rem">belum punya akun?</span>
            <div style="position:absolute;top:50%;left:0;right:0;height:1px;background:var(--border);z-index:0"></div>
        </div>

        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-secondary w-full" style="text-align:center;justify-content:center">
            Daftar Akun Baru
        </a>

        <div class="login-footer" style="margin-top:1.25rem">
            <p>Demo: <strong>petugas@lab.sch.id</strong> atau <strong>budi@siswa.sch.id</strong></p>
            <p style="margin-top:0.3rem">Password: <strong>password</strong></p>
        </div>
    </div>
</div>
</body>
</html>