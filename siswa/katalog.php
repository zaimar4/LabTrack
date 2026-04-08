<?php
// siswa/katalog.php
require_once __DIR__ . '/../config/app.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Katalog Barang';

// Search & filter
$search = sanitize($_GET['q'] ?? '');
$kategori = sanitize($_GET['kategori'] ?? '');

$params = [];
$where = "WHERE 1=1";
if ($search) { $where .= " AND (nama_barang LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($kategori) { $where .= " AND kategori = ?"; $params[] = $kategori; }

$stmt = $db->prepare("SELECT * FROM barang $where ORDER BY kategori ASC, nama_barang ASC");
$stmt->execute($params);
$barangList = $stmt->fetchAll();

$kategoris = $db->query("SELECT DISTINCT kategori FROM barang ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

$kategoriIcons = [
    'Optik' => '🔭', 'Kaca' => '🧪', 'Pembakar' => '🔥',
    'Penyangga' => '🔩', 'Pengukur' => '📏', 'Elektronik' => '⚡',
    'Umum' => '🧰',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Katalog Barang</h1>
        <p>Pilih barang yang ingin dipinjam, lalu ajukan peminjaman</p>
    </div>
    <a href="<?= BASE_URL ?>/siswa/peminjaman.php" class="btn btn-secondary">Riwayat Peminjaman</a>
</div>

<div class="two-col">
<!-- Katalog -->
<div>
    <!-- Search -->
    <div style="margin-bottom:1.25rem">
        <form method="GET" class="search-bar">
            <div class="search-input-wrap" style="flex:1">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="form-control" placeholder="Cari barang laboratorium..." value="<?= sanitize($search) ?>">
            </div>
            <select name="kategori" class="form-control" style="width:160px" onchange="this.form.submit()">
                <option value="">Semua</option>
                <?php foreach ($kategoris as $k): ?>
                <option value="<?= sanitize($k) ?>" <?= $kategori===$k?'selected':'' ?>><?= sanitize($k) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if ($search||$kategori): ?>
            <a href="<?= BASE_URL ?>/siswa/katalog.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="katalog-grid" id="katalog-grid">
        <?php if (empty($barangList)): ?>
        <div style="grid-column:1/-1">
            <div class="empty-state"><div class="empty-icon">🔭</div><p>Tidak ada barang ditemukan</p></div>
        </div>
        <?php else: ?>
        <?php foreach ($barangList as $b): ?>
        <?php
            $icon = $kategoriIcons[$b['kategori']] ?? '🧰';
            $habis = $b['stok'] <= 0;
        ?>
        <div
            class="barang-card <?= $habis ? 'stok-nol' : '' ?>"
            data-barang-id="<?= $b['id'] ?>"
            onclick="<?= $habis ? '' : "toggleBarang({$b['id']}, '".addslashes(sanitize($b['nama_barang']))."', {$b['stok']})" ?>"
            title="<?= $habis ? 'Stok habis' : 'Klik untuk memilih' ?>"
            style="<?= $habis ? 'cursor:not-allowed' : '' ?>"
        >
            <div class="barang-icon"><?= $icon ?></div>
            <div class="barang-nama"><?= sanitize($b['nama_barang']) ?></div>
            <div class="barang-kategori"><?= sanitize($b['kategori']) ?></div>
            <div class="barang-deskripsi"><?= sanitize($b['deskripsi'] ?: 'Tidak ada deskripsi') ?></div>
            <div class="barang-stok">
                <span class="stok-label">Stok tersedia</span>
                <span class="stok-value <?= getStokClass($b['stok']) ?>"><?= $b['stok'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Keranjang -->
<div class="keranjang-sidebar">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                🛒 Keranjang Pinjam
                <span id="keranjang-count" style="background:var(--accent-blue);color:#0d1117;padding:0.1rem 0.5rem;border-radius:20px;font-size:0.72rem;font-weight:700;margin-left:0.35rem">0</span>
            </span>
        </div>
        <div class="card-body">
            <div id="keranjang-empty" style="text-align:center;color:var(--text-muted);padding:1.5rem 0;font-size:0.875rem">
                Belum ada barang dipilih.<br>
                <span style="font-size:0.8rem">Klik barang pada katalog untuk menambahkan.</span>
            </div>
            <div id="keranjang-list"></div>
        </div>

        <form id="form-pinjam" method="POST" action="<?= BASE_URL ?>/siswa/proses_pinjam.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="card-body" style="border-top:1px solid var(--border);padding-top:1rem">
                <div class="form-group">
                    <label class="form-label">Tanggal Pinjam *</label>
                    <input type="date" name="tanggal_pinjam" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rencana Tanggal Kembali</label>
                    <input type="date" name="tanggal_kembali" class="form-control" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea name="catatan" class="form-control" rows="2" placeholder="Keperluan peminjaman..."></textarea>
                </div>
                <button type="submit" id="btn-submit-pinjam" class="btn btn-primary w-full btn-lg" disabled>
                    Ajukan Peminjaman
                </button>
                <div class="text-muted text-sm" style="margin-top:0.5rem;text-align:center">
                    Peminjaman akan diproses setelah disetujui petugas
                </div>
            </div>
        </form>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>