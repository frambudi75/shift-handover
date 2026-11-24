<?php
// auth/login.php - Clean, Professional & Simple Login Template
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan kata sandi wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND status = 'aktif' LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['jabatan']      = $user['jabatan'];

            flash_set('success', "Selamat datang kembali, {$user['nama_lengkap']}!");
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit;
        } else {
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
  <script>
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
          colors: {
            brand: {
              purple: '#6558f5',
              hover: '#5244e8'
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen w-full bg-[#f8fafc] dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex items-center justify-center p-4 sm:p-6 font-sans transition-colors duration-200 relative selection:bg-[#6558f5] selection:text-white">

  <!-- Subtle Ambient Glow -->
  <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-80 bg-gradient-to-b from-[#6558f5]/10 via-[#6558f5]/5 to-transparent blur-3xl pointer-events-none"></div>

  <!-- Floating Dark/Light Toggle -->
  <button onclick="toggleTheme()" type="button" 
          aria-label="Ganti Tema"
          class="fixed top-5 right-5 w-10 h-10 rounded-full bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 shadow-sm hover:shadow hover:scale-105 active:scale-95 transition flex items-center justify-center cursor-pointer z-50">
    <i class="ph-bold ph-moon text-lg dark:hidden"></i>
    <i class="ph-bold ph-sun text-lg hidden dark:inline text-amber-400"></i>
  </button>

  <!-- Login Card Container -->
  <div class="w-full max-w-[420px] relative z-10">

    <!-- Brand Header -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-[22px] bg-[#6558f5] text-white shadow-lg shadow-[#6558f5]/25 mb-4 p-3.5 transition hover:scale-105">
        <?php if ($appLogo): ?>
          <img src="<?= $appLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
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

    <!-- Main Card -->
    <div class="bg-white dark:bg-[#131b2e] rounded-[28px] shadow-xl shadow-slate-200/60 dark:shadow-black/50 border border-slate-200/80 dark:border-slate-800 p-7 sm:p-9 transition-colors">
      
      <!-- Card Title -->
      <div class="mb-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Masuk ke Akun</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gunakan akun operasional resmi untuk melanjutkan.</p>
      </div>

      <!-- Feedback Alerts -->
      <?php if (!empty($error)): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5">
          <i class="ph-bold ph-warning-circle text-base flex-shrink-0 mt-0.5 text-rose-500"></i>
          <span class="font-medium leading-relaxed"><?= $error ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($flashes['success'])): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 text-xs flex items-start gap-2.5">
          <i class="ph-bold ph-check-circle text-base flex-shrink-0 mt-0.5 text-emerald-500"></i>
          <span class="font-medium leading-relaxed"><?= $flashes['success'] ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($flashes['error'])): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5">
          <i class="ph-bold ph-x-circle text-base flex-shrink-0 mt-0.5 text-rose-500"></i>
          <span class="font-medium leading-relaxed"><?= $flashes['error'] ?></span>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="" class="space-y-4">
        
        <!-- Username Field -->
        <div>
          <label for="username" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
            Username
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <i class="ph ph-user text-base"></i>
            </span>
            <input type="text" name="username" id="username" required autofocus autocomplete="username"
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#6558f5] focus:border-transparent transition"
                   placeholder="Nama pengguna atau NIK" />
          </div>
        </div>

        <!-- Password Field -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label for="password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
              Kata Sandi
            </label>
            <button type="button" onclick="showHelpModal()" class="text-[11px] font-semibold text-slate-400 hover:text-[#6558f5] transition">
              Lupa sandi?
            </button>
          </div>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <i class="ph ph-lock-key text-base"></i>
            </span>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#6558f5] focus:border-transparent transition"
                   placeholder="••••••••" />
            <button type="button" onclick="togglePasswordVisibility()" 
                    tabindex="-1"
                    title="Lihat / Sembunyikan Kata Sandi"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
              <i id="togglePasswordIcon" class="ph ph-eye text-base"></i>
            </button>
          </div>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-1">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" name="remember" class="w-4 h-4 rounded text-[#6558f5] focus:ring-[#6558f5] border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 transition cursor-pointer">
            <span class="text-xs text-slate-600 dark:text-slate-400 font-medium">Ingat saya</span>
          </label>
        </div>

        <!-- Submit Button -->
        <button type="submit"
                class="w-full mt-2 py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-[#6558f5]/20 hover:shadow-lg hover:shadow-[#6558f5]/30 transition-all flex items-center justify-center gap-2 cursor-pointer">
          <span>Masuk ke Sistem</span>
          <i class="ph-bold ph-arrow-right"></i>
        </button>

      </form>

    </div>

    <!-- Security & Status Footer -->
    <div class="mt-6 text-center">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200/50 dark:bg-slate-800/50 text-[11px] font-medium text-slate-500 dark:text-slate-400 border border-slate-200/50 dark:border-slate-700/50">
        <i class="ph-fill ph-shield-check text-emerald-500"></i>
        <span>SOP Shift Handover • Akses Terenkripsi</span>
      </div>
    </div>

  </div>

  <!-- Help Modal -->
  <div id="helpModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#131b2e] rounded-3xl max-w-sm w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl">
      <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] flex items-center justify-center text-xl mb-3.5">
        <i class="ph-bold ph-key"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white">Bantuan Akses Akun</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
        Sesuai regulasi keamanan operasional, permohonan reset kata sandi atau pembuatan akun baru dilakukan melalui <b>Super Administrator</b> atau tim IT Administrasi.
      </p>
      <button type="button" onclick="closeHelpModal()"
              class="w-full mt-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-bold text-xs transition cursor-pointer">
        Tutup
      </button>
    </div>
  </div>

  <script>
    function toggleTheme() {
      const html = document.documentElement;
      if (html.classList.contains('dark')) {
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
      if (passInput.type === 'password') {
        passInput.type = 'text';
        toggleIcon.classList.remove('ph-eye');
        toggleIcon.classList.add('ph-eye-slash');
      } else {
        passInput.type = 'password';
        toggleIcon.classList.remove('ph-eye-slash');
        toggleIcon.classList.add('ph-eye');
      }
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
