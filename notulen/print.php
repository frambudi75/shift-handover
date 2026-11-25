<?php
// notulen/print.php - Cetak PDF A4 Notulen Meeting / Briefing
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

require_login();
ensure_notulen_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM notulen_meeting WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$notulen = $stmt->fetch();

if (!$notulen) {
    die('Notulen tidak ditemukan.');
}

$itemsStmt = $pdo->prepare("SELECT * FROM notulen_action_items WHERE notulen_id = :id ORDER BY urutan ASC, id ASC");
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

$pIds = json_decode($notulen['peserta_ids'] ?? '[]', true);
$participants = [];
if (is_array($pIds) && !empty($pIds)) {
    $cleanIds = array_values(array_unique(array_map('intval', $pIds)));
    $ph = implode(',', array_fill(0, count($cleanIds), '?'));
    $pStmt = $pdo->prepare("SELECT nama_lengkap, jabatan FROM users WHERE id IN ({$ph}) ORDER BY nama_lengkap ASC");
    $pStmt->execute($cleanIds);
    $participants = $pStmt->fetchAll();
}

$appName    = get_setting('app_name', 'OMIT Shift Handover');
$companySub = get_setting('company_name', 'Operasional Terpadu');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Notulen <?= sanitize($notulen['nomor_notulen']) ?></title>
  <style>
    @page { size: A4 portrait; margin: 14mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; font-size: 10pt; line-height: 1.45; margin: 0; }
    .header { border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: flex-end; }
    .header h1 { margin: 0; font-size: 14pt; text-transform: uppercase; }
    .header p { margin: 2px 0 0; font-size: 9pt; color: #475569; }
    .doc-no { font-family: monospace; font-weight: bold; font-size: 10pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; font-size: 9pt; text-transform: uppercase; }
    .section-title { font-size: 10pt; font-weight: bold; text-transform: uppercase; background: #e2e8f0; padding: 5px 8px; margin: 12px 0 6px; }
    .box { border: 1px solid #cbd5e1; padding: 8px 10px; white-space: pre-line; min-height: 36px; }
    .sign-box { margin-top: 24px; width: 240px; margin-left: auto; text-align: center; }
    .sign-space { height: 55px; }
    .no-print { margin-bottom: 12px; padding: 10px; background: #eef2ff; border: 1px solid #c7d2fe; display: flex; justify-content: space-between; align-items: center; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print">
    <span>Preview Cetak Notulen <strong><?= sanitize($notulen['nomor_notulen']) ?></strong></span>
    <button onclick="window.print()" style="padding:6px 14px; background:#6558f5; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Cetak / Simpan PDF</button>
  </div>

  <div class="header">
    <div>
      <h1>NOTULEN <?= strtoupper(sanitize($notulen['jenis'])) ?></h1>
      <p><?= sanitize($appName) ?> • <?= sanitize($companySub) ?></p>
    </div>
    <div style="text-align:right;">
      <div class="doc-no"><?= sanitize($notulen['nomor_notulen']) ?></div>
      <div style="font-size:8.5pt;">Status: <strong><?= sanitize($notulen['status']) ?></strong></div>
    </div>
  </div>

  <table>
    <tr>
      <th style="width:18%;">Topik / Judul</th>
      <td colspan="3"><strong><?= sanitize($notulen['judul']) ?></strong></td>
    </tr>
    <tr>
      <th>Tanggal</th>
      <td style="width:32%;"><?= format_tanggal($notulen['tanggal']) ?></td>
      <th style="width:18%;">Waktu</th>
      <td><?= substr($notulen['jam_mulai'], 0, 5) ?><?= $notulen['jam_selesai'] ? ' - ' . substr($notulen['jam_selesai'], 0, 5) : '' ?> WIB</td>
    </tr>
    <tr>
      <th>Lokasi / Ruang</th>
      <td><?= sanitize($notulen['lokasi']) ?></td>
      <th>Pimpinan / Notulis</th>
      <td><?= sanitize($notulen['pimpinan_rapat']) ?></td>
    </tr>
    <tr>
      <th>Peserta</th>
      <td colspan="3">
        <?php
          $names = array_map(fn($p) => $p['nama_lengkap'] . ($p['jabatan'] ? " ({$p['jabatan']})" : ''), $participants);
          if (!empty($notulen['peserta_eksternal'])) $names[] = 'Eksternal: ' . $notulen['peserta_eksternal'];
          echo $names ? sanitize(implode(', ', $names)) : '-';
        ?>
      </td>
    </tr>
  </table>

  <?php if (!empty($notulen['agenda'])): ?>
    <div class="section-title">Agenda Pertemuan</div>
    <div class="box"><?= sanitize($notulen['agenda']) ?></div>
  <?php endif; ?>

  <div class="section-title">Ringkasan Pembahasan</div>
  <div class="box"><?= !empty($notulen['pembahasan']) ? sanitize($notulen['pembahasan']) : '-' ?></div>

  <div class="section-title">Keputusan / Kesimpulan</div>
  <div class="box"><?= !empty($notulen['keputusan']) ? sanitize($notulen['keputusan']) : '-' ?></div>

  <div class="section-title">Action Items / Tindak Lanjut</div>
  <table>
    <thead>
      <tr>
        <th style="width:6%; text-align:center;">No</th>
        <th>Uraian Tugas</th>
        <th style="width:20%;">PIC</th>
        <th style="width:16%;">Target</th>
        <th style="width:15%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="5" style="text-align:center;">Tidak ada action item.</td></tr>
      <?php else: ?>
        <?php foreach ($items as $idx => $it): ?>
          <tr>
            <td style="text-align:center;"><?= $idx + 1 ?></td>
            <td><?= sanitize($it['uraian_tugas']) ?></td>
            <td><?= sanitize($it['pic'] ?: '-') ?></td>
            <td><?= !empty($it['target_tanggal']) ? format_tanggal($it['target_tanggal']) : '-' ?></td>
            <td><strong><?= sanitize($it['status']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="sign-box">
    <div>Pimpinan Rapat / Notulis,</div>
    <div class="sign-space"></div>
    <div style="border-top:1px solid #334155; padding-top:4px; font-weight:bold;"><?= sanitize($notulen['pimpinan_rapat']) ?></div>
  </div>
</body>
</html>
