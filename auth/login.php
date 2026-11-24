<?php
// auth/login.php - Professional NoteFlow Login Page with SOP OMIT Branding
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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
              hover: '#5244e8',
              surface: '#f4f5fa'
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen w-full bg-[#f4f5fa] dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-10 font-sans transition-colors duration-300 relative overflow-x-hidden">

  <!-- Ambient Background Accents -->
  <div class="fixed top-[-10%] right-[-10%] w-[550px] h-[550px] rounded-full bg-[#6558f5]/10 dark:bg-[#6558f5]/15 blur-[120px] pointer-events-none"></div>
  <div class="fixed bottom-[-10%] left-[-10%] w-[550px] h-[550px] rounded-full bg-indigo-500/10 dark:bg-indigo-600/15 blur-[120px] pointer-events-none"></div>

  <!-- Main Card Container: Form on the Left (lg:col-span-7), Hero Banner on the Right (lg:col-span-5) -->
  <div class="w-full max-w-5xl bg-white dark:bg-[#131b2e] rounded-[28px] sm:rounded-[36px] shadow-2xl shadow-slate-200/50 dark:shadow-black/50 border border-slate-200/80 dark:border-slate-800/80 overflow-hidden grid grid-cols-1 lg:grid-cols-12 relative z-10 transition-all">

    <!-- LEFT COLUMN: Authentication Form (7 cols on desktop) -->
    <div class="lg:col-span-7 p-6 sm:p-10 lg:p-12 flex flex-col justify-between bg-white dark:bg-[#131b2e] transition-colors order-1">
      
      <div>
        <!-- Top Toolbar: Brand & Theme Switcher -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-[#6558f5] flex items-center justify-center text-white p-2 shadow-md shadow-[#6558f5]/25 flex-shrink-0">
              <?php if ($appLogo): ?>
                <img src="<?= $appLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
              <?php else: ?>
                <i class="ph-fill ph-clipboard-text text-xl"></i>
              <?php endif; ?>
            </div>
            <div>
              <h2 class="text-base font-extrabold text-slate-900 dark:text-white leading-tight tracking-tight"><?= sanitize($appName) ?></h2>
              <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#6558f5] dark:text-indigo-400">
                <i class="ph-bold ph-shield-check"></i> SOP Shift Handover
              </span>
            </div>
          </div>

          <!-- Theme Toggle Button -->
          <button onclick="toggleTheme()" type="button" 
                  aria-label="Ganti Mode Tampilan"
                  class="p-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-2 border border-slate-200/80 dark:border-slate-700/80">
            <i class="ph-bold ph-moon text-base dark:hidden"></i>
            <i class="ph-bold ph-sun text-base hidden dark:inline text-amber-400"></i>
            <span class="text-xs font-semibold hidden sm:inline">Mode</span>
          </button>
        </div>

        <!-- Form Heading -->
        <div class="mb-7">
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            Selamat Datang!
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
            Silakan masukkan kredensial akun terdaftar untuk memulai atau melanjutkan laporan serah terima shift Anda.
          </p>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if (!empty($error)): ?>
          <div class="mb-5 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-start gap-3 shadow-sm">
            <i class="ph-bold ph-warning-circle text-lg flex-shrink-0 mt-0.5 text-rose-500"></i>
            <div class="flex-1 font-medium leading-relaxed"><?= $error ?></div>
          </div>
        <?php endif; ?>

        <?php if (isset($flashes['success'])): ?>
          <div class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm flex items-start gap-3 shadow-sm">
            <i class="ph-bold ph-check-circle text-lg flex-shrink-0 mt-0.5 text-emerald-500"></i>
            <div class="flex-1 font-medium leading-relaxed"><?= $flashes['success'] ?></div>
          </div>
        <?php endif; ?>

        <?php if (isset($flashes['error'])): ?>
          <div class="mb-5 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-start gap-3 shadow-sm">
            <i class="ph-bold ph-x-circle text-lg flex-shrink-0 mt-0.5 text-rose-500"></i>
            <div class="flex-1 font-medium leading-relaxed"><?= $flashes['error'] ?></div>
          </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="" class="space-y-4">
          
          <!-- Username Input -->
          <div>
            <label for="username" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
              Username / Akun Operasional <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                <i class="ph-bold ph-user text-lg"></i>
              </span>
              <input type="text" name="username" id="username" required autofocus autocomplete="username"
                     class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#6558f5] focus:border-transparent transition"
                     placeholder="Masukkan username atau NIK" />
            </div>
          </div>

          <!-- Password Input with Toggle -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label for="password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                Kata Sandi <span class="text-rose-500">*</span>
              </label>
              <button type="button" onclick="showHelpModal()" class="text-[11px] font-semibold text-slate-400 hover:text-[#6558f5] transition">
                Lupa sandi?
              </button>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                <i class="ph-bold ph-lock-key text-lg"></i>
              </span>
              <input type="password" name="password" id="password" required autocomplete="current-password"
                     class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#6558f5] focus:border-transparent transition"
                     placeholder="••••••••" />
              <button type="button" onclick="togglePasswordVisibility()" 
                      tabindex="-1"
                      title="Lihat / Sembunyikan Kata Sandi"
                      class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                <i id="togglePasswordIcon" class="ph-bold ph-eye text-lg"></i>
              </button>
            </div>
          </div>

          <!-- Remember & Security Info -->
          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2.5 cursor-pointer select-none">
              <input type="checkbox" name="remember" class="w-4 h-4 rounded-md text-[#6558f5] focus:ring-[#6558f5] border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 transition cursor-pointer">
              <span class="text-xs text-slate-600 dark:text-slate-400 font-medium">Ingat perangkat ini</span>
            </label>
            <span class="text-[11px] text-slate-400 flex items-center gap-1.5 font-medium">
              <i class="ph-fill ph-shield-check text-emerald-500"></i> Audit Terproteksi
            </span>
          </div>

          <!-- Submit CTA -->
          <button type="submit" id="submitBtn"
                  class="w-full mt-2 py-3.5 px-6 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-bold text-sm shadow-xl shadow-[#6558f5]/25 hover:shadow-[#6558f5]/40 hover:-translate-y-0.5 active:translate-y-0 transition-all flex items-center justify-center gap-2.5">
            <span>Masuk ke Sistem Operasional</span>
            <i class="ph-bold ph-arrow-right text-base"></i>
          </button>
        </form>

        <!-- Quick Demo Credentials Chips for Effortless Testing -->
        <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
              <i class="ph-bold ph-lightning text-amber-500"></i> Akun Cepat Pengujian:
            </span>
            <span class="text-[10px] text-slate-400 font-medium">Klik untuk otomatis mengisi</span>
          </div>

          <div class="grid grid-cols-3 gap-2 sm:gap-2.5">
            <button type="button" onclick="fillCreds('superadmin', 'password', this)"
                    class="cred-pill group p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-left hover:border-[#6558f5] dark:hover:border-[#6558f5] hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition cursor-pointer">
              <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-[#6558f5]">
                <i class="ph-fill ph-crown text-amber-500"></i> Superadmin
              </div>
              <div class="text-[10px] text-slate-400 font-mono mt-0.5">superadmin</div>
            </button>

            <button type="button" onclick="fillCreds('admin', 'password', this)"
                    class="cred-pill group p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-left hover:border-[#6558f5] dark:hover:border-[#6558f5] hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition cursor-pointer">
              <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-[#6558f5]">
                <i class="ph-fill ph-shield-check text-blue-500"></i> Admin
              </div>
              <div class="text-[10px] text-slate-400 font-mono mt-0.5">admin</div>
            </button>

            <button type="button" onclick="fillCreds('andi', 'password', this)"
                    class="cred-pill group p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-left hover:border-[#6558f5] dark:hover:border-[#6558f5] hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition cursor-pointer">
              <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-[#6558f5]">
                <i class="ph-fill ph-hard-hat text-emerald-500"></i> Operator
              </div>
              <div class="text-[10px] text-slate-400 font-mono mt-0.5">andi</div>
            </button>
          </div>
        </div>
      </div>

      <!-- Footer Info -->
      <div class="mt-8 pt-4 border-t border-slate-100 dark:border-slate-800 text-center flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-400 gap-2">
        <span>&copy; <?= date('Y') ?> <?= sanitize($appName) ?> • NoteFlow Standard</span>
        <span class="flex items-center gap-1.5">
          <i class="ph-fill ph-lock-simple text-emerald-500"></i> 256-Bit SSL Enkripsi Terverifikasi
        </span>
      </div>

    </div>

    <!-- RIGHT COLUMN: Brand Hero & Operational SOP Showcase (5 cols on desktop, hidden on small screens) -->
    <div class="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-[#5946ea] via-[#6558f5] to-[#7f73fc] text-white p-8 sm:p-10 flex-col justify-between relative overflow-hidden order-2">
      
      <!-- Ambient Decorative Rings -->
      <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full border border-white/10 pointer-events-none"></div>
      <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full border border-white/15 pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>

      <!-- Hero Header -->
      <div class="relative z-10">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-[11px] font-bold uppercase tracking-wider text-white border border-white/20 mb-4">
          <i class="ph-bold ph-shield-check text-amber-300"></i> SOP OMIT Standard
        </div>

        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug">
          Standar Resmi Serah Terima Tugas Shift Operasional
        </h2>
        <p class="text-white/80 text-xs sm:text-sm mt-3 leading-relaxed font-normal">
          Dokumentasikan perpindahan tugas stasiun kerja secara real-time, validasi e-sign digital, dan eskalasikan tiket kritis lintas shift tanpa kendala.
        </p>

        <!-- SOP Highlights List -->
        <div class="mt-8 space-y-3.5">
          <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 transition hover:bg-white/15">
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0 text-amber-300">
              <i class="ph-bold ph-signature text-lg"></i>
            </div>
            <div>
              <h3 class="text-xs font-bold text-white">Handover Digital & E-Sign</h3>
              <p class="text-[11px] text-white/75 leading-relaxed mt-0.5">Tanda tangan digital kanvas, verifikasi QR code instan, dan upload bukti fisik.</p>
            </div>
          </div>

          <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 transition hover:bg-white/15">
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0 text-emerald-300">
              <i class="ph-bold ph-arrows-clockwise text-lg"></i>
            </div>
            <div>
              <h3 class="text-xs font-bold text-white">Eskalasi Tiket Berkelanjutan</h3>
              <p class="text-[11px] text-white/75 leading-relaxed mt-0.5">Zero unhandled ticket dengan pemantauan otomatis pekerjaan kritis antar shift.</p>
            </div>
          </div>

          <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 transition hover:bg-white/15">
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0 text-cyan-300">
              <i class="ph-bold ph-shield-check text-lg"></i>
            </div>
            <div>
              <h3 class="text-xs font-bold text-white">Audit Trail & Redis Caching</h3>
              <p class="text-[11px] text-white/75 leading-relaxed mt-0.5">Akses secepat kilat dengan perlindungan log aktivitas berstandar kepatuhan.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Hero Footer Badge -->
      <div class="mt-8 pt-6 border-t border-white/15 flex items-center justify-between text-xs text-white/80 relative z-10">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="font-semibold text-[11px] tracking-wide">Sistem Aktif 24/7 • Rotasi Shift Aman</span>
        </div>
        <span class="text-[10px] font-mono text-white/60 bg-black/10 px-2 py-0.5 rounded-md border border-white/10">v2.4 LTS</span>
      </div>
    </div>

  </div>

  <!-- Help Modal -->
  <div id="helpModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#131b2e] rounded-3xl max-w-sm w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl">
      <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] flex items-center justify-center text-2xl mb-4">
        <i class="ph-bold ph-key"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white">Bantuan Reset Sandi Akun</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
        Sesuai regulasi keamanan SOP OMIT, pengaturan ulang kata sandi operasional dikelola langsung oleh <b>Super Administrator</b> atau tim IT Infrastruktur setempat.
      </p>
      <div class="mt-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 text-xs text-slate-600 dark:text-slate-300 font-medium">
        Silakan hubungi admin piket atau koordinasikan via grup WhatsApp resmi operasional stasiun kerja Anda.
      </div>
      <button type="button" onclick="closeHelpModal()"
              class="w-full mt-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-bold text-xs transition cursor-pointer">
        Saya Mengerti
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

    function fillCreds(username, password, btnElement) {
      const userInput = document.getElementById('username');
      const passInput = document.getElementById('password');
      
      userInput.value = username;
      passInput.value = password;
      
      // Visual feedback
      document.querySelectorAll('.cred-pill').forEach(el => {
        el.classList.remove('ring-2', 'ring-[#6558f5]', 'bg-indigo-50', 'dark:bg-indigo-950/40');
      });
      if (btnElement) {
        btnElement.classList.add('ring-2', 'ring-[#6558f5]', 'bg-indigo-50', 'dark:bg-indigo-950/40');
      }
      
      userInput.focus();
    }

    function showHelpModal() {
      document.getElementById('helpModal').classList.remove('hidden');
    }

    function closeHelpModal() {
      document.getElementById('helpModal').classList.add('hidden');
    }

    // Close modal on click outside
    document.getElementById('helpModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeHelpModal();
      }
    });
  </script>
</body>
</html>
