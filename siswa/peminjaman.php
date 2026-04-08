<?php
// siswa/peminjaman.php
require_once __DIR__ . '/../config/app.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Peminjaman Saya';
$userId = currentUser()['id'];

$statusFilter = sanitize($_GET['status'] ?? '');
$params = [$userId];
$where = "WHERE p.user_id = ?";
if ($statusFilter) { $where .= " AND p.status = ?"; $params[] = $statusFilter; }

$stmt = $db->prepare("
    SELECT p.*,
        GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS daftar_barang,
        SUM(pd.jumlah) AS total_item,
        COUNT(DISTINCT pd.id) AS total_jenis
    FROM peminjaman p
    JOIN peminjaman_detail pd ON p.id = pd.peminjaman_id
    JOIN barang b ON pd.barang_id = b.id
    $where
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$peminjamans = $stmt->fetchAll();

// Count per status for tabs
$counts = [];
foreach (['pending','approved','borrowed','returned','rejected'] as $s) {
    $r = $db->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=? AND status=?");
    $r->execute([$userId, $s]);
    $counts[$s] = $r->fetchColumn();
}

// Status step map
function getStatusSteps(string $status): array {
    $order = ['pending', 'approved', 'borrowed', 'returned'];
    $labels = ['Pending', 'Disetujui', 'Dipinjam', 'Dikembalikan'];
    if ($status === 'rejected') return [];

    $current = array_search($status, $order);
    $steps = [];
    foreach ($order as $i => $s) {
        $steps[] = [
            'label' => $labels[$i],
            'done'  => $i <= $current,
            'current' => $i === $current,
        ];
    }
    return $steps;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Peminjaman Saya</h1>
        <p>Riwayat dan status semua peminjaman Anda</p>
    </div>
    <a href="<?= BASE_URL ?>/siswa/katalog.php" class="btn btn-primary">+ Pinjam Baru</a>
</div>

<!-- Status tabs -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <a href="<?= BASE_URL ?>/siswa/peminjaman.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-secondary' ?>">
        Semua <span style="opacity:0.7;font-size:0.75rem">(<?= array_sum($counts) ?>)</span>
    </a>
    <?php
    $tabLabels = ['pending'=>'Menunggu','approved'=>'Disetujui','borrowed'=>'Dipinjam','returned'=>'Selesai','rejected'=>'Ditolak'];
    foreach ($tabLabels as $s => $label): ?>
    <a href="<?= BASE_URL ?>/siswa/peminjaman.php?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter===$s ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $label ?> <span style="opacity:0.7;font-size:0.75rem">(<?= $counts[$s] ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($peminjamans)): ?>
<div class="card">
    <div class="empty-state" style="padding:4rem">
        <div class="empty-icon">📋</div>
        <p style="margin-bottom:1rem">Belum ada peminjaman<?= $statusFilter ? ' dengan status ini' : '' ?>.</p>
        <a href="<?= BASE_URL ?>/siswa/katalog.php" class="btn btn-primary">Lihat Katalog Barang</a>
    </div>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($peminjamans as $row): ?>
    <?php $steps = getStatusSteps($row['status']); ?>

    <div class="card">
        <div style="padding:1.25rem 1.5rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;flex-wrap:wrap">
                    <span style="font-family:var(--font-display);font-weight:700;font-size:0.9rem;color:var(--text-muted)">#<?= $row['id'] ?></span>
                    <?= statusBadge($row['status']) ?>
                    <span class="text-muted text-sm">Diajukan <?= formatDate($row['created_at']) ?></span>
                </div>

                <div style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.5rem">
                    <?= sanitize($row['daftar_barang']) ?>
                </div>

                <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
                    <div class="text-muted text-sm">
                        📅 Pinjam: <strong style="color:var(--text-primary)"><?= formatDate($row['tanggal_pinjam']) ?></strong>
                    </div>
                    <?php if ($row['tanggal_kembali']): ?>
                    <div class="text-muted text-sm">
                        📅 Kembali: <strong style="color:var(--text-primary)"><?= formatDate($row['tanggal_kembali']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="text-muted text-sm">
                        📦 <?= $row['total_jenis'] ?> jenis · <?= $row['total_item'] ?> unit
                    </div>
                </div>
            </div>

            <button class="btn btn-sm btn-secondary" onclick="openModal('detail-<?= $row['id'] ?>')">
                Lihat Detail
            </button>
        </div>

        <?php if (!empty($steps) && $row['status'] !== 'returned'): ?>
        <div style="padding:0.75rem 1.5rem 1.25rem;border-top:1px solid var(--border-light)">
            <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.6rem;text-transform:uppercase;letter-spacing:0.06em">Progres</div>
            <div class="status-steps">
                <?php foreach ($steps as $i => $step): ?>
                <div class="status-step <?= $step['done'] ? 'done' : '' ?> <?= $step['current'] ? 'current' : '' ?>">
                    <div class="step-dot"><?= $step['done'] ? '✓' : ($i+1) ?></div>
                    <div class="step-label"><?= $step['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($row['catatan_petugas']): ?>
        <div style="padding:0.75rem 1.5rem;border-top:1px solid var(--border-light);background:<?= $row['status']==='rejected' ? 'var(--red-dim)' : 'var(--blue-dim)' ?>">
            <div style="font-size:0.78rem;color:var(--text-muted)">Catatan Petugas:</div>
            <div style="font-size:0.875rem;margin-top:0.2rem"><?= sanitize($row['catatan_petugas']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Detail Modal -->
    <div id="detail-<?= $row['id'] ?>" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('detail-<?= $row['id'] ?>')">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">Detail Peminjaman #<?= $row['id'] ?></span>
                <button class="modal-close" onclick="closeModal('detail-<?= $row['id'] ?>')">✕</button>
            </div>
            <?php
            $dStmt = $db->prepare("SELECT pd.*, b.nama_barang, b.kategori FROM peminjaman_detail pd JOIN barang b ON pd.barang_id = b.id WHERE pd.peminjaman_id=?");
            $dStmt->execute([$row['id']]);
            $dItems = $dStmt->fetchAll();
            ?>
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                    <div>
                        <div class="text-muted text-sm">Status</div>
                        <?= statusBadge($row['status']) ?>
                    </div>
                    <div>
                        <div class="text-muted text-sm">Tgl Pengajuan</div>
                        <div style="font-size:0.875rem"><?= formatDate($row['created_at']) ?></div>
                    </div>
                    <div>
                        <div class="text-muted text-sm">Tgl Pinjam</div>
                        <div style="font-size:0.875rem"><?= formatDate($row['tanggal_pinjam']) ?></div>
                    </div>
                    <div>
                        <div class="text-muted text-sm">Rencana Kembali</div>
                        <div style="font-size:0.875rem"><?= formatDate($row['tanggal_kembali']) ?></div>
                    </div>
                    <?php if ($row['tanggal_dikembalikan']): ?>
                    <div>
                        <div class="text-muted text-sm">Dikembalikan Pada</div>
                        <div style="font-size:0.875rem"><?= formatDate($row['tanggal_dikembalikan']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($row['catatan']): ?>
                <div style="background:var(--bg-hover);padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:1rem">
                    <div class="text-muted text-sm" style="margin-bottom:0.25rem">Catatan Anda</div>
                    <div style="font-size:0.875rem"><?= sanitize($row['catatan']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($row['catatan_petugas']): ?>
                <div style="background:var(--blue-dim);padding:0.75rem;border-radius:var(--radius-sm);border:1px solid rgba(88,166,255,0.2);margin-bottom:1rem">
                    <div class="text-muted text-sm" style="margin-bottom:0.25rem">Catatan Petugas</div>
                    <div style="font-size:0.875rem"><?= sanitize($row['catatan_petugas']) ?></div>
                </div>
                <?php endif; ?>

                <div class="text-muted text-sm" style="margin-bottom:0.5rem">Daftar Barang yang Dipinjam</div>
                <div class="detail-items">
                    <?php foreach ($dItems as $di): ?>
                    <div class="detail-item">
                        <div>
                            <div class="detail-item-name"><?= sanitize($di['nama_barang']) ?></div>
                            <div class="text-muted text-sm"><?= sanitize($di['kategori']) ?></div>
                        </div>
                        <span class="detail-item-qty"><?= $di['jumlah'] ?> unit</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('detail-<?= $row['id'] ?>')">Tutup</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>