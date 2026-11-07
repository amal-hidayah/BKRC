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
                            red: { 700: '#991b1b', 800: '#8b1e1e', 900: '#731717', 950: '#450a0a' },
                            stone: { 50: '#faf8f5', 100: '#f5f1ea', 200: '#e8e2d7' }
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-stone-900 text-stone-100 min-h-screen flex items-center justify-center p-4 font-sans">

    <div class="w-full max-w-md relative z-10">
        <!-- Logo / Brand Header -->
        <div class="text-center mb-8">
            <a href="<?= getBaseUrl() ?>/index.php" class="inline-flex items-center gap-3">
                <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-14 h-14 object-contain">
            </a>
            <h1 class="text-2xl font-semibold text-white mt-4 tracking-tight">Portal pengurus BKRC</h1>
            <p class="text-xs text-stone-400 mt-1">Sistem informasi manajemen & transparansi relawan</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl p-7 sm:p-8 border border-stone-200/90 text-stone-900">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-stone-900 tracking-tight">Masuk akun pengurus</h2>
                <p class="text-xs text-stone-500 mt-0.5">Gunakan kredensial yang terdaftar untuk mengelola sistem.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-700 text-xs p-3.5 rounded-lg flex items-start gap-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 mt-0.5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-medium text-stone-700 mb-1.5">Email pengurus</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-stone-400 pointer-events-none">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="nama@bkrc.id" class="w-full pl-10 pr-4 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 placeholder:text-stone-400 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-stone-700 mb-1.5">Kata sandi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-stone-400 pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 placeholder:text-stone-400 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full min-h-[44px] bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-medium py-2.5 rounded-lg text-sm transition-colors flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
                        <span>Masuk ke dashboard</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </form>

            <!-- Hint Box for Assessors -->
            <div class="mt-6 pt-5 border-t border-stone-100 bg-stone-50 p-3 rounded-lg text-xs text-stone-600 space-y-1">
                <div class="font-medium text-stone-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-info text-stone-500"></i> Akun pengujian default:
                </div>
                <div>Super admin: <code class="bg-white px-1.5 py-0.5 rounded border border-stone-200 font-mono text-stone-800">admin@bkrc.id</code> (Ketua)</div>
                <div>Admin: <code class="bg-white px-1.5 py-0.5 rounded border border-stone-200 font-mono text-stone-800">bendahara@bkrc.id</code></div>
                <div>Password: <code class="bg-white px-1.5 py-0.5 rounded border border-stone-200 font-mono text-stone-800">admin123</code></div>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="<?= getBaseUrl() ?>/index.php" class="min-h-[44px] inline-flex items-center justify-center gap-1.5 text-xs text-stone-400 hover:text-stone-200 transition-colors py-2 px-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke portal publik BKRC
            </a>
        </div>
    </div>

</body>
</html>
