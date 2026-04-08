// assets/js/app.js

// ── Auto-dismiss flash messages ──
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.querySelector('.flash');
  if (flash) {
    setTimeout(() => {
      flash.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-10px)';
      setTimeout(() => flash.remove(), 500);
    }, 4000);
  }

  // ── Modal handling ──
  document.querySelectorAll('[data-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.modal);
      if (target) target.style.display = 'flex';
    });
  });

  document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
    el.addEventListener('click', (e) => {
      if (e.target === el) {
        const overlay = el.closest('.modal-overlay') || el.parentElement.querySelector('.modal-overlay');
        if (overlay) overlay.style.display = 'none';
        else {
          const modal = el.closest('.modal-overlay');
          if (modal) modal.style.display = 'none';
        }
      }
    });
  });

  // Prevent modal content clicks from closing
  document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => e.stopPropagation());
  });

  // ESC key to close modals
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
  });
});

// ── Katalog: Keranjang Peminjaman ──
let keranjang = {};

function toggleBarang(id, nama, stok) {
  const card = document.querySelector(`[data-barang-id="${id}"]`);
  if (keranjang[id]) {
    delete keranjang[id];
    card?.classList.remove('selected');
  } else {
    if (stok <= 0) { showToast('Stok barang habis!', 'error'); return; }
    keranjang[id] = { nama, stok, jumlah: 1 };
    card?.classList.add('selected');
  }
  updateKeranjang();
}

function updateJumlah(id, delta) {
  if (!keranjang[id]) return;
  const newVal = keranjang[id].jumlah + delta;
  if (newVal <= 0) {
    removeFromKeranjang(id);
    return;
  }
  if (newVal > keranjang[id].stok) {
    showToast('Melebihi stok yang tersedia!', 'error'); return;
  }
  keranjang[id].jumlah = newVal;
  updateKeranjang();
}

function removeFromKeranjang(id) {
  if (!keranjang[id]) return;
  delete keranjang[id];
  const card = document.querySelector(`[data-barang-id="${id}"]`);
  card?.classList.remove('selected');
  updateKeranjang();
}

function updateKeranjang() {
  const list = document.getElementById('keranjang-list');
  const emptyMsg = document.getElementById('keranjang-empty');
  const submitBtn = document.getElementById('btn-submit-pinjam');
  const countBadge = document.getElementById('keranjang-count');
  const totalItems = Object.keys(keranjang).length;

  if (countBadge) countBadge.textContent = totalItems;
  if (submitBtn) submitBtn.disabled = totalItems === 0;

  if (!list) return;
  if (totalItems === 0) {
    list.innerHTML = '';
    if (emptyMsg) emptyMsg.style.display = 'block';
    return;
  }
  if (emptyMsg) emptyMsg.style.display = 'none';

  list.innerHTML = Object.entries(keranjang).map(([id, item]) => `
    <div class="keranjang-item">
      <div class="keranjang-nama">${escapeHtml(item.nama)}</div>
      <div class="keranjang-qty">
        <button class="qty-btn" onclick="updateJumlah(${id}, -1)">−</button>
        <span class="qty-num">${item.jumlah}</span>
        <button class="qty-btn" onclick="updateJumlah(${id}, 1)">+</button>
        <button class="btn-remove-item" onclick="removeFromKeranjang(${id})" title="Hapus">✕</button>
      </div>
    </div>
  `).join('');

  // Update hidden inputs
  const form = document.getElementById('form-pinjam');
  if (form) {
    form.querySelectorAll('.keranjang-input').forEach(i => i.remove());
    Object.entries(keranjang).forEach(([id, item]) => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = `items[${id}]`;
      inp.value = item.jumlah;
      inp.className = 'keranjang-input';
      form.appendChild(inp);
    });
  }
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `flash flash-${type}`;
  toast.style.cssText = 'position:fixed;bottom:2rem;right:2rem;z-index:9999;max-width:350px;animation:slideDown 0.3s ease';
  toast.innerHTML = `<span class="flash-icon">${type==='success'?'✓':type==='error'?'✕':'ℹ'}</span> ${escapeHtml(message)}`;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.5s'; setTimeout(()=>toast.remove(), 500); }, 3000);
}

// ── Confirm delete ──
function confirmDelete(url, name) {
  if (confirm(`Hapus "${name}"? Tindakan ini tidak dapat dibatalkan.`)) {
    window.location.href = url;
  }
}

// ── Table row click to show detail ──
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = 'flex';
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = 'none';
}