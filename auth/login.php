<?php
// auth/login.php - Professional NoteFlow Glassmorphism Login with Micro-Interactions
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate-limit failed logins per IP (max 8 attempts per 10 minutes)
    require_once __DIR__ . '/../includes/audit_helper.php';
    ensure_audit_table($pdo);
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($clientIp, ',') !== false) {
        $clientIp = trim(explode(',', $clientIp)[0]);
    }
    $stmtRate = $pdo->prepare("
        SELECT COUNT(*) FROM activity_logs
        WHERE action = 'LOGIN_FAILED'
          AND ip_address = :ip
          AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmtRate->execute(['ip' => mb_substr($clientIp, 0, 45)]);
    $recentFails = (int)$stmtRate->fetchColumn();

    if ($recentFails >= 8) {
        $error = 'Terlalu banyak percobaan login gagal. Silakan tunggu 10 menit sebelum mencoba lagi.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username dan kata sandi wajib diisi!';
    } else {
        // 1. Coba autentikasi LDAP terlebih dahulu jika diaktifkan (Hybrid Fallback)
        require_once __DIR__ . '/../includes/ldap_helper.php';
        $authVia = 'local';
        $user = ldap_authenticate_user($pdo, $username, $password);
        if ($user) {
            $authVia = 'ldap';
        } else {
            // 2. Fallback ke autentikasi database lokal
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND status = 'aktif' LIMIT 1");
            $stmt->execute(['username' => $username]);
            $candidate = $stmt->fetch();
            if ($candidate && password_verify($password, $candidate['password'])) {
                $user = $candidate;
            }
        }

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['jabatan']      = $user['jabatan'];

            $viaLabel = $authVia === 'ldap' ? ' via LDAP' : '';
            log_activity($pdo, 'LOGIN_SUCCESS', "Login berhasil ke sistem ({$user['role']}){$viaLabel}.", (int)$user['id'], $user['username']);

            flash_set('success', "Selamat datang kembali, {$user['nama_lengkap']}!");
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        } else {
            log_activity($pdo, 'LOGIN_FAILED', "Percobaan login gagal untuk username '{$username}'.", null, $username);
            $error = 'Username atau kata sandi tidak valid. Silakan periksa kembali kredensial Anda.';
        }
    }
}

$flashes = flash_get();
$appLogo = get_app_logo_url();
$appName = get_app_name();
$appFullName = get_app_full_name();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Masuk — <?= sanitize($appFullName) ?></title>
  <?php $faviconUrl = $appLogo ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%236558f5'/%3E%3Cpath d='M10 10h12v2H10zm0 5h12v2H10zm0 5h8v2h-8z' fill='white'/%3E%3C/svg%3E"; ?>
  <link rel="icon" href="<?= $faviconUrl ?>" />
  <link rel="manifest" href="<?= BASE_URL ?>manifest" />
  <meta name="theme-color" content="#6558f5" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <script>
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '280' ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '250' ?>" />
</head>
<body class="min-h-screen w-full bg-[#f8fafc] dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex items-center justify-center p-4 sm:p-6 font-sans transition-colors duration-300 relative overflow-x-hidden selection:bg-[#6558f5] selection:text-white">

  <!-- Subtle Modern Batik Kawung Motif Background -->
  <div class="fixed inset-0 pointer-events-none opacity-[0.12] dark:opacity-[0.08]"
       style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='64' height='64' viewBox='0 0 64 64' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%236558f5' stroke-width='1.2'%3E%3Cpath d='M32 0 C40 12 40 20 32 32 C24 20 24 12 32 0 Z'/%3E%3Cpath d='M32 32 C40 44 40 52 32 64 C24 52 24 44 32 32 Z'/%3E%3Cpath d='M0 32 C12 24 20 24 32 32 C20 40 12 40 0 32 Z'/%3E%3Cpath d='M32 32 C44 24 52 24 64 32 C52 40 44 40 32 32 Z'/%3E%3Ccircle cx='32' cy='32' r='2.5' fill='%236558f5'/%3E%3Ccircle cx='0' cy='0' r='2' fill='%236558f5'/%3E%3Ccircle cx='64' cy='0' r='2' fill='%236558f5'/%3E%3Ccircle cx='0' cy='64' r='2' fill='%236558f5'/%3E%3Ccircle cx='64' cy='64' r='2' fill='%236558f5'/%3E%3C/g%3E%3C/svg%3E&quot;); background-size: 64px 64px;"></div>

  <!-- Subtle Floating Ambient Mesh Orbs -->
  <div class="fixed top-[-8%] left-[-8%] w-[420px] h-[420px] rounded-full bg-[#6558f5]/15 dark:bg-[#6558f5]/10 blur-[100px] pointer-events-none animate-float-orb-1"></div>
  <div class="fixed bottom-[-8%] right-[-8%] w-[460px] h-[460px] rounded-full bg-indigo-500/15 dark:bg-indigo-600/10 blur-[120px] pointer-events-none animate-float-orb-2"></div>

  <!-- Floating Dark/Light Toggle with Rotation Effect -->
  <button onclick="toggleTheme()" type="button" 
          aria-label="Ganti Tema"
          class="fixed top-5 right-5 w-11 h-11 rounded-full bg-white/90 dark:bg-[#131b2e]/90 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 shadow-md hover:shadow-lg hover:scale-105 active:scale-90 transition-all flex items-center justify-center cursor-pointer z-50 group">
    <i id="themeToggleIcon" class="theme-spin-icon ph-bold ph-moon text-lg dark:hidden"></i>
    <i id="themeToggleIconDark" class="theme-spin-icon ph-bold ph-sun text-lg hidden dark:inline text-amber-400"></i>
  </button>

  <!-- Login Container -->
  <div class="w-full max-w-[430px] relative z-10">

    <!-- Brand Header with Micro-scale on Hover -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-[22px] <?= $appLogo ? 'bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 shadow-lg p-2.5' : 'bg-[#6558f5] text-white shadow-xl shadow-[#6558f5]/30 p-3.5' ?> mb-4 transition-all duration-300 hover:scale-110 hover:-translate-y-0.5 cursor-default overflow-hidden">
        <?php if ($appLogo): ?>
          <img src="<?= $appLogo ?>" alt="Logo" class="w-full h-full object-contain" />
        <?php else: ?>
          <i class="ph-fill ph-clipboard-text text-3xl"></i>
        <?php endif; ?>
      </div>
      <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
        <?= sanitize($appName) ?>
      </h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">
        <?= sanitize($appFullName) ?>
      </p>
    </div>

    <!-- Main Frosted Glassmorphic Card -->
    <div class="glass-card rounded-[30px] p-7 sm:p-9 transition-all duration-300 relative overflow-hidden">
      
      <!-- Subtle internal top highlight line -->
      <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-[#6558f5]/40 to-transparent"></div>

      <!-- Card Title -->
      <div class="mb-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Masuk ke Akun</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gunakan akun operasional resmi untuk melanjutkan.</p>
      </div>

      <!-- Feedback Alerts -->
      <?php if (!empty($error)): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5 animate-shake shadow-sm">
          <i class="ph-bold ph-warning-circle text-base flex-shrink-0 mt-0.5 text-rose-500"></i>
          <span class="font-medium leading-relaxed"><?= $error ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($flashes['success'])): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 text-xs flex items-start gap-2.5 shadow-sm">
          <i class="ph-bold ph-check-circle text-base flex-shrink-0 mt-0.5 text-emerald-500"></i>
          <span class="font-medium leading-relaxed"><?= $flashes['success'] ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($flashes['error'])): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5 shadow-sm">
          <i class="ph-bold ph-x-circle text-base flex-shrink-0 mt-0.5 text-rose-500"></i>
          <span class="font-medium leading-relaxed"><?= $flashes['error'] ?></span>
        </div>
      <?php endif; ?>

      <!-- Login Form with Micro-Interaction Inputs -->
      <form id="loginForm" method="POST" action="" onsubmit="handleLoginSubmit(event)" class="space-y-4">
        
        <!-- Username Field with Interactive Focus -->
        <div>
          <label for="username" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
            Username
          </label>
          <div class="input-group relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <i class="input-icon ph ph-user text-base text-slate-400 dark:text-slate-500 transition-all duration-200"></i>
            </span>
            <input type="text" name="username" id="username" required autofocus autocomplete="username"
                   class="input-interactive w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none transition-all duration-200"
                   placeholder="Masukkan username" />
          </div>
        </div>

        <!-- Password Field with Interactive Focus & Smooth Morph Toggle -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label for="password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
              Kata Sandi
            </label>
            <button type="button" onclick="showHelpModal()" class="text-[11px] font-semibold text-slate-400 hover:text-[#6558f5] dark:hover:text-[#7f73fc] transition">
              Lupa sandi?
            </button>
          </div>
          <div class="input-group relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <i class="input-icon ph ph-lock-key text-base text-slate-400 dark:text-slate-500 transition-all duration-200"></i>
            </span>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   class="input-interactive w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none transition-all duration-200"
                   placeholder="••••••••" />
            <button type="button" onclick="togglePasswordVisibility()" 
                    tabindex="-1"
                    title="Lihat / Sembunyikan Kata Sandi"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 active:scale-75 transition-all duration-200 cursor-pointer">
              <i id="togglePasswordIcon" class="ph ph-eye text-base transition-transform duration-200"></i>
            </button>
          </div>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-1">
          <label class="flex items-center gap-2 cursor-pointer select-none group">
            <input type="checkbox" name="remember" class="w-4 h-4 rounded text-[#6558f5] focus:ring-[#6558f5] border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 transition cursor-pointer">
            <span class="text-xs text-slate-600 dark:text-slate-400 font-medium group-hover:text-slate-900 dark:group-hover:text-slate-200 transition">Ingat saya</span>
          </label>
        </div>

        <!-- Submit Button with Tactile Press & Loading State -->
        <button type="submit" id="submitBtn"
                class="w-full mt-2 py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-[0.98] text-white font-bold text-sm shadow-md shadow-[#6558f5]/25 hover:shadow-xl hover:shadow-[#6558f5]/35 hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer">
          <span id="submitBtnText">Masuk ke Sistem</span>
          <i id="submitBtnIcon" class="ph-bold ph-arrow-right text-base transition-transform group-hover:translate-x-1"></i>
        </button>

      </form>

    </div>

    <!-- Security & Status Footer with Subtle Radar Pulse -->
    <div class="mt-6 text-center">
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/70 dark:bg-[#131b2e]/70 backdrop-blur-md text-[11px] font-medium text-slate-500 dark:text-slate-400 border border-slate-200/60 dark:border-slate-800 shadow-sm">
        <span class="pulse-radar-wrap w-2 h-2">
          <span class="pulse-radar-ring"></span>
          <span class="relative inline-flex rounded-full w-2 h-2 bg-emerald-500"></span>
        </span>
        <span><?= sanitize($appName) ?> • <?= APP_VERSION ?></span>
      </div>
    </div>

  </div>

  <!-- Help Modal with Backdrop Blur -->
  <div id="helpModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden flex items-center justify-center p-4 transition-opacity">
    <div class="glass-card rounded-3xl max-w-sm w-full p-6 shadow-2xl animate-pop">
      <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-2xl mb-4 shadow-sm">
        <i class="ph-bold ph-key"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white">Bantuan Akses Akun</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
        Sesuai regulasi keamanan operasional, permohonan reset kata sandi atau pembuatan akun baru dilakukan melalui <b>Super Administrator</b> atau tim IT Administrasi setempat.
      </p>
      <button type="button" onclick="closeHelpModal()"
              class="w-full mt-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-bold text-xs shadow-md shadow-[#6558f5]/25 transition-all cursor-pointer">
        Tutup
      </button>
    </div>
  </div>

  <script>
    function toggleTheme() {
      const html = document.documentElement;
      const isDark = html.classList.contains('dark');
      
      // Animate icon rotation
      const icons = document.querySelectorAll('.theme-spin-icon');
      icons.forEach(icon => {
        icon.style.transform = 'rotate(180deg)';
        setTimeout(() => icon.style.transform = '', 350);
      });

      if (isDark) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
      }
    }

    function togglePasswordVisibility() {
      const passInput = document.getElementById('password');
      const toggleIcon = document.getElementById('togglePasswordIcon');
      
      toggleIcon.style.transform = 'scale(0.7) rotate(90deg)';
      setTimeout(() => {
        if (passInput.type === 'password') {
          passInput.type = 'text';
          toggleIcon.classList.remove('ph-eye');
          toggleIcon.classList.add('ph-eye-slash');
        } else {
          passInput.type = 'password';
          toggleIcon.classList.remove('ph-eye-slash');
          toggleIcon.classList.add('ph-eye');
        }
        toggleIcon.style.transform = 'scale(1) rotate(0deg)';
      }, 120);
    }

    function handleLoginSubmit(e) {
      const btn = document.getElementById('submitBtn');
      const text = document.getElementById('submitBtnText');
      const icon = document.getElementById('submitBtnIcon');

      // Loading state micro-interaction
      btn.disabled = true;
      btn.classList.add('opacity-90', 'cursor-wait');
      text.textContent = 'Memverifikasi...';
      icon.className = 'ph-bold ph-spinner animate-spin text-base';
    }

    function showHelpModal() {
      document.getElementById('helpModal').classList.remove('hidden');
    }

    function closeHelpModal() {
      document.getElementById('helpModal').classList.add('hidden');
    }

    document.getElementById('helpModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeHelpModal();
      }
    });
  </script>
</body>
</html>
