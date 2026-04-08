<?php
// index.php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(BASE_URL . ($role === 'petugas' ? '/petugas/dashboard.php' : '/siswa/katalog.php'));
} else {
    redirect(BASE_URL . '/auth/login.php');
}