<?php
/**
 * Modul C: Manajemen Database Anggota / Relawan Cluster BKRC
 * Mendukung Alur Pendaftaran Mandiri (Pending -> Approve/Reject) & Manajemen Status (Aktif / Resign).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Actions (POST dengan CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid. Silakan ulangi.');
        header('Location: ' . getBaseUrl() . '/admin/members.php');
        exit;
    }

    // 1. Approve Calon Anggota (Pending -> Aktif)
    if ($action === 'approve_member') {
        $id = (int)$_POST['id'];
        
        // Auto-generate member code unik
        $stmtCode = $pdo->query("SELECT member_code FROM members WHERE member_code LIKE 'BKRC-M-%' ORDER BY id DESC LIMIT 1");
        $lastCode = $stmtCode->fetchColumn();
        if ($lastCode) {
            $num = (int)substr($lastCode, 7);
            $memberCode = 'BKRC-M-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $memberCode = 'BKRC-M-001';
        }

        $stmt = $pdo->prepare("
            UPDATE members 
            SET member_code = ?, status = 'aktif', joined_date = CURDATE(), rejection_reason = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$memberCode, $id]);

        setFlash('success', "Calon anggota berhasil di-Approve! Kode Anggota resmi: {$memberCode}");
        header('Location: ' . getBaseUrl() . '/admin/members.php?status=pending');
        exit;
    }

    // 2. Reject Calon Anggota
    if ($action === 'reject_member') {
        $id = (int)$_POST['id'];
        $reason = trim($_POST['rejection_reason'] ?? 'Data tidak memenuhi verifikasi warga cluster');
        
        // Hapus pendaftaran atau simpan penolakan
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);

        setFlash('success', 'Pendaftaran calon anggota telah ditolak dan dihapus dari antrean.');
        header('Location: ' . getBaseUrl() . '/admin/members.php?status=pending');
        exit;
    }

    // 3. Ubah Status Anggota (Aktif <-> Resign)
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['new_status'] === 'resign' ? 'resign' : 'aktif';

        $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        if ($newStatus === 'resign') {
            setFlash('success', 'Status anggota diubah menjadi Resign. Anggota ini diblokir dari form iuran baru, namun seluruh data historisnya tetap tersimpan.');
        } else {
            setFlash('success', 'Anggota berhasil diaktifkan kembali dan dapat membayar iuran bulanan.');
        }
        header('Location: ' . getBaseUrl() . '/admin/members.php');
        exit;
    }

    // 4. Hapus Anggota / Relawan Permanen
    if ($action === 'delete_member') {
        $id = (int)($_POST['id'] ?? 0);

        $stM = $pdo->prepare("SELECT full_name, member_code FROM members WHERE id = ?");
        $stM->execute([$id]);
        $targetMember = $stM->fetch();

        if ($targetMember) {
            // Null-kan relasi ke tabel donasi agar tidak merusak data historis pembukuan kas
            $updDon = $pdo->prepare("UPDATE donations SET member_id = NULL WHERE member_id = ?");
            $updDon->execute([$id]);

            // Hapus data anggota
            $stmtDel = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $stmtDel->execute([$id]);

            setFlash('success', "Data anggota {$targetMember['full_name']} ({$targetMember['member_code']}) berhasil dihapus permanen.");
        } else {
            setFlash('error', 'Data anggota tidak ditemukan.');
        }

        header('Location: ' . getBaseUrl() . '/admin/members.php');
        exit;
    }

    // 5. Tambah / Edit Manual oleh Pengurus
    if ($action === 'create' || $action === 'update') {
        $nik = trim($_POST['nik'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $clusterBlock = trim($_POST['cluster_block'] ?? '');
        $rtRw = trim($_POST['rt_rw'] ?? '');
        $position = trim($_POST['position'] ?? 'Relawan Lapangan');
        $joinedDate = $_POST['joined_date'] ?: date('Y-m-d');
        $status = $_POST['status'] ?? 'aktif';

        if (empty($fullName) || empty($clusterBlock)) {
            setFlash('error', 'Nama Lengkap dan Cluster/Blok wajib diisi!');
        } else {
            // Validasi format NIK jika diisi
            if (!empty($nik) && !preg_match('/^[0-9]{16}$/', $nik)) {
                setFlash('error', 'Format NIK harus 16 digit angka sesuai KTP!');
                header('Location: ' . getBaseUrl() . '/admin/members.php');
                exit;
            }

            // Validasi anti-duplikasi NIK
            if (!empty($nik)) {
                $idCheck = ($action === 'update') ? (int)$_POST['id'] : 0;
                $chkNik = $pdo->prepare("SELECT id, full_name FROM members WHERE nik = ?" . ($idCheck > 0 ? " AND id != ?" : ""));
                $paramsNik = ($idCheck > 0) ? [$nik, $idCheck] : [$nik];
                $chkNik->execute($paramsNik);
                if ($existingMember = $chkNik->fetch()) {
                    setFlash('error', "NIK {$nik} sudah terdaftar atas nama '{$existingMember['full_name']}'!");
                    header('Location: ' . getBaseUrl() . '/admin/members.php');
                    exit;
                }
            }

            if ($action === 'create') {
                $stmtCode = $pdo->query("SELECT member_code FROM members WHERE member_code LIKE 'BKRC-M-%' ORDER BY id DESC LIMIT 1");
                $lastCode = $stmtCode->fetchColumn();
                if ($lastCode) {
                    $num = (int)substr($lastCode, 7);
                    $memberCode = 'BKRC-M-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $memberCode = 'BKRC-M-001';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO members (member_code, nik, full_name, phone, cluster_block, rt_rw, position, joined_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$memberCode, $nik ?: null, $fullName, $phone, $clusterBlock, $rtRw, $position, $joinedDate, $status]);
                setFlash('success', "Anggota baru berhasil didaftarkan: {$fullName} ({$memberCode})");
            } elseif ($action === 'update') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("
                    UPDATE members 
                    SET nik = ?, full_name = ?, phone = ?, cluster_block = ?, rt_rw = ?, position = ?, joined_date = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nik ?: null, $fullName, $phone, $clusterBlock, $rtRw, $position, $joinedDate, $status, $id]);
                setFlash('success', 'Data relawan berhasil diperbarui.');
            }
            header('Location: ' . getBaseUrl() . '/admin/members.php');
            exit;
        }
    }
}

// Hitung Counter Anggota
$countAll = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'pending'")->fetchColumn();
$countActive = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'aktif'")->fetchColumn();
$countResign = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'resign'")->fetchColumn();

// Filter & Pencarian
$statusFilter = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$query = "SELECT * FROM members WHERE 1=1";
$params = [];

if ($statusFilter !== 'semua') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}
if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR member_code LIKE ? OR cluster_block LIKE ? OR position LIKE ? OR phone LIKE ? OR nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY CASE WHEN status = 'pending' THEN 0 WHEN status = 'aktif' THEN 1 ELSE 2 END, id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll();

$pageTitle = 'Data Relawan & Anggota';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: false, 
    rejectModal: false, 
    resignModal: false,
    deleteModal: false,
    activeMember: { id: null, name: '', status: '', code: '' },
    isEdit: false, 
    form: { id: '', nik: '', full_name: '', phone: '', cluster_block: '', rt_rw: 'RT 01 / RW 08', position: 'Relawan Lapangan', joined_date: '<?= date('Y-m-d') ?>', status: 'aktif' } 
}" @keydown.escape.window="modalOpen = false; rejectModal = false; resignModal = false; deleteModal = false" class="flex-1 flex flex-col w-full">

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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Data Relawan & Anggota</h1>
            </div>
        </div>

        <button @click="isEdit = false; form = { id: '', nik: '', full_name: '', phone: '', cluster_block: '', rt_rw: 'RT 01 / RW 08', position: 'Relawan Lapangan', joined_date: '<?= date('Y-m-d') ?>', status: 'aktif' }; modalOpen = true" class="inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
            <span>Tambah Anggota</span>
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

        <!-- Filter Tabs & Search Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-xs border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
            <!-- Tabs Status Anggota -->
            <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                <a href="<?= getBaseUrl() ?>/admin/members.php?status=semua" class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition <?= $statusFilter === 'semua' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    Semua (<?= $countAll ?>)
                </a>
                <a href="<?= getBaseUrl() ?>/admin/members.php?status=pending" class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition inline-flex items-center gap-1.5 <?= $statusFilter === 'pending' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200/80 hover:bg-amber-100' ?>">
                    <span>Pending Approval</span>
                    <?php if ($countPending > 0): ?>
                        <span class="w-5 h-5 rounded-full bg-rose-600 text-white text-[10px] font-extrabold flex items-center justify-center">
                            <?= $countPending ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= getBaseUrl() ?>/admin/members.php?status=aktif" class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition <?= $statusFilter === 'aktif' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    Aktif (<?= $countActive ?>)
                </a>
                <a href="<?= getBaseUrl() ?>/admin/members.php?status=resign" class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition <?= $statusFilter === 'resign' ? 'bg-slate-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    Resign (<?= $countResign ?>)
                </a>
            </div>

            <!-- Search Form -->
            <form action="" method="GET" class="w-full md:w-72 flex items-center gap-2">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <div class="relative w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, blok, posisi..." class="w-full pl-9 pr-3 py-2 min-h-[40px] bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>
            </form>
        </div>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Kode & Nama Anggota</th>
                            <th class="py-3 px-4 whitespace-nowrap">NIK (UU PDP)</th>
                            <th class="py-3 px-4">Kontak (WhatsApp)</th>
                            <th class="py-3 px-4">Alamat Cluster</th>
                            <th class="py-3 px-4">Posisi / Minat</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi / Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-users text-3xl mb-2 text-slate-300 block"></i>
                                    Tidak ada data anggota/relawan pada kategori ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $m): ?>
                                <?php
                                $isPending = ($m['status'] === 'pending');
                                $isActive = ($m['status'] === 'aktif');
                                $isResign = ($m['status'] === 'resign');
                                $maskedPhone = maskPhone($m['phone']);
                                $rawPhone = htmlspecialchars($m['phone'] ?? '');
                                ?>
                                <tr class="hover:bg-slate-50/80 transition <?= $isPending ? 'bg-amber-50/30' : '' ?>" x-data="{ phoneRevealed: false, nikRevealed: false }">
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($m['full_name']) ?></div>
                                        <div class="text-[11px] font-mono mt-0.5">
                                            <?php if ($isPending): ?>
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 font-mono text-[10px] font-bold" title="Nomor Registrasi Antrean Sementara (Akan menjadi BKRC-M-XXX saat di-approve)">
                                                    <i class="fa-solid fa-clock text-[9px]"></i> <?= htmlspecialchars($m['member_code']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 font-mono"><?= htmlspecialchars($m['member_code'] ?: '–') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <!-- NIK (UU PDP: Disamarkan secara default dengan opsi toggle reveal) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if (!empty($m['nik'])): ?>
                                            <div class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-700">
                                                <span class="tabular-nums font-semibold" x-text="nikRevealed ? '<?= htmlspecialchars($m['nik']) ?>' : '<?= htmlspecialchars(maskNik($m['nik'])) ?>'"></span>
                                                <button @click="nikRevealed = !nikRevealed" type="button" class="text-slate-400 hover:text-slate-700 p-1 rounded transition" :title="nikRevealed ? 'Sembunyikan NIK' : 'Buka NIK Lengkap'">
                                                    <i class="fa-solid" :class="nikRevealed ? 'fa-eye-slash' : 'fa-eye'"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">Belum diisi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs text-slate-700" x-text="phoneRevealed ? '<?= $rawPhone ?>' : '<?= $maskedPhone ?>'"></span>
                                            <?php if (!empty($m['phone'])): ?>
                                                <button type="button" @click="phoneRevealed = !phoneRevealed" class="text-slate-400 hover:text-slate-700 p-1 rounded" :title="phoneRevealed ? 'Sembunyikan' : 'Tampilkan No HP'">
                                                    <i :class="phoneRevealed ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" class="text-xs"></i>
                                                </button>
                                                <a href="<?= buildWaUrl($m['phone']) ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 p-1" title="Buka WhatsApp">
                                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($m['cluster_block']) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($m['rt_rw']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                            <?= htmlspecialchars($m['position']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($isPending): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                Pending Approval
                                            </span>
                                        <?php elseif ($isActive): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-slate-100 text-slate-600 border border-slate-300">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                Resign
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($isPending): ?>
                                            <!-- Tombol Approval Calon Anggota -->
                                            <div class="inline-flex items-center gap-2">
                                                <form action="" method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action" value="approve_member">
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-xs transition inline-flex items-center gap-1">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" @click.stop="activeMember = { id: <?= $m['id'] ?>, name: '<?= htmlspecialchars(addslashes($m['full_name'])) ?>' }; rejectModal = true" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-xmark"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <!-- Action untuk Anggota Aktif / Resign -->
                                            <div class="inline-flex items-center gap-2">
                                                <button type="button" @click.stop="isEdit = true; form = { id: '<?= $m['id'] ?>', nik: '<?= htmlspecialchars(addslashes($m['nik'] ?? '')) ?>', full_name: '<?= htmlspecialchars(addslashes($m['full_name'])) ?>', phone: '<?= htmlspecialchars(addslashes($m['phone'] ?? '')) ?>', cluster_block: '<?= htmlspecialchars(addslashes($m['cluster_block'])) ?>', rt_rw: '<?= htmlspecialchars(addslashes($m['rt_rw'])) ?>', position: '<?= htmlspecialchars(addslashes($m['position'])) ?>', joined_date: '<?= $m['joined_date'] ?>', status: '<?= $m['status'] ?>' }; modalOpen = true" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition" title="Edit Data Anggota">
                                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                                </button>

                                                <?php if ($isActive): ?>
                                                    <!-- Tombol Tandai Resign -->
                                                    <button type="button" @click.stop="activeMember = { id: <?= $m['id'] ?>, name: '<?= htmlspecialchars(addslashes($m['full_name'])) ?>', status: 'resign', code: '<?= htmlspecialchars(addslashes($m['member_code'])) ?>' }; resignModal = true" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-rose-700 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 transition" title="Ubah status ke Resign">
                                                        <span>Tandai Resign</span>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Tombol Aktifkan Kembali -->
                                                    <form action="" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <input type="hidden" name="new_status" value="aktif">
                                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 hover:bg-emerald-50 border border-emerald-200 transition" title="Aktifkan Kembali">
                                                            <i class="fa-solid fa-rotate-left mr-1"></i> Aktifkan
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Tombol Hapus Permanen -->
                                                <button type="button" @click.stop="activeMember = { id: <?= $m['id'] ?>, name: '<?= htmlspecialchars(addslashes($m['full_name'])) ?>', status: '<?= $m['status'] ?>', code: '<?= htmlspecialchars(addslashes($m['member_code'])) ?>' }; deleteModal = true" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-rose-600 hover:text-rose-700 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 transition" title="Hapus Data Anggota">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form Tambah / Edit Anggota -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="modalOpen = false">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Data Anggota' : 'Tambah Anggota Baru'"></h3>
                <button @click="modalOpen = false" type="button" aria-label="Tutup form" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-slate-400 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" x-model="form.full_name" required placeholder="Contoh: Ahmad Fauzi, S.Kom" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp</label>
                        <input type="tel" name="phone" x-model="form.phone" placeholder="081234567890" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">NIK (Opsional)</label>
                        <input type="text" name="nik" x-model="form.nik" placeholder="Kosongkan jika tidak ada" maxlength="16" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Blok Rumah Cluster <span class="text-rose-500">*</span></label>
                        <input type="text" name="cluster_block" x-model="form.cluster_block" required placeholder="Cluster Gardenia Blok B3/12" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">RT / RW</label>
                        <input type="text" name="rt_rw" x-model="form.rt_rw" placeholder="RT 02 / RW 08" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Posisi / Jabatan</label>
                        <input type="text" name="position" x-model="form.position" placeholder="Relawan Lapangan / Pengurus" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status Keanggotaan</label>
                        <select name="status" x-model="form.status" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                            <option value="aktif">Aktif</option>
                            <option value="pending">Pending Approval</option>
                            <option value="resign">Resign</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="modalOpen = false" class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none">Batal</button>
                    <button type="submit" class="min-h-[44px] bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-xs transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Reject Calon Anggota -->
    <div x-show="rejectModal" x-cloak @click.self="rejectModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="rejectModal = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 mx-auto flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Tolak Pendaftaran Calon Anggota?</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Pendaftaran atas nama <strong x-text="activeMember.name"></strong> akan ditolak dan dihapus dari antrean pending.
            </p>
            <form action="" method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="reject_member">
                <input type="hidden" name="id" :value="activeMember.id">
                <div class="flex items-center justify-center gap-3">
                    <button type="button" @click="rejectModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">Kembali</button>
                    <button type="submit" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-xs transition">Ya, Tolak Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Tandai Resign -->
    <div x-show="resignModal" x-cloak @click.self="resignModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="resignModal = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 mx-auto flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Tandai Anggota Sebagai Resign?</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Anggota <strong x-text="activeMember.name"></strong> tidak akan bisa membayar iuran baru, namun riwayat historis masa lalunya tetap tersimpan utuh di sistem demi jejak audit.
            </p>
            <form action="" method="POST" class="flex items-center justify-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" :value="activeMember.id">
                <input type="hidden" name="new_status" value="resign">
                <button type="button" @click="resignModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">Batal</button>
                <button type="submit" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 shadow-xs transition">Ya, Tandai Resign</button>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Data Anggota -->
    <div x-show="deleteModal" x-cloak @click.self="deleteModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="deleteModal = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 mx-auto flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Hapus Data Anggota?</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin menghapus data anggota <strong x-text="activeMember.name"></strong> (<span class="font-mono text-slate-700 font-semibold" x-text="activeMember.code"></span>)? Tindakan ini bersifat permanen dan data anggota akan dihapus dari sistem.
            </p>
            <form action="" method="POST" class="flex items-center justify-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="delete_member">
                <input type="hidden" name="id" :value="activeMember.id">
                <button type="button" @click="deleteModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">Batal</button>
                <button type="submit" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-xs transition inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                    <span>Ya, Hapus Permanen</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
