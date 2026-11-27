<?php
// includes/header.php - NoteFlow Interactive SaaS Workspace Theme & Curved Organic Sidebar
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_login();
$user = current_user();
$current_page = $current_page ?? 'dashboard';
$flashes = flash_get();

$appName     = get_app_name();
$appFullName = get_app_full_name();
$appLogo     = get_app_logo_url();

require_once __DIR__ . '/bell_helper.php';
$bellFeed    = bell_get_feed($pdo, (int)($user['id'] ?? 0), $user['role'] ?? 'user');
$bellUnread  = (int)($bellFeed['unread_count'] ?? 0);

// Opportunistic auto system maintenance check for admin/superadmin
if (in_array($user['role'] ?? '', ['admin', 'superadmin'])) {
    require_once __DIR__ . '/optimizer_helper.php';
    check_and_run_auto_optimization($pdo);
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($page_title) ? sanitize($page_title) . ' — ' : '' ?><?= sanitize($appName) ?></title>
  <?php $faviconUrl = $appLogo ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%236558f5'/%3E%3Cpath d='M10 10h12v2H10zm0 5h12v2H10zm0 5h8v2h-8z' fill='white'/%3E%3C/svg%3E"; ?>
  <link rel="icon" href="<?= $faviconUrl ?>" />
  <link rel="manifest" href="<?= BASE_URL ?>manifest" />
  <meta name="theme-color" content="#6558f5" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  
  <!-- Prevent FOUC (Default Light Mode unless explicitly switched to Dark) -->
  <script>
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
    } else {
      document.documentElement.classList.add('light');
      document.documentElement.classList.remove('dark');
    }
  </script>

  <!-- Tailwind CSS Local (Compiled & Auto Cache-Busted) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '280' ?>" />
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Phosphor Icons (Local) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/regular/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/bold/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/fill/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/duotone/style.css" />

  <!-- NoteFlow Main Styling (Auto Cache-Busted) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '250' ?>" />
</head>

<body class="p-2 sm:p-4 lg:p-5 flex items-center justify-center min-h-screen md:h-screen md:overflow-hidden relative select-none">

  <!-- Decorative Outer Background Lines & Floating Dots (NoteFlow aesthetic framing) -->
  <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
    <svg class="absolute top-0 left-1/3 w-96 h-40 opacity-60 dark:opacity-20" viewBox="0 0 400 160" fill="none">
      <line x1="220" y1="0" x2="100" y2="80" stroke="white" stroke-width="1.5" />
    </svg>
    <svg class="absolute bottom-0 right-0 w-72 h-72 opacity-70 dark:opacity-20" viewBox="0 0 300 300" fill="none">
      <path d="M260 60 C210 120, 290 190, 250 260" stroke="white" stroke-width="1.5" fill="none"/>
      <circle cx="282" cy="195" r="5" fill="#7c73e6" />
      <circle cx="205" cy="292" r="14" fill="#2d3142" />
    </svg>
    <div class="absolute bottom-24 left-3 w-3 h-3 rounded-full bg-[#68cfa3] opacity-80"></div>
    <div class="absolute bottom-3 left-10 w-8 h-8 rounded-full border border-[#a5a1d4] opacity-50"></div>
  </div>

  <!-- Main application shell -->
  <div id="app-shell" class="relative z-10 w-full max-w-[1580px] min-h-[92vh] md:h-[calc(100vh-1.5rem)] lg:h-[calc(100vh-2.5rem)] bg-[#f7f7fb] dark:bg-[#131522] rounded-3xl lg:rounded-[34px] shadow-shell border border-white/80 dark:border-slate-800/80 transition-colors duration-300 overflow-hidden flex flex-col">

    <!-- Curved purple sidebar (Desktop Only) -->
    <aside class="curved-sidebar-wrapper hidden md:flex md:flex-col shrink-0">
      <!-- Top concave curve -->
      <svg class="sidebar-curve-top" viewBox="0 0 32 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 0 C0 22, 12 40, 32 40 L0 40 Z" fill="#6558f5"/>
      </svg>

      <!-- Purple vertical bar -->
      <div class="sidebar-body">
        <div class="flex flex-col items-center gap-1.5 w-full overflow-y-auto no-scrollbar py-0.5">
          <!-- 1. Dashboard -->
          <a href="<?= BASE_URL ?>dashboard" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'dashboard' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-squares-four text-lg"></i>
            <span class="sidebar-tooltip">Dashboard Overview</span>
          </a>

          <!-- 2. Buat Serah Terima Baru -->
          <a href="<?= BASE_URL ?>laporan/create" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'laporan_create' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-file-plus text-lg"></i>
            <span class="sidebar-tooltip">Buat Serah Terima Baru</span>
          </a>

          <!-- 3. Riwayat Laporan (with active SHIFT pill badge) -->
          <a href="<?= BASE_URL ?>laporan/index" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'laporan_list' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-folders text-lg"></i>
            <span class="absolute -right-2.5 top-2.5 bg-[#68cfa3] text-[#181b2c] text-[7px] font-extrabold px-1.5 py-[1px] rounded-full shadow-sm tracking-tighter">SHIFT</span>
            <span class="sidebar-tooltip">Riwayat Laporan Shift</span>
          </a>

          <!-- 4. Rekap Harian (Logbook) -->
          <a href="<?= BASE_URL ?>rekap/index" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'rekap' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-calendar-check text-lg"></i>
            <span class="sidebar-tooltip">Rekap Logbook</span>
          </a>

          <!-- 4b. Notulen Meeting & Briefing -->
          <a href="<?= BASE_URL ?>notulen/index" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'notulen' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-notepad text-lg"></i>
            <span class="sidebar-tooltip">Notulen Meeting &amp; Briefing</span>
          </a>

          <!-- 4c. Log Kunci & Aset Shift (Publik) -->
          <a href="<?= BASE_URL ?>aset/index" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'aset' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-key text-lg"></i>
            <span class="sidebar-tooltip">Log Kunci &amp; Aset Shift (Publik)</span>
          </a>

          <!-- 4d. Kanban & Catatan Pribadi (Privat) -->
          <a href="<?= BASE_URL ?>kanban/index" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'kanban' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-kanban text-lg"></i>
            <span class="sidebar-tooltip">Kanban &amp; Catatan Pribadi</span>
          </a>

          <?php if (is_admin()): ?>
            <div class="w-6 h-[1px] bg-white/20 my-1 shrink-0"></div>

            <!-- 5. Master Operasional (Shift, Lokasi, Kondisi) -->
            <?php $is_master_ops = in_array($current_page, ['master_shift', 'master_lokasi', 'master_kondisi']); ?>
            <a href="<?= BASE_URL ?>master/shift" 
               class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $is_master_ops ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
              <i class="ph-fill ph-sliders-horizontal text-lg"></i>
              <span class="sidebar-tooltip">Master Operasional (Shift, Lokasi, Kondisi)</span>
            </a>

            <!-- 7. Kelola Pengguna -->
            <a href="<?= BASE_URL ?>master/users" 
               class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_users' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
              <i class="ph-fill ph-users-three text-lg"></i>
              <span class="sidebar-tooltip">Kelola Pengguna Sistem</span>
            </a>

            <?php if (is_superadmin()): ?>
              <!-- 8. Pengaturan & Logo (Superadmin Only) -->
              <a href="<?= BASE_URL ?>master/pengaturan" 
                 class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_pengaturan' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
                <i class="ph-fill ph-gear text-lg"></i>
                <span class="sidebar-tooltip">Pengaturan Sistem &amp; Logo</span>
              </a>

              <!-- 9. Backup & Disaster Recovery (Superadmin Only) -->
              <a href="<?= BASE_URL ?>master/backup" 
                 class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_backup' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
                <i class="ph-fill ph-database text-lg"></i>
                <span class="sidebar-tooltip">Pusat Backup &amp; Pemulihan Sistem</span>
              </a>

              <!-- 10. Audit Trail (Superadmin Only) -->
              <a href="<?= BASE_URL ?>audit" 
                 class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'audit' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
                <i class="ph-fill ph-fingerprint text-lg"></i>
                <span class="sidebar-tooltip">Audit Trail &amp; Log Aktivitas</span>
              </a>

              <!-- 11. Integrasi LDAP (Superadmin Only) -->
              <a href="<?= BASE_URL ?>master/ldap" 
                 class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_ldap' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
                <i class="ph-fill ph-tree-structure text-lg"></i>
                <span class="sidebar-tooltip">Integrasi Server LDAP</span>
              </a>
            <?php endif; ?>
          <?php endif; ?>

          <div class="w-6 h-[1px] bg-white/20 my-1 shrink-0"></div>

          <!-- 8. Profil & Keamanan Akun -->
          <a href="<?= BASE_URL ?>profile" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'profile' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-user-circle text-lg"></i>
            <span class="sidebar-tooltip">Profil Saya</span>
          </a>

          <!-- 9. Informasi Sistem & Pembaruan -->
          <a href="<?= BASE_URL ?>info" 
             class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'info' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
            <i class="ph-fill ph-info text-lg"></i>
            <span class="sidebar-tooltip">Informasi Sistem &amp; Update</span>
          </a>
        </div>

        <!-- Bottom Cloud Sync / Reload Button with Smooth Micro-spin -->
        <button onclick="triggerCloudSync()" type="button" class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 text-white/80 hover:text-white hover:bg-white/15 hover:scale-105 active:scale-95 shrink-0 mt-1" title="Sinkronisasi Data">
          <i id="sync-sidebar-icon" class="ph-bold ph-arrows-clockwise text-lg transition-transform duration-500"></i>
          <span class="sidebar-tooltip">Sinkronisasi Database (Siap)</span>
        </button>
      </div>

      <!-- Bottom concave curve -->
      <svg class="sidebar-curve-bottom" viewBox="0 0 32 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 40 C0 18, 12 0, 32 0 L0 0 Z" fill="#6558f5"/>
      </svg>
    </aside>

    <!-- Top navigation bar -->
    <header class="shrink-0 w-full h-16 sm:h-20 px-3.5 sm:px-6 md:pl-24 md:pr-8 flex items-center justify-between gap-2 sm:gap-4 border-b border-slate-200/50 dark:border-slate-800/60 bg-white/70 dark:bg-[#131522]/80 backdrop-blur-md z-20 sticky top-0 transition-colors">
      
      <!-- Left: Mobile Menu + Brand Logo & Current Page Breadcrumb -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        
        <!-- Mobile Drawer Hamburger Toggle (Mobile Only) -->
        <button type="button" onclick="toggleMobileMenu()" class="md:hidden p-1.5 sm:p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:bg-slate-200/50 dark:hover:bg-slate-800 transition shrink-0" aria-label="Menu Navigasi">
          <i class="ph-bold ph-list text-2xl"></i>
        </button>

        <!-- Brand Logo & App Name -->
        <a href="<?= BASE_URL ?>dashboard" class="flex items-center gap-2 sm:gap-2.5 group min-w-0">
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl <?= $appLogo ? 'bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 p-1.5' : 'bg-[#6558f5] text-white p-1.5' ?> flex items-center justify-center shadow-md overflow-hidden group-hover:scale-105 transition-transform shrink-0">
            <?php if ($appLogo): ?>
              <img src="<?= $appLogo ?>" alt="Logo" class="w-full h-full object-contain" />
            <?php else: ?>
              <i class="ph-fill ph-clipboard-text text-lg sm:text-xl"></i>
            <?php endif; ?>
          </div>
          <div class="min-w-0">
            <span class="text-xs sm:text-base font-extrabold text-[#181b2c] dark:text-white tracking-tight block leading-tight truncate max-w-[125px] xs:max-w-[180px] sm:max-w-none">
              <?= sanitize($appName) ?>
            </span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium tracking-tight hidden sm:block truncate">
              <?= sanitize($appFullName) ?>
            </span>
          </div>
        </a>
      </div>

      <!-- Center: Quick Search Bar (Spacious NoteFlow Pill) -->
      <div id="search-wrap" class="relative max-w-sm lg:max-w-md w-full hidden md:block mx-4">
        <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input
          id="global-search"
          type="text"
          placeholder="Cari laporan, tiket, unit... (Ctrl + K)"
          oninput="handleQuickSearch(this.value)"
          class="w-full pl-10 pr-4 py-2.5 text-xs font-medium bg-white dark:bg-[#1d2032] text-slate-700 dark:text-slate-200 placeholder-slate-400 rounded-full border border-slate-200/80 dark:border-slate-700/70 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition-all shadow-sm"
        />
        <div id="search-dropdown" class="hidden absolute top-full left-0 right-0 mt-2 bg-white dark:bg-[#1b1e30] rounded-2xl shadow-pop border border-slate-100 dark:border-slate-800 overflow-hidden z-50 text-xs"></div>
      </div>

      <!-- Right Action Controls (Light/Dark Toggle, Notifications, Create Button & Profile) -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        
        <!-- Mobile Single Dark Mode Toggle Button (Compact & Responsive) -->
        <button
          id="btn-theme-toggle-mobile"
          onclick="toggleTheme()"
          type="button"
          class="md:hidden w-8 h-8 rounded-full bg-[#ebebf7] dark:bg-[#1e2135] border border-slate-200/70 dark:border-slate-700/70 flex items-center justify-center text-slate-700 dark:text-amber-400 hover:scale-105 active:scale-95 transition-all shadow-sm shrink-0"
          title="Beralih Mode Tampilan"
          aria-label="Toggle Mode"
        >
          <i id="theme-icon-mobile" class="ph-fill ph-moon text-base"></i>
        </button>

        <!-- Desktop Light / Dark Mode Pill Toggle -->
        <div class="hidden md:flex bg-[#ebebf7] dark:bg-[#1e2135] p-1 rounded-full items-center text-[10.5px] font-semibold border border-slate-200/60 dark:border-slate-700/60 shrink-0">
          <button
            id="btn-theme-light"
            onclick="setThemeMode('light')"
            type="button"
            class="flex items-center gap-1 px-2.5 py-0.5 rounded-full transition-all duration-200"
          >
            <i class="ph-fill ph-sun text-xs"></i>
            <span>Light</span>
          </button>
          <button
            id="btn-theme-dark"
            onclick="setThemeMode('dark')"
            type="button"
            class="flex items-center gap-1 px-2.5 py-0.5 rounded-full transition-all duration-200"
          >
            <i class="ph-fill ph-moon text-xs"></i>
            <span>Dark</span>
          </button>
        </div>

        <!-- Interactive Notification Bell & Broadcast Popover -->
        <div id="bell-container" class="relative shrink-0">
          <button type="button" id="bell-toggle-btn" onclick="toggleBellDropdown(event)"
                  class="relative w-9 h-9 rounded-full flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200/70 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                  title="Notifikasi &amp; Pengumuman">
            <i class="ph-bold ph-bell text-[18px]"></i>
            <span id="bell-badge" class="<?= $bellUnread > 0 ? '' : 'hidden' ?> absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[9.5px] font-black flex items-center justify-center shadow-sm ring-2 ring-white dark:ring-[#131522]">
              <?= $bellUnread > 99 ? '99+' : $bellUnread ?>
            </span>
          </button>

          <!-- Bell Popover Dropdown -->
          <div id="bell-dropdown" class="hidden fixed sm:absolute right-3 sm:right-0 top-16 sm:top-full sm:mt-2.5 w-[calc(100vw-1.5rem)] sm:w-[390px] max-w-[400px] bg-white dark:bg-[#1b1e30] rounded-[24px] shadow-2xl border border-slate-200/80 dark:border-slate-700/80 overflow-hidden z-50">
            
            <!-- Popover Header -->
            <div class="px-4 py-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 bg-[#f7f7fb] dark:bg-[#151828]">
              <div class="flex items-center gap-2">
                <i class="ph-fill ph-bell-ringing text-[#6558f5] text-base"></i>
                <span class="text-xs font-extrabold text-[#181b2c] dark:text-white">Notifikasi &amp; Pengumuman</span>
              </div>
              <div class="flex items-center gap-1.5">
                <?php if (is_superadmin()): ?>
                  <button type="button" onclick="openCreateAnnouncementModal()"
                          class="px-2.5 py-1 rounded-lg bg-[#6558f5] hover:bg-[#5244e8] text-white text-[10px] font-extrabold transition flex items-center gap-1 cursor-pointer shadow-xs">
                    <i class="ph-bold ph-megaphone"></i>
                    <span>+ Pengumuman</span>
                  </button>
                <?php endif; ?>
                <button type="button" onclick="markAllBellRead()"
                        title="Tandai semua sudah dibaca"
                        class="px-2 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 hover:bg-slate-300/70 text-slate-600 dark:text-slate-300 text-[10px] font-bold transition cursor-pointer">
                  Dibaca
                </button>
              </div>
            </div>

            <!-- Feed Content Container -->
            <div id="bell-feed-list" class="max-h-[400px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80">
              <div class="p-6 text-center text-xs text-slate-400">Memuat notifikasi...</div>
            </div>

            <!-- Footer Link -->
            <div class="px-4 py-2.5 bg-[#f7f7fb] dark:bg-[#151828] border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px]">
              <a href="<?= BASE_URL ?>laporan/index?status=MENUNGGU+VALIDASI" class="font-extrabold text-[#6558f5] hover:underline flex items-center gap-1">
                <span>Lihat Laporan Menunggu Validasi</span>
                <i class="ph-bold ph-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Quick Action "+ Buat Serah Terima" Button (Desktop Only) -->
        <a href="<?= BASE_URL ?>laporan/create"
           class="hidden sm:flex bg-[#181b2c] dark:bg-[#6558f5] hover:bg-[#272b42] dark:hover:bg-[#5446e8] text-white px-3.5 sm:px-4 py-2 rounded-full text-[11px] sm:text-[11.5px] font-extrabold shadow-sm hover:shadow-md transition-all active:scale-95 items-center gap-1.5 whitespace-nowrap">
          <i class="ph-bold ph-plus text-xs"></i>
          <span>Buat Serah Terima</span>
        </a>

        <!-- User Profile Pill -->
        <div class="flex items-center gap-1.5 pl-1.5 sm:pl-2 border-l border-slate-200 dark:border-slate-800 shrink-0">
          <a href="<?= BASE_URL ?>profile" class="flex items-center gap-2 group hover:opacity-90 transition" title="Pengaturan Profil Saya">
            <div class="relative w-8 h-8 rounded-full overflow-hidden ring-2 ring-[#6558f5]/30 group-hover:ring-[#6558f5] transition-all bg-gradient-to-tr from-[#6558f5] to-indigo-400 text-white flex items-center justify-center font-black text-xs shadow-sm">
              <?php $hdrAvatar = get_user_avatar_url($user['foto'] ?? null); ?>
              <?php if ($hdrAvatar): ?>
                <img src="<?= $hdrAvatar ?>" alt="Foto" class="w-full h-full object-cover">
              <?php else: ?>
                <span><?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 2)) ?></span>
              <?php endif; ?>
            </div>
            <div class="hidden xl:block text-left">
              <div class="text-[11px] font-extrabold text-slate-800 dark:text-slate-100 leading-none group-hover:text-[#6558f5] transition-colors">
                <?= sanitize($user['nama_lengkap'] ?? 'User') ?>
              </div>
              <div class="text-[8.5px] font-extrabold uppercase tracking-wider text-[#6558f5] mt-0.5">
                <?= $user['role'] ?? 'USER' ?>
              </div>
            </div>
          </a>

          <!-- Quick Action to Profile (Desktop only) -->
          <a href="<?= BASE_URL ?>profile" class="hidden sm:flex w-7 h-7 rounded-full items-center justify-center text-slate-400 hover:text-[#6558f5] hover:bg-[#efeffb] dark:hover:bg-slate-800 transition" title="Pengaturan Profil">
            <i class="ph-bold ph-user-gear text-sm"></i>
          </a>

          <!-- Logout Button (Desktop only) -->
          <a href="<?= BASE_URL ?>auth/logout" onclick="return confirm('Apakah Anda ingin keluar dari sistem?')" class="hidden sm:flex w-7 h-7 rounded-full items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" title="Logout">
            <i class="ph-bold ph-sign-out text-sm"></i>
          </a>
        </div>

      </div>
    </header>

    <!-- Main content container -->
    <main class="flex-1 overflow-y-auto px-4 sm:px-8 md:pl-24 md:pr-8 pb-24 md:pb-8 pt-4">

      <!-- Floating Modern Toast Notifications -->
      <?php if (!empty($flashes)): ?>
        <div id="flash-toast-stack" class="fixed top-5 right-5 z-[9990] flex flex-col gap-2.5 max-w-sm w-[calc(100vw-2.5rem)] pointer-events-none">
          <?php foreach ($flashes as $f): 
            $msgType = is_array($f) ? ($f['type'] ?? 'info') : 'info';
            $msgText = is_array($f) ? ($f['message'] ?? '') : (string)$f;
            if (empty($msgText)) continue;
            $toastClass = $msgType === 'success'
                ? 'bg-white/95 dark:bg-[#181b2c]/95 text-[#1c7a52] dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800/70'
                : ($msgType === 'warning'
                    ? 'bg-white/95 dark:bg-[#181b2c]/95 text-amber-700 dark:text-amber-300 border-amber-200/80 dark:border-amber-800/70'
                    : 'bg-white/95 dark:bg-[#181b2c]/95 text-[#a9233c] dark:text-rose-300 border-rose-200/80 dark:border-rose-800/70');
            $toastIcon = $msgType === 'success'
                ? 'ph-check-circle text-emerald-500'
                : ($msgType === 'warning' ? 'ph-warning text-amber-500' : 'ph-warning-circle text-rose-500');
          ?>
            <div class="flash-toast pointer-events-auto p-3.5 rounded-2xl backdrop-blur-xl border shadow-2xl flex items-start justify-between gap-3 text-xs font-bold transition-all duration-300 animate-pop <?= $toastClass ?>">
              <div class="flex items-start gap-2.5">
                <i class="ph-fill <?= $toastIcon ?> text-lg shrink-0 mt-0.5"></i>
                <span class="leading-relaxed"><?= sanitize($msgText) ?></span>
              </div>
              <button type="button" onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100 p-0.5 cursor-pointer shrink-0">
                <i class="ph-bold ph-x text-sm"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php
        // Pengingat Otomatis Sebelum Ganti Shift (30 menit sebelum jam serah terima hingga 5 menit setelahnya)
        $shiftReminder = null;
        if (($current_page ?? '') !== 'laporan_create' && function_exists('get_master_shift')) {
            $nowTs = time();
            foreach (get_master_shift(true) as $sh) {
                $hTime = !empty($sh['jam_serah_terima']) ? $sh['jam_serah_terima'] : ($sh['jam_selesai'] ?? '');
                if ($hTime === '') continue;
                $targetTs = strtotime(date('Y-m-d') . ' ' . $hTime);
                if ($targetTs === false) continue;

                $diffSec = $targetTs - $nowTs;
                if ($diffSec <= 1800 && $diffSec >= -300) {
                    $stmtRem = $pdo->prepare("
                        SELECT 1 FROM laporan_serah_terima
                        WHERE shift_id = :sid
                          AND status_validasi != 'DRAFT'
                          AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                          AND created_at >= DATE_SUB(NOW(), INTERVAL 10 HOUR)
                        LIMIT 1
                    ");
                    $stmtRem->execute(['sid' => (int)$sh['id']]);
                    if (!$stmtRem->fetchColumn()) {
                        $shiftReminder = [
                            'id'        => (int)$sh['id'],
                            'name'      => $sh['nama_shift'],
                            'target_ts' => $targetTs,
                            'key'       => 'omit_rem_' . date('Ymd') . '_' . (int)$sh['id'],
                        ];
                    }
                    break;
                }
            }
        }
      ?>
      <?php if ($shiftReminder): ?>
        <div id="shiftHandoverReminder"
             data-key="<?= htmlspecialchars($shiftReminder['key']) ?>"
             data-target="<?= (int)$shiftReminder['target_ts'] ?>"
             class="mb-4 p-3.5 sm:px-5 rounded-2xl bg-amber-50/95 dark:bg-amber-950/40 border border-amber-300/80 dark:border-amber-700/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 animate-pulse">
              <i class="ph-bold ph-bell-ringing text-lg"></i>
            </div>
            <div class="text-xs">
              <p class="font-extrabold text-amber-900 dark:text-amber-200">
                Pengingat Ganti Shift <?= sanitize($shiftReminder['name']) ?>
                <span id="shiftCountdownText" class="ml-1.5 px-2 py-0.5 rounded-md bg-amber-200/80 dark:bg-amber-900/70 font-mono text-[11px]"></span>
              </p>
              <p class="text-[11px] text-amber-700 dark:text-amber-300/90 mt-0.5">
                Belum ada laporan serah terima resmi yang dikirim untuk shift ini.
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 self-end sm:self-auto">
            <a href="<?= BASE_URL ?>laporan/create"
               class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold shadow-sm transition flex items-center gap-1.5">
              <i class="ph-bold ph-file-plus"></i>
              <span>Buat Serah Terima</span>
            </a>
            <button type="button" onclick="dismissShiftReminder()" title="Tutup sementara"
                    class="p-1.5 rounded-lg text-amber-700 dark:text-amber-300 hover:bg-amber-200/60 cursor-pointer">
              <i class="ph-bold ph-x text-sm"></i>
            </button>
          </div>
        </div>
        <script>
          (function() {
            const box = document.getElementById('shiftHandoverReminder');
            if (!box) return;
            const key = box.getAttribute('data-key');
            if (sessionStorage.getItem(key) === '1') { box.remove(); return; }
            const target = parseInt(box.getAttribute('data-target'), 10) * 1000;
            const lbl = document.getElementById('shiftCountdownText');
            function tick() {
              const diff = Math.floor((target - Date.now()) / 1000);
              if (diff >= 0) {
                const m = String(Math.floor(diff / 60)).padStart(2, '0');
                const s = String(diff % 60).padStart(2, '0');
                lbl.textContent = 'dalam ' + m + ':' + s;
              } else {
                lbl.textContent = 'waktunya serah terima!';
              }
            }
            tick();
            setInterval(tick, 1000);
            window.dismissShiftReminder = function() {
              sessionStorage.setItem(key, '1');
              box.remove();
            };
          })();
        </script>
      <?php endif; ?>
