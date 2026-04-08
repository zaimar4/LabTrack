<?php
// siswa/proses_pinjam.php
require_once __DIR__ . '/../config/app.php';
requireRole('siswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/siswa/katalog.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Token tidak valid, coba lagi.');
    redirect(BASE_URL . '/siswa/katalog.php');
}

$db = getDB();
$userId = currentUser()['id'];

// Parse items from POST: items[barang_id] = jumlah
$items = $_POST['items'] ?? [];
if (empty($items) || !is_array($items)) {
    setFlash('error', 'Pilih minimal satu barang untuk dipinjam.');
    redirect(BASE_URL . '/siswa/katalog.php');
}

$tanggalPinjam  = sanitize($_POST['tanggal_pinjam'] ?? '');
$tanggalKembali = sanitize($_POST['tanggal_kembali'] ?? '') ?: null;
$catatan        = sanitize($_POST['catatan'] ?? '');

// Validate date
if (!$tanggalPinjam || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPinjam)) {
    setFlash('error', 'Tanggal pinjam tidak valid.');
    redirect(BASE_URL . '/siswa/katalog.php');
}

// Validate & sanitize items
$validItems = [];
foreach ($items as $barangId => $jumlah) {
    $barangId = (int)$barangId;
    $jumlah   = (int)$jumlah;
    if ($barangId <= 0 || $jumlah <= 0) continue;

    $stmt = $db->prepare("SELECT id, stok, nama_barang FROM barang WHERE id=?");
    $stmt->execute([$barangId]);
    $barang = $stmt->fetch();

    if (!$barang) continue;
    if ($jumlah > $barang['stok']) {
        setFlash('error', "Jumlah peminjaman '{$barang['nama_barang']}' melebihi stok yang tersedia ({$barang['stok']} unit).");
        redirect(BASE_URL . '/siswa/katalog.php');
    }

    $validItems[$barangId] = $jumlah;
}

if (empty($validItems)) {
    setFlash('error', 'Tidak ada barang valid yang dipilih.');
    redirect(BASE_URL . '/siswa/katalog.php');
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO peminjaman (user_id, status, tanggal_pinjam, tanggal_kembali, catatan) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, 'pending', $tanggalPinjam, $tanggalKembali, $catatan]);
    $pinjamId = $db->lastInsertId();

    $stmtDetail = $db->prepare("INSERT INTO peminjaman_detail (peminjaman_id, barang_id, jumlah) VALUES (?,?,?)");
    foreach ($validItems as $barangId => $jumlah) {
        $stmtDetail->execute([$pinjamId, $barangId, $jumlah]);
    }

    $db->commit();

    setFlash('success', 'Peminjaman berhasil diajukan! Nomor transaksi: #' . $pinjamId . '. Silakan tunggu persetujuan petugas.');
    redirect(BASE_URL . '/siswa/peminjaman.php');

} catch (Exception $e) {
    $db->rollBack();
    setFlash('error', 'Gagal mengajukan peminjaman. Silakan coba lagi.');
    redirect(BASE_URL . '/siswa/katalog.php');
}