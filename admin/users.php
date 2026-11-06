<?php
/**
 * Manajemen Akun Pengurus / Admin (Khusus Super Admin / Ketua BKRC)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Proteksi: Khusus Super Admin
requireSuperAdmin();

// Handle Tambah / Edit / Hapus Pengurus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.');
        header('Location: ' . getBaseUrl() . '/admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId === (int)$currentUser['id']) {
            setFlash('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delId]);
            setFlash('success', 'Akun pengurus berhasil dihapus.');
        }
        header('Location: ' . getBaseUrl() . '/admin/users.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email)) {
        setFlash('error', 'Nama dan email wajib diisi!');
    } else {
        if ($action === 'create') {
            if (empty($password)) {
                setFlash('error', 'Kata sandi wajib diisi untuk akun baru!');
            } else {
                // Check email
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    setFlash('error', 'Email sudah digunakan!');
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $role, $phone]);
                    setFlash('success', "Akun pengurus baru '{$name}' berhasil dibuat.");
                }
            }
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hash, $role, $phone, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $phone, $id]);
            }
            setFlash('success', 'Akun pengurus berhasil diperbarui.');
        }
        header('Location: ' . getBaseUrl() . '/admin/users.php');
        exit;
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC")->fetchAll();

$pageTitle = 'Akun Pengurus';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ modalOpen: false, isEdit: false, form: { id: '', name: '', email: '', role: 'admin', phone: '', password: '' } }" @keydown.escape.window="modalOpen = false" class="flex-1 flex flex-col w-full">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 h-20 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-3">
            <button @click="sidebarOpen = true" 
                    type="button" 
                    class="lg:hidden text-slate-600 hover:text-slate-900 min-h-[44px] min-w-[44px] -ml-2 inline-flex items-center justify-center p-2 rounded-xl hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" 
                    aria-label="Buka Menu Navigasi">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Kelola Akun Pengurus</h1>
            </div>
        </div>

        <button @click="isEdit = false; form = { id: '', name: '', email: '', role: 'admin', phone: '', password: '' }; modalOpen = true" class="inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
            <span>Tambah Akun</span>
        </button>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5">
        <?php if ($flash = getFlash('success')): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash = getFlash('error')): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Nama Pengurus</th>
                            <th class="py-3 px-4">Email Login</th>
                            <th class="py-3 px-4">No. HP</th>
                            <th class="py-3 px-4">Hak Akses (Role)</th>
                            <th class="py-3 px-4">Terdaftar</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 font-bold text-slate-900 flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-slate-800 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <span><?= htmlspecialchars($u['name']) ?></span>
                                    <?php if ($u['id'] === $currentUser['id']): ?>
                                        <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-200 font-medium">(Anda)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-700 whitespace-nowrap"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="py-3.5 px-4 text-xs whitespace-nowrap"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if ($u['role'] === 'superadmin'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/60">
                                            <i class="fa-solid fa-crown text-[10px]"></i> Super Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                            Admin Pengurus
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600 whitespace-nowrap"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button @click="isEdit = true; form = { id: '<?= $u['id'] ?>', name: '<?= addslashes($u['name']) ?>', email: '<?= addslashes($u['email']) ?>', role: '<?= $u['role'] ?>', phone: '<?= addslashes($u['phone'] ?? '') ?>', password: '' }; modalOpen = true" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-semibold transition" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <?php if ($u['id'] !== $currentUser['id']): ?>
                                            <form action="" method="POST" class="inline m-0 p-0" onsubmit="return confirm('Hapus akun pengurus ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition" title="Hapus">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form Akun -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Akun Pengurus' : 'Tambah Akun Pengurus Baru'"></h3>
                <button @click="modalOpen = false" class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap Pengurus <span class="text-rose-600">*</span></label>
                    <input type="text" name="name" x-model="form.name" required placeholder="Contoh: Ahmad Fauzi" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Login <span class="text-rose-600">*</span></label>
                    <input type="email" name="email" x-model="form.email" required placeholder="pengurus@bkrc.id" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp</label>
                    <input type="text" name="phone" x-model="form.phone" placeholder="08..." class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Hak Akses (Role)</label>
                    <select name="role" x-model="form.role" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                        <option value="admin">Admin Pengurus</option>
                        <option value="superadmin">Super Admin / Ketua BKRC</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kata Sandi</label>
                    <input type="password" name="password" x-model="form.password" placeholder="Kosongkan jika tidak ingin mengubah kata sandi" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
