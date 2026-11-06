<?php
/**
 * Modul E: Database Penerima Santunan & Sembako (Penerima Manfaat)
 * Fitur: Penyamaran NIK (UU PDP), Validasi Anti-Duplikasi NIK, Algoritma Monitoring Batas Usia
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Tambah / Edit / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/beneficiaries.php');
        exit;
    }

    $action = $_POST['action'];
    $nik = trim($_POST['nik'] ?? '');
    $kkNumber = trim($_POST['kk_number'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $birthDate = $_POST['birth_date'] ?: '2015-01-01';
    $guardianName = trim($_POST['guardian_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $rtRw = trim($_POST['rt_rw'] ?? '');
    $category = $_POST['category'] ?? 'yatim';
    $status = $_POST['status'] ?? 'layak_aktif';
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'delete') {
        $delId = (int)$_POST['id'];
        // Cek apakah ada riwayat penyaluran
        $chk = $pdo->prepare("SELECT COUNT(*) FROM distribution_logs WHERE beneficiary_id = ?");
        $chk->execute([$delId]);
        $hasLogs = (int)$chk->fetchColumn();

        if ($hasLogs > 0) {
            setFlash('error', "Gagal menghapus: Penerima manfaat memiliki {$hasLogs} riwayat penyaluran santunan tercatat. Hapus dicegah demi integritas laporan pertanggungjawaban (LPJ).");
        } else {
            $stmt = $pdo->prepare("DELETE FROM beneficiaries WHERE id = ?");
            $stmt->execute([$delId]);
            setFlash('success', 'Data penerima manfaat berhasil dihapus.');
        }
        header('Location: ' . getBaseUrl() . '/admin/beneficiaries.php');
        exit;
    }

    if (empty($nik) || empty($fullName) || empty($address)) {
        setFlash('error', 'NIK, Nama Lengkap, dan Alamat wajib diisi!');
    } else {
        if ($action === 'create') {
            // Validasi Anti-Duplikasi NIK
            $checkNik = $pdo->prepare("SELECT id, full_name FROM beneficiaries WHERE nik = ? LIMIT 1");
            $checkNik->execute([$nik]);
            if ($existing = $checkNik->fetch()) {
                setFlash('error', "PERINGATAN: NIK {$nik} sudah terdaftar atas nama '{$existing['full_name']}'! Penyaluran ganda dicegah.");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO beneficiaries (nik, kk_number, full_name, birth_date, guardian_name, phone, address, rt_rw, category, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nik, $kkNumber, $fullName, $birthDate, $guardianName, $phone, $address, $rtRw, $category, $status, $notes]);
                setFlash('success', "Penerima manfaat baru berhasil didaftarkan: {$fullName}");
            }
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $checkNik = $pdo->prepare("SELECT id FROM beneficiaries WHERE nik = ? AND id != ? LIMIT 1");
            $checkNik->execute([$nik, $id]);
            if ($checkNik->fetch()) {
                setFlash('error', "NIK {$nik} sudah digunakan oleh penerima lain!");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE beneficiaries 
                    SET nik = ?, kk_number = ?, full_name = ?, birth_date = ?, guardian_name = ?, phone = ?, address = ?, rt_rw = ?, category = ?, status = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nik, $kkNumber, $fullName, $birthDate, $guardianName, $phone, $address, $rtRw, $category, $status, $notes, $id]);
                setFlash('success', 'Data penerima manfaat berhasil diperbarui.');
            }
        }
        header('Location: ' . getBaseUrl() . '/admin/beneficiaries.php');
        exit;
    }
}

// Filter & Pencarian
$categoryFilter = $_GET['kategori'] ?? 'semua';
$statusFilter = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$query = "
    SELECT b.*,
        (SELECT COUNT(*) FROM distribution_logs dl JOIN distribution_events de ON dl.event_id = de.id WHERE dl.beneficiary_id = b.id AND (dl.status = 'distributed' OR de.disbursement_status = 'tersalurkan')) as received_count,
        (SELECT MAX(COALESCE(dl.distributed_at, de.event_date)) FROM distribution_logs dl JOIN distribution_events de ON dl.event_id = de.id WHERE dl.beneficiary_id = b.id AND (dl.status = 'distributed' OR de.disbursement_status = 'tersalurkan')) as last_received_at,
        (SELECT COUNT(*) FROM distribution_logs dl WHERE dl.beneficiary_id = b.id) as log_count
    FROM beneficiaries b 
    WHERE 1=1
";
$params = [];

if ($categoryFilter !== 'semua') {
    $query .= " AND b.category = ?";
    $params[] = $categoryFilter;
}
if ($statusFilter !== 'semua') {
    $query .= " AND b.status = ?";
    $params[] = $statusFilter;
}
if (!empty($search)) {
    $query .= " AND (b.full_name LIKE ? OR b.nik LIKE ? OR b.address LIKE ? OR b.guardian_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY b.category ASC, b.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$beneficiaries = $stmt->fetchAll();

$pageTitle = 'Penerima Manfaat';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: <?= (isset($_GET['action']) && $_GET['action'] === 'create') ? 'true' : 'false' ?>, 
    deleteModalOpen: false,
    isEdit: false, 
    form: { id: '', nik: '', kk_number: '', full_name: '', birth_date: '2015-01-01', guardian_name: '', phone: '', address: '', rt_rw: 'RT 01 / RW 03', category: 'yatim', status: 'layak_aktif', notes: '' },
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Penerima Manfaat</h1>
            </div>
        </div>

        <button @click="isEdit = false; form = { id: '', nik: '', kk_number: '', full_name: '', birth_date: '2015-01-01', guardian_name: '', phone: '', address: '', rt_rw: 'RT 01 / RW 03', category: 'yatim', status: 'layak_aktif', notes: '' }; modalOpen = true" class="min-h-[44px] inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
            <span>Tambah Penerima</span>
        </button>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5">
        <!-- Flash Message -->
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

        <!-- Filter & Search Toolbar -->
        <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="inline-flex p-1 bg-slate-100 rounded-xl text-xs font-semibold flex-wrap gap-1 w-full md:w-auto">
                <a href="<?= getBaseUrl() ?>/admin/beneficiaries.php?kategori=semua&q=<?= urlencode($search) ?>" class="px-3.5 py-1.5 rounded-lg transition <?= $categoryFilter === 'semua' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Semua</a>
                <a href="<?= getBaseUrl() ?>/admin/beneficiaries.php?kategori=yatim&q=<?= urlencode($search) ?>" class="px-3.5 py-1.5 rounded-lg transition <?= $categoryFilter === 'yatim' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Anak Yatim</a>
                <a href="<?= getBaseUrl() ?>/admin/beneficiaries.php?kategori=sembako&q=<?= urlencode($search) ?>" class="px-3.5 py-1.5 rounded-lg transition <?= $categoryFilter === 'sembako' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Paket Sembako</a>
                <a href="<?= getBaseUrl() ?>/admin/beneficiaries.php?kategori=dhuafa&q=<?= urlencode($search) ?>" class="px-3.5 py-1.5 rounded-lg transition <?= $categoryFilter === 'dhuafa' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Dhuafa & Lansia</a>
            </div>

            <!-- Pencarian Cepat -->
            <form method="GET" class="w-full md:w-80 relative flex items-center">
                <input type="hidden" name="kategori" value="<?= htmlspecialchars($categoryFilter) ?>">
                <div class="relative w-full">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama penerima atau NIK..." class="w-full pl-9 pr-3 py-2 min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                </div>
            </form>
        </div>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Nama Penerima</th>
                            <th class="py-3 px-4 whitespace-nowrap">NIK (Privasi PDP)</th>
                            <th class="py-3 px-4 whitespace-nowrap">Kategori & Usia</th>
                            <th class="py-3 px-4">Alamat Domisili</th>
                            <th class="py-3 px-4 whitespace-nowrap">Riwayat Bantuan</th>
                            <th class="py-3 px-4 whitespace-nowrap">Status Kelayakan</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($beneficiaries)): ?>
                            <tr><td colspan="7" class="py-12 text-center text-slate-400">Belum ada data penerima manfaat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($beneficiaries as $b): ?>
                                <?php
                                $ageData = calculateBeneficiaryAge($b['birth_date']);
                                $isYatim = ($b['category'] === 'yatim');
                                $rawNik = $b['nik'];
                                $maskedNik = maskNik($rawNik);
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <!-- Nama Penerima & Wali -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($b['full_name']) ?></div>
                                        <?php if (!empty($b['guardian_name']) && $b['guardian_name'] !== '-'): ?>
                                             <div class="text-xs text-slate-500 mt-0.5">Wali: <?= htmlspecialchars($b['guardian_name']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- NIK (UU PDP: Disamarkan secara default dengan opsi toggle reveal) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap" x-data="{ revealed: false }">
                                        <div class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-700">
                                            <span class="tabular-nums font-semibold" x-text="revealed ? '<?= htmlspecialchars($rawNik) ?>' : '<?= htmlspecialchars($maskedNik) ?>'"></span>
                                            <button @click="revealed = !revealed" type="button" class="text-slate-400 hover:text-slate-700 p-1 rounded transition" :title="revealed ? 'Sembunyikan NIK' : 'Buka NIK Lengkap'">
                                                <i class="fa-solid" :class="revealed ? 'fa-eye-slash' : 'fa-eye'"></i>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Kategori & Usia (Tanpa duplikasi status) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            <?= ucfirst($b['category']) ?>
                                        </span>
                                        <?php if ($isYatim): ?>
                                            <div class="text-xs text-slate-500 mt-1 tabular-nums">
                                                <?= $ageData['text'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Alamat Domisili Bersih (Tanpa Duplikasi RT/RW) -->
                                    <td class="py-3.5 px-4 text-xs">
                                        <div class="text-slate-900 font-medium"><?= htmlspecialchars($b['address']) ?></div>
                                        <div class="text-slate-400 mt-0.5"><?= htmlspecialchars($b['rt_rw']) ?></div>
                                    </td>

                                    <!-- Riwayat Bantuan -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ((int)$b['received_count'] > 0): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <i class="fa-solid fa-hand-holding-heart text-emerald-600 text-[11px]"></i>
                                                <?= (int)$b['received_count'] ?>x Tersalurkan
                                            </span>
                                            <?php if (!empty($b['last_received_at'])): ?>
                                                <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                                                    <i class="fa-regular fa-clock text-[10px]"></i>
                                                    <span>Terakhir: <?= date('d/m/Y', strtotime($b['last_received_at'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                                <i class="fa-solid fa-clock-rotate-left text-slate-400 text-[10px]"></i>
                                                0x (Belum pernah)
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Tunggal Bersih -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($isYatim && $ageData['is_over_age']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200" title="Batas usia santunan anak yatim 15 tahun">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Evaluasi Usia (&gt;15 Thn)
                                            </span>
                                        <?php elseif ($b['status'] === 'layak_aktif'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Layak Aktif
                                            </span>
                                        <?php elseif ($b['status'] === 'lulus_usia'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Lulus Usia (Mandiri)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> <?= ucfirst($b['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button @click="isEdit = true; form = { id: '<?= $b['id'] ?>', nik: '<?= addslashes($b['nik']) ?>', kk_number: '<?= addslashes($b['kk_number'] ?? '') ?>', full_name: '<?= addslashes($b['full_name']) ?>', birth_date: '<?= $b['birth_date'] ?>', guardian_name: '<?= addslashes($b['guardian_name'] ?? '') ?>', phone: '<?= addslashes($b['phone'] ?? '') ?>', address: '<?= addslashes($b['address']) ?>', rt_rw: '<?= addslashes($b['rt_rw']) ?>', category: '<?= $b['category'] ?>', status: '<?= $b['status'] ?>', notes: '<?= addslashes($b['notes'] ?? '') ?>' }; modalOpen = true" class="px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 text-xs font-semibold transition" title="Edit Data">
                                                <i class="fa-solid fa-pen-to-square text-slate-400"></i> Edit
                                            </button>

                                            <?php if ($b['log_count'] > 0): ?>
                                                <button @click="deleteForm = { id: '<?= $b['id'] ?>', name: '<?= addslashes($b['full_name']) ?>', count: <?= $b['log_count'] ?> }; deleteModalOpen = true" class="px-2 py-1.5 rounded-lg text-slate-400 hover:text-slate-600 bg-slate-50 border border-slate-200 text-xs transition" title="Informasi Penghapusan">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            <?php else: ?>
                                                <button @click="deleteForm = { id: '<?= $b['id'] ?>', name: '<?= addslashes($b['full_name']) ?>', count: 0 }; deleteModalOpen = true" class="px-2 py-1.5 rounded-lg text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs transition" title="Hapus Penerima">
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

    <!-- Modal Form Tambah / Edit Penerima -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="modalOpen = false">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Data Penerima Manfaat' : 'Tambah Penerima Manfaat Baru'"></h3>
                <button @click="modalOpen = false" type="button" aria-label="Tutup form" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="form.id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">NIK (16 Digit) <span class="text-rose-500">*</span></label>
                        <input type="text" name="nik" x-model="form.nik" required maxlength="16" placeholder="Contoh: 320101..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400 tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">No. Kartu Keluarga</label>
                        <input type="text" name="kk_number" x-model="form.kk_number" maxlength="16" placeholder="Nomor KK..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400 tabular-nums">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap Penerima <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" x-model="form.full_name" required placeholder="Contoh: Muhammad Ilham Pratama" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Lahir <span class="text-rose-500">*</span></label>
                        <input type="date" name="birth_date" x-model="form.birth_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kategori</label>
                        <select name="category" x-model="form.category" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                            <option value="yatim">Anak Yatim</option>
                            <option value="sembako">Penerima Sembako</option>
                            <option value="dhuafa">Keluarga Dhuafa</option>
                            <option value="lansia">Lansia Sakit</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Wali / Ibu / Pengasuh</label>
                        <input type="text" name="guardian_name" x-model="form.guardian_name" placeholder="Contoh: Ibu Maryati (Ibu Kandung)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">No. Telepon / WhatsApp</label>
                        <input type="text" name="phone" x-model="form.phone" placeholder="Contoh: 085711223344" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Tempat Tinggal <span class="text-rose-500">*</span></label>
                        <input type="text" name="address" x-model="form.address" required placeholder="Contoh: Kp. Rawasari, Dekat Musholla" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">RT / RW</label>
                        <input type="text" name="rt_rw" x-model="form.rt_rw" placeholder="RT 01 / RW 03" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Status Kelayakan</label>
                    <select name="status" x-model="form.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="layak_aktif">Layak Aktif</option>
                        <option value="lulus_usia">Lulus Usia (&gt;15 Thn / Mandiri)</option>
                        <option value="pindah">Pindah Domisili</option>
                        <option value="mampu">Keluarga Mampu</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan Latar Belakang</label>
                    <textarea name="notes" x-model="form.notes" rows="2" placeholder="Kondisi ekonomi, sekolah, atau status orang tua..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button @click="modalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                        Simpan Penerima Manfaat
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Penerima Manfaat -->
    <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="deleteModalOpen = false">
        <div @click.away="deleteModalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <template x-if="deleteForm.count > 0">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg mb-3">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">Penerima Tidak Dapat Dihapus</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Penerima <strong class="text-slate-900" x-text="deleteForm.name"></strong> memiliki <strong class="text-slate-900" x-text="deleteForm.count"></strong> riwayat log penyaluran santunan tercatat di pembukuan. Untuk menonaktifkan, silakan ubah status menjadi <em>Lulus Usia</em> atau <em>Pindah</em>.
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
                    <h3 class="text-base font-bold text-slate-900 mb-1">Hapus Data Penerima</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Apakah Anda yakin ingin menghapus data penerima <strong class="text-slate-900" x-text="deleteForm.name"></strong>?
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
