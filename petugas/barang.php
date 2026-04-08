<?php
// petugas/barang.php
require_once __DIR__ . '/../config/app.php';
requireRole('petugas');

$db = getDB();
$pageTitle = 'Manajemen Barang';
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token tidak valid.');
        redirect(BASE_URL . '/petugas/barang.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $nama      = sanitize($_POST['nama_barang'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $stok      = (int)($_POST['stok'] ?? 0);
        $kategori  = sanitize($_POST['kategori'] ?? 'Umum');
        $barangId  = (int)($_POST['barang_id'] ?? 0);

        $errors = [];
        if (empty($nama)) $errors[] = 'Nama barang wajib diisi.';
        if ($stok < 0) $errors[] = 'Stok tidak boleh negatif.';

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            redirect(BASE_URL . '/petugas/barang.php?action=' . ($barangId ? 'edit&id=' . $barangId : 'add'));
        }

        if ($barangId > 0) {
            $stmt = $db->prepare("UPDATE barang SET nama_barang=?, deskripsi=?, stok=?, kategori=? WHERE id=?");
            $stmt->execute([$nama, $deskripsi, $stok, $kategori, $barangId]);
            setFlash('success', 'Barang berhasil diperbarui.');
        } else {
            $stmt = $db->prepare("INSERT INTO barang (nama_barang, deskripsi, stok, kategori) VALUES (?,?,?,?)");
            $stmt->execute([$nama, $deskripsi, $stok, $kategori]);
            setFlash('success', 'Barang berhasil ditambahkan.');
        }
        redirect(BASE_URL . '/petugas/barang.php');
    }

    if ($postAction === 'update_stok') {
        $barangId  = (int)($_POST['barang_id'] ?? 0);
        $stok      = (int)($_POST['stok_baru'] ?? 0);
        if ($stok < 0) { setFlash('error', 'Stok tidak boleh negatif.'); redirect(BASE_URL . '/petugas/barang.php'); }
        $stmt = $db->prepare("UPDATE barang SET stok=? WHERE id=?");
        $stmt->execute([$stok, $barangId]);
        setFlash('success', 'Stok berhasil diperbarui.');
        redirect(BASE_URL . '/petugas/barang.php');
    }
}

// ── Handle DELETE ──
if ($action === 'delete' && $editId > 0) {
    if (!verifyCsrf($_GET['token'] ?? '')) {
        setFlash('error', 'Token tidak valid.');
        redirect(BASE_URL . '/petugas/barang.php');
    }
    // Check if item is currently borrowed
    $check = $db->prepare("SELECT COUNT(*) FROM peminjaman_detail pd JOIN peminjaman p ON pd.peminjaman_id = p.id WHERE pd.barang_id = ? AND p.status IN ('approved','borrowed')");
    $check->execute([$editId]);
    if ($check->fetchColumn() > 0) {
        setFlash('error', 'Tidak dapat menghapus barang yang sedang dipinjam.');
        redirect(BASE_URL . '/petugas/barang.php');
    }
    $stmt = $db->prepare("DELETE FROM barang WHERE id=?");
    $stmt->execute([$editId]);
    setFlash('success', 'Barang berhasil dihapus.');
    redirect(BASE_URL . '/petugas/barang.php');
}

// ── Fetch for edit ──
$editBarang = null;
if ($action === 'edit' && $editId > 0) {
    $stmt = $db->prepare("SELECT * FROM barang WHERE id=?");
    $stmt->execute([$editId]);
    $editBarang = $stmt->fetch();
    if (!$editBarang) { setFlash('error', 'Barang tidak ditemukan.'); redirect(BASE_URL . '/petugas/barang.php'); }
}

// ── List ──
$search = sanitize($_GET['q'] ?? '');
$kategoriFilter = sanitize($_GET['kategori'] ?? '');
$params = [];
$where = "WHERE 1=1";
if ($search) { $where .= " AND (nama_barang LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($kategoriFilter) { $where .= " AND kategori = ?"; $params[] = $kategoriFilter; }

$stmt = $db->prepare("SELECT * FROM barang $where ORDER BY nama_barang ASC");
$stmt->execute($params);
$barangList = $stmt->fetchAll();

$kategoris = $db->query("SELECT DISTINCT kategori FROM barang ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Manajemen Barang</h1>
        <p>Kelola inventaris peralatan laboratorium</p>
    </div>
    <a href="<?= BASE_URL ?>/petugas/barang.php?action=add" class="btn btn-primary">+ Tambah Barang</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Form Add/Edit -->
<div class="card" style="max-width:640px;margin-bottom:1.5rem">
    <div class="card-header">
        <span class="card-title"><?= $action === 'edit' ? 'Edit Barang' : 'Tambah Barang Baru' ?></span>
        <a href="<?= BASE_URL ?>/petugas/barang.php" class="btn btn-sm btn-secondary">← Kembali</a>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="save">
            <?php if ($editBarang): ?>
            <input type="hidden" name="barang_id" value="<?= $editBarang['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nama Barang *</label>
                    <input type="text" name="nama_barang" class="form-control" required
                        value="<?= sanitize($editBarang['nama_barang'] ?? '') ?>"
                        placeholder="Contoh: Mikroskop Binokuler">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <input type="text" name="kategori" class="form-control" list="kategori-list"
                        value="<?= sanitize($editBarang['kategori'] ?? 'Umum') ?>">
                    <datalist id="kategori-list">
                        <?php foreach ($kategoris as $k): ?>
                        <option value="<?= sanitize($k) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"
                    placeholder="Deskripsi singkat tentang barang ini..."><?= sanitize($editBarang['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="max-width:200px">
                <label class="form-label">Stok *</label>
                <input type="number" name="stok" class="form-control" min="0" required
                    value="<?= (int)($editBarang['stok'] ?? 0) ?>">
                <div class="form-hint">Jumlah unit yang tersedia</div>
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:0.5rem">
                <button type="submit" class="btn btn-primary">
                    <?= $action === 'edit' ? 'Simpan Perubahan' : 'Tambah Barang' ?>
                </button>
                <a href="<?= BASE_URL ?>/petugas/barang.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Search & Filter -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="padding:1rem 1.5rem">
        <form method="GET" action="" class="search-bar">
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="form-control" placeholder="Cari nama barang..." value="<?= sanitize($search) ?>">
            </div>
            <select name="kategori" class="form-control" style="width:180px">
                <option value="">Semua Kategori</option>
                <?php foreach ($kategoris as $k): ?>
                <option value="<?= sanitize($k) ?>" <?= $kategoriFilter === $k ? 'selected' : '' ?>><?= sanitize($k) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($search || $kategoriFilter): ?>
            <a href="<?= BASE_URL ?>/petugas/barang.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Barang</span>
        <span class="text-muted text-sm"><?= count($barangList) ?> barang ditemukan</span>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th>Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($barangList)): ?>
                <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">🧪</div><p>Tidak ada barang ditemukan</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($barangList as $i => $b): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:500"><?= sanitize($b['nama_barang']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:0.75rem;padding:0.2rem 0.6rem;background:var(--bg-hover);border-radius:20px;color:var(--text-secondary)">
                            <?= sanitize($b['kategori']) ?>
                        </span>
                    </td>
                    <td style="max-width:250px">
                        <div style="font-size:0.8rem;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= sanitize($b['deskripsi'] ?: '-') ?>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.75rem">
                            <span class="<?= getStokClass($b['stok']) ?>" style="font-family:var(--font-display);font-weight:700;font-size:1rem">
                                <?= $b['stok'] ?>
                            </span>
                            <!-- Quick stok update -->
                            <button class="btn btn-sm btn-secondary" onclick="openModal('stok-modal-<?= $b['id'] ?>')">✏</button>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:0.4rem">
                            <a href="<?= BASE_URL ?>/petugas/barang.php?action=edit&id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete('<?= BASE_URL ?>/petugas/barang.php?action=delete&id=<?= $b['id'] ?>&token=<?= csrfToken() ?>', '<?= sanitize($b['nama_barang']) ?>')">Hapus</button>
                        </div>
                    </td>
                </tr>

                <!-- Stok Modal -->
                <div id="stok-modal-<?= $b['id'] ?>" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('stok-modal-<?= $b['id'] ?>')">
                    <div class="modal">
                        <div class="modal-header">
                            <span class="modal-title">Update Stok: <?= sanitize($b['nama_barang']) ?></span>
                            <button class="modal-close" onclick="closeModal('stok-modal-<?= $b['id'] ?>')">✕</button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="update_stok">
                                <input type="hidden" name="barang_id" value="<?= $b['id'] ?>">
                                <div class="form-group">
                                    <label class="form-label">Stok Baru</label>
                                    <input type="number" name="stok_baru" class="form-control" min="0" value="<?= $b['stok'] ?>" required>
                                    <div class="form-hint">Stok saat ini: <?= $b['stok'] ?> unit</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('stok-modal-<?= $b['id'] ?>')">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>