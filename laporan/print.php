<?php
// laporan/print.php - Format Dokumen Cetak Standar OMIT (1:1 Identik dengan Template PDF)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("ID Laporan tidak valid.");
}

$stmt = $pdo->prepare("
    SELECT l.*, s.nama_shift, m.nama_lokasi,
           u1.nama_lengkap AS nama_menyerahkan, u1.jabatan AS jabatan_menyerahkan,
           u2.nama_lengkap AS nama_menerima, u2.jabatan AS jabatan_menerima
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    WHERE l.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$report = $stmt->fetch();

if (!$report) {
    die("Laporan serah terima tidak ditemukan.");
}

// Fetch tasks
$stmtTasks = $pdo->prepare("SELECT * FROM laporan_pekerjaan WHERE laporan_id = :id ORDER BY urutan ASC, id ASC");
$stmtTasks->execute(['id' => $id]);
$tasks = $stmtTasks->fetchAll();

// Calculate metrics
$totalAktif = 0;
$totalKritis = 0;
foreach ($tasks as $t) {
    if ($t['status'] !== 'Selesai') $totalAktif++;
    if ($t['prioritas'] === 'Tinggi / Kritis' && $t['status'] !== 'Selesai') $totalKritis++;
}

// Prepare 10 rows (if less than 10, fill empty rows like in Excel/PDF template)
$displayRows = [];
for ($i = 0; $i < 10; $i++) {
    if (isset($tasks[$i])) {
        $displayRows[] = $tasks[$i];
    } else {
        $displayRows[] = [
            'no_tiket' => '',
            'pekerjaan_masalah' => '',
            'prioritas' => '',
            'status' => '',
            'kondisi_tindak_lanjut' => '',
            'pic' => '',
            'target_selesai' => ''
        ];
    }
}

$appLogo = get_app_logo_url();
$appName = get_app_name();
$appFullName = get_app_full_name();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Formulir_Serah_Terima_Shift_<?= sanitize($report['nomor_laporan']) ?></title>
  
  <style>
    /* PAGE SETUP FOR A4 PRINT */
    @page {
      size: A4 portrait;
      margin: 8mm 10mm 8mm 10mm;
    }

    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
      font-size: 8.5pt;
      color: #1a1a1a;
      background-color: #f1f3f8;
    }

    /* Print Preview Wrapper on Screen */
    .screen-container {
      max-width: 210mm;
      margin: 15px auto;
      background: #ffffff;
      padding: 8mm 10mm;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      border-radius: 4px;
    }

    @media print {
      body {
        background: #ffffff;
      }
      .screen-container {
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
        width: 100%;
      }
      .no-print {
        display: none !important;
      }
    }

    /* Floating Tool bar */
    .print-toolbar {
      position: sticky;
      top: 10px;
      z-index: 999;
      max-width: 210mm;
      margin: 10px auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 18px;
      background: #1e3860;
      color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(30, 56, 96, 0.25);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .print-btn {
      background: #22c55e;
      color: #ffffff;
      border: none;
      padding: 8px 18px;
      font-size: 13px;
      font-weight: bold;
      border-radius: 8px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .print-btn:hover {
      background: #16a34a;
      transform: translateY(-1px);
    }
    .back-btn {
      color: #cbd5e1;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
    }
    .back-btn:hover {
      color: #ffffff;
    }

    /* TABLE LAYOUT & STYLES */
    table.doc-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 6px;
      table-layout: fixed;
    }

    table.doc-table td, table.doc-table th {
      border: 1px solid #7f99b2;
      padding: 3px 5px;
      font-size: 8pt;
      line-height: 1.15;
      vertical-align: middle;
      overflow: hidden;
      word-wrap: break-word;
    }

    /* Colors matching PDF template */
    .bg-navy-header {
      background-color: #1e3860 !important;
      color: #ffffff !important;
      font-size: 9.5pt;
      font-weight: bold;
      letter-spacing: 0.3px;
      text-align: left;
      padding: 4px 6px !important;
    }

    .main-doc-title {
      background-color: #1e3860 !important;
      color: #ffffff !important;
      font-size: 12pt;
      font-weight: bold;
      letter-spacing: 0.5px;
      padding: 6px !important;
      text-align: left;
    }

    .sub-guide-banner {
      background-color: #e8eef7 !important;
      color: #334e68 !important;
      font-size: 7pt;
      font-style: italic;
      padding: 2.5px 6px !important;
      border: 1px solid #7f99b2;
      border-top: none;
      margin-bottom: 4px;
    }

    .bg-th {
      background-color: #d9e1f2 !important;
      color: #1e3860 !important;
      font-weight: bold;
      text-align: center;
      font-size: 7.5pt;
    }

    .bg-krem {
      background-color: #fffbf0 !important;
    }

    .bg-green-auto {
      background-color: #e2efda !important;
      color: #276738 !important;
      font-weight: bold;
      text-align: center;
      font-size: 13pt;
    }

    .bg-light-blue {
      background-color: #f2f5fb !important;
    }

    .text-center { text-align: center !important; }
    .text-left { text-align: left !important; }
    .text-right { text-align: right !important; }
    .font-bold { font-weight: bold !important; }

    /* Photo container */
    .photo-cell {
      height: 125px;
      background-color: #fafbfc;
      text-align: center;
      vertical-align: middle !important;
      padding: 2px !important;
    }
    .photo-cell img {
      max-width: 100%;
      max-height: 120px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
    }
    .photo-placeholder {
      color: #8c9ba5;
      font-size: 8pt;
      font-style: italic;
    }

    /* Signature image */
    .sig-img {
      max-height: 40px;
      max-width: 100%;
      display: block;
      margin: 0 auto;
    }
  </style>
</head>
<body>

  <!-- Floating Screen Toolbar -->
  <div class="print-toolbar no-print">
    <div>
      <span style="font-weight: bold; font-size: 14px;">Pratinjau Dokumen Cetak OMIT</span>
      <span style="color: #94a3b8; font-size: 12px; margin-left: 8px;">(Format 1:1 Sesuai Template PDF)</span>
    </div>
    <div style="display: flex; items-center; gap: 14px;">
      <a href="<?= BASE_URL ?>laporan/detail?id=<?= $report['id'] ?>" class="back-btn">← Kembali ke Sistem</a>
      <button onclick="window.print()" class="print-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"></polyline>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
          <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        <span>Cetak / Simpan ke PDF</span>
      </button>
    </div>
  </div>

  <!-- MAIN DOCUMENT CONTAINER -->
  <div class="screen-container">

    <!-- TITLE & GUIDELINE BANNER -->
    <div style="background-color: #1e3860; color: #ffffff; padding: 6px 10px; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <?php if (!empty($appLogo)): ?>
          <img src="<?= $appLogo ?>" alt="Logo" style="max-height: 26px; max-width: 90px; object-fit: contain; background-color: #ffffff; padding: 2px 4px; border-radius: 3px; display: inline-block;">
        <?php endif; ?>
        <div>
          <div style="font-size: 11pt; font-weight: bold; letter-spacing: 0.5px;">FORMULIR SERAH TERIMA SHIFT OPERASIONAL</div>
          <div style="font-size: 7pt; opacity: 0.85; font-weight: normal;"><?= sanitize($appFullName) ?></div>
        </div>
      </div>
      <div style="text-align: right;">
        <span style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; background: rgba(255,255,255,0.18); padding: 2px 7px; border-radius: 3px; letter-spacing: 0.5px;">
          <?= sanitize($appName) ?>
        </span>
      </div>
    </div>
    <div class="sub-guide-banner">
      Panduan Cepat: Isi kotak krem (gunakan dropdown). Kotak hijau terisi otomatis. Sisipkan foto bukti di kotak dokumentasi foto (Insert &gt; Pictures).
    </div>

    <!-- ==========================================
         BAGIAN 1: INFORMASI SHIFT & KONDISI UMUM
         ========================================== -->
    <table class="doc-table">
      <thead>
        <tr>
          <th colspan="5" class="bg-navy-header">1. INFORMASI SHIFT &amp; KONDISI UMUM</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="bg-th" style="width: 18%;">Tanggal</td>
          <td class="bg-th" style="width: 18%;">Shift</td>
          <td class="bg-th" style="width: 18%;">Jam Serah Terima</td>
          <td class="bg-th" style="width: 23%;">Lokasi / Unit</td>
          <td class="bg-th" style="width: 23%;">Kondisi Operasional Umum</td>
        </tr>
        <tr>
          <td class="text-center bg-krem font-bold"><?= format_tanggal($report['tanggal']) ?></td>
          <td class="text-center bg-krem font-bold"><?= sanitize($report['nama_shift']) ?></td>
          <td class="text-center bg-krem font-bold"><?= format_jam($report['jam_serah_terima']) ?></td>
          <td class="text-center bg-krem font-bold"><?= sanitize($report['nama_lokasi']) ?></td>
          <td class="text-center bg-krem font-bold"><?= sanitize($report['kondisi_operasional']) ?></td>
        </tr>
        <tr>
          <td colspan="2" class="bg-th">Petugas Menyerahkan (Shift Lama)</td>
          <td class="bg-th">Petugas Menerima (Shift Baru)</td>
          <td class="bg-th">Total Pekerjaan Aktif</td>
          <td class="bg-th">Pekerjaan Prioritas Tinggi/Kritis</td>
        </tr>
        <tr>
          <td colspan="2" class="text-center bg-krem font-bold" style="padding: 6px;">
            <?= sanitize($report['nama_menyerahkan']) ?>
          </td>
          <td class="text-center bg-krem font-bold" style="padding: 6px;">
            <?= sanitize($report['nama_menerima'] ?? '(Menunggu Pengesahan)') ?>
          </td>
          <td class="bg-green-auto"><?= $totalAktif ?></td>
          <td class="bg-green-auto" style="<?= $totalKritis > 0 ? 'color: #b91c1c !important;' : '' ?>"><?= $totalKritis ?></td>
        </tr>
        <tr>
          <td colspan="5" class="bg-th" style="text-align: left; padding: 2px 5px;">
            Catatan Penting / Kendala Utama Shift Ini (Opsional)
          </td>
        </tr>
        <tr>
          <td colspan="5" class="bg-krem text-left" style="min-height: 28px; height: 32px; padding: 4px 6px;">
            <?= !empty($report['catatan_penting']) ? nl2br(sanitize($report['catatan_penting'])) : '&nbsp;' ?>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ========================================================
         BAGIAN 2: DAFTAR PEKERJAAN & TINDAK LANJUT SHIFT BERIKUTNYA
         ======================================================== -->
    <table class="doc-table">
      <thead>
        <tr>
          <th colspan="7" class="bg-navy-header">2. DAFTAR PEKERJAAN &amp; TINDAK LANJUT UNTUK SHIFT BERIKUTNYA</th>
        </tr>
        <tr>
          <th colspan="7" class="text-left font-normal" style="background-color: #f2f5fb; font-style: italic; font-size: 7pt; padding: 2px 5px;">
            Tips: Cukup tulis pekerjaan yang belum selesai atau perlu dipantau. Gunakan dropdown pada kolom Prioritas, Status, dan PIC.
          </th>
        </tr>
        <tr>
          <th class="bg-th" style="width: 11%;">No / Tiket</th>
          <th class="bg-th" style="width: 25%;">Pekerjaan / Masalah</th>
          <th class="bg-th" style="width: 12%;">Prioritas</th>
          <th class="bg-th" style="width: 10%;">Status</th>
          <th class="bg-th" style="width: 24%;">Kondisi Terakhir &amp; Tindak Lanjut</th>
          <th class="bg-th" style="width: 10%;">PIC</th>
          <th class="bg-th" style="width: 8%;">Target Selesai</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($displayRows as $row): ?>
          <tr style="height: 18px;">
            <td class="text-center bg-krem font-bold" style="font-size: 7.5pt;"><?= sanitize($row['no_tiket']) ?: '&nbsp;' ?></td>
            <td class="text-left bg-krem" style="font-size: 7.5pt;"><?= sanitize($row['pekerjaan_masalah']) ?: '&nbsp;' ?></td>
            <td class="text-center bg-krem" style="font-size: 7.5pt; <?= $row['prioritas'] === 'Tinggi / Kritis' ? 'color: #b91c1c; font-weight: bold;' : '' ?>">
              <?= sanitize($row['prioritas']) ?: '&nbsp;' ?>
            </td>
            <td class="text-center bg-krem" style="font-size: 7.5pt; font-weight: 500;"><?= sanitize($row['status']) ?: '&nbsp;' ?></td>
            <td class="text-left bg-krem" style="font-size: 7.5pt;"><?= sanitize($row['kondisi_tindak_lanjut']) ?: '&nbsp;' ?></td>
            <td class="text-center bg-krem" style="font-size: 7.5pt;"><?= sanitize($row['pic']) ?: '&nbsp;' ?></td>
            <td class="text-center bg-krem" style="font-size: 7.5pt;"><?= sanitize($row['target_selesai']) ?: '&nbsp;' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- ========================================================
         BAGIAN 3: DOKUMENTASI FOTO / BUKTI VISUAL SHIFT
         ======================================================== -->
    <table class="doc-table">
      <thead>
        <tr>
          <th colspan="3" class="bg-navy-header">3. DOKUMENTASI FOTO / BUKTI VISUAL SHIFT (KONDISI LAPANGAN / LOGBOOK / PANEL)</th>
        </tr>
        <tr>
          <th class="bg-th" style="width: 33.33%;">Foto 1: Kondisi Area / Peralatan</th>
          <th class="bg-th" style="width: 33.33%;">Foto 2: Temuan / Kendala / Indikator</th>
          <th class="bg-th" style="width: 33.33%;">Foto 3: Bukti Serah Terima / Lainnya</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <!-- FOTO 1 -->
          <td class="photo-cell">
            <?php if (!empty($report['foto_1']) && file_exists(UPLOAD_DIR . $report['foto_1'])): ?>
              <img src="<?= UPLOAD_URL . $report['foto_1'] ?>" alt="Foto 1" />
            <?php else: ?>
              <div class="photo-placeholder">[Tempel / Insert Foto 1 di sini]</div>
            <?php endif; ?>
          </td>

          <!-- FOTO 2 -->
          <td class="photo-cell">
            <?php if (!empty($report['foto_2']) && file_exists(UPLOAD_DIR . $report['foto_2'])): ?>
              <img src="<?= UPLOAD_URL . $report['foto_2'] ?>" alt="Foto 2" />
            <?php else: ?>
              <div class="photo-placeholder">[Tempel / Insert Foto 2 di sini]</div>
            <?php endif; ?>
          </td>

          <!-- FOTO 3 -->
          <td class="photo-cell">
            <?php if (!empty($report['foto_3']) && file_exists(UPLOAD_DIR . $report['foto_3'])): ?>
              <img src="<?= UPLOAD_URL . $report['foto_3'] ?>" alt="Foto 3" />
            <?php else: ?>
              <div class="photo-placeholder">[Tempel / Insert Foto 3 di sini]</div>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="text-left bg-light-blue" style="font-size: 7.5pt;">
            <strong>Keterangan Foto 1:</strong> <?= sanitize($report['ket_foto_1'] ?: '') ?>
          </td>
          <td class="text-left bg-light-blue" style="font-size: 7.5pt;">
            <strong>Keterangan Foto 2:</strong> <?= sanitize($report['ket_foto_2'] ?: '') ?>
          </td>
          <td class="text-left bg-light-blue" style="font-size: 7.5pt;">
            <strong>Keterangan Foto 3:</strong> <?= sanitize($report['ket_foto_3'] ?: '') ?>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ========================================================
         BAGIAN 4: KONFIRMASI & PENGESAHAN SERAH TERIMA
         ======================================================== -->
    <table class="doc-table" style="margin-bottom: 0;">
      <thead>
        <tr>
          <th colspan="4" class="bg-navy-header">4. KONFIRMASI &amp; PENGESAHAN SERAH TERIMA</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="text-left" style="width: 38%;">1. Pekerjaan &amp; kendala sudah dijelaskan lisan?</td>
          <td class="text-center bg-krem font-bold" style="width: 12%;"><?= sanitize($report['check_lisan']) ?></td>
          <td class="text-left" style="width: 38%;">2. Tiket/catatan sistem sudah diperbarui?</td>
          <td class="text-center bg-krem font-bold" style="width: 12%;"><?= sanitize($report['check_tiket']) ?></td>
        </tr>
        <tr>
          <td class="text-left">3. Risiko &amp; target waktu sudah diinfokan?</td>
          <td class="text-center bg-krem font-bold"><?= sanitize($report['check_risiko']) ?></td>
          <td class="text-left">4. Kunci / alat / akses sudah diserahkan?</td>
          <td class="text-center bg-krem font-bold"><?= sanitize($report['check_kunci']) ?></td>
        </tr>
        <tr>
          <td class="bg-th">Diserahkan Oleh (Otomatis)</td>
          <td class="bg-th" colspan="2">Diterima Oleh (Otomatis)</td>
          <td class="bg-th">Jam Serah Terima</td>
        </tr>
        <tr style="height: 52px;">
          <!-- Diserahkan Oleh -->
          <td class="text-center bg-krem font-bold" style="vertical-align: middle; padding: 4px;">
            <?php 
              $srcMenyerahkan = '';
              if (!empty($report['ttd_menyerahkan'])) {
                  if (str_starts_with($report['ttd_menyerahkan'], 'data:image')) {
                      $srcMenyerahkan = $report['ttd_menyerahkan'];
                  } elseif (file_exists(UPLOAD_DIR . $report['ttd_menyerahkan'])) {
                      $srcMenyerahkan = UPLOAD_URL . $report['ttd_menyerahkan'];
                  }
              }
            ?>
            <?php if (!empty($srcMenyerahkan)): ?>
              <img src="<?= $srcMenyerahkan ?>" class="sig-img" alt="TTD" style="max-height: 42px; max-width: 90px; margin: 0 auto 2px auto;" />
            <?php endif; ?>
            <div style="font-size: 8pt;"><?= sanitize($report['nama_menyerahkan']) ?></div>
            <div style="font-size: 6.5pt; color: #166534; font-weight: normal;">
              <?= strtoupper($report['jenis_ttd_menyerahkan'] ?? 'DIGITAL') ?> VERIFIED
            </div>
          </td>

          <!-- Diterima Oleh (BARU MUNCUL SETELAH APPROVAL) -->
          <td class="text-center bg-krem font-bold" colspan="2" style="vertical-align: middle; padding: 4px;">
            <?php 
              $srcMenerima = '';
              if ($report['status_validasi'] === 'TERVALIDASI' && !empty($report['ttd_menerima'])) {
                  if (str_starts_with($report['ttd_menerima'], 'data:image')) {
                      $srcMenerima = $report['ttd_menerima'];
                  } elseif (file_exists(UPLOAD_DIR . $report['ttd_menerima'])) {
                      $srcMenerima = UPLOAD_URL . $report['ttd_menerima'];
                  }
              }
            ?>
            <?php if (!empty($srcMenerima)): ?>
              <img src="<?= $srcMenerima ?>" class="sig-img" alt="TTD" style="max-height: 42px; max-width: 90px; margin: 0 auto 2px auto;" />
              <div style="font-size: 8pt;"><?= sanitize($report['nama_menerima']) ?></div>
              <div style="font-size: 6.5pt; color: #166534; font-weight: normal;">
                APPROVED (<?= strtoupper($report['jenis_ttd_menerima'] ?? 'DIGITAL') ?>)
              </div>
            <?php else: ?>
              <div style="color: #d97706; font-size: 7.5pt; font-style: italic; font-weight: bold;">
                (Menunggu Approval &amp; Tanda Tangan Shift Baru)
              </div>
              <div style="font-size: 8pt; color: #475569; margin-top: 2px;">
                <?= $report['petugas_menerima_id'] ? sanitize($report['nama_menerima']) : '(Isi nama di Bagian 1)' ?>
              </div>
            <?php endif; ?>
          </td>

          <!-- Jam Serah Terima -->
          <td class="text-center bg-krem font-bold" style="vertical-align: middle; font-size: 9pt;">
            <?= format_jam($report['jam_serah_terima']) ?>
          </td>
        </tr>
        <tr>
          <td colspan="4" class="text-center font-bold" style="padding: 4px; background-color: <?= $report['status_validasi'] === 'TERVALIDASI' ? '#e2efda' : '#fff2cc' ?>; color: <?= $report['status_validasi'] === 'TERVALIDASI' ? '#276738' : '#b45309' ?>; font-size: 8.5pt;">
            Status Validasi Serah Terima: 
            <?= $report['status_validasi'] === 'TERVALIDASI' ? 'TERVALIDASI (LENGKAP)' : ($report['petugas_menerima_id'] ? 'MENUNGGU PENGESAHAN PETUGAS BARU' : 'MENUNGGU NAMA PETUGAS') ?>
          </td>
        </tr>
      </tbody>
    </table>

  </div>

</body>
</html>

