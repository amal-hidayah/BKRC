<?php
/**
 * Modul D.1: Manajemen Database Profil Donatur (CRUD)
 * Standar Keamanan: CSRF, Penyamaran Nomor HP (UU PDP), Validasi Anti-Orphan Audit
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Tambah / Edit Donatur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/donors.php');
        exit;
    }

    $action = $_POST['action'];
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $clusterOrAddress = trim($_POST['cluster_or_address'] ?? '');
    $donorType = $_POST['donor_type'] ?? 'insidental';
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if ($action === 'delete') {
        $delId = (int)$_POST['id'];
        // Cek apakah ada riwayat donasi
        $chk = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = ?");
        $chk->execute([$delId]);
        $hasDonations = (int)$chk->fetchColumn();

        if ($hasDonations > 0) {
            setFlash('error', "Gagal menghapus: Donatur memiliki {$hasDonations} riwayat transaksi kwitansi aktif. Hapus dibatalkan demi integritas pembukuan.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM donors WHERE id = ?");
            $stmt->execute([$delId]);
            setFlash('success', 'Data donatur berhasil dihapus.');
        }
        header('Location: ' . getBaseUrl() . '/admin/donors.php');
        exit;
    }

    if (empty($fullName) || empty($clusterOrAddress)) {
        setFlash('error', 'Nama donatur dan alamat/cluster wajib diisi!');
    } else {
        if ($action === 'create') {
            $stmtLast = $pdo->query("SELECT donor_code FROM donors WHERE donor_code LIKE 'DNR-%' ORDER BY id DESC LIMIT 1");
            $lastCode = $stmtLast->fetchColumn();
            if ($lastCode) {
                $num = (int)substr($lastCode, 4);
                $newCode = 'DNR-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newCode = 'DNR-001';
            }

            $stmt = $pdo->prepare("
                INSERT INTO donors (donor_code, full_name, phone, cluster_or_address, donor_type, is_anonymous)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$newCode, $fullName, $phone, $clusterOrAddress, $donorType, $isAnonymous]);
            setFlash('success', "Donatur baru berhasil didaftarkan: {$fullName} ({$newCode})");
        } elseif ($action === 'update') {
            $donorId = (int)$_POST['id'];
            $stmt = $pdo->prepare("
                UPDATE donors 
                SET full_name = ?, phone = ?, cluster_or_address = ?, donor_type = ?, is_anonymous = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $phone, $clusterOrAddress, $donorType, $isAnonymous, $donorId]);
            setFlash('success', 'Profil donatur berhasil diperbarui.');
        }
        header('Location: ' . getBaseUrl() . '/admin/donors.php');
        exit;
    }
}

// Data Donatur + Agregasi Total Donasi (Hanya donasi approved yang diagregasikan)
$search = trim($_GET['q'] ?? '');
$query = "
    SELECT dn.*, 
        COALESCE(SUM(CASE WHEN d.type = 'uang' AND d.status = 'approved' THEN d.amount ELSE 0 END), 0) as total_cash_donated,
        COALESCE(SUM(CASE WHEN d.type = 'sembako' AND d.status = 'approved' THEN d.package_qty ELSE 0 END), 0) as total_packages_donated,
        COUNT(CASE WHEN d.status = 'approved' THEN d.id ELSE NULL END) as donation_count
    FROM donors dn
    LEFT JOIN donations d ON dn.id = d.donor_id
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $query .= " AND (dn.full_name LIKE ? OR dn.donor_code LIKE ? OR dn.cluster_or_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " GROUP BY dn.id ORDER BY dn.id ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$donors = $stmt->fetchAll();

$pageTitle = 'Database Donatur';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: false, 
    deleteModalOpen: false,
    showPhones: {},
    isEdit: false, 
    form: { id: '', full_name: '', phone: '', cluster_or_address: '', donor_type: 'rutin', is_anonymous: false },
    deleteForm: { id: '', name: '', count: 0 }
}" class="flex-1 flex flex-col w-full">
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Data Donatur</h1>
            </div>
        </div>

        <button @click="isEdit = false; form = { id: '', full_name: '', phone: '', cluster_or_address: '', donor_type: 'rutin', is_anonymous: false }; modalOpen = true" class="min-h-[44px] inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
            <span>Tambah Donatur</span>
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
                <i class="fa-solid fa-circle-xmark text-rose-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search Bar & Summary -->
        <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs sm:text-sm text-slate-600 px-1 font-medium">
                Total Terdata: <strong class="text-slate-900"><?= count($donors) ?> Donatur</strong>
            </div>
            <form method="GET" class="w-full sm:w-80 relative flex items-center">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, kode, atau perumahan..." class="w-full pl-9 pr-3 py-2 min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
            </form>
        </div>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Kode</th>
                            <th class="py-3 px-4">Nama Donatur</th>
                            <th class="py-3 px-4 whitespace-nowrap">Kontak (PDP)</th>
                            <th class="py-3 px-4">Cluster / Alamat</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tipe Partisipasi</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Total Donasi</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($donors)): ?>
                            <tr><td colspan="7" class="py-12 text-center text-slate-400">Belum ada donatur terdaftar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($donors as $dn): ?>
                                <?php
                                $dTypeCalculated = calculateDonorType($dn['donation_count'], $dn['full_name'], $dn['cluster_or_address']);
                                $rawPhone = $dn['phone'] ?? '';
                                $maskedPhone = maskPhone($rawPhone);
                                $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <!-- Kode Donatur -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="tabular-nums text-xs bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200 text-slate-700 inline-block font-semibold">
                                            <?= htmlspecialchars($dn['donor_code']) ?>
                                        </span>
                                    </td>

                                    <!-- Nama & Label Anonim -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($dn['full_name']) ?></div>
                                        <?php if ($dn['is_anonymous']): ?>
                                            <span class="inline-flex items-center text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded mt-0.5 border border-slate-200">
                                                Publik: Hamba Allah (Anonim)
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kontak (UU PDP Masked dengan Toggle Reveal) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($rawPhone): ?>
                                            <div class="inline-flex items-center gap-1.5" x-data="{ revealed: false }">
                                                <span class="tabular-nums font-mono text-xs text-slate-700" x-text="revealed ? '<?= htmlspecialchars($rawPhone) ?>' : '<?= htmlspecialchars($maskedPhone) ?>'"></span>
                                                <button @click="revealed = !revealed" type="button" class="text-slate-400 hover:text-slate-700 p-1 rounded transition" :title="revealed ? 'Sembunyikan' : 'Buka Penyamaran'">
                                                    <i class="fa-solid" :class="revealed ? 'fa-eye-slash' : 'fa-eye'"></i>
                                                </button>
                                                <a href="https://wa.me/<?= $cleanPhone ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 ml-1" title="Kirim Pesan WhatsApp">
                                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400">–</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Alamat Cluster -->
                                    <td class="py-3.5 px-4 text-xs text-slate-700 font-medium"><?= htmlspecialchars($dn['cluster_or_address']) ?></td>

                                    <!-- Tipe Donatur (Diturunkan dari Riwayat Transaksi) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($dTypeCalculated === 'Rutin Bulanan'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Rutin Bulanan
                                            </span>
                                        <?php elseif ($dTypeCalculated === 'Sponsor Lembaga'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Sponsor Lembaga
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Insidental
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Total Donasi (Rata Kanan & Tabular Nums) -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="font-bold text-slate-900 text-sm tabular-nums">
                                            <?= formatRupiah($dn['total_cash_donated'], true) ?>
                                        </div>
                                        <?php if ($dn['total_packages_donated'] > 0): ?>
                                            <div class="text-xs font-semibold text-amber-700 tabular-nums"><?= $dn['total_packages_donated'] ?> Paket Sembako</div>
                                        <?php endif; ?>
                                        <div class="text-[11px] text-slate-400 tabular-nums"><?= $dn['donation_count'] ?>x donasi</div>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button @click="isEdit = true; form = { id: '<?= $dn['id'] ?>', full_name: '<?= addslashes($dn['full_name']) ?>', phone: '<?= addslashes($dn['phone']) ?>', cluster_or_address: '<?= addslashes($dn['cluster_or_address']) ?>', donor_type: '<?= $dn['donor_type'] ?>', is_anonymous: <?= $dn['is_anonymous'] ? 'true' : 'false' ?> }; modalOpen = true" class="px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 text-xs font-semibold transition" title="Edit">
                                                <i class="fa-solid fa-pen-to-square text-slate-400"></i> Edit
                                            </button>

                                            <?php if ($dn['donation_count'] > 0): ?>
                                                <button @click="deleteForm = { id: '<?= $dn['id'] ?>', name: '<?= addslashes($dn['full_name']) ?>', count: <?= $dn['donation_count'] ?> }; deleteModalOpen = true" class="px-2 py-1.5 rounded-lg text-slate-400 hover:text-slate-600 bg-slate-50 border border-slate-200 text-xs transition" title="Informasi Penghapusan">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            <?php else: ?>
                                                <button @click="deleteForm = { id: '<?= $dn['id'] ?>', name: '<?= addslashes($dn['full_name']) ?>', count: 0 }; deleteModalOpen = true" class="px-2 py-1.5 rounded-lg text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs transition" title="Hapus Donatur">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form Donatur -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="modalOpen = false">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Profil Donatur' : 'Daftarkan Donatur Baru'"></h3>
                <button @click="modalOpen = false" type="button" aria-label="Tutup form" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap Donatur <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" x-model="form.full_name" required placeholder="Contoh: Ir. H. Gunawan Wibisono" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp / HP</label>
                    <input type="text" name="phone" x-model="form.phone" placeholder="Contoh: 081234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Cluster / Blok / Alamat <span class="text-rose-500">*</span></label>
                    <input type="text" name="cluster_or_address" x-model="form.cluster_or_address" required placeholder="Contoh: Cluster Gardenia Blok A1/02" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tipe Partisipasi</label>
                    <select name="donor_type" x-model="form.donor_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="rutin">Rutin Bulanan</option>
                        <option value="insidental">Insidental</option>
                        <option value="sponsor">Sponsor Lembaga / Usaha</option>
                    </select>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="is_anonymous" x-model="form.is_anonymous" class="rounded border-slate-300 text-bkrc-red-800 focus:ring-bkrc-red-800 w-4 h-4">
                        <span class="text-xs font-medium text-slate-700">Tampilkan sebagai <strong>Hamba Allah</strong> di papan publik</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button @click="modalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                        Simpan Donatur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Donatur -->
    <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="deleteModalOpen = false">
        <div @click.away="deleteModalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <template x-if="deleteForm.count > 0">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">Donatur Tidak Dapat Dihapus</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Donatur <strong class="text-slate-900" x-text="deleteForm.name"></strong> memiliki <strong class="text-slate-900" x-text="deleteForm.count"></strong> riwayat donasi tercatat. Data donatur diperlukan untuk menjaga keabsahan kwitansi dan audit trail pembukuan.
                    </p>
                    <div class="flex justify-end">
                        <button @click="deleteModalOpen = false" type="button" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-black transition">
                            Tutup
                        </button>
                    </div>
                </div>
            </template>
            <template x-if="deleteForm.count === 0">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">Hapus Profil Donatur</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Apakah Anda yakin ingin menghapus data donatur <strong class="text-slate-900" x-text="deleteForm.name"></strong>? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <form action="" method="POST" class="flex items-center justify-end gap-2.5">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="id" :value="deleteForm.id">
                        <button @click="deleteModalOpen = false" type="button" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition">
                            Hapus
                        </button>
                    </form>
                </div>
            </template>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
