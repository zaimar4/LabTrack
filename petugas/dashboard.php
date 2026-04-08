<?php
// petugas/dashboard.php
require_once __DIR__ . '/../config/app.php';
requireRole('petugas');

$db = getDB();
$pageTitle = 'Dashboard';

// Stats
$stats = [];

$r = $db->query("SELECT COUNT(*) FROM barang"); 
$stats['total_barang'] = $r->fetchColumn();

$r = $db->query("SELECT SUM(stok) FROM barang"); 
$stats['total_stok'] = (int)$r->fetchColumn();

$r = $db->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'"); 
$stats['pending'] = $r->fetchColumn();

$r = $db->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'borrowed'"); 
$stats['dipinjam'] = $r->fetchColumn();

$r = $db->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'approved'"); 
$stats['approved'] = $r->fetchColumn();

$r = $db->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'returned' AND DATE(updated_at) = CURDATE()");
$stats['kembali_hari_ini'] = $r->fetchColumn();

$r = $db->query("SELECT COUNT(*) FROM barang WHERE stok = 0");
$stats['stok_habis'] = $r->fetchColumn();

// Recent peminjaman
$recent = $db->query("
    SELECT p.*, u.nama AS nama_siswa,
        GROUP_CONCAT(b.nama_barang SEPARATOR ', ') AS daftar_barang,
        SUM(pd.jumlah) AS total_item
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN peminjaman_detail pd ON p.id = pd.peminjaman_id
    JOIN barang b ON pd.barang_id = b.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Low stock
$lowstock = $db->query("SELECT * FROM barang WHERE stok <= 3 ORDER BY stok ASC LIMIT 5")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>Selamat datang kembali, <?= sanitize(currentUser()['nama']) ?> 👋</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/petugas/barang.php?action=add" class="btn btn-primary">+ Tambah Barang</a>
        <a href="<?= BASE_URL ?>/petugas/peminjaman.php?status=pending" class="btn btn-secondary">
            Lihat Pending
            <?php if ($stats['pending'] > 0): ?>
                <span style="background:var(--accent-orange);color:#0d1117;padding:0.1rem 0.45rem;border-radius:20px;font-size:0.72rem;font-weight:700;margin-left:0.25rem"><?= $stats['pending'] ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🧪</div>
        <div>
            <div class="stat-label">Total Barang</div>
            <div class="stat-value"><?= $stats['total_barang'] ?></div>
            <div class="stat-sub"><?= $stats['total_stok'] ?> unit tersedia</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">⏳</div>
        <div>
            <div class="stat-label">Permintaan Pending</div>
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-sub">menunggu persetujuan</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">📦</div>
        <div>
            <div class="stat-label">Sedang Dipinjam</div>
            <div class="stat-value"><?= $stats['dipinjam'] ?></div>
            <div class="stat-sub"><?= $stats['approved'] ?> siap diambil</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div>
            <div class="stat-label">Kembali Hari Ini</div>
            <div class="stat-value"><?= $stats['kembali_hari_ini'] ?></div>
            <div class="stat-sub">transaksi selesai</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div>
            <div class="stat-label">Stok Habis</div>
            <div class="stat-value"><?= $stats['stok_habis'] ?></div>
            <div class="stat-sub">barang perlu restock</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

<!-- Recent Peminjaman -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Transaksi Terbaru</span>
        <a href="<?= BASE_URL ?>/petugas/peminjaman.php" class="btn btn-sm btn-secondary">Lihat Semua</a>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Siswa</th>
                    <th>Barang</th>
                    <th>Tgl Pinjam</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">Belum ada transaksi</td></tr>
                <?php else: ?>
                <?php foreach ($recent as $row): ?>
                <tr>
                    <td>
                        <div style="font-weight:500"><?= sanitize($row['nama_siswa']) ?></div>
                        <div class="text-muted text-sm"><?= $row['total_item'] ?> item</div>
                    </td>
                    <td>
                        <div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.8rem;color:var(--text-secondary)"
                             title="<?= sanitize($row['daftar_barang']) ?>">
                            <?= sanitize($row['daftar_barang']) ?>
                        </div>
                    </td>
                    <td class="text-sm"><?= formatDate($row['tanggal_pinjam']) ?></td>
                    <td><?= statusBadge($row['status']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/petugas/peminjaman.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary">Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Right sidebar -->
<div style="display:flex;flex-direction:column;gap:1rem">
    <!-- Stok rendah -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚠ Stok Rendah</span>
            <a href="<?= BASE_URL ?>/petugas/barang.php" class="btn btn-sm btn-secondary">Kelola</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($lowstock)): ?>
                <div class="empty-state" style="padding:2rem">
                    <p>Semua stok aman ✓</p>
                </div>
            <?php else: ?>
                <?php foreach ($lowstock as $b): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.25rem;border-bottom:1px solid var(--border-light)">
                    <div>
                        <div style="font-size:0.875rem;font-weight:500"><?= sanitize($b['nama_barang']) ?></div>
                        <div class="text-muted text-sm"><?= sanitize($b['kategori']) ?></div>
                    </div>
                    <span class="<?= getStokClass($b['stok']) ?>" style="font-family:var(--font-display);font-size:1.1rem">
                        <?= $b['stok'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="card">
        <div class="card-header"><span class="card-title">Aksi Cepat</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem">
            <a href="<?= BASE_URL ?>/petugas/peminjaman.php?status=pending" class="btn btn-warning w-full">
                ⏳ Review Pending (<?= $stats['pending'] ?>)
            </a>
            <a href="<?= BASE_URL ?>/petugas/peminjaman.php?status=approved" class="btn btn-outline w-full">
                📋 Konfirmasi Pengambilan (<?= $stats['approved'] ?>)
            </a>
            <a href="<?= BASE_URL ?>/petugas/peminjaman.php?status=borrowed" class="btn btn-secondary w-full">
                📦 Konfirmasi Pengembalian (<?= $stats['dipinjam'] ?>)
            </a>
            <a href="<?= BASE_URL ?>/petugas/barang.php?action=add" class="btn btn-secondary w-full">
                + Tambah Barang Baru
            </a>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>