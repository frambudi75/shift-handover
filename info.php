<?php
// info.php - Informasi Sistem, Versi Aplikasi & Riwayat Pembaruan
$current_page = 'info';
$page_title = 'Informasi Sistem & Pembaruan';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_once __DIR__ . '/includes/header.php';

// Live System Telemetry
$appVersion     = APP_VERSION;
$releaseDate    = '27 November 2026';
$isDocker       = file_exists('/.dockerenv') || getenv('DB_HOST') !== false;
$envLabel       = $isDocker ? 'Docker Container (PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ')' : 'Web Server (' . PHP_OS_FAMILY . ')';
$dbVersion      = $pdo->query("SELECT VERSION()")->fetchColumn();
$cacheInfo      = cache_get_stats();
$opcacheEnabled = function_exists('opcache_get_status') && @opcache_get_status(false) !== false;
$uploadWritable = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);
$diskFreeBytes  = @disk_free_space(__DIR__);
$diskFreeFmt    = $diskFreeBytes !== false ? format_bytes((int)$diskFreeBytes) : '—';

// Database record summary
$cntLaporan = (int)$pdo->query("SELECT COUNT(*) FROM laporan_serah_terima")->fetchColumn();
$cntTugas   = (int)$pdo->query("SELECT COUNT(*) FROM laporan_pekerjaan")->fetchColumn();
$cntUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'aktif'")->fetchColumn();
$cntLokasi  = (int)$pdo->query("SELECT COUNT(*) FROM master_lokasi WHERE status = 'aktif'")->fetchColumn();

// Extension checks
$extensions = [
    ['name' => 'PDO MySQL',     'ok' => extension_loaded('pdo_mysql'), 'desc' => 'Koneksi database aman (Prepared Statements)'],
    ['name' => 'GD Library',    'ok' => extension_loaded('gd'),        'desc' => 'Kompresi & rotasi foto lampiran'],
    ['name' => 'Redis Cache',   'ok' => class_exists('Redis'),         'desc' => 'Akselerator in-memory cache'],
    ['name' => 'OPcache JIT',   'ok' => $opcacheEnabled,               'desc' => 'Kompilasi bytecode PHP di RAM'],
    ['name' => 'cURL / HTTPS',  'ok' => extension_loaded('curl'),      'desc' => 'Integrasi WhatsApp Gateway & Webhook'],
    ['name' => 'ZipArchive',    'ok' => extension_loaded('zip'),       'desc' => 'Kompresi arsip & paket backup ZIP'],
    ['name' => 'FileInfo MIME', 'ok' => extension_loaded('fileinfo'),  'desc' => 'Validasi keamanan tipe berkas upload'],
    ['name' => 'LDAP Directory','ok' => extension_loaded('ldap'),      'desc' => 'Integrasi autentikasi AD / OpenLDAP'],
    ['name' => 'Uploads Dir',   'ok' => $uploadWritable,               'desc' => 'Hak tulis direktori lampiran & logo'],
];

// Release History / Changelog Data
$releases = [
    [
        'version' => 'v2.12.0 LTS',
        'date'    => '27 Nov 2026',
        'title'   => 'Kalender Jadwal Kerja, Libur Nasional & Cuti Bersama, Drag-Drop Kanban & Portal Scan QR Kunci Tamu',
        'badge'   => 'TERBARU',
        'color'   => 'bg-[#6558f5] text-white',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Kalender Jadwal Kerja Pribadi di /kanban dengan dropdown Bulan & Tahun, jadwal berulang (harian/Sen-Jum/mingguan), jadwal 7 hari ke depan, dan Export .ICS ke Google/Apple Calendar.'],
            ['type' => 'Baru',      'text' => 'Integrasi otomatis Hari Libur Nasional & Cuti Bersama SKB 3 Menteri (2025-2027) lengkap dengan penanda warna berbeda di kalender dan panel agenda.'],
            ['type' => 'Baru',      'text' => 'Drag & Drop kartu Kanban antar kolom via AJAX serta fitur Edit Tugas Pribadi langsung dari papan Kanban.'],
            ['type' => 'Baru',      'text' => 'Portal Scan QR Kunci Tamu (/aset/scan) bertoken HMAC tanpa login, modal Label QR dengan Simpan PNG & Cetak Label, serta Cetak Semua QR grid A4 (/aset/print_qr).'],
            ['type' => 'Keamanan',  'text' => 'Validasi anti-duplikat pinjam pada scan QR, notifikasi Lonceng & Discord saat transaksi QR tamu, pengingat kunci dipinjam > 12 jam, serta Filter, Pagination & Export CSV riwayat aset.'],
        ]
    ],
    [
        'version' => 'v2.11.0 LTS',
        'date'    => '27 Nov 2026',
        'title'   => 'Notulen Share Publik, Komentar Laporan, Mode Monitor TV & Ekspor Audit CSV',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Kanban Tugas & Catatan Pribadi (/kanban) yang 100% privat per akun, serta Log Kunci, Alat & Aset Shift (/aset) bersama untuk seluruh petugas.'],
            ['type' => 'Baru',      'text' => 'Tautan Publik Baca-Saja (/notulen/share) bertoken HMAC untuk Notulen Meeting & Briefing agar dapat dibagikan via WhatsApp tanpa login.'],
            ['type' => 'Baru',      'text' => 'Catatan & Diskusi Tindak Lanjut pada detail laporan serah terima (/laporan/detail) tanpa mengubah isi berita acara resmi yang sudah bertanda tangan.'],
            ['type' => 'Baru',      'text' => 'Mode Monitor TV / Kiosk di Dashboard dengan tampilan layar penuh & auto-refresh 60 detik, serta chip filter cepat "Perlu Validasi Saya" & "Laporan Saya".'],
            ['type' => 'Keamanan',  'text' => 'Pembatasan menu Pusat Backup & Pemulihan (/master/backup) khusus Superadmin, tombol Ekspor CSV pada Audit Trail (/audit), dan perapihan lekukan kurva sidebar.'],
        ]
    ],
    [
        'version' => 'v2.10.0 LTS',
        'date'    => '27 Nov 2026',
        'title'   => 'Analitik Rekap, Notif Keamanan Discord, Sakuga Micro-VFX & Mobile Card View',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Analitik & Grafik Tren Bulanan pada Rekap Logbook (/rekap) lengkap dengan KPI validasi, penyelesaian tiket, grafik harian lokal, serta ekspor Excel (.xls).'],
            ['type' => 'Keamanan',  'text' => 'Notifikasi Keamanan & Audit Log ke Discord Webhook (khusus Superadmin) dengan filter event Login Gagal/Sukses, Laporan/Notulen, dan Aksi Kritis Sistem.'],
            ['type' => 'Baru',      'text' => 'Pengingat Otomatis Sebelum Ganti Shift: banner countdown 30 menit menjelang jam serah terima bila laporan shift berjalan belum dikirim.'],
            ['type' => 'Tampilan',  'text' => 'Efek mikro Sakuga VFX (Canvas 2D & animasi spring bertema NoteFlow OMIT) serta tampilan Card View khusus mobile di halaman Riwayat Laporan, Notulen, dan Audit Trail.'],
        ]
    ],
    [
        'version' => 'v2.9.0 LTS',
        'date'    => '26 Nov 2026',
        'title'   => 'Integrasi LDAP / AD, Restore & Migrasi Sistem, Tautan Publik & Cron Auto-Backup',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Menu Integrasi Server LDAP / Active Directory (/master/ldap) khusus Superadmin dengan autentikasi Hybrid, preset AD/OpenLDAP, Test Koneksi, Impor/Sync massal user, dan ekstensi PHP ext-ldap di container.'],
            ['type' => 'Baru',      'text' => 'Fitur Restore & Migrasi Sistem (.sql / .zip) pada Pusat Backup khusus Superadmin lengkap dengan snapshot pengaman otomatis sebelum restore dan opsi pertahankan konfigurasi DB server.'],
            ['type' => 'Baru',      'text' => 'Tautan Publik Baca-Saja (/laporan/share) bertoken HMAC pada pesan WhatsApp & halaman detail laporan agar penerima/manajemen dapat melihat laporan tanpa login.'],
            ['type' => 'Baru',      'text' => 'Skrip Cron Backup Otomatis (cron/auto_backup.php) yang langsung mengirim arsip (multi-part 8 MB jika besar) ke Discord Webhook.'],
            ['type' => 'Keamanan',  'text' => 'Pembatasan menu Pengaturan Sistem & Logo (/master/pengaturan) khusus Superadmin serta penyeragaman tema default ke Light Mode di semua halaman.'],
        ]
    ],
    [
        'version' => 'v2.8.1 LTS',
        'date'    => '25 Nov 2026',
        'title'   => 'Tailwind CSS Lokal, Pengerasan Keamanan, QR Fleksibel & Nomor Tiket Otomatis',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Performa',  'text' => 'Compile Tailwind CSS lokal (assets/css/tailwind.min.css) menggantikan CDN agar loading lebih cepat di LAN dan bebas warning console.'],
            ['type' => 'Keamanan',  'text' => 'Blokir akses HTTP ke direktori internal/backup (.htaccess), cookie sesi SameSite=Lax + session_regenerate_id, serta rate limit login 8x/10 menit.'],
            ['type' => 'Perbaikan', 'text' => 'Barcode QR TTD kini fleksibel mengikuti domain/IP aktif di browser, nomor tiket otomatis (TK-{id}-01) saat dikosongkan, dan perbaikan sidebar PC agar tersembunyi di mobile.'],
        ]
    ],
    [
        'version' => 'v2.8.0 LTS',
        'date'    => '25 Nov 2026',
        'title'   => 'Notulen Meeting & Briefing, Simpan Draft Laporan & Sinkronisasi WIB',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Modul Notulen Meeting & Briefing (/notulen) terbuka untuk semua pengguna: catat agenda, pembahasan, keputusan, peserta, foto dokumentasi, Action Items, cetak PDF A4, salin WhatsApp, serta notifikasi lonceng peserta saat finalisasi.'],
            ['type' => 'Baru',      'text' => 'Fitur Simpan Draft & Lanjutkan Edit Draft pada Serah Terima Shift agar pengisian pekerjaan bisa dicicil bertahap sebelum diajukan ke shift penerima.'],
            ['type' => 'Tampilan',  'text' => 'Pembaruan Dashboard dengan kartu cepat & widget Notulen Terbaru, banner lanjutan Draft, dan filter statistik resmi tanpa Draft.'],
            ['type' => 'Perbaikan', 'text' => 'Penegakan sesi MySQL/MariaDB time_zone = +07:00 (WIB) pada PDO (jam Audit Trail akurat), scroll independen pada mobile side drawer, serta penambahan meta tag mobile-web-app-capable.'],
        ]
    ],
    [
        'version' => 'v2.7.0 LTS',
        'date'    => '25 Nov 2026',
        'title'   => 'Audit Trail, Live Search (Ctrl+K), Idle Auto-Logout & PWA Support',
        'badge'   => 'LTS',
        'color'   => 'bg-indigo-100 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Live Quick Search AJAX di header (Ctrl+K) untuk mencari nomor laporan, tiket pekerjaan, lokasi, dan petugas secara instan.'],
            ['type' => 'Keamanan',  'text' => 'Halaman Audit Trail & Log Aktivitas khusus Superadmin (/audit) mencatat login, serah terima, validasi, reset password, dan ubah pengaturan.'],
            ['type' => 'Keamanan',  'text' => 'Auto-Logout Idle Session (45 menit tanpa aktivitas dengan hitungan mundur 60 detik) untuk keamanan PC Ruang Kontrol antar-shift.'],
            ['type' => 'Baru',      'text' => 'Dukungan Progressive Web App (PWA) dengan manifest dinamis & Service Worker sehingga aplikasi dapat di-install ke HP/Desktop (via HTTPS/localhost).'],
        ]
    ],
    [
        'version' => 'v2.6.0 LTS',
        'date'    => '25 Nov 2026',
        'title'   => 'Notifikasi Lonceng, Pengumuman Superadmin, Live Shift & Modern UI',
        'badge'   => 'STABLE',
        'color'   => 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Lonceng Notifikasi Interaktif dengan daftar notifikasi laporan (hilang otomatis setelah dibaca) & pengiriman Email penerima.'],
            ['type' => 'Baru',      'text' => 'Pengumuman Resmi Superadmin lengkap dengan kategori urgensi, target peran, dan Banner Pengumuman di atas Dashboard.'],
            ['type' => 'Baru',      'text' => 'Widget Live Shift Aktif & Jam Real-Time WIB di hero Dashboard (mendukung deteksi shift malam lintas hari).'],
            ['type' => 'Baru',      'text' => 'Chip Filter Cepat 1-Klik di Riwayat Laporan (Semua, Hari Ini, Menunggu Validasi, Tervalidasi, Ada Gangguan).'],
            ['type' => 'Baru',      'text' => 'Floating Toast Notification frosted glass yang otomatis menghilang lembut dalam 4 detik tanpa menggeser tata letak.'],
            ['type' => 'Baru',      'text' => 'Floating Glass Pill Tooltip dengan animasi pegas pada ikon sidebar PC & multi-part backup otomatis 8 MB/part ke Discord.'],
        ]
    ],
    [
        'version' => 'v2.5.0 LTS',
        'date'    => '24 Nov 2026',
        'title'   => 'Clean URLs, Custom Penomoran Dokumen & Proteksi Superadmin',
        'badge'   => 'STABLE',
        'color'   => 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Penomoran dokumen serah terima dapat dikustomisasi di Pengaturan (Prefix kustom & siklus reset Harian/Bulanan).'],
            ['type' => 'Baru',      'text' => 'Clean URLs tanpa ekstensi .php di seluruh sistem (/dashboard, /auth/login, /master/lokasi).'],
            ['type' => 'Baru',      'text' => 'Favicon dinamis di tab browser dan penyimpanan logo PNG transparan murni tanpa latar hitam.'],
            ['type' => 'Baru',      'text' => 'Halaman Informasi Sistem & Riwayat Pembaruan (/info) dengan telemetri infrastruktur real-time.'],
            ['type' => 'Keamanan',  'text' => 'Proteksi RBAC penuh: Role Admin tidak dapat mereset password, mengedit, atau menghapus akun Superadmin.'],
            ['type' => 'Perbaikan', 'text' => 'Modal Edit Lokasi / Unit, perbaikan placeholder PDO native untuk PHP 8.5, serta kompatibilitas penuh cPanel/aaPanel.'],
        ]
    ],
    [
        'version' => 'v2.4.0',
        'date'    => '24 Nov 2026',
        'title'   => 'Docker PHP 8.5 + MariaDB 10.11, Redis Cache & Login Glassmorphism',
        'badge'   => 'INFRASTRUKTUR',
        'color'   => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        'items'   => [
            ['type' => 'Performa',  'text' => 'Orkestrasi kontainer Docker PHP 8.5 Apache + MariaDB 10.11 LTS + Redis 7 In-Memory Cache.'],
            ['type' => 'Baru',      'text' => 'Desain ulang halaman Login dengan Frosted Glassmorphism, fokus input interaktif, dan tombol loading anti double-click.'],
            ['type' => 'Baru',      'text' => 'Format pesan notifikasi WhatsApp eksekutif yang rapi beserta tautan dokumen otomatis berbasis URL publik.'],
            ['type' => 'Perbaikan', 'text' => 'Kepatuhan penuh PHP 8.5 (pembaruan konstanta Pdo\\Mysql, penghapusan fungsi usang curl_close & imagedestroy).'],
        ]
    ],
    [
        'version' => 'v2.3.0',
        'date'    => '24 Nov 2026',
        'title'   => 'Konsolidasi Master Operasional & Responsivitas Mobile',
        'badge'   => 'UX UPDATE',
        'color'   => 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300',
        'items'   => [
            ['type' => 'Baru',      'text' => 'Konsolidasi menu Jadwal Shift, Master Lokasi/Unit, dan Kondisi Operasional ke dalam satu halaman bertab terpadu.'],
            ['type' => 'Perbaikan', 'text' => 'Penyempurnaan header mobile dengan tombol Dark/Light mode ringkas tanpa overflow horizontal.'],
            ['type' => 'Perbaikan', 'text' => 'Pemulihan konstanta database pada fitur Backup & Auto-Optimize.'],
        ]
    ],
    [
        'version' => 'v2.0.0',
        'date'    => 'Nov 2026',
        'title'   => 'Arsitektur NoteFlow, Tanda Tangan Multi-Mode & Eskalasi Tiket',
        'badge'   => 'CORE RELEASE',
        'color'   => 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
        'items'   => [
            ['type' => 'Baru', 'text' => 'Antarmuka NoteFlow Workspace dengan dukungan penuh Light & Dark Mode.'],
            ['type' => 'Baru', 'text' => 'Tanda tangan digital 3 mode (Kanvas, Barcode QR Code Verifikasi, dan Upload TTD Basah).'],
            ['type' => 'Baru', 'text' => 'Eskalasi otomatis tugas belum selesai antar-shift dan cetak PDF standar resmi OMIT.'],
            ['type' => 'Baru', 'text' => 'Pusat Backup Bencana (SQL/ZIP) dengan pengiriman otomatis ke Discord Webhook & Auto-Optimize mingguan.'],
        ]
    ],
];
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- HERO BANNER -->
  <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#5946ea] via-[#6558f5] to-[#7c70fa] text-white p-6 sm:p-8 shadow-xl shadow-[#6558f5]/20">
    <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full border border-white/10 pointer-events-none"></div>
    <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
      <div class="flex items-start sm:items-center gap-4 sm:gap-5">
        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-[22px] <?= $appLogo ? 'bg-white p-2.5' : 'bg-white/15 backdrop-blur-md border border-white/25 p-3.5' ?> flex items-center justify-center shadow-lg shrink-0">
          <?php if ($appLogo): ?>
            <img src="<?= $appLogo ?>" alt="Logo" class="w-full h-full object-contain" />
          <?php else: ?>
            <i class="ph-fill ph-clipboard-text text-3xl sm:text-4xl text-white"></i>
          <?php endif; ?>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap mb-1.5">
            <span class="px-2.5 py-0.5 rounded-full bg-white/20 backdrop-blur-md border border-white/25 text-[10px] font-extrabold uppercase tracking-wider">
              <?= $appVersion ?>
            </span>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-400/20 border border-emerald-300/30 text-emerald-100 text-[10px] font-bold flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
              Sistem Berjalan Normal
            </span>
          </div>
          <h1 class="text-xl sm:text-3xl font-black tracking-tight leading-tight">
            <?= sanitize($appName) ?>
          </h1>
          <p class="text-xs sm:text-sm text-white/80 mt-1 font-medium">
            <?= sanitize($appFullName) ?>
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="px-4 py-2.5 rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 text-xs">
          <span class="block text-[10px] text-white/70 font-semibold uppercase tracking-wider">Rilis Terakhir</span>
          <span class="font-extrabold text-white"><?= $releaseDate ?></span>
        </div>
        <div class="px-4 py-2.5 rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 text-xs">
          <span class="block text-[10px] text-white/70 font-semibold uppercase tracking-wider">Lingkungan</span>
          <span class="font-extrabold text-white"><?= sanitize($envLabel) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- 4 TELEMETRY CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
    
    <!-- Card 1: PHP Runtime -->
    <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">PHP Runtime</span>
        <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-400 flex items-center justify-center text-lg">
          <i class="ph-fill ph-code"></i>
        </div>
      </div>
      <div class="mt-3">
        <div class="text-xl font-black text-[#181b2c] dark:text-white tracking-tight">PHP <?= PHP_VERSION ?></div>
        <div class="text-[11px] text-slate-400 font-semibold mt-0.5 flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full <?= $opcacheEnabled ? 'bg-emerald-500' : 'bg-amber-400' ?>"></span>
          <span>OPcache: <?= $opcacheEnabled ? 'Aktif (Optimized)' : 'Standar' ?></span>
        </div>
      </div>
    </div>

    <!-- Card 2: Database -->
    <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Database Engine</span>
        <div class="w-10 h-10 rounded-2xl bg-[#e6f7f0] dark:bg-emerald-950/60 text-[#1c7a52] dark:text-emerald-400 flex items-center justify-center text-lg">
          <i class="ph-fill ph-database"></i>
        </div>
      </div>
      <div class="mt-3">
        <div class="text-lg font-black text-[#181b2c] dark:text-white tracking-tight truncate" title="<?= sanitize($dbVersion) ?>">
          <?= sanitize(explode('-', $dbVersion)[0]) ?>
        </div>
        <div class="text-[11px] text-slate-400 font-semibold mt-0.5">
          <?= $cntLaporan ?> Laporan • <?= $cntTugas ?> Tiket • <?= $cntUsers ?> User
        </div>
      </div>
    </div>

    <!-- Card 3: Redis Cache -->
    <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">In-Memory Cache</span>
        <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/50 text-rose-500 flex items-center justify-center text-lg">
          <i class="ph-fill ph-lightning"></i>
        </div>
      </div>
      <div class="mt-3">
        <div class="text-xl font-black text-[#181b2c] dark:text-white tracking-tight">
          <?= !empty($cacheInfo['active']) ? 'Redis Aktif' : 'Fallback Mode' ?>
        </div>
        <div class="text-[11px] text-slate-400 font-semibold mt-0.5">
          <?= !empty($cacheInfo['active']) ? "Memori: {$cacheInfo['memory_human']} • {$cacheInfo['keys_count']} Keys" : sanitize($cacheInfo['client']) ?>
        </div>
      </div>
    </div>

    <!-- Card 4: Storage & Timezone -->
    <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Penyimpanan &amp; Zona</span>
        <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
          <i class="ph-fill ph-hard-drives"></i>
        </div>
      </div>
      <div class="mt-3">
        <div class="text-xl font-black text-[#181b2c] dark:text-white tracking-tight">Bebas <?= $diskFreeFmt ?></div>
        <div class="text-[11px] text-slate-400 font-semibold mt-0.5">
          <?= date_default_timezone_get() ?> • <?= $cntLokasi ?> Unit Aktif
        </div>
      </div>
    </div>

  </div>

  <!-- 2-COLUMN SECTION: CHANGELOG TIMELINE (8 COLS) + HEALTH CHECKLIST (4 COLS) -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

    <!-- Riwayat Pembaruan (Changelog) -->
    <div class="xl:col-span-8 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl">
            <i class="ph-bold ph-sparkle"></i>
          </div>
          <div>
            <h2 class="text-base font-extrabold text-[#181b2c] dark:text-white">Riwayat Pembaruan &amp; Rilis</h2>
            <p class="text-[11px] text-slate-400">Catatan fitur baru, peningkatan performa, dan perbaikan keamanan</p>
          </div>
        </div>
      </div>

      <div class="space-y-6 relative before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100 dark:before:bg-slate-800">
        <?php foreach ($releases as $rel): ?>
          <div class="relative pl-10">
            <!-- Timeline Dot -->
            <span class="absolute left-2 top-1.5 w-4 h-4 rounded-full border-[3px] border-white dark:border-[#1b1e30] <?= $rel['badge'] === 'TERBARU' ? 'bg-[#6558f5] ring-4 ring-[#6558f5]/20' : 'bg-slate-300 dark:bg-slate-700' ?>"></span>

            <div class="p-4 sm:p-5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/60 dark:border-slate-800/80">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-black text-[#181b2c] dark:text-white"><?= $rel['version'] ?></span>
                  <span class="px-2.5 py-0.5 rounded-full text-[9.5px] font-extrabold uppercase tracking-wider <?= $rel['color'] ?>">
                    <?= $rel['badge'] ?>
                  </span>
                </div>
                <span class="text-[11px] font-bold text-slate-400"><?= $rel['date'] ?></span>
              </div>

              <h3 class="text-xs sm:text-sm font-extrabold text-slate-800 dark:text-slate-100 mb-3">
                <?= sanitize($rel['title']) ?>
              </h3>

              <ul class="space-y-2">
                <?php foreach ($rel['items'] as $item): ?>
                  <?php
                    $tagStyle = match($item['type']) {
                        'Baru'      => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300',
                        'Keamanan'  => 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300',
                        'Performa'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300',
                        default     => 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300',
                    };
                  ?>
                  <li class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    <span class="px-2 py-0.5 rounded-md text-[9.5px] font-extrabold uppercase shrink-0 mt-0.5 <?= $tagStyle ?>">
                      <?= $item['type'] ?>
                    </span>
                    <span><?= sanitize($item['text']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Kesiapan Modul & Kepatuhan (4 Cols) -->
    <div class="xl:col-span-4 space-y-6">
      
      <!-- Checklist Modul Sistem -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
          <div class="w-10 h-10 rounded-2xl bg-[#e6f7f0] dark:bg-emerald-950/60 text-[#1c7a52] dark:text-emerald-400 flex items-center justify-center text-xl">
            <i class="ph-bold ph-shield-check"></i>
          </div>
          <div>
            <h2 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Status Ekstensi &amp; Modul</h2>
            <p class="text-[11px] text-slate-400">Komponen inti server aplikasi</p>
          </div>
        </div>

        <div class="space-y-2.5">
          <?php foreach ($extensions as $ext): ?>
            <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800/80">
              <div class="min-w-0 pr-2">
                <div class="text-xs font-extrabold text-slate-800 dark:text-slate-100"><?= $ext['name'] ?></div>
                <div class="text-[10px] text-slate-400 truncate"><?= $ext['desc'] ?></div>
              </div>
              <?php if ($ext['ok']): ?>
                <span class="px-2 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-extrabold shrink-0 flex items-center gap-1">
                  <i class="ph-bold ph-check"></i> Siap
                </span>
              <?php else: ?>
                <span class="px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 text-[10px] font-extrabold shrink-0">
                  Opsional
                </span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Informasi Arsitektur & Akses Cepat -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6">
        <h3 class="text-xs font-extrabold text-[#181b2c] dark:text-white uppercase tracking-wider mb-3">
          Standar &amp; Keamanan Sistem
        </h3>
        <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
          <div class="flex items-center gap-2">
            <i class="ph-fill ph-lock-key text-[#6558f5]"></i>
            <span>Password Bcrypt &amp; PDO Prepared Statements</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="ph-fill ph-users-three text-[#6558f5]"></i>
            <span>Multi-Tier RBAC (Superadmin, Admin, Operator)</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="ph-fill ph-qr-code text-[#6558f5]"></i>
            <span>Verifikasi Dokumen QR &amp; TTD Digital</span>
          </div>
        </div>

        <?php if (is_admin()): ?>
          <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-2">
            <a href="<?= BASE_URL ?>master/pengaturan"
               class="w-full py-2.5 px-4 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 hover:bg-[#6558f5] text-[#6558f5] dark:text-indigo-300 hover:text-white font-extrabold text-xs transition flex items-center justify-center gap-2">
              <i class="ph-bold ph-gear"></i>
              <span>Buka Pengaturan Sistem</span>
            </a>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
