<?php
// notulen/share.php - Tautan Publik Baca-Saja Khusus 1 Notulen (Tanpa Login, Token HMAC)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

ensure_notulen_tables($pdo);

$id    = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

$notulen      = null;
$items        = [];
$participants = [];

if ($id > 0 && $token !== '') {
    $stmt = $pdo->prepare("
        SELECT n.*, u1.nama_lengkap AS pembuat_nama
        FROM notulen_meeting n
        LEFT JOIN users u1 ON n.created_by = u1.id
        WHERE n.id = :id AND n.status != 'DRAFT'
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $candidate = $stmt->fetch();

    if ($candidate && hash_equals(get_notulen_share_token((int)$candidate['id'], $candidate['nomor_notulen']), $token)) {
        $notulen = $candidate;
        $iStmt = $pdo->prepare("SELECT * FROM notulen_action_items WHERE notulen_id = :id ORDER BY urutan ASC, id ASC");
        $iStmt->execute(['id' => $id]);
        $items = $iStmt->fetchAll();

        $pIds = json_decode($notulen['peserta_ids'] ?? '[]', true);
        if (is_array($pIds) && !empty($pIds)) {
            $cleanIds = array_values(array_unique(array_map('intval', $pIds)));
            $ph = implode(',', array_fill(0, count($cleanIds), '?'));
            $pStmt = $pdo->prepare("SELECT nama_lengkap, jabatan FROM users WHERE id IN ({$ph}) ORDER BY nama_lengkap ASC");
            $pStmt->execute($cleanIds);
            $participants = $pStmt->fetchAll();
        }
    }
}

$appName     = get_app_name();
$appFullName = get_app_full_name();
$appLogo     = get_app_logo_url();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $notulen ? sanitize($notulen['nomor_notulen']) . ' — ' : '' ?><?= sanitize($appName) ?></title>
  <script>
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '2100' ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/regular/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/bold/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/fill/style.css" />
</head>
<body class="min-h-screen bg-[#f5f5fa] dark:bg-[#121421] text-slate-800 dark:text-slate-100 font-sans py-6 px-4 sm:px-6">

  <div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 bg-white dark:bg-[#1b1e30] rounded-2xl px-5 py-3.5 border border-slate-200/80 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-[#6558f5] text-white flex items-center justify-center font-bold overflow-hidden p-1">
          <?php if ($appLogo): ?>
            <img src="<?= $appLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
          <?php else: ?>
            <i class="ph-fill ph-notepad text-lg"></i>
          <?php endif; ?>
        </div>
        <div>
          <h1 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight"><?= sanitize($appName) ?></h1>
          <p class="text-[11px] text-slate-400">Notulen Meeting &amp; Briefing (Baca Saja)</p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>auth/login"
         class="px-4 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-extrabold transition flex items-center gap-1.5">
        <i class="ph-bold ph-sign-in"></i>
        <span>Masuk Sistem</span>
      </a>
    </div>

    <?php if (!$notulen): ?>
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200 dark:border-slate-800 p-10 text-center shadow-sm">
        <div class="w-16 h-16 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 mx-auto flex items-center justify-center text-3xl mb-4">
          <i class="ph-bold ph-lock-key"></i>
        </div>
        <h2 class="text-lg font-black text-slate-900 dark:text-white">Tautan Notulen Tidak Valid atau Masih Draft</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 max-w-md mx-auto">
          Pastikan tautan lengkap dan notulen sudah berstatus FINAL, atau silakan masuk menggunakan akun Anda.
        </p>
      </div>
    <?php else: ?>
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5 flex-wrap">
            <span class="font-mono text-lg font-black text-slate-900 dark:text-white"><?= sanitize($notulen['nomor_notulen']) ?></span>
            <?= badge_status_notulen($notulen['status']) ?>
            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[11px] font-extrabold">
              <?= sanitize($notulen['jenis']) ?>
            </span>
          </div>
        </div>

        <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white"><?= sanitize($notulen['judul']) ?></h2>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828]">
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Tanggal</span>
            <strong><?= format_tanggal($notulen['tanggal']) ?></strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Waktu (WIB)</span>
            <strong><?= substr($notulen['jam_mulai'], 0, 5) ?><?= $notulen['jam_selesai'] ? ' - ' . substr($notulen['jam_selesai'], 0, 5) : '' ?></strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Lokasi / Ruang</span>
            <strong><?= sanitize($notulen['lokasi']) ?></strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Pimpinan / Notulis</span>
            <strong><?= sanitize($notulen['pimpinan_rapat']) ?></strong>
          </div>
        </div>

        <div>
          <span class="text-[11px] font-extrabold uppercase text-slate-400 block mb-2">Peserta</span>
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
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-5">
        <?php if (!empty($notulen['agenda'])): ?>
          <div>
            <h3 class="text-xs font-extrabold uppercase text-slate-400 mb-2">Agenda</h3>
            <div class="p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs whitespace-pre-line leading-relaxed"><?= sanitize($notulen['agenda']) ?></div>
          </div>
        <?php endif; ?>
        <div>
          <h3 class="text-xs font-extrabold uppercase text-slate-400 mb-2">Ringkasan Pembahasan</h3>
          <div class="p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs sm:text-sm whitespace-pre-line leading-relaxed"><?= !empty($notulen['pembahasan']) ? sanitize($notulen['pembahasan']) : '-' ?></div>
        </div>
        <?php if (!empty($notulen['keputusan'])): ?>
          <div>
            <h3 class="text-xs font-extrabold uppercase text-emerald-600 mb-2">Keputusan / Kesimpulan</h3>
            <div class="p-4 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-800/50 text-xs sm:text-sm whitespace-pre-line leading-relaxed"><?= sanitize($notulen['keputusan']) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($notulen['lampiran_foto'])): ?>
          <div>
            <h3 class="text-xs font-extrabold uppercase text-slate-400 mb-2">Dokumentasi</h3>
            <img src="<?= UPLOAD_URL . $notulen['lampiran_foto'] ?>" alt="Dokumentasi Rapat" class="max-h-72 rounded-2xl border border-slate-200 dark:border-slate-700 object-contain" />
          </div>
        <?php endif; ?>
      </div>

      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm">
        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white mb-4">Action Items / Tindak Lanjut (<?= count($items) ?>)</h3>
        <?php if (empty($items)): ?>
          <p class="text-xs text-slate-400 italic">Tidak ada action item.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[540px]">
              <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase text-[10px]">
                <tr>
                  <th class="py-2 px-3">No</th>
                  <th class="py-2 px-3">Uraian Tugas</th>
                  <th class="py-2 px-3">PIC</th>
                  <th class="py-2 px-3">Target</th>
                  <th class="py-2 px-3">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($items as $idx => $it): ?>
                  <tr>
                    <td class="py-2.5 px-3 font-bold text-slate-400"><?= $idx + 1 ?></td>
                    <td class="py-2.5 px-3 font-semibold"><?= sanitize($it['uraian_tugas']) ?></td>
                    <td class="py-2.5 px-3"><?= sanitize($it['pic'] ?: '-') ?></td>
                    <td class="py-2.5 px-3"><?= !empty($it['target_tanggal']) ? format_tanggal($it['target_tanggal']) : '-' ?></td>
                    <td class="py-2.5 px-3 font-bold"><?= sanitize($it['status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <p class="text-center text-[11px] text-slate-400 pt-2">
      <?= sanitize($appFullName) ?> • Halaman Publik Baca-Saja
    </p>
  </div>
</body>
</html>
