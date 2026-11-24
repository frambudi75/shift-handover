<?php
// auth/login.php - Login Page with Superadmin, Admin, and User support
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
        $error = 'Username dan password wajib diisi!';
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
            $error = 'Username atau password tidak sesuai!';
        }
    }
}

$flashes = flash_get();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Masuk — <?= APP_FULL_NAME ?></title>
  <script>
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
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
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen w-full bg-[#f8fafc] dark:bg-[#0b0f19] flex items-center justify-center p-4 font-sans transition-colors duration-200">

  <!-- Floating Theme Switcher -->
  <button onclick="toggleTheme()" type="button" 
          class="fixed top-5 right-5 p-3 rounded-2xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 shadow-md hover:scale-105 transition">
    <i class="ph-bold ph-moon text-xl dark:hidden"></i>
    <i class="ph-bold ph-sun text-xl hidden dark:inline text-amber-300"></i>
  </button>

  <div class="w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl p-7 sm:p-9 border border-slate-200/80 dark:border-slate-800">
    
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#6558f5] text-white shadow-lg shadow-indigo-500/30 mb-3 overflow-hidden p-2">
        <?php $loginLogo = get_app_logo_url(); ?>
        <?php if ($loginLogo): ?>
          <img src="<?= $loginLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
        <?php else: ?>
          <i class="ph-fill ph-clipboard-text text-3xl"></i>
        <?php endif; ?>
      </div>
      <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight"><?= sanitize(get_app_name()) ?></h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium"><?= sanitize(get_app_full_name()) ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-center gap-2">
        <i class="ph-bold ph-warning-circle text-lg flex-shrink-0"></i>
        <span><?= $error ?></span>
      </div>
    <?php endif; ?>

    <?php if (isset($flashes['success'])): ?>
      <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm flex items-center gap-2">
        <i class="ph-bold ph-check-circle text-lg flex-shrink-0"></i>
        <span><?= $flashes['success'] ?></span>
      </div>
    <?php endif; ?>

    <?php if (isset($flashes['error'])): ?>
      <div class="mb-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-center gap-2">
        <i class="ph-bold ph-x-circle text-lg flex-shrink-0"></i>
        <span><?= $flashes['error'] ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Username</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="ph ph-user text-lg"></i>
          </span>
          <input type="text" name="username" id="username" required
                 class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[#6558f5] transition"
                 placeholder="Masukkan username" />
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="ph ph-lock text-lg"></i>
          </span>
          <input type="password" name="password" id="password" required
                 class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[#6558f5] transition"
                 placeholder="••••••••" />
        </div>
      </div>

      <button type="submit"
              class="w-full py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-sm shadow-lg shadow-indigo-500/25 transition flex items-center justify-center gap-2 mt-2">
        <span>Masuk ke Sistem</span>
        <i class="ph-bold ph-arrow-right"></i>
      </button>
    </form>

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
  </script>
</body>
</html>
