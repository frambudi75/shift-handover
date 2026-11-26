    </main>

    <!-- Mobile drawer sidebar -->
    <div id="mobileDrawer" class="fixed inset-0 z-50 transform -translate-x-full transition-transform duration-300 md:hidden flex">
      <!-- Backdrop -->
      <div onclick="toggleMobileMenu()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm touch-none"></div>

      <!-- Drawer Content -->
      <div class="relative w-72 max-w-[80vw] bg-[#6558f5] text-white h-full overflow-y-auto overscroll-contain p-6 flex flex-col justify-between gap-6 shadow-2xl z-10">
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
            <a href="<?= BASE_URL ?>notulen/index" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'notulen' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-notepad text-lg"></i>
              <span>Notulen Meeting &amp; Briefing</span>
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
              <?php if (is_superadmin()): ?>
                <a href="<?= BASE_URL ?>audit" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'audit' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                  <i class="ph-fill ph-fingerprint text-lg"></i>
                  <span>Audit Trail &amp; Log</span>
                </a>
                <a href="<?= BASE_URL ?>master/ldap" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'master_ldap' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
                  <i class="ph-fill ph-tree-structure text-lg"></i>
                  <span>Integrasi Server LDAP</span>
                </a>
              <?php endif; ?>
            <?php endif; ?>

            <div class="pt-3 pb-1 border-t border-white/20 mt-3">
              <span class="text-[10px] uppercase tracking-wider text-white/60 px-4">Akun &amp; Keamanan</span>
            </div>
            <a href="<?= BASE_URL ?>profile" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'profile' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-user-circle text-lg"></i>
              <span>Profil &amp; Kata Sandi</span>
            </a>
            <a href="<?= BASE_URL ?>info" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= $current_page === 'info' ? 'bg-white text-[#6558f5] shadow-md' : 'text-white/85 hover:bg-white/15' ?>">
              <i class="ph-fill ph-info text-lg"></i>
              <span>Informasi Sistem &amp; Update</span>
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
        const isClosed = drawer.classList.toggle('-translate-x-full');
        const isOpen = !isClosed;
        document.body.classList.toggle('overflow-hidden', isOpen);
        const mainEl = document.querySelector('main');
        if (mainEl) {
          mainEl.classList.toggle('overflow-hidden', isOpen);
          mainEl.classList.toggle('overflow-y-auto', !isOpen);
        }
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

    // 4. Live AJAX Quick Search Handler + Ctrl+K Shortcut
    let searchTimer = null;
    function handleQuickSearch(q) {
      const dropdown = document.getElementById('search-dropdown');
      if (!dropdown) return;
      q = q.trim();
      if (q.length < 2) {
        dropdown.classList.add('hidden');
        dropdown.innerHTML = '';
        return;
      }

      clearTimeout(searchTimer);
      dropdown.classList.remove('hidden');
      dropdown.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Mencari...</div>';

      searchTimer = setTimeout(async () => {
        try {
          const res = await fetch('<?= BASE_URL ?>api/search?q=' + encodeURIComponent(q));
          const data = await res.json();
          const rows = data.results || [];
          let html = `
            <div class="px-3.5 py-2 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 flex justify-between">
              <span>Hasil Pencarian Langsung</span>
              <span>${rows.length} ditemukan</span>
            </div>
            <div class="p-1.5 max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">`;

          if (rows.length === 0) {
            html += `<div class="p-4 text-center text-xs text-slate-400">Tidak ada laporan/tiket yang cocok.</div>`;
          } else {
            rows.forEach(r => {
              const stClass = r.status === 'TERVALIDASI'
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300';
              html += `
                <a href="${r.url}" class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-[#efeffb] dark:hover:bg-slate-800 transition">
                  <div class="min-w-0">
                    <div class="text-xs font-extrabold text-[#6558f5] dark:text-indigo-300 truncate">${escHtml(r.nomor)}</div>
                    <div class="text-[11px] text-slate-600 dark:text-slate-300 truncate">${escHtml(r.lokasi)} • Shift ${escHtml(r.shift)} (${escHtml(r.tanggal)})</div>
                  </div>
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold shrink-0 ${stClass}">${escHtml(r.status)}</span>
                </a>`;
            });
          }

          html += `
              <a href="<?= BASE_URL ?>laporan/index?search=${encodeURIComponent(q)}" class="flex items-center justify-center gap-1.5 p-2.5 text-[11px] font-extrabold text-[#6558f5] hover:bg-[#efeffb] dark:hover:bg-slate-800 rounded-xl transition">
                <span>Lihat semua hasil untuk "${escHtml(q)}"</span>
                <i class="ph-bold ph-arrow-right"></i>
              </a>
            </div>`;
          dropdown.innerHTML = html;
        } catch (err) {
          dropdown.innerHTML = '<div class="p-4 text-center text-xs text-rose-500">Gagal memuat pencarian.</div>';
        }
      }, 220);
    }

    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const inp = document.getElementById('global-search');
        if (inp) { inp.focus(); inp.select(); }
      }
    });

    // Close search & bell dropdowns on click outside
    document.addEventListener('click', (e) => {
      const wrap = document.getElementById('search-wrap');
      const dd = document.getElementById('search-dropdown');
      if (dd && wrap && !wrap.contains(e.target)) {
        dd.classList.add('hidden');
      }
      const bellWrap = document.getElementById('bell-container');
      const bellDd = document.getElementById('bell-dropdown');
      if (bellDd && bellWrap && !bellWrap.contains(e.target)) {
        bellDd.classList.add('hidden');
      }
    });

    // =========================================================================
    // 5. Bell Notification & Broadcast Announcement System
    // =========================================================================
    const BASE_APP_URL = <?= json_encode(BASE_URL) ?>;
    let bellLoadedOnce = false;

    function escHtml(str) {
      return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function toggleBellDropdown(e) {
      if (e) e.stopPropagation();
      const dd = document.getElementById('bell-dropdown');
      if (!dd) return;
      const isHidden = dd.classList.contains('hidden');
      dd.classList.toggle('hidden');
      if (isHidden) {
        loadBellFeed();
      }
    }

    async function loadBellFeed() {
      const list = document.getElementById('bell-feed-list');
      if (!list) return;
      try {
        const res = await fetch(BASE_APP_URL + 'api/notifications?action=feed');
        const data = await res.json();
        if (!data.success) return;

        bellLoadedOnce = true;
        updateBellBadge(data.unread_count);
        renderBellFeed(data);
      } catch (err) {
        list.innerHTML = '<div class="p-5 text-center text-xs text-rose-500">Gagal memuat notifikasi.</div>';
      }
    }

    function updateBellBadge(count) {
      const badge = document.getElementById('bell-badge');
      if (!badge) return;
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
      } else {
        badge.classList.add('hidden');
      }
    }

    function renderBellFeed(data) {
      const list = document.getElementById('bell-feed-list');
      if (!list) return;
      let html = '';

      const anns = data.announcements || [];
      const notifs = data.notifications || [];

      if (anns.length > 0) {
        html += `<div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pengumuman Resmi</div>`;
        anns.forEach(a => {
          const unread = Number(a.is_read) === 0;
          const catBadge = a.category === 'darurat'
            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300'
            : (a.category === 'penting'
              ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300'
              : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300');
          const delBtn = data.is_superadmin
            ? `<button type="button" onclick="event.stopPropagation(); deleteAnnouncement(${a.id})" title="Hapus Pengumuman" class="p-1 text-slate-300 hover:text-rose-500 transition cursor-pointer"><i class="ph-bold ph-trash text-xs"></i></button>`
            : '';

          html += `
            <div onclick='openReadAnnouncementModal(${JSON.stringify(a)})'
                 class="p-3.5 hover:bg-[#f7f7fb] dark:hover:bg-slate-800/60 transition cursor-pointer flex items-start gap-3 ${unread ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''}">
              <div class="w-8 h-8 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center shrink-0 mt-0.5">
                <i class="ph-fill ph-megaphone text-base"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase ${catBadge}">${escHtml(a.category)}</span>
                  <div class="flex items-center gap-1">
                    ${unread ? '<span class="w-2 h-2 rounded-full bg-[#6558f5]"></span>' : ''}
                    ${delBtn}
                  </div>
                </div>
                <div class="text-xs font-extrabold text-slate-800 dark:text-white mt-1 truncate">${escHtml(a.title)}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">${escHtml(a.message)}</div>
                <div class="text-[10px] text-slate-400 mt-1">${escHtml(a.author_name || 'Superadmin')} • ${escHtml((a.created_at || '').slice(0, 16))}</div>
              </div>
            </div>`;
        });
      }

      if (notifs.length > 0) {
        html += `<div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Aktivitas &amp; Serah Terima</div>`;
        notifs.forEach(n => {
          const unread = Number(n.is_read) === 0;
          const icon = n.type === 'approved' ? 'ph-check-circle text-emerald-500' : 'ph-clipboard-text text-[#6558f5]';
          html += `
            <div onclick="openBellNotification(${n.id}, '${escHtml(n.link_url || '')}')"
                 class="p-3.5 hover:bg-[#f7f7fb] dark:hover:bg-slate-800/60 transition cursor-pointer flex items-start gap-3 ${unread ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''}">
              <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0 mt-0.5">
                <i class="ph-fill ${icon} text-base"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-extrabold text-slate-800 dark:text-white truncate">${escHtml(n.title)}</span>
                  ${unread ? '<span class="w-2 h-2 rounded-full bg-[#6558f5] shrink-0"></span>' : ''}
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">${escHtml(n.message)}</div>
                <div class="text-[10px] text-slate-400 mt-1">${escHtml((n.created_at || '').slice(0, 16))}</div>
              </div>
            </div>`;
        });
      }

      if (html === '') {
        html = `
          <div class="p-8 text-center">
            <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2.5 text-xl">
              <i class="ph ph-bell-slash"></i>
            </div>
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Belum ada notifikasi atau pengumuman.</p>
          </div>`;
      }

      list.innerHTML = html;
    }

    async function markAllBellRead() {
      const fd = new FormData();
      fd.append('action', 'mark_all_read');
      await fetch(BASE_APP_URL + 'api/notifications', { method: 'POST', body: fd });
      updateBellBadge(0);
      loadBellFeed();
    }

    async function openBellNotification(id, linkUrl) {
      const fd = new FormData();
      fd.append('action', 'mark_read');
      fd.append('kind', 'notification');
      fd.append('id', id);
      await fetch(BASE_APP_URL + 'api/notifications', { method: 'POST', body: fd });
      if (linkUrl) {
        window.location.href = BASE_APP_URL + linkUrl;
      } else {
        loadBellFeed();
      }
    }

    function openReadAnnouncementModal(ann) {
      const modal = document.getElementById('modalReadAnnouncement');
      if (!modal) return;
      document.getElementById('readAnnTitle').textContent = ann.title || '';
      document.getElementById('readAnnMessage').textContent = ann.message || '';
      document.getElementById('readAnnMeta').textContent = `Oleh ${ann.author_name || 'Superadmin'} • ${(ann.created_at || '').slice(0, 16)}`;
      const badge = document.getElementById('readAnnBadge');
      badge.textContent = (ann.category || 'info').toUpperCase();
      badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase ' + (
        ann.category === 'darurat' ? 'bg-rose-100 text-rose-700' :
        (ann.category === 'penting' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700')
      );
      modal.classList.remove('hidden');

      const fd = new FormData();
      fd.append('action', 'mark_read');
      fd.append('kind', 'announcement');
      fd.append('id', ann.id);
      fetch(BASE_APP_URL + 'api/notifications', { method: 'POST', body: fd }).then(() => {
        if (bellLoadedOnce) loadBellFeed();
      });
    }

    function closeReadAnnouncementModal() {
      document.getElementById('modalReadAnnouncement')?.classList.add('hidden');
    }

    function openCreateAnnouncementModal() {
      document.getElementById('bell-dropdown')?.classList.add('hidden');
      document.getElementById('modalCreateAnnouncement')?.classList.remove('hidden');
      document.getElementById('annInputTitle')?.focus({ preventScroll: true });
    }

    function closeCreateAnnouncementModal() {
      document.getElementById('modalCreateAnnouncement')?.classList.add('hidden');
    }

    async function submitAnnouncementForm(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('btnSubmitAnn');
      btn.disabled = true;
      btn.textContent = 'Mengirim...';
      try {
        const fd = new FormData(form);
        fd.append('action', 'create_announcement');
        const res = await fetch(BASE_APP_URL + 'api/notifications', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          form.reset();
          closeCreateAnnouncementModal();
          window.location.reload();
        } else {
          alert(data.message || 'Gagal membuat pengumuman.');
        }
      } catch (err) {
        alert('Terjadi kesalahan jaringan.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Publikasikan Pengumuman';
      }
    }

    async function deleteAnnouncement(id) {
      if (!confirm('Arsipkan / hapus pengumuman ini?')) return;
      const fd = new FormData();
      fd.append('action', 'delete_announcement');
      fd.append('id', id);
      await fetch(BASE_APP_URL + 'api/notifications', { method: 'POST', body: fd });
      loadBellFeed();
    }

    // =========================================================================
    // 6. Floating Glass Pill Tooltip for Desktop Sidebar
    // =========================================================================
    (function initSidebarGlassTooltips() {
      if (!window.matchMedia('(hover: hover)').matches) return;
      const tt = document.createElement('div');
      tt.id = 'pc-sidebar-tooltip';
      tt.style.position = 'fixed';
      tt.style.pointerEvents = 'none';
      tt.style.zIndex = '9999';
      tt.innerHTML = '<span class="tt-dot"></span><span class="tt-text"></span>';
      document.body.appendChild(tt);

      const ttText = tt.querySelector('.tt-text');
      document.querySelectorAll('aside .sidebar-btn').forEach(btn => {
        const span = btn.querySelector('.sidebar-tooltip');
        const label = span ? span.textContent.trim() : (btn.getAttribute('title') || '');
        if (!label) return;
        btn.removeAttribute('title');

        btn.addEventListener('mouseenter', () => {
          const rect = btn.getBoundingClientRect();
          ttText.textContent = label;
          tt.style.left = (rect.right + 14) + 'px';
          tt.style.top  = (rect.top + rect.height / 2) + 'px';
          tt.classList.add('is-visible');
        });
        btn.addEventListener('mouseleave', () => {
          tt.classList.remove('is-visible');
        });
      });
    })();

    // =========================================================================
    // 7. Auto-Dismiss Floating Toasts (4s) & Live WIB Clock
    // =========================================================================
    setTimeout(() => {
      document.querySelectorAll('#flash-toast-stack .flash-toast').forEach((el, idx) => {
        setTimeout(() => {
          el.style.opacity = '0';
          el.style.transform = 'translateY(-10px)';
          setTimeout(() => el.remove(), 350);
        }, idx * 250);
      });
    }, 4000);

    const wibClockEl = document.getElementById('live-wib-clock');
    if (wibClockEl) {
      setInterval(() => {
        const now = new Date();
        const fmt = new Intl.DateTimeFormat('id-ID', {
          timeZone: 'Asia/Jakarta',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false
        }).format(now).replace(/\./g, ':');
        wibClockEl.textContent = fmt + ' WIB';
      }, 1000);
    }

    // =========================================================================
    // 8. Control Room Idle Auto-Logout Guard (45 Menit Idle -> Countdown 60s)
    // =========================================================================
    (function initIdleTimeout() {
      const IDLE_LIMIT_MS = 45 * 60 * 1000;
      let idleTimer = null;
      let countTimer = null;
      let secondsLeft = 60;

      function resetIdle() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(showIdleWarning, IDLE_LIMIT_MS);
      }

      function showIdleWarning() {
        const modal = document.getElementById('modalIdleWarning');
        const counter = document.getElementById('idleCountdown');
        if (!modal || !counter) return;
        secondsLeft = 60;
        counter.textContent = secondsLeft;
        modal.classList.remove('hidden');

        clearInterval(countTimer);
        countTimer = setInterval(() => {
          secondsLeft--;
          counter.textContent = secondsLeft;
          if (secondsLeft <= 0) {
            clearInterval(countTimer);
            window.location.href = BASE_APP_URL + 'auth/logout';
          }
        }, 1000);
      }

      window.stayLoggedIn = function() {
        clearInterval(countTimer);
        document.getElementById('modalIdleWarning')?.classList.add('hidden');
        resetIdle();
      };

      ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetIdle, { passive: true });
      });
      resetIdle();
    })();

    // =========================================================================
    // 9. PWA Service Worker Registration
    // =========================================================================
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register(BASE_APP_URL + 'sw.js').catch(() => {});
      });
    }
  </script>

  <!-- Modal Peringatan Idle Auto-Logout -->
  <div id="modalIdleWarning" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
    <div class="relative z-10 bg-white dark:bg-[#1b1e30] rounded-[26px] max-w-sm w-full p-6 border border-slate-100 dark:border-slate-800 shadow-2xl text-center">
      <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-500 flex items-center justify-center text-2xl mx-auto mb-3.5">
        <i class="ph-bold ph-clock-countdown"></i>
      </div>
      <h3 class="text-base font-extrabold text-[#181b2c] dark:text-white">Sesi Tidak Aktif</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
        Demi keamanan akun shift Anda, sesi akan otomatis keluar dalam <strong id="idleCountdown" class="text-rose-500 font-black">60</strong> detik.
      </p>
      <div class="flex gap-2.5 mt-5">
        <a href="<?= BASE_URL ?>auth/logout" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-xs">
          Keluar
        </a>
        <button type="button" onclick="stayLoggedIn()" class="flex-1 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md cursor-pointer">
          Tetap Masuk
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Baca Pengumuman -->
  <div id="modalReadAnnouncement" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeReadAnnouncementModal()"></div>
    <div class="relative z-10 bg-white dark:bg-[#1b1e30] rounded-[26px] max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 border border-slate-100 dark:border-slate-800 shadow-2xl">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
        <div class="flex items-center gap-2">
          <i class="ph-fill ph-megaphone text-[#6558f5] text-xl"></i>
          <span id="readAnnBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-indigo-100 text-indigo-700">INFO</span>
        </div>
        <button type="button" onclick="closeReadAnnouncementModal()" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
          <i class="ph-bold ph-x text-lg"></i>
        </button>
      </div>
      <h3 id="readAnnTitle" class="text-base font-extrabold text-[#181b2c] dark:text-white leading-snug"></h3>
      <p id="readAnnMeta" class="text-[11px] text-slate-400 font-semibold mt-1"></p>
      <div id="readAnnMessage" class="mt-4 p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line border border-slate-100 dark:border-slate-800"></div>
      <button type="button" onclick="closeReadAnnouncementModal()" class="w-full mt-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs transition cursor-pointer">
        Tutup
      </button>
    </div>
  </div>

  <?php if (is_superadmin()): ?>
  <!-- Modal Buat Pengumuman (Khusus Superadmin) -->
  <div id="modalCreateAnnouncement" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCreateAnnouncementModal()"></div>
    <div class="relative z-10 bg-white dark:bg-[#1b1e30] rounded-[26px] max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 border border-slate-100 dark:border-slate-800 shadow-2xl">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl">
            <i class="ph-bold ph-megaphone"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Buat Pengumuman Resmi</h3>
            <p class="text-[11px] text-slate-400">Tampil di lonceng notifikasi &amp; banner Dashboard</p>
          </div>
        </div>
        <button type="button" onclick="closeCreateAnnouncementModal()" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
          <i class="ph-bold ph-x text-lg"></i>
        </button>
      </div>

      <form onsubmit="submitAnnouncementForm(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Judul Pengumuman *</label>
          <input type="text" name="title" id="annInputTitle" required maxlength="150"
                 placeholder="Contoh: Pemeliharaan Server Terjadwal Pukul 23:00 WIB"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Kategori</label>
            <select name="category" class="w-full px-3 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100">
              <option value="info">Info Umum</option>
              <option value="penting">Penting</option>
              <option value="darurat">Darurat / Gangguan</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Target Penerima</label>
            <select name="target_role" class="w-full px-3 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100">
              <option value="all">Semua Pengguna</option>
              <option value="admin">Admin &amp; Supervisor</option>
              <option value="user">Operator Shift</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Isi Pengumuman *</label>
          <textarea name="message" rows="4" required placeholder="Tuliskan instruksi atau informasi lengkap untuk tim operasional..."
                    class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition"></textarea>
        </div>

        <label class="flex items-center gap-2.5 cursor-pointer select-none">
          <input type="checkbox" name="show_banner" value="1" checked class="w-4 h-4 rounded text-[#6558f5] focus:ring-[#6558f5]" />
          <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Sematkan sebagai banner pengumuman di atas Dashboard</span>
        </label>

        <div class="flex items-center gap-2.5 pt-2">
          <button type="button" onclick="closeCreateAnnouncementModal()" class="flex-1 py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-extrabold text-xs cursor-pointer">
            Batal
          </button>
          <button type="submit" id="btnSubmitAnn" class="flex-1 py-2.5 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md transition cursor-pointer">
            Publikasikan Pengumuman
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</body>
</html>
