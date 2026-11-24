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
  
  <!-- Prevent FOUC (Flash of Unstyled Theme) -->
  <script>
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
    } else {
      document.documentElement.classList.add('light');
      document.documentElement.classList.remove('dark');
    }
  </script>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Phosphor Icons -->
  <script src="https://unpkg.com/@phosphor-icons/web"></script>

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
          },
          colors: {
            brand: {
              purple: '#6558f5',
              purpleLight: '#efeffb',
              purpleSoft: '#e4e3f8',
              navy: '#181b2c',
              mint: '#68cfa3',
              mintSoft: '#e6f7f0',
              coral: '#e0536c',
              coralSoft: '#fce8ec',
              bgOuter: '#dcd9f5',
              bgInner: '#f7f7fb',
            }
          },
          boxShadow: {
            'shell': '0 30px 70px -15px rgba(45, 40, 105, 0.18), 0 0 1px 1px rgba(255,255,255,0.6)',
            'card': '0 10px 30px -8px rgba(35, 32, 85, 0.05)',
            'card-hover': '0 18px 38px -6px rgba(99, 91, 255, 0.13)',
            'pop': '0 18px 40px -10px rgba(28, 25, 68, 0.14)',
            'feature': '0 16px 35px -8px rgba(35, 32, 85, 0.08)',
          }
        }
      }
    }
  </script>

  <!-- NoteFlow Main Styling -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css" />
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

    <!-- Curved purple sidebar -->
    <aside class="curved-sidebar-wrapper hidden md:flex shrink-0">
      <!-- Top concave curve -->
      <svg class="sidebar-curve-top" viewBox="0 0 38 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 0 C0 26, 20 38, 38 48 L0 48 Z" fill="#6558f5"/>
      </svg>

      <!-- Purple vertical bar -->
      <div class="sidebar-body">
        <div class="flex flex-col items-center gap-2 w-full overflow-y-auto no-scrollbar py-1">
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

            <!-- 8. Pengaturan & Logo -->
            <a href="<?= BASE_URL ?>master/pengaturan" 
               class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_pengaturan' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
              <i class="ph-fill ph-gear text-lg"></i>
              <span class="sidebar-tooltip">Pengaturan Sistem &amp; Logo</span>
            </a>

            <!-- 9. Backup & Disaster Recovery -->
            <a href="<?= BASE_URL ?>master/backup" 
               class="sidebar-btn group relative w-10 h-10 rounded-[14px] flex items-center justify-center transition-all duration-200 shrink-0 <?= $current_page === 'master_backup' ? 'bg-white text-[#6558f5] shadow-md scale-105' : 'text-white/80 hover:text-white hover:bg-white/15 hover:scale-105' ?>">
              <i class="ph-fill ph-database text-lg"></i>
              <span class="sidebar-tooltip">Pusat Backup &amp; Pemulihan Sistem</span>
            </a>
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
      <svg class="sidebar-curve-bottom" viewBox="0 0 38 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 48 C0 22, 20 10, 38 0 L0 0 Z" fill="#6558f5"/>
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
          placeholder="Cari nomor laporan, nama unit, tiket pekerjaan..."
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

        <!-- Notification Bell with Ping Indicator -->
        <a href="<?= BASE_URL ?>laporan/index?status=MENUNGGU+VALIDASI" 
           class="relative w-8 h-8 rounded-full flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-800 transition-colors shrink-0" 
           title="Notifikasi & Validasi">
          <i class="ph ph-bell text-[17px]"></i>
          <span class="w-2 h-2 rounded-full bg-[#6558f5] absolute top-1.5 right-1.5 animate-ping"></span>
          <span class="w-1.5 h-1.5 rounded-full bg-[#6558f5] absolute top-1.5 right-1.5"></span>
        </a>

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

      <!-- Flash messages -->
      <?php if (!empty($flashes)): ?>
        <div class="mb-5 space-y-2.5 animate-stagger-1">
          <?php foreach ($flashes as $f): 
            $msgType = is_array($f) ? ($f['type'] ?? 'info') : 'info';
            $msgText = is_array($f) ? ($f['message'] ?? '') : (string)$f;
            if (empty($msgText)) continue;
          ?>
            <div class="p-4 rounded-2xl flex items-center justify-between gap-3 text-xs font-bold shadow-card <?= $msgType === 'success' ? 'bg-[#e6f7f0] text-[#1c7a52] border border-[#68cfa3]/40 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-[#fce8ec] text-[#a9233c] border border-[#e0536c]/40 dark:bg-rose-950/50 dark:text-rose-300' ?>">
              <div class="flex items-center gap-2.5">
                <i class="ph-bold <?= $msgType === 'success' ? 'ph-check-circle text-lg text-[#68cfa3]' : 'ph-warning-circle text-lg text-[#e0536c]' ?>"></i>
                <span><?= sanitize($msgText) ?></span>
              </div>
              <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">
                <i class="ph-bold ph-x text-sm"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
