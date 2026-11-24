    </main>

    <!-- Mobile drawer sidebar -->
    <div id="mobileDrawer" class="fixed inset-0 z-50 transform -translate-x-full transition-transform duration-300 md:hidden flex">
      <!-- Backdrop -->
      <div onclick="toggleMobileMenu()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

      <!-- Drawer Content -->
      <div class="relative w-72 max-w-[80vw] bg-[#6558f5] text-white h-full p-6 flex flex-col justify-between shadow-2xl z-10">
        <div>
          <!-- Header Drawer -->
          <div class="flex items-center justify-between pb-6 border-b border-white/20 mb-6">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-white text-[#6558f5] flex items-center justify-center font-bold text-xl shadow-md p-1 overflow-hidden">
                <?php if ($appLogo): ?>
                  <img src="<?= $appLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
                <?php else: ?>
                  <i class="ph-fill ph-clipboard-text"></i>
                <?php endif; ?>
              </div>
              <div>
                <h3 class="font-extrabold text-sm tracking-tight"><?= sanitize($appName) ?></h3>
                <p class="text-[10px] text-white/70">Menu Navigasi</p>
              </div>
            </div>
            <button onclick="toggleMobileMenu()" class="p-1 rounded-lg hover:bg-white/10 text-white/80 hover:text-white">
              <i class="ph-bold ph-x text-xl"></i>
            </button>
          </div>

          <!-- Theme Switcher in Mobile Drawer -->
          <div class="p-2.5 rounded-2xl bg-white/10 border border-white/15 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i class="ph-fill ph-circle-half text-base"></i>
              <span class="text-xs font-bold">Tema Sistem</span>
            </div>
            <div class="flex items-center gap-1 bg-black/20 p-1 rounded-xl text-xs">
              <button type="button" onclick="setThemeMode('light')" id="drawer-theme-light" class="px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 transition">
                <i class="ph-fill ph-sun"></i> Terang
              </button>
              <button type="button" onclick="setThemeMode('dark')" id="drawer-theme-dark" class="px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 transition">
                <i class="ph-fill ph-moon"></i> Gelap
              </button>
            </div>
          </div>

          <!-- Navigation Links -->
          <nav class="space-y-1.5 font-bold text-xs">
            <a href="<?= BASE_URL ?>dashboard" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'dashboard' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-squares-four text-lg"></i>
              <span>Dashboard Overview</span>
            </a>
            <a href="<?= BASE_URL ?>laporan/create" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'laporan_create' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-file-plus text-lg"></i>
              <span>Buat Serah Terima</span>
            </a>
            <a href="<?= BASE_URL ?>laporan/index" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'laporan_list' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-folders text-lg"></i>
              <span>Riwayat Laporan Shift</span>
            </a>
            <a href="<?= BASE_URL ?>rekap/index" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'rekap' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-calendar-check text-lg"></i>
              <span>Rekap Harian Logbook</span>
            </a>

            <?php if (is_admin()): ?>
              <div class="pt-3 pb-1 border-t border-white/20 mt-3">
                <span class="text-[10px] uppercase tracking-wider text-white/60 px-4">Menu Administrator</span>
              </div>
              <a href="<?= BASE_URL ?>master/shift" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= in_array($current_page, ['master_shift', 'master_lokasi', 'master_kondisi']) ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                <i class="ph-fill ph-sliders-horizontal text-lg"></i>
                <span>Master Operasional (Shift, Lokasi, Kondisi)</span>
              </a>
              <a href="<?= BASE_URL ?>master/users" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'master_users' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                <i class="ph-fill ph-users-three text-lg"></i>
                <span>Kelola Pengguna Sistem</span>
              </a>
              <a href="<?= BASE_URL ?>master/pengaturan" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'master_pengaturan' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                <i class="ph-fill ph-gear text-lg"></i>
                <span>Pengaturan &amp; Logo</span>
              </a>
              <a href="<?= BASE_URL ?>master/backup" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'master_backup' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                <i class="ph-fill ph-database text-lg"></i>
                <span>Pusat Backup &amp; Pemulihan</span>
              </a>
            <?php endif; ?>

            <div class="pt-3 pb-1 border-t border-white/20 mt-3">
              <span class="text-[10px] uppercase tracking-wider text-white/60 px-4">Akun &amp; Keamanan</span>
            </div>
            <a href="<?= BASE_URL ?>profile" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'profile' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-user-circle text-lg"></i>
              <span>Profil &amp; Kata Sandi</span>
            </a>
          </nav>
        </div>

        <!-- Footer Drawer Profile & Logout -->
        <div class="pt-6 border-t border-white/20">
          <div class="flex items-center justify-between">
            <a href="<?= BASE_URL ?>profile" class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full overflow-hidden bg-white/20 flex items-center justify-center font-bold text-xs">
                <?php $mobAvatar = get_user_avatar_url($user['foto'] ?? null); ?>
                <?php if ($mobAvatar): ?>
                  <img src="<?= $mobAvatar ?>" alt="Avatar" class="w-full h-full object-cover">
                <?php else: ?>
                  <?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 2)) ?>
                <?php endif; ?>
              </div>
              <div>
                <p class="font-extrabold text-xs"><?= sanitize($user['nama_lengkap'] ?? 'User') ?></p>
                <p class="text-[10px] text-white/70 uppercase font-bold"><?= $user['role'] ?? 'USER' ?></p>
              </div>
            </a>
            <a href="<?= BASE_URL ?>auth/logout" onclick="return confirm('Keluar dari sistem?')" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 text-rose-200" title="Keluar">
              <i class="ph-bold ph-sign-out text-lg"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile bottom navigation -->
    <div class="md:hidden fixed bottom-3 left-4 right-4 z-40 bg-[#6558f5] text-white rounded-2xl px-4 py-2.5 shadow-xl flex items-center justify-around border border-white/20 backdrop-blur-md">
      <a href="<?= BASE_URL ?>dashboard" class="p-2 rounded-xl <?= $current_page === 'dashboard' ? 'bg-white text-[#6558f5] shadow-md' : 'hover:bg-white/10' ?>">
        <i class="ph-fill ph-squares-four text-lg"></i>
      </a>
      <a href="<?= BASE_URL ?>laporan/create" class="p-2 rounded-xl <?= $current_page === 'laporan_create' ? 'bg-white text-[#6558f5] shadow-md' : 'hover:bg-white/10' ?>">
        <i class="ph-fill ph-file-plus text-lg"></i>
      </a>
      <a href="<?= BASE_URL ?>laporan/index" class="p-2 rounded-xl <?= $current_page === 'laporan_list' ? 'bg-white text-[#6558f5] shadow-md' : 'hover:bg-white/10' ?>">
        <i class="ph-fill ph-folders text-lg"></i>
      </a>
      <a href="<?= BASE_URL ?>rekap/index" class="p-2 rounded-xl <?= $current_page === 'rekap' ? 'bg-white text-[#6558f5] shadow-md' : 'hover:bg-white/10' ?>">
        <i class="ph-fill ph-calendar-check text-lg"></i>
      </a>
      <button onclick="toggleMobileMenu()" type="button" class="p-2 rounded-xl hover:bg-white/10">
        <i class="ph-bold ph-dots-three-outline text-lg"></i>
      </button>
    </div>

  </div> <!-- /#app-shell -->

  <!-- NOTEFLOW INTERACTION SCRIPTS -->
  <script>
    // 1. Theme Pill Toggle & Mobile Sync
    function toggleTheme() {
      const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      setThemeMode(current === 'dark' ? 'light' : 'dark');
    }

    function updateThemeUI(theme) {
      // 1. Desktop Pill Toggle
      const btnLight = document.getElementById('btn-theme-light');
      const btnDark = document.getElementById('btn-theme-dark');
      if (btnLight && btnDark) {
        if (theme === 'dark') {
          btnDark.className = 'flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#6558f5] text-white shadow-sm transition-all duration-200';
          btnLight.className = 'flex items-center gap-1 px-2.5 py-0.5 rounded-full text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200';
        } else {
          btnLight.className = 'flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#6558f5] text-white shadow-sm transition-all duration-200';
          btnDark.className = 'flex items-center gap-1 px-2.5 py-0.5 rounded-full text-slate-500 dark:text-slate-400 hover:text-slate-900 transition-all duration-200';
        }
      }

      // 2. Mobile Single Toggle Button & Icon
      const mobIcon = document.getElementById('theme-icon-mobile');
      const mobBtn = document.getElementById('btn-theme-toggle-mobile');
      if (mobIcon && mobBtn) {
        if (theme === 'dark') {
          mobIcon.className = 'ph-fill ph-sun text-base text-amber-400';
          mobBtn.title = 'Beralih ke Mode Terang (Light Mode)';
        } else {
          mobIcon.className = 'ph-fill ph-moon text-base text-[#6558f5]';
          mobBtn.title = 'Beralih ke Mode Gelap (Dark Mode)';
        }
      }

      // 3. Mobile Drawer Theme Buttons
      const drawerLight = document.getElementById('drawer-theme-light');
      const drawerDark = document.getElementById('drawer-theme-dark');
      if (drawerLight && drawerDark) {
        if (theme === 'dark') {
          drawerDark.className = 'px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 bg-white text-[#6558f5] shadow-sm';
          drawerLight.className = 'px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 text-white/70 hover:text-white';
        } else {
          drawerLight.className = 'px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 bg-white text-[#6558f5] shadow-sm';
          drawerDark.className = 'px-2.5 py-1 rounded-lg text-[10.5px] font-bold flex items-center gap-1 text-white/70 hover:text-white';
        }
      }
    }

    function setThemeMode(mode) {
      const html = document.documentElement;
      if (mode === 'dark') {
        html.classList.add('dark');
        html.classList.remove('light');
        localStorage.setItem('theme', 'dark');
      } else {
        html.classList.remove('dark');
        html.classList.add('light');
        localStorage.setItem('theme', 'light');
      }
      updateThemeUI(mode);
    }

    // Initialize Theme Pill on Page Load
    document.addEventListener('DOMContentLoaded', () => {
      const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      updateThemeUI(currentTheme);
    });

    // 2. Mobile Drawer Toggle
    function toggleMobileMenu() {
      const drawer = document.getElementById('mobileDrawer');
      if (drawer) {
        drawer.classList.toggle('-translate-x-full');
      }
    }

    // 3. Cloud Sync Button Animation
    function triggerCloudSync() {
      const icon = document.getElementById('sync-sidebar-icon');
      if (icon) {
        icon.classList.add('rotate-180');
        setTimeout(() => {
          icon.classList.remove('rotate-180');
        }, 500);
      }
      // Quick flash notification
      const toast = document.createElement('div');
      toast.className = 'fixed top-6 right-6 z-50 bg-[#181b2c] text-white px-4 py-2.5 rounded-2xl shadow-pop text-xs font-bold flex items-center gap-2 animate-stagger-1';
      toast.innerHTML = '<i class="ph-bold ph-check text-[#68cfa3] text-base"></i> Sistem telah tersinkronisasi!';
      document.body.appendChild(toast);
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
      }, 2000);
    }

    // 4. Global Quick Search Handler
    function handleQuickSearch(q) {
      const dropdown = document.getElementById('search-dropdown');
      if (!dropdown) return;
      q = q.trim().toLowerCase();
      if (q.length < 2) {
        dropdown.classList.add('hidden');
        dropdown.innerHTML = '';
        return;
      }

      dropdown.classList.remove('hidden');
      dropdown.innerHTML = `
        <div class="p-3 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
          Pencarian Pintar
        </div>
        <div class="p-2 space-y-1">
          <a href="<?= BASE_URL ?>laporan/index?q=${encodeURIComponent(q)}" class="flex items-center gap-2 p-2 rounded-xl hover:bg-[#efeffb] dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition">
            <i class="ph-bold ph-magnifying-glass text-[#6558f5]"></i>
            <span>Cari laporan dengan kata kunci "<strong>${q}</strong>"</span>
          </a>
          <a href="<?= BASE_URL ?>laporan/create" class="flex items-center gap-2 p-2 rounded-xl hover:bg-[#efeffb] dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition">
            <i class="ph-bold ph-plus-circle text-[#68cfa3]"></i>
            <span>Buat serah terima shift baru</span>
          </a>
        </div>
      `;
    }

    // Close search dropdown on click outside
    document.addEventListener('click', (e) => {
      const wrap = document.getElementById('search-wrap');
      const dd = document.getElementById('search-dropdown');
      if (dd && wrap && !wrap.contains(e.target)) {
        dd.classList.add('hidden');
      }
    });
  </script>

</body>
</html>
