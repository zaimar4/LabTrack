<?php
// includes/functions.php

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (isLoggedIn()) {
        return [
            'id'   => $_SESSION['user_id'],
            'nama' => $_SESSION['user_nama'],
            'role' => $_SESSION['user_role'],
            'email'=> $_SESSION['user_email'],
        ];
    }
    return null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

function statusBadge(string $status): string {
    $badges = [
        'pending'  => ['label' => 'Menunggu',    'class' => 'badge-pending'],
        'approved' => ['label' => 'Disetujui',   'class' => 'badge-approved'],
        'borrowed' => ['label' => 'Dipinjam',    'class' => 'badge-borrowed'],
        'returned' => ['label' => 'Dikembalikan','class' => 'badge-returned'],
        'rejected' => ['label' => 'Ditolak',     'class' => 'badge-rejected'],
    ];
    $b = $badges[$status] ?? ['label' => $status, 'class' => 'badge-default'];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function formatDate(?string $date): string {
    if (!$date) return '-';
    $dt = new DateTime($date);
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $dt->format('d') . ' ' . $bulan[(int)$dt->format('m')] . ' ' . $dt->format('Y');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getStokClass(int $stok): string {
    if ($stok === 0) return 'stok-habis';
    if ($stok <= 3) return 'stok-kritis';
    if ($stok <= 7) return 'stok-rendah';
    return 'stok-aman';
}