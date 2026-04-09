<?php
// auth/register.php
require_once __DIR__ . '/../config/app.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(BASE_URL . ($role === 'petugas' ? '/petugas/dashboard.php' : '/siswa/katalog.php'));
}

$errors  = [];
$success = false;
$old     = []; // untuk repopulate form jika error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token tidak valid, coba refresh halaman.';
    } else {
        $nama     = sanitize($_POST['nama'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $konfirm  = $_POST['konfirmasi_password'] ?? '';
        $role     = sanitize($_POST['role'] ?? 'siswa');

        $old = compact('nama', 'email', 'role');

        // Validasi
        if (empty($nama))
            $errors[] = 'Nama lengkap wajib diisi.';
        elseif (strlen($nama) < 3)
            $errors[] = 'Nama minimal 3 karakter.';

        if (empty($email))
            $errors[] = 'Email wajib diisi.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Format email tidak valid.';

        if (empty($password))
            $errors[] = 'Password wajib diisi.';
        elseif (strlen($password) < 6)
            $errors[] = 'Password minimal 6 karakter.';

        if ($password !== $konfirm)
            $errors[] = 'Konfirmasi password tidak cocok.';

        if (!in_array($role, ['siswa', 'petugas']))
            $errors[] = 'Role tidak valid.';

        if (empty($errors)) {
            $db = getDB();

            // Cek email sudah terdaftar
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email sudah terdaftar, silakan gunakan email lain.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama, $email, $hash, $role]);

                setFlash('success', 'Akun berhasil dibuat! Silakan masuk dengan email dan password Anda.');
                redirect(BASE_URL . '/auth/login.php');
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
    <title>Daftar Akun — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        /* Role selector cards */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .role-option { position: relative; }
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0; height: 0;
        }
        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 1rem 0.75rem;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        .role-label:hover {
            border-color: var(--accent-blue);
        }
        .role-option input:checked + .role-label {
            border-color: var(--accent-blue);
            background: var(--blue-dim);
        }
        .role-icon { font-size: 1.75rem; }
        .role-title {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        .role-desc {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Password strength */
        .strength-bar {
            height: 3px;
            border-radius: 3px;
            background: var(--border);
            margin-top: 0.5rem;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 3px;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .strength-text {
            font-size: 0.72rem;
            margin-top: 0.3rem;
            color: var(--text-muted);
        }

        /* Divider */
        .auth-divider {
            text-align: center;
            margin: 1.25rem 0;
            position: relative;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .auth-divider::before, .auth-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 38%;
            height: 1px;
            background: var(--border);
        }
        .auth-divider::before { left: 0; }
        .auth-divider::after  { right: 0; }

        .login-card { max-width: 460px; }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="brand-icon">⚗</span>
            <h1><?= APP_NAME ?></h1>
            <p>Buat akun baru untuk mengakses sistem</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" style="margin-bottom:1.25rem;flex-direction:column;align-items:flex-start;gap:0.35rem">
            <div style="display:flex;align-items:center;gap:0.5rem;font-weight:600">
                <span class="flash-icon">✕</span> Terdapat kesalahan:
            </div>
            <ul style="margin:0;padding-left:1.5rem;font-size:0.85rem">
                <?php foreach ($errors as $e): ?>
                <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="form-register" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <!-- Role selector -->
            <div class="form-group">
                <label class="form-label">Daftar sebagai</label>
                <div class="role-selector">
                    <div class="role-option">
                        <input type="radio" name="role" id="role-siswa" value="siswa"
                            <?= ($old['role'] ?? 'siswa') === 'siswa' ? 'checked' : '' ?>>
                        <label class="role-label" for="role-siswa">
                            <span class="role-icon">🎒</span>
                            <span class="role-title">Siswa</span>
                            <span class="role-desc">Pinjam peralatan laboratorium</span>
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" name="role" id="role-petugas" value="petugas"
                            <?= ($old['role'] ?? '') === 'petugas' ? 'checked' : '' ?>>
                        <label class="role-label" for="role-petugas">
                            <span class="role-icon">🔑</span>
                            <span class="role-title">Petugas</span>
                            <span class="role-desc">Kelola inventaris & peminjaman</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="nama">Nama Lengkap</label>
                <input
                    type="text"
                    id="nama"
                    name="nama"
                    class="form-control"
                    placeholder="Masukkan nama lengkap Anda"
                    value="<?= sanitize($old['nama'] ?? '') ?>"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="nama@sekolah.sch.id"
                    value="<?= sanitize($old['email'] ?? '') ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position:relative">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Minimal 6 karakter"
                        autocomplete="new-password"
                        oninput="checkStrength(this.value)"
                        required
                        style="padding-right:2.5rem"
                    >
                    <button type="button" onclick="togglePass('password', this)"
                        style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;padding:0">
                        👁
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                <div class="strength-text" id="strength-text">Masukkan password</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="konfirmasi_password">Konfirmasi Password</label>
                <div style="position:relative">
                    <input
                        type="password"
                        id="konfirmasi_password"
                        name="konfirmasi_password"
                        class="form-control"
                        placeholder="Ulangi password Anda"
                        autocomplete="new-password"
                        required
                        style="padding-right:2.5rem"
                    >
                    <button type="button" onclick="togglePass('konfirmasi_password', this)"
                        style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;padding:0">
                        👁
                    </button>
                </div>
                <div class="form-hint" id="match-hint"></div>
            </div>

            <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:0.25rem">
                Buat Akun
            </button>
        </form>

        <div class="auth-divider">sudah punya akun?</div>

        <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary w-full" style="text-align:center;justify-content:center">
            Masuk ke Sistem
        </a>
    </div>
</div>

<script>
function checkStrength(val) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    if (!fill) return;

    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: 'var(--border)',        label: 'Masukkan password' },
        { pct: '25%',  color: 'var(--accent-red)',     label: '🔴 Sangat lemah' },
        { pct: '40%',  color: 'var(--accent-red)',     label: '🔴 Lemah' },
        { pct: '60%',  color: 'var(--accent-orange)',  label: '🟡 Sedang' },
        { pct: '80%',  color: 'var(--accent-blue)',    label: '🔵 Kuat' },
        { pct: '100%', color: 'var(--accent-green)',   label: '🟢 Sangat kuat' },
    ];
    const lv = levels[Math.min(score, 5)];
    fill.style.width = lv.pct;
    fill.style.background = lv.color;
    text.textContent = val.length === 0 ? 'Masukkan password' : lv.label;
    text.style.color = lv.color === 'var(--border)' ? 'var(--text-muted)' : lv.color;
}

document.getElementById('konfirmasi_password')?.addEventListener('input', function() {
    const hint = document.getElementById('match-hint');
    const pass = document.getElementById('password').value;
    if (!this.value) { hint.textContent = ''; return; }
    if (this.value === pass) {
        hint.textContent = '✓ Password cocok';
        hint.style.color = 'var(--accent-green)';
    } else {
        hint.textContent = '✕ Password tidak cocok';
        hint.style.color = 'var(--accent-red)';
    }
});

function togglePass(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈';
    } else {
        inp.type = 'password';
        btn.textContent = '👁';
    }
}
</script>
</body>
</html>