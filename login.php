<?php
/**
 * Halaman Login Pengurus / Admin BKRC
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Jika sudah login, redirect langsung ke admin
if (isLoggedIn()) {
    header('Location: ' . getBaseUrl() . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login Berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: ' . getBaseUrl() . '/admin/index.php');
            exit;
        } else {
            $error = 'Email atau kata sandi tidak cocok. Silakan periksa kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pengurus — Bantuan Kemanusiaan Relawan Cluster (BKRC)</title>
    <link rel="icon" type="image/png" href="<?= getBaseUrl() ?>/assets/img/logo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        bkrc: {
                            red: { 700: '#b91c1c', 800: '#991b1b', 900: '#7f1d1d', 950: '#450a0a' },
                            gold: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' }
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-800 min-h-screen flex items-center justify-center p-4 font-sans">

    <div class="w-full max-w-md relative z-10">
        <!-- Logo / Brand Header -->
        <div class="text-center mb-8">
            <a href="<?= getBaseUrl() ?>/index.php" class="inline-flex items-center gap-3 group">
                <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-16 h-16 object-contain group-hover:scale-105 transition drop-shadow-md">
            </a>
            <h1 class="text-2xl font-extrabold text-white mt-4 tracking-normal">Portal Pengurus BKRC</h1>
            <p class="text-xs text-slate-300 mt-1">Sistem Informasi Manajemen & Operasional Relawan</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 border border-slate-100">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Masuk Akun Pengurus</h2>
                <p class="text-xs text-slate-500">Gunakan kredensial yang terdaftar untuk mengelola database.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-700 text-xs p-3.5 rounded-xl flex items-start gap-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 mt-0.5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Pengurus</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 pointer-events-none">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="nama@bkrc.id" class="w-full pl-10 pr-4 py-3 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm placeholder:text-slate-500 text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-3 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm placeholder:text-slate-500 text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full min-h-[48px] bg-red-800 hover:bg-red-900 active:bg-red-950 text-white font-bold py-3 rounded-xl text-sm shadow-sm transition-all flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
                        <i class="fa-solid fa-right-to-bracket text-amber-400"></i> Masuk ke Dashboard
                    </button>
                </div>
            </form>

            <!-- Demo Hint Box for Examiners / Assessors -->
            <div class="mt-6 pt-5 border-t border-slate-100 bg-slate-50 p-3.5 rounded-xl text-xs text-slate-700 space-y-1">
                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-info text-amber-600"></i> Akun Pengujian Default:
                </div>
                <div>Super Admin: <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200 font-mono text-slate-900 font-semibold">admin@bkrc.id</code> (Ketua)</div>
                <div>Admin: <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200 font-mono text-slate-900 font-semibold">bendahara@bkrc.id</code></div>
                <div>Password: <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200 font-mono text-slate-900 font-semibold">admin123</code></div>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="<?= getBaseUrl() ?>/index.php" class="min-h-[44px] inline-flex items-center justify-center gap-1.5 text-xs text-slate-300 hover:text-white transition py-2 px-3 rounded-lg focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Portal Publik BKRC
            </a>
        </div>
    </div>

</body>
</html>
