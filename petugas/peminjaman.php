<?php
// petugas/peminjaman.php
require_once __DIR__ . '/../config/app.php';
requireRole('petugas');

$db = getDB();
$pageTitle = 'Manajemen Peminjaman';

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token tidak valid.'); redirect(BASE_URL . '/petugas/peminjaman.php');
    }

    $pinjamId  = (int)($_POST['peminjaman_id'] ?? 0);
    $postAction = $_POST['action'] ?? '';
    $catatan   = sanitize($_POST['catatan_petugas'] ?? '');

    if (!$pinjamId) { setFlash('error', 'ID tidak valid.'); redirect(BASE_URL . '/petugas/peminjaman.php'); }

    // Fetch current status
    $stmt = $db->prepare("SELECT * FROM peminjaman WHERE id=?");
    $stmt->execute([$pinjamId]);
    $p = $stmt->fetch();
    if (!$p) { setFlash('error', 'Data tidak ditemukan.'); redirect(BASE_URL . '/petugas/peminjaman.php'); }

    try {
        if ($postAction === 'approve' && $p['status'] === 'pending') {
            $stmt = $db->prepare("UPDATE peminjaman SET status='approved', catatan_petugas=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$catatan, $pinjamId]);
            setFlash('success', 'Peminjaman #' . $pinjamId . ' disetujui.');

        } elseif ($postAction === 'reject' && $p['status'] === 'pending') {
            $stmt = $db->prepare("UPDATE peminjaman SET status='rejected', catatan_petugas=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$catatan, $pinjamId]);
            setFlash('success', 'Peminjaman #' . $pinjamId . ' ditolak.');

        } elseif ($postAction === 'confirm_pickup' && $p['status'] === 'approved') {
            // Reduce stock
            $db->beginTransaction();
            $details = $db->prepare("SELECT * FROM peminjaman_detail WHERE peminjaman_id=?");
            $details->execute([$pinjamId]);
            $items = $details->fetchAll();

            foreach ($items as $item) {
                // Check stock
                $s = $db->prepare("SELECT stok FROM barang WHERE id=? FOR UPDATE");
                $s->execute([$item['barang_id']]);
                $stok = (int)$s->fetchColumn();
                if ($stok < $item['jumlah']) {
                    $db->rollBack();
                    setFlash('error', 'Stok tidak mencukupi untuk salah satu barang.');
                    redirect(BASE_URL . '/petugas/peminjaman.php?id=' . $pinjamId);
                }
                $u = $db->prepare("UPDATE barang SET stok = stok - ? WHERE id=?");
                $u->execute([$item['jumlah'], $item['barang_id']]);
            }
            $stmt = $db->prepare("UPDATE peminjaman SET status='borrowed', catatan_petugas=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$catatan, $pinjamId]);
            $db->commit();
            setFlash('success', 'Pengambilan barang dikonfirmasi. Stok diperbarui.');

        } elseif ($postAction === 'confirm_return' && $p['status'] === 'borrowed') {
            // Increase stock
            $db->beginTransaction();
            $details = $db->prepare("SELECT * FROM peminjaman_detail WHERE peminjaman_id=?");
            $details->execute([$pinjamId]);
            foreach ($details->fetchAll() as $item) {
                $u = $db->prepare("UPDATE barang SET stok = stok + ? WHERE id=?");
                $u->execute([$item['jumlah'], $item['barang_id']]);
            }
            $stmt = $db->prepare("UPDATE peminjaman SET status='returned', tanggal_dikembalikan=CURDATE(), catatan_petugas=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$catatan, $pinjamId]);
            $db->commit();
            setFlash('success', 'Pengembalian barang dikonfirmasi. Stok dipulihkan.');
        } else {
            setFlash('error', 'Aksi tidak valid untuk status saat ini.');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }

    redirect(BASE_URL . '/petugas/peminjaman.php');
}

// ── Filter & Search ──
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['q'] ?? '');
$detailId = (int)($_GET['id'] ?? 0);

$params = [];
$where = "WHERE 1=1";
if ($status) { $where .= " AND p.status=?"; $params[] = $status; }
if ($search) { $where .= " AND (u.nama LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT p.*, u.nama AS nama_siswa, u.email AS email_siswa,
        GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS daftar_barang,
        SUM(pd.jumlah) AS total_item,
        COUNT(DISTINCT pd.id) AS total_jenis
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN peminjaman_detail pd ON p.id = pd.peminjaman_id
    JOIN barang b ON pd.barang_id = b.id
    $where
    GROUP BY p.id
    ORDER BY FIELD(p.status,'pending','approved','borrowed','returned','rejected'), p.created_at DESC
");
$stmt->execute($params);
$peminjamans = $stmt->fetchAll();

// Detail view
$detail = null;
$detailItems = [];
if ($detailId > 0) {
    $stmt = $db->prepare("SELECT p.*, u.nama AS nama_siswa, u.email AS email_siswa FROM peminjaman p JOIN users u ON p.user_id = u.id WHERE p.id=?");
    $stmt->execute([$detailId]);
    $detail = $stmt->fetch();
    if ($detail) {
        $stmt2 = $db->prepare("SELECT pd.*, b.nama_barang, b.stok FROM peminjaman_detail pd JOIN barang b ON pd.barang_id = b.id WHERE pd.peminjaman_id=?");
        $stmt2->execute([$detailId]);
        $detailItems = $stmt2->fetchAll();
    }
}

// Count per status
$counts = [];
foreach (['pending','approved','borrowed','returned','rejected'] as $s) {
    $r = $db->prepare("SELECT COUNT(*) FROM peminjaman WHERE status=?");
    $r->execute([$s]);
    $counts[$s] = $r->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Manajemen Peminjaman</h1>
        <p>Review dan proses semua permintaan peminjaman</p>
    </div>
</div>

<!-- Status Tabs -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <a href="<?= BASE_URL ?>/petugas/peminjaman.php" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-secondary' ?>">
        Semua <span style="opacity:0.7;font-size:0.75rem">(<?= array_sum($counts) ?>)</span>
    </a>
    <?php
    $tabLabels = ['pending'=>'Pending','approved'=>'Disetujui','borrowed'=>'Dipinjam','returned'=>'Dikembalikan','rejected'=>'Ditolak'];
    foreach ($tabLabels as $s => $label): ?>
    <a href="<?= BASE_URL ?>/petugas/peminjaman.php?status=<?= $s ?>" class="btn btn-sm <?= $status===$s ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $label ?> <span style="opacity:0.7;font-size:0.75rem">(<?= $counts[$s] ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="padding:0.875rem 1.25rem">
        <form method="GET" class="search-bar">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= sanitize($status) ?>"><?php endif; ?>
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="form-control" placeholder="Cari nama atau email siswa..." value="<?= sanitize($search) ?>">
            </div>
            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if ($search): ?><a href="<?= BASE_URL ?>/petugas/peminjaman.php<?= $status ? '?status='.$status : '' ?>" class="btn btn-secondary">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Peminjaman</span>
        <span class="text-muted text-sm"><?= count($peminjamans) ?> transaksi</span>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Siswa</th>
                    <th>Barang</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Kembali</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($peminjamans)): ?>
                <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📋</div><p>Tidak ada peminjaman ditemukan</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($peminjamans as $row): ?>
                <tr>
                    <td><span style="font-family:var(--font-display);font-weight:600;color:var(--text-muted)">#<?= $row['id'] ?></span></td>
                    <td>
                        <div style="font-weight:500"><?= sanitize($row['nama_siswa']) ?></div>
                        <div class="text-muted text-sm"><?= sanitize($row['email_siswa']) ?></div>
                    </td>
                    <td>
                        <div style="font-size:0.8rem;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($row['daftar_barang']) ?>">
                            <?= sanitize($row['daftar_barang']) ?>
                        </div>
                        <div class="text-muted text-sm"><?= $row['total_jenis'] ?> jenis • <?= $row['total_item'] ?> unit</div>
                    </td>
                    <td class="text-sm"><?= formatDate($row['tanggal_pinjam']) ?></td>
                    <td class="text-sm"><?= formatDate($row['tanggal_kembali']) ?></td>
                    <td><?= statusBadge($row['status']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="openModal('detail-<?= $row['id'] ?>')">Detail</button>
                        <?php if ($row['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-success" onclick="openModal('aksi-<?= $row['id'] ?>')">Proses</button>
                        <?php elseif ($row['status'] === 'approved'): ?>
                        <button class="btn btn-sm btn-outline" onclick="openModal('aksi-<?= $row['id'] ?>')">Konfirmasi</button>
                        <?php elseif ($row['status'] === 'borrowed'): ?>
                        <button class="btn btn-sm btn-warning" onclick="openModal('aksi-<?= $row['id'] ?>')">Terima Kembali</button>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Detail Modal -->
                <div id="detail-<?= $row['id'] ?>" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('detail-<?= $row['id'] ?>')">
                    <div class="modal">
                        <div class="modal-header">
                            <span class="modal-title">Detail Peminjaman #<?= $row['id'] ?></span>
                            <button class="modal-close" onclick="closeModal('detail-<?= $row['id'] ?>')">✕</button>
                        </div>
                        <?php
                        $detailStmt = $db->prepare("SELECT pd.*, b.nama_barang FROM peminjaman_detail pd JOIN barang b ON pd.barang_id = b.id WHERE pd.peminjaman_id=?");
                        $detailStmt->execute([$row['id']]);
                        $dItems = $detailStmt->fetchAll();
                        ?>
                        <div class="modal-body">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                                <div>
                                    <div class="text-muted text-sm">Siswa</div>
                                    <div style="font-weight:500"><?= sanitize($row['nama_siswa']) ?></div>
                                </div>
                                <div>
                                    <div class="text-muted text-sm">Status</div>
                                    <div><?= statusBadge($row['status']) ?></div>
                                </div>
                                <div>
                                    <div class="text-muted text-sm">Tanggal Pinjam</div>
                                    <div style="font-size:0.875rem"><?= formatDate($row['tanggal_pinjam']) ?></div>
                                </div>
                                <div>
                                    <div class="text-muted text-sm">Tgl Rencana Kembali</div>
                                    <div style="font-size:0.875rem"><?= formatDate($row['tanggal_kembali']) ?></div>
                                </div>
                                <?php if ($row['tanggal_dikembalikan']): ?>
                                <div>
                                    <div class="text-muted text-sm">Tgl Dikembalikan</div>
                                    <div style="font-size:0.875rem"><?= formatDate($row['tanggal_dikembalikan']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($row['catatan']): ?>
                            <div style="background:var(--bg-hover);padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:1rem">
                                <div class="text-muted text-sm" style="margin-bottom:0.25rem">Catatan Siswa</div>
                                <div style="font-size:0.875rem"><?= sanitize($row['catatan']) ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if ($row['catatan_petugas']): ?>
                            <div style="background:var(--blue-dim);padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:1rem">
                                <div class="text-muted text-sm" style="margin-bottom:0.25rem">Catatan Petugas</div>
                                <div style="font-size:0.875rem"><?= sanitize($row['catatan_petugas']) ?></div>
                            </div>
                            <?php endif; ?>

                            <div class="text-muted text-sm" style="margin-bottom:0.5rem">Daftar Barang</div>
                            <div class="detail-items">
                                <?php foreach ($dItems as $di): ?>
                                <div class="detail-item">
                                    <span class="detail-item-name"><?= sanitize($di['nama_barang']) ?></span>
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

                <?php if (in_array($row['status'], ['pending','approved','borrowed'])): ?>
                <!-- Aksi Modal -->
                <div id="aksi-<?= $row['id'] ?>" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('aksi-<?= $row['id'] ?>')">
                    <div class="modal">
                        <div class="modal-header">
                            <span class="modal-title">
                                <?php if ($row['status']==='pending'): ?>Setujui / Tolak
                                <?php elseif ($row['status']==='approved'): ?>Konfirmasi Pengambilan
                                <?php else: ?>Konfirmasi Pengembalian
                                <?php endif; ?>
                                — #<?= $row['id'] ?>
                            </span>
                            <button class="modal-close" onclick="closeModal('aksi-<?= $row['id'] ?>')">✕</button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="peminjaman_id" value="<?= $row['id'] ?>">

                                <div style="background:var(--bg-hover);padding:0.875rem;border-radius:var(--radius-sm);margin-bottom:1rem">
                                    <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem">Siswa: <strong style="color:var(--text-primary)"><?= sanitize($row['nama_siswa']) ?></strong></div>
                                    <div style="font-size:0.8rem;color:var(--text-muted)">Barang: <?= sanitize(substr($row['daftar_barang'],0,80)) ?><?= strlen($row['daftar_barang'])>80?'...':'' ?></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Catatan Petugas (opsional)</label>
                                    <textarea name="catatan_petugas" class="form-control" rows="2" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('aksi-<?= $row['id'] ?>')">Batal</button>
                                <?php if ($row['status']==='pending'): ?>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">✕ Tolak</button>
                                    <button type="submit" name="action" value="approve" class="btn btn-success">✓ Setujui</button>
                                <?php elseif ($row['status']==='approved'): ?>
                                    <button type="submit" name="action" value="confirm_pickup" class="btn btn-primary">✓ Konfirmasi Pengambilan</button>
                                <?php elseif ($row['status']==='borrowed'): ?>
                                    <button type="submit" name="action" value="confirm_return" class="btn btn-warning">↩ Konfirmasi Pengembalian</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>