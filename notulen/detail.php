<?php
// notulen/detail.php - Detail Notulen Meeting & Briefing + Update Status Action Item
$current_page = 'notulen';
$page_title = 'Detail Notulen Meeting';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

require_login();
$user = current_user();
ensure_notulen_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'notulen/index');
    exit;
}

// Handle quick inline status update for an action item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_item_id'], $_POST['new_status'])) {
    $itemId = (int)$_POST['action_item_id'];
    $newSt  = $_POST['new_status'];
    if (in_array($newSt, ['Belum Mulai', 'Berjalan', 'Selesai'], true)) {
        $pdo->prepare("UPDATE notulen_action_items SET status = :st WHERE id = :iid AND notulen_id = :nid")
            ->execute(['st' => $newSt, 'iid' => $itemId, 'nid' => $id]);
        $pdo->prepare("UPDATE notulen_meeting SET updated_by = :uid WHERE id = :nid")
            ->execute(['uid' => (int)$user['id'], 'nid' => $id]);
        flash_set('success', 'Status tindak lanjut berhasil diperbarui.');
    }
    header('Location: ' . BASE_URL . 'notulen/detail?id=' . $id);
    exit;
}

$stmt = $pdo->prepare("
    SELECT n.*, u1.nama_lengkap AS pembuat_nama, u2.nama_lengkap AS pengubah_nama
    FROM notulen_meeting n
    LEFT JOIN users u1 ON n.created_by = u1.id
    LEFT JOIN users u2 ON n.updated_by = u2.id
    WHERE n.id = :id LIMIT 1
");
$stmt->execute(['id' => $id]);
$notulen = $stmt->fetch();

if (!$notulen) {
    flash_set('error', 'Notulen tidak ditemukan.');
    header('Location: ' . BASE_URL . 'notulen/index');
    exit;
}

$itemsStmt = $pdo->prepare("SELECT * FROM notulen_action_items WHERE notulen_id = :id ORDER BY urutan ASC, id ASC");
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

// Resolve internal participant names
$pIds = json_decode($notulen['peserta_ids'] ?? '[]', true);
$participants = [];
if (is_array($pIds) && !empty($pIds)) {
    $cleanIds = array_values(array_unique(array_map('intval', $pIds)));
    $ph = implode(',', array_fill(0, count($cleanIds), '?'));
    $pStmt = $pdo->prepare("SELECT id, nama_lengkap, jabatan FROM users WHERE id IN ({$ph}) ORDER BY nama_lengkap ASC");
    $pStmt->execute($cleanIds);
    $participants = $pStmt->fetchAll();
}

// Build WhatsApp summary text
$waLines = [
    "*NOTULEN " . strtoupper($notulen['jenis']) . "*",
    "No: {$notulen['nomor_notulen']}",
    "Topik: *{$notulen['judul']}*",
    "Tanggal: " . format_tanggal($notulen['tanggal']) . " | " . substr($notulen['jam_mulai'], 0, 5) . ($notulen['jam_selesai'] ? '-' . substr($notulen['jam_selesai'], 0, 5) : '') . " WIB",
    "Lokasi: {$notulen['lokasi']}",
    "Pimpinan/Notulis: {$notulen['pimpinan_rapat']}",
];
if (!empty($notulen['keputusan'])) {
    $waLines[] = "\n*Keputusan:*\n" . $notulen['keputusan'];
}
if (!empty($items)) {
    $waLines[] = "\n*Action Items:*";
    foreach ($items as $idx => $it) {
        $waLines[] = ($idx + 1) . ". {$it['uraian_tugas']} (PIC: " . ($it['pic'] ?: '-') . " | Status: {$it['status']})";
    }
}
require_once __DIR__ . '/../includes/notifications.php';
$notulenShareUrl = get_full_web_url("notulen/share?id={$notulen['id']}&token=" . get_notulen_share_token((int)$notulen['id'], $notulen['nomor_notulen']));
if ($notulen['status'] === 'FINAL') {
    $waLines[] = "\n*Tautan Dokumen (Tanpa Login):*\n{$notulenShareUrl}";
}
$waText = implode("\n", $waLines);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto pb-12 space-y-6 animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <a href="<?= BASE_URL ?>notulen/index" class="text-xs font-bold text-[#6558f5] hover:underline flex items-center gap-1 mb-2">
        <i class="ph-bold ph-arrow-left"></i>
        <span>Kembali ke Daftar Notulen</span>
      </a>
      <div class="flex items-center gap-3 flex-wrap">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
          <?= sanitize($notulen['nomor_notulen']) ?>
        </h2>
        <?= badge_status_notulen($notulen['status']) ?>
        <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[11px] font-extrabold">
          <?= sanitize($notulen['jenis']) ?>
        </span>
      </div>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Dicatat oleh <strong><?= sanitize($notulen['pembuat_nama'] ?? '-') ?></strong>
        <?php if (!empty($notulen['pengubah_nama'])): ?>
          • Terakhir diperbarui oleh <strong><?= sanitize($notulen['pengubah_nama']) ?></strong> (<?= date('d M Y, H:i', strtotime($notulen['updated_at'])) ?> WIB)
        <?php endif; ?>
      </p>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>notulen/create?edit=<?= $notulen['id'] ?>"
         class="px-4 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5">
        <i class="ph-bold ph-pencil-simple text-base"></i>
        <span>Edit / Update</span>
      </a>
      <button type="button" onclick="copyNotulenWA()"
              class="px-4 py-2.5 rounded-2xl bg-[#25D366] hover:bg-[#20bd5a] text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-whatsapp-logo text-base"></i>
        <span>Salin WA</span>
      </button>
      <?php if ($notulen['status'] === 'FINAL'): ?>
        <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($notulenShareUrl, ENT_QUOTES) ?>').then(() => alert('Link notulen tanpa login berhasil disalin!'))"
                class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-link text-base"></i>
          <span>Salin Link Publik</span>
        </button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>notulen/print?id=<?= $notulen['id'] ?>" target="_blank"
         class="px-4 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5">
        <i class="ph-bold ph-printer text-base"></i>
        <span>Cetak PDF</span>
      </a>
    </div>
  </div>

  <!-- Info Card -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8 space-y-5">
    <h3 class="text-lg sm:text-xl font-black text-[#181b2c] dark:text-white"><?= sanitize($notulen['judul']) ?></h3>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs">
      <div>
        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Tanggal</span>
        <strong class="text-slate-800 dark:text-white"><?= format_tanggal($notulen['tanggal']) ?></strong>
      </div>
      <div>
        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Waktu (WIB)</span>
        <strong class="text-slate-800 dark:text-white"><?= substr($notulen['jam_mulai'], 0, 5) ?><?= $notulen['jam_selesai'] ? ' - ' . substr($notulen['jam_selesai'], 0, 5) : '' ?></strong>
      </div>
      <div>
        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Lokasi / Ruang</span>
        <strong class="text-slate-800 dark:text-white"><?= sanitize($notulen['lokasi']) ?></strong>
      </div>
      <div>
        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Pimpinan / Notulis</span>
        <strong class="text-slate-800 dark:text-white"><?= sanitize($notulen['pimpinan_rapat']) ?></strong>
      </div>
    </div>

    <div>
      <span class="text-[11px] font-extrabold uppercase text-slate-400 block mb-2">Peserta Pertemuan</span>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach ($participants as $p): ?>
          <span class="px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold">
            <?= sanitize($p['nama_lengkap']) ?>
          </span>
        <?php endforeach; ?>
        <?php if (!empty($notulen['peserta_eksternal'])): ?>
          <span class="px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold">
            Eksternal: <?= sanitize($notulen['peserta_eksternal']) ?>
          </span>
        <?php endif; ?>
        <?php if (empty($participants) && empty($notulen['peserta_eksternal'])): ?>
          <span class="text-xs text-slate-400">-</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Agenda, Pembahasan, Keputusan -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8 space-y-6">
    <?php if (!empty($notulen['agenda'])): ?>
      <div>
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Agenda</h4>
        <div class="p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed"><?= sanitize($notulen['agenda']) ?></div>
      </div>
    <?php endif; ?>

    <div>
      <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Ringkasan Pembahasan</h4>
      <div class="p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs sm:text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed"><?= !empty($notulen['pembahasan']) ? sanitize($notulen['pembahasan']) : '<span class="text-slate-400 italic">Belum ada catatan pembahasan.</span>' ?></div>
    </div>

    <?php if (!empty($notulen['keputusan'])): ?>
      <div>
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-2">Keputusan / Kesimpulan</h4>
        <div class="p-4 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-800/50 text-xs sm:text-sm text-slate-800 dark:text-emerald-100 whitespace-pre-line leading-relaxed"><?= sanitize($notulen['keputusan']) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($notulen['lampiran_foto'])): ?>
      <div>
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Lampiran Dokumentasi</h4>
        <a href="<?= UPLOAD_URL . $notulen['lampiran_foto'] ?>" target="_blank" class="inline-block rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 max-w-md">
          <img src="<?= UPLOAD_URL . $notulen['lampiran_foto'] ?>" alt="Dokumentasi Rapat" class="max-h-72 object-contain bg-slate-100 dark:bg-slate-900" />
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Action Items -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
    <h4 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200 mb-4">
      Action Items / Tindak Lanjut (<?= count($items) ?>)
    </h4>

    <?php if (empty($items)): ?>
      <p class="text-xs text-slate-400 italic">Tidak ada action item yang dicatat.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs min-w-[620px]">
          <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase text-[10px] tracking-wider">
            <tr>
              <th class="py-2.5 px-3 w-10 text-center">No</th>
              <th class="py-2.5 px-3">Uraian Tugas</th>
              <th class="py-2.5 px-3 w-36">PIC</th>
              <th class="py-2.5 px-3 w-32">Target</th>
              <th class="py-2.5 px-3 w-40">Ubah Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($items as $idx => $it): ?>
              <tr>
                <td class="py-3 px-3 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                <td class="py-3 px-3 font-semibold text-slate-800 dark:text-slate-100"><?= sanitize($it['uraian_tugas']) ?></td>
                <td class="py-3 px-3 text-slate-600 dark:text-slate-300"><?= sanitize($it['pic'] ?: '-') ?></td>
                <td class="py-3 px-3 text-slate-500"><?= !empty($it['target_tanggal']) ? format_tanggal($it['target_tanggal']) : '-' ?></td>
                <td class="py-3 px-3">
                  <form method="POST" action="">
                    <input type="hidden" name="action_item_id" value="<?= $it['id'] ?>" />
                    <select name="new_status" onchange="this.form.submit()"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold cursor-pointer">
                      <?php foreach (['Belum Mulai', 'Berjalan', 'Selesai'] as $st): ?>
                        <option value="<?= $st ?>" <?= $it['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<textarea id="waNotulenPayload" class="hidden"><?= htmlspecialchars($waText) ?></textarea>
<script>
  function copyNotulenWA() {
    const txt = document.getElementById('waNotulenPayload').value;
    navigator.clipboard.writeText(txt).then(() => {
      alert('Ringkasan notulen berhasil disalin! Silakan paste ke WhatsApp.');
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
