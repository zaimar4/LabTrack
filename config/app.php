<?php
// config/app.php

define('APP_NAME', 'LabTrack');
define('APP_SUBTITLE', 'Sistem Peminjaman Laboratorium');

// ── BASE_URL: deteksi otomatis nama folder project ────────────────────────────
// Bekerja di Windows (XAMPP) maupun Linux tanpa perlu diubah manual.
// Contoh: htdocs/sistem_peminjaman_barang/config/app.php
//   __DIR__       = C:/xampp/htdocs/sistem_peminjaman_barang/config
//   dirname(__DIR__) = C:/xampp/htdocs/sistem_peminjaman_barang
$_docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$_subPath  = str_replace($_docRoot, '', $_projRoot); // => /sistem_peminjaman_barang
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $_protocol . '://' . $_host . $_subPath);
// ─────────────────────────────────────────────────────────────────────────────

define('SESSION_NAME', 'lab_session');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Autoload — pakai __DIR__ supaya path selalu akurat di Windows & Linux
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';