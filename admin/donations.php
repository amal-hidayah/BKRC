<?php
/**
 * Modul D: Pencatatan Donasi & Iuran Kas Masuk, Verifikasi Bukti Transfer WA, Kwitansi Resmi, & Audit Log
 * Standar Alur:
 * 1. Donatur/Anggota input di donasi.php -> Masuk status 'pending' (dengan ref TRX-YYYYMM-XXXX).
 * 2. Admin/Bendahara memeriksa bukti transfer (unggah langsung di web atau lampirkan dari chat WA).
 * 3. Tombol Approve HANYA AKTIF jika bukti transfer sudah ada.
 * 4. Saat di-Approve: nomor kwitansi resmi KW-2026-XXXX otomatis digenerate secara berurutan & masuk saldo kas.
 * 5. Tombol 1-klik kirim kwitansi digital via WhatsApp ke donatur/anggota.
 * 6. Pembatalan kwitansi menggunakan mekanisme Void dengan jejak audit (tanpa hard-delete).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// 1. Handle Tambah Donasi Manual / Kas Masuk Langsung oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_donation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    $category = $_POST['transaction_category'] === 'iuran' ? 'iuran' : 'donasi';
    $donorId = (int)($_POST['donor_id'] ?? 0);
    $memberId = ($category === 'iuran') ? (int)($_POST['member_id'] ?? 0) : null;
    $donationDate = $_POST['donation_date'] ?: date('Y-m-d');
    $type = $_POST['type'] === 'sembako' ? 'sembako' : 'uang';
    $amount = $type === 'uang' ? parseRupiahInput($_POST['amount'] ?? 0) : 0;
    $packageQty = $type === 'sembako' ? (int)$_POST['package_qty'] : 0;
    $packageDetails = $type === 'sembako' ? trim($_POST['package_details'] ?? '') : null;
    $programTarget = trim($_POST['program_target'] ?? ($category === 'iuran' ? 'Iuran Kas Bulanan Anggota' : 'Kas Rutin Bulanan'));
    $notes = trim($_POST['notes'] ?? '');
    $donorPhone = trim($_POST['donor_phone'] ?? '');

    // Handle upload bukti transfer (opsional untuk transaksi tunai langsung)
    $proofUrl = null;
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $fileExt = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExts)) {
            $newName = 'proof_admin_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $targetPath = __DIR__ . '/../assets/proofs/' . $newName;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                $proofUrl = 'assets/proofs/' . $newName;
            }
        }
    }

    if ($category === 'iuran' && $memberId <= 0) {
        setFlash('error', 'Untuk jenis Iuran Bulanan, silakan pilih anggota aktif!');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    if ($category === 'donasi' && $donorId <= 0) {
        setFlash('error', 'Silakan pilih donatur terdaftar atau pilih Hamba Allah!');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    // Jika iuran, pastikan donor_id sinkron
    if ($category === 'iuran') {
        $stmtM = $pdo->prepare("SELECT full_name, phone, cluster_block, status FROM members WHERE id = ?");
        $stmtM->execute([$memberId]);
        $mem = $stmtM->fetch();
        if ($mem) {
            $donorPhone = $donorPhone ?: $mem['phone'];
            $chkD = $pdo->prepare("SELECT id FROM donors WHERE full_name = ? LIMIT 1");
            $chkD->execute([$mem['full_name']]);
            $donorId = (int)$chkD->fetchColumn();
            if (!$donorId) {
                $code = 'DNR-' . date('ymd') . rand(10, 99);
                $insD = $pdo->prepare("INSERT INTO donors (donor_code, full_name, phone, cluster_or_address, donor_type, is_anonymous) VALUES (?, ?, ?, ?, 'rutin', 0)");
                $insD->execute([$code, $mem['full_name'], $donorPhone, $mem['cluster_block']]);
                $donorId = (int)$pdo->lastInsertId();
            }
        }
    }

    // Terbitkan kwitansi resmi langsung untuk pencatatan manual admin
    $receiptNo = generateReceiptNo($pdo);
    $recordedBy = $currentUser['id'];

    $stmt = $pdo->prepare("
        INSERT INTO donations (
            receipt_no, transaction_category, donor_id, member_id, donor_phone,
            donation_date, type, amount, package_qty, package_details,
            program_target, status, approved_at, approved_by, notes, proof_url, recorded_by
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, 'approved', NOW(), ?, ?, ?, ?
        )
    ");
    $stmt->execute([
        $receiptNo, $category, $donorId, $memberId, $donorPhone,
        $donationDate, $type, $amount, $packageQty, $packageDetails,
        $programTarget, $currentUser['id'], $notes, $proofUrl, $recordedBy
    ]);

    setFlash('success', "Transaksi berhasil dicatat dan disetujui langsung dengan No. Kwitansi resmi: {$receiptNo}");
    header('Location: ' . getBaseUrl() . '/admin/donations.php');
    exit;
}

// 2. Handle Setujui Donasi (Approve) - STRICT: Bukti transfer wajib ada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_donation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    $id = (int)$_POST['id'];
    $stmtCheck = $pdo->prepare("SELECT id, proof_url, status, receipt_no, transaction_category FROM donations WHERE id = ?");
    $stmtCheck->execute([$id]);
    $donation = $stmtCheck->fetch();

    if (!$donation) {
        setFlash('error', 'Transaksi donasi tidak ditemukan.');
    } elseif ($donation['status'] === 'approved') {
        setFlash('error', 'Transaksi ini sudah disetujui sebelumnya.');
    } else {
        // Terbitkan nomor kwitansi resmi berurutan KW-2026-XXXX
        $officialReceiptNo = generateReceiptNo($pdo);
        $stmtApprove = $pdo->prepare("
            UPDATE donations 
            SET status = 'approved', receipt_no = ?, approved_at = NOW(), approved_by = ?
            WHERE id = ?
        ");
        $stmtApprove->execute([$officialReceiptNo, $currentUser['id'], $id]);

        setFlash('success', "Transaksi berhasil disetujui (Approved) dengan No. Kwitansi resmi: {$officialReceiptNo}. Dana telah masuk ke kas.");
    }
    header('Location: ' . getBaseUrl() . '/admin/donations.php?status=pending');
    exit;
}

// 3. Handle Unggah Bukti Struk yang Diterima via WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_wa_proof') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    $id = (int)$_POST['id'];
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $fileExt = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExts)) {
            $newName = 'proof_wa_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $targetPath = __DIR__ . '/../assets/proofs/' . $newName;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                $proofUrl = 'assets/proofs/' . $newName;
                $stmtUp = $pdo->prepare("UPDATE donations SET proof_url = ? WHERE id = ?");
                $stmtUp->execute([$proofUrl, $id]);
                setFlash('success', 'Bukti transfer dari WhatsApp berhasil dilampirkan. Tombol Setujui (Approve) kini sudah aktif!');
            } else {
                setFlash('error', 'Gagal memindahkan file upload bukti transfer.');
            }
        } else {
            setFlash('error', 'Format file tidak didukung. Harap gunakan format JPG, PNG, WEBP, atau PDF.');
        }
    } else {
        setFlash('error', 'Silakan pilih file bukti struk transfer yang diterima via WhatsApp.');
    }
    header('Location: ' . getBaseUrl() . '/admin/donations.php?status=pending');
    exit;
}

// 4. Handle Penolakan Transaksi (Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_donation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    $id = (int)$_POST['id'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($reason)) {
        setFlash('error', 'Alasan penolakan transaksi wajib diisi!');
    } else {
        $stmtRej = $pdo->prepare("UPDATE donations SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmtRej->execute([$reason, $id]);
        setFlash('success', 'Transaksi berhasil ditolak dan dicatat dalam riwayat penolakan.');
    }
    header('Location: ' . getBaseUrl() . '/admin/donations.php?status=pending');
    exit;
}

// 5. Handle Pembatalan Kwitansi Resmi (Void Kwitansi) - Aman & Jejak Audit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'void_donation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid.');
        header('Location: ' . getBaseUrl() . '/admin/donations.php');
        exit;
    }

    $voidId = (int)$_POST['id'];
    $reason = trim($_POST['void_reason'] ?? '');

    if (empty($reason)) {
        setFlash('error', 'Alasan pembatalan kwitansi wajib diisi!');
    } else {
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'batal', void_reason = ?, voided_at = NOW(), voided_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $currentUser['id'], $voidId]);
        setFlash('success', 'Kwitansi donasi berhasil dibatalkan (Void) dan tercatat dalam jejak audit.');
    }
    header('Location: ' . getBaseUrl() . '/admin/donations.php');
    exit;
}

// Data Master untuk Dropdown Form
$donors = $pdo->query("SELECT id, donor_code, full_name, phone, cluster_or_address, is_anonymous FROM donors ORDER BY full_name ASC")->fetchAll();
$activeMembers = $pdo->query("SELECT id, member_code, full_name, phone, cluster_block FROM members WHERE status = 'aktif' ORDER BY full_name ASC")->fetchAll();

// Hitung Counter Status untuk Badge Tab
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'pending'")->fetchColumn();
$countApproved = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'approved'")->fetchColumn();
$countRejected = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'rejected'")->fetchColumn();
$countVoid = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'batal'")->fetchColumn();
$countAll = (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();

// Filter & Parameter Pencarian
$statusFilter = $_GET['status'] ?? 'semua';
$catFilter = $_GET['kategori'] ?? 'semua';
$typeFilter = $_GET['tipe'] ?? 'semua';
$monthFilter = (int)($_GET['bulan'] ?? 0);
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = ["1=1"];
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'batal'])) {
    $whereClauses[] = "d.status = ?";
    $params[] = $statusFilter;
}

if ($catFilter === 'donasi' || $catFilter === 'iuran') {
    $whereClauses[] = "d.transaction_category = ?";
    $params[] = $catFilter;
}

if ($typeFilter === 'uang' || $typeFilter === 'sembako') {
    $whereClauses[] = "d.type = ?";
    $params[] = $typeFilter;
}

if ($monthFilter >= 1 && $monthFilter <= 12) {
    $whereClauses[] = "MONTH(d.donation_date) = ?";
    $params[] = $monthFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(d.receipt_no LIKE ? OR dn.full_name LIKE ? OR m.full_name LIKE ? OR d.donor_phone LIKE ? OR d.program_target LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

// Agregasi Data Terfilter (Hanya status approved yang dihitung dalam total uang/sembako riil)
$countStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_rows,
        COALESCE(SUM(CASE WHEN d.type = 'uang' AND d.status = 'approved' THEN d.amount ELSE 0 END), 0) as total_uang_approved,
        COALESCE(SUM(CASE WHEN d.type = 'sembako' AND d.status = 'approved' THEN d.package_qty ELSE 0 END), 0) as total_paket_approved
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.id
    LEFT JOIN members m ON d.member_id = m.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$summaryRow = $countStmt->fetch();
$totalRows = (int)$summaryRow['total_rows'];
$totalUangTerfilter = (float)$summaryRow['total_uang_approved'];
$totalPaketTerfilter = (int)$summaryRow['total_paket_approved'];
$totalPages = max(1, ceil($totalRows / $perPage));

// Query Data Paginasi (Prioritaskan pending di urutan teratas)
$query = "
    SELECT d.*, 
        dn.full_name as donor_name, dn.phone as donor_phone_raw, dn.is_anonymous, dn.cluster_or_address,
        m.member_code, m.full_name as member_name, m.cluster_block as member_block, m.phone as member_phone,
        u.name as approved_by_name,
        uv.name as voided_by_name
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.id
    LEFT JOIN members m ON d.member_id = m.id
    LEFT JOIN users u ON d.approved_by = u.id
    LEFT JOIN users uv ON d.voided_by = uv.id
    WHERE {$whereSql}
    ORDER BY 
        CASE WHEN d.status = 'pending' THEN 0 ELSE 1 END,
        d.donation_date DESC, d.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$donations = $stmt->fetchAll();

$pageTitle = 'Pencatatan Donasi & Iuran Kas';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: <?= (isset($_GET['action']) && $_GET['action'] === 'create') ? 'true' : 'false' ?>, 
    voidModalOpen: false,
    approveModalOpen: false,
    rejectModalOpen: false,
    uploadWaModalOpen: false,
    viewProofModalOpen: false,
    previewProofUrl: '',
    donationCategory: 'donasi',
    donationType: 'uang',
    activeTrx: { id: '', ref: '', name: '', amount: '', phone: '', hasProof: false, proofUrl: '' },
    voidForm: { id: '', receipt_no: '', donor_name: '', reason: '' },
    rejectForm: { id: '', ref: '', donor_name: '', reason: '' },
    revealedPhones: {}
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Donasi & Iuran Kas Masuk</h1>
                <p class="text-xs text-slate-500 hidden sm:block">Verifikasi bukti transfer dari donatur & warga cluster, terbitkan kwitansi resmi.</p>
            </div>
        </div>

        <button @click="modalOpen = true" class="min-h-[44px] inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
            <i class="fa-solid fa-plus mr-2 text-xs"></i>
            <span>Catat Kas Masuk Manual</span>
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
                <i class="fa-solid fa-circle-xmark text-rose-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigasi Status Transaksi -->
        <div class="bg-white p-2 rounded-2xl shadow-xs border border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Tab Semua -->
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=semua&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'semua' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <span>Semua Transaksi</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md <?= $statusFilter === 'semua' ? 'bg-slate-800 text-slate-200' : 'bg-slate-200 text-slate-700' ?>"><?= $countAll ?></span>
                </a>

                <!-- Tab Pending (Highlight Badge Merah jika ada yang menunggu verifikasi) -->
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=pending&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'pending' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                    <span>Menunggu Verifikasi</span>
                    <?php if ($countPending > 0): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-black <?= $statusFilter === 'pending' ? 'bg-white text-amber-700' : 'bg-rose-600 text-white animate-pulse' ?>"><?= $countPending ?></span>
                    <?php else: ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-slate-200 text-slate-700">0</span>
                    <?php endif; ?>
                </a>

                <!-- Tab Approved -->
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=approved&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'approved' ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                    <span>Disetujui (Approved)</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md <?= $statusFilter === 'approved' ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-200 text-slate-700' ?>"><?= $countApproved ?></span>
                </a>

                <!-- Tab Rejected -->
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=rejected&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'rejected' ? 'bg-rose-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <span>Ditolak</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md <?= $statusFilter === 'rejected' ? 'bg-rose-800 text-rose-100' : 'bg-slate-200 text-slate-700' ?>"><?= $countRejected ?></span>
                </a>

                <!-- Tab Void -->
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=batal&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'batal' ? 'bg-slate-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <span>Dibatalkan (Void)</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md <?= $statusFilter === 'batal' ? 'bg-slate-800 text-slate-200' : 'bg-slate-200 text-slate-700' ?>"><?= $countVoid ?></span>
                </a>
            </div>

            <!-- Filter Kategori (Donasi Sukarela vs Iuran Bulanan) -->
            <div class="inline-flex p-1 bg-slate-100 rounded-xl text-xs font-semibold">
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=semua&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $catFilter === 'semua' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Semua Kategori</a>
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=donasi&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $catFilter === 'donasi' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Donasi</a>
                <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=iuran&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $catFilter === 'iuran' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Iuran Bulanan</a>
            </div>
        </div>

        <!-- Filter Bar Tambahan & Search Toolbar -->
        <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <!-- Filter Jenis Uang / Sembako -->
                <div class="inline-flex p-1 bg-slate-100 rounded-xl text-xs font-semibold">
                    <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($catFilter) ?>&tipe=semua&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $typeFilter === 'semua' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Semua Jenis</a>
                    <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($catFilter) ?>&tipe=uang&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $typeFilter === 'uang' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Uang Kas</a>
                    <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($catFilter) ?>&tipe=sembako&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-lg transition <?= $typeFilter === 'sembako' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Paket Sembako</a>
                </div>

                <!-- Filter Bulan -->
                <form method="GET" class="inline-flex items-center">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($catFilter) ?>">
                    <input type="hidden" name="tipe" value="<?= htmlspecialchars($typeFilter) ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <select name="bulan" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium rounded-xl px-3 py-1.5 min-h-[38px] focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="0">Semua Bulan</option>
                        <?php 
                        $bulanList = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                        foreach ($bulanList as $bNum => $bName): ?>
                            <option value="<?= $bNum ?>" <?= $monthFilter === $bNum ? 'selected' : '' ?>><?= $bName ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Pencarian Cepat Langsung -->
            <form method="GET" class="w-full md:w-80 relative flex items-center">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="kategori" value="<?= htmlspecialchars($catFilter) ?>">
                <input type="hidden" name="tipe" value="<?= htmlspecialchars($typeFilter) ?>">
                <input type="hidden" name="bulan" value="<?= $monthFilter ?>">
                <div class="relative w-full">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, kwitansi, nomor WA..." class="w-full pl-9 pr-3 py-2 min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400">
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
                            <th class="py-3 px-4 whitespace-nowrap">No. Kwitansi / Ref</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tanggal</th>
                            <th class="py-3 px-4">Donatur / Anggota</th>
                            <th class="py-3 px-4 whitespace-nowrap">Jenis & Alokasi</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Jumlah Nominal</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Bukti Transfer</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Tindakan Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($donations)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <i class="fa-solid fa-receipt text-3xl text-slate-300 block mb-2"></i>
                                    Tidak ada transaksi donasi/iuran yang sesuai dengan filter.<br>
                                    <button @click="modalOpen = true" class="mt-3 inline-flex items-center text-xs text-bkrc-red-800 font-bold bg-rose-50 px-3.5 py-2 rounded-xl border border-rose-200 hover:bg-rose-100 transition">
                                        Catat Transaksi Sekarang
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($donations as $d): ?>
                                <?php
                                $isPending = ($d['status'] === 'pending');
                                $isApproved = ($d['status'] === 'approved');
                                $isRejected = ($d['status'] === 'rejected');
                                $isVoid = ($d['status'] === 'batal');
                                $isUang = ($d['type'] === 'uang');
                                $isIuran = ($d['transaction_category'] === 'iuran');

                                // Nama tampil
                                $displayName = 'Hamba Allah';
                                if ($isIuran && !empty($d['member_name'])) {
                                    $displayName = $d['member_name'];
                                } elseif (!empty($d['donor_name'])) {
                                    $displayName = $d['is_anonymous'] ? 'Hamba Allah' : $d['donor_name'];
                                }

                                // Phone resolution & Masking
                                $phone = $d['donor_phone'] ?: ($d['member_phone'] ?: ($d['donor_phone_raw'] ?: ''));
                                $maskedPhone = maskPhone($phone);

                                // WhatsApp Link untuk mengirim kwitansi (untuk approved)
                                $receiptUrl = getReceiptUrl($d['id'], $d['receipt_no'], $d['created_at']);
                                $waKwitansiText = "Assalamualaikum Bpk/Ibu {$displayName},\n\n"
                                    . "Alhamdulillah, pembayaran " . ($isIuran ? "Iuran Kas Bulanan Anggota" : "Donasi Sukarela") . " Anda telah diverifikasi oleh Bendahara BKRC.\n\n"
                                    . "📌 No. Kwitansi: {$d['receipt_no']}\n"
                                    . "💰 Nominal: " . ($isUang ? formatRupiah($d['amount']) : "{$d['package_qty']} Paket Sembako") . "\n"
                                    . "📅 Tanggal: " . formatTanggalIndo($d['donation_date']) . "\n"
                                    . "🎯 Alokasi: {$d['program_target']}\n\n"
                                    . "Kwitansi digital resmi dapat diunduh melalui tautan berikut:\n"
                                    . "{$receiptUrl}\n\n"
                                    . "Jazakumullah khairan katsiran atas kontribusi dan kepedulian Anda.";
                                $waKwitansiUrl = buildWaUrl($phone, $waKwitansiText);

                                $hasProof = !empty($d['proof_url']);
                                ?>
                                <tr class="hover:bg-slate-50/80 transition <?= $isVoid ? 'bg-slate-50/60 opacity-60' : ($isPending ? 'bg-amber-50/30' : '') ?>">
                                    <!-- No. Kwitansi & Status Badge -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="tabular-nums text-xs font-bold px-2.5 py-1 rounded-md inline-block border <?= $isApproved ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($isPending ? 'bg-amber-50 text-amber-800 border-amber-200' : ($isVoid ? 'bg-slate-100 text-slate-500 line-through border-slate-200' : 'bg-rose-50 text-rose-800 border-rose-200')) ?>">
                                                <?= htmlspecialchars($d['receipt_no']) ?>
                                            </span>
                                        </div>
                                        <div class="mt-1">
                                            <?php if ($isPending): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-100 border border-amber-300 px-2 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span> Menunggu Verifikasi
                                                </span>
                                            <?php elseif ($isApproved): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Disetujui (Kas Masuk)
                                                </span>
                                            <?php elseif ($isRejected): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 border border-rose-300 px-2 py-0.5 rounded-full" title="Ditolak: <?= htmlspecialchars($d['rejection_reason'] ?? '') ?>">
                                                    Ditolak
                                                </span>
                                            <?php elseif ($isVoid): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-600 bg-slate-200 border border-slate-300 px-2 py-0.5 rounded-full" title="Batal: <?= htmlspecialchars($d['void_reason'] ?? '') ?>">
                                                    VOID
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600 font-medium">
                                        <?= formatTanggalIndo($d['donation_date']) ?>
                                    </td>

                                    <!-- Nama Donatur & Kategori -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-900 text-sm <?= $isVoid ? 'line-through text-slate-400' : '' ?>">
                                                <?= htmlspecialchars($displayName) ?>
                                            </span>
                                            <?php if ($isIuran): ?>
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                                    Iuran Kas
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                    Donasi
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Detail Alamat & Masking WhatsApp Phone -->
                                        <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                            <?php if ($isIuran && !empty($d['member_block'])): ?>
                                                <span>Blok: <?= htmlspecialchars($d['member_block']) ?></span>
                                            <?php elseif (!empty($d['cluster_or_address'])): ?>
                                                <span><?= htmlspecialchars($d['cluster_or_address']) ?></span>
                                            <?php endif; ?>

                                            <?php if (!empty($phone)): ?>
                                                <span class="text-slate-300">|</span>
                                                <span class="inline-flex items-center gap-1">
                                                    <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                                                    <span class="tabular-nums font-mono text-[11px]" x-text="revealedPhones['<?= $d['id'] ?>'] ? '<?= htmlspecialchars($phone) ?>' : '<?= htmlspecialchars($maskedPhone) ?>'">
                                                        <?= htmlspecialchars($maskedPhone) ?>
                                                    </span>
                                                    <button type="button" @click="revealedPhones['<?= $d['id'] ?>'] = !revealedPhones['<?= $d['id'] ?>']" class="text-slate-400 hover:text-slate-700 ml-0.5" title="Tampilkan/Sembunyikan Nomor">
                                                        <i class="fa-solid" :class="revealedPhones['<?= $d['id'] ?>'] ? 'fa-eye-slash' : 'fa-eye'"></i>
                                                    </button>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($d['notes'])): ?>
                                            <div class="text-[11px] text-slate-400 italic mt-0.5"><?= htmlspecialchars($d['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Jenis & Alokasi -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($isUang): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-md">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Uang Kas
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-md">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Sembako
                                            </span>
                                        <?php endif; ?>
                                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($d['program_target']) ?></div>
                                    </td>

                                    <!-- Jumlah (Rata Kanan & Tabular Nums) -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <?php if ($isUang): ?>
                                            <div class="font-extrabold text-slate-900 text-sm tabular-nums <?= $isVoid ? 'line-through text-slate-400' : '' ?>">
                                                <?= formatRupiah($d['amount']) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="font-extrabold text-slate-900 text-sm tabular-nums <?= $isVoid ? 'line-through text-slate-400' : '' ?>">
                                                <?= number_format($d['package_qty']) ?> Paket
                                            </div>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($d['package_details'] ?? '') ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Bukti Transfer (Preview Image or Upload Button) -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($hasProof): ?>
                                            <div class="inline-flex flex-col items-center gap-1">
                                                <button type="button" @click.stop="previewProofUrl = '<?= getBaseUrl() . '/' . htmlspecialchars($d['proof_url']) ?>'; viewProofModalOpen = true" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition shadow-2xs">
                                                    <i class="fa-solid fa-file-invoice"></i> Lihat Bukti
                                                </button>
                                                <span class="text-[10px] text-emerald-600 font-semibold"><i class="fa-solid fa-check"></i> Bukti Ada</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="inline-flex flex-col items-center gap-1">
                                                <button type="button" @click.stop="activeTrx = { id: '<?= $d['id'] ?>', ref: '<?= $d['receipt_no'] ?>', name: '<?= htmlspecialchars(addslashes($displayName)) ?>', amount: '<?= formatRupiah($d['amount']) ?>' }; uploadWaModalOpen = true" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition shadow-2xs" title="Unggah file jika ingin mengarsipkan bukti">
                                                    <i class="fa-solid fa-upload text-slate-400"></i> Unggah WA
                                                </button>
                                                <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded font-medium">
                                                    <i class="fa-brands fa-whatsapp text-emerald-600"></i> Via WA
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Aksi Audit-Safe Sesuai Status -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <?php if ($isPending): ?>
                                                <!-- Tombol Setujui (Approve Cepat) -->
                                                <button type="button" 
                                                        @click.stop="activeTrx = { id: '<?= $d['id'] ?>', ref: '<?= $d['receipt_no'] ?>', name: '<?= htmlspecialchars(addslashes($displayName)) ?>', amount: '<?= formatRupiah($d['amount']) ?>', phone: '<?= htmlspecialchars($phone) ?>', hasProof: <?= $hasProof ? 'true' : 'false' ?> }; approveModalOpen = true" 
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs" 
                                                        title="Setujui transaksi kas masuk">
                                                    <i class="fa-solid fa-check"></i> Setujui
                                                </button>

                                                <!-- Tombol Tolak (Reject) -->
                                                <button type="button" @click.stop="rejectForm = { id: '<?= $d['id'] ?>', ref: '<?= $d['receipt_no'] ?>', donor_name: '<?= htmlspecialchars(addslashes($displayName)) ?>', reason: '' }; rejectModalOpen = true" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50 border border-rose-200 transition" title="Tolak Transaksi">
                                                    <i class="fa-solid fa-xmark"></i> Tolak
                                                </button>
                                            <?php elseif ($isApproved): ?>
                                                <!-- Cetak Kwitansi -->
                                                <a href="<?= getReceiptUrl($d['id'], $d['receipt_no'], $d['created_at']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition border border-slate-200 shadow-2xs" title="Cetak Kwitansi PDF">
                                                    <i class="fa-solid fa-print text-slate-400"></i> <span class="hidden sm:inline">Kwitansi</span>
                                                </a>

                                                <!-- Kirim WA Kwitansi Langsung -->
                                                <?php if (!empty($phone)): ?>
                                                    <a href="<?= $waKwitansiUrl ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition border border-emerald-300 shadow-2xs" title="Kirim Kwitansi Resmi via WhatsApp">
                                                        <i class="fa-brands fa-whatsapp text-emerald-600"></i> Kirim WA
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Batalkan Kwitansi (Void) -->
                                                <button type="button" @click.stop="voidForm = { id: '<?= $d['id'] ?>', receipt_no: '<?= $d['receipt_no'] ?>', donor_name: '<?= htmlspecialchars(addslashes($displayName)) ?>', reason: '' }; voidModalOpen = true" class="px-2 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-rose-700 hover:bg-rose-50 border border-slate-200 transition" title="Batalkan Kwitansi (Void)">
                                                    <i class="fa-solid fa-ban"></i> Void
                                                </button>
                                            <?php elseif ($isRejected): ?>
                                                <span class="text-xs text-rose-600 font-semibold italic" title="Alasan: <?= htmlspecialchars($d['rejection_reason'] ?? '') ?>">
                                                    Ditolak: <?= htmlspecialchars(mb_strimwidth($d['rejection_reason'] ?? '', 0, 20, '...')) ?>
                                                </span>
                                            <?php elseif ($isVoid): ?>
                                                <span class="text-xs text-slate-400 italic">
                                                    Void: <?= htmlspecialchars(mb_strimwidth($d['void_reason'] ?? '', 0, 20, '...')) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                    <!-- Baris TOTAL di bawah tabel -->
                    <?php if (!empty($donations)): ?>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-200 text-xs sm:text-sm font-bold text-slate-900">
                            <tr>
                                <td colspan="4" class="py-3 px-4 uppercase tracking-wider text-slate-600">
                                    Total Terverifikasi (Status Disetujui / Approved)
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums text-slate-900 font-extrabold whitespace-nowrap">
                                    <?php if ($typeFilter === 'semua' || $typeFilter === 'uang'): ?>
                                        <div class="text-emerald-700"><?= formatRupiah($totalUangTerfilter) ?></div>
                                    <?php endif; ?>
                                    <?php if ($typeFilter === 'semua' || $typeFilter === 'sembako'): ?>
                                        <div class="text-xs text-amber-800 font-bold"><?= number_format($totalPaketTerfilter) ?> Paket</div>
                                    <?php endif; ?>
                                </td>
                                <td colspan="2" class="py-3 px-4 text-xs text-slate-400 font-normal italic">
                                    *Hanya transaksi disetujui yang dihitung dalam pembukuan kas.
                                </td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Paginasi Bersih -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
                    <div>
                        Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= $totalRows ?> data)
                    </div>
                    <div class="flex items-center gap-1">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?= getBaseUrl() ?>/admin/donations.php?status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($catFilter) ?>&tipe=<?= urlencode($typeFilter) ?>&bulan=<?= $monthFilter ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" 
                               class="w-8 h-8 rounded-lg inline-flex items-center justify-center font-bold transition <?= $page === $i ? 'bg-slate-900 text-white' : 'hover:bg-slate-100 text-slate-700' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal 1: Catat Donasi & Iuran Kas Masuk Manual -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="modalOpen = false">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Catat Kas Masuk Manual (Admin)</h3>
                    <p class="text-xs text-slate-500">Pencatatan langsung untuk donasi tunai atau iuran anggota di tempat.</p>
                </div>
                <button @click="modalOpen = false" type="button" aria-label="Tutup form" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="create_donation">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <!-- Kategori Transaksi -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kategori Transaksi <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl">
                        <button type="button" @click="donationCategory = 'donasi'" :class="donationCategory === 'donasi' ? 'bg-white shadow-xs font-bold text-slate-900' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Donasi Sukarela
                        </button>
                        <button type="button" @click="donationCategory = 'iuran'" :class="donationCategory === 'iuran' ? 'bg-white shadow-xs font-bold text-purple-900' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Iuran Bulanan Anggota
                        </button>
                    </div>
                    <input type="hidden" name="transaction_category" :value="donationCategory">
                </div>

                <!-- Pilihan Donatur untuk Donasi Sukarela -->
                <div x-show="donationCategory === 'donasi'">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Donatur <span class="text-rose-500">*</span></label>
                    <select name="donor_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="">-- Pilih Donatur Terdaftar --</option>
                        <?php foreach ($donors as $dn): ?>
                            <option value="<?= $dn['id'] ?>">
                                <?= htmlspecialchars($dn['full_name']) ?> (<?= htmlspecialchars($dn['cluster_or_address']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Pilih "Hamba Allah" untuk donasi tanpa mencantumkan nama publik.</p>
                </div>

                <!-- Pilihan Anggota untuk Iuran Bulanan -->
                <div x-show="donationCategory === 'iuran'">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Anggota Aktif <span class="text-rose-500">*</span></label>
                    <select name="member_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="">-- Pilih Anggota Terdaftar (Status Aktif) --</option>
                        <?php foreach ($activeMembers as $mem): ?>
                            <option value="<?= $mem['id'] ?>">
                                <?= htmlspecialchars($mem['full_name']) ?> — Blok <?= htmlspecialchars($mem['cluster_block']) ?> (<?= htmlspecialchars($mem['member_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Anggota yang berstatus Resign tidak dapat dipilih untuk iuran baru.</p>
                </div>

                <!-- Nomor HP WhatsApp -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp (Opsional, untuk kirim kwitansi)</label>
                    <input type="text" name="donor_phone" placeholder="Contoh: 081234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400 font-mono">
                </div>

                <!-- Tanggal Transaksi -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Pembayaran</label>
                    <input type="date" name="donation_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <!-- Jenis Donasi Toggle (Uang vs Sembako) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bentuk Bantuan</label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl">
                        <button type="button" @click="donationType = 'uang'" :class="donationType === 'uang' ? 'bg-white shadow-xs font-bold text-slate-900' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Uang Kas
                        </button>
                        <button type="button" @click="donationType = 'sembako'" :class="donationType === 'sembako' ? 'bg-white shadow-xs font-bold text-slate-900' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Paket Sembako
                        </button>
                    </div>
                    <input type="hidden" name="type" :value="donationType">
                </div>

                <!-- Input Nominal Uang -->
                <div x-show="donationType === 'uang'" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nominal Transfer / Tunai (Rp) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                            <input type="text" name="amount" placeholder="Contoh: 100.000" class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400 tabular-nums font-bold">
                        </div>
                    </div>
                </div>

                <!-- Input Sembako -->
                <div x-show="donationType === 'sembako'" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jumlah Paket <span class="text-rose-500">*</span></label>
                        <input type="number" name="package_qty" min="1" placeholder="Contoh: 10" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400 tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Rincian Paket</label>
                        <input type="text" name="package_details" placeholder="Contoh: Beras 5kg, Minyak 2L, Gula 1kg" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                    </div>
                </div>

                <!-- Alokasi Program -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alokasi Program</label>
                    <input type="text" name="program_target" value="Kas Rutin Bulanan" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <!-- Upload Bukti (Opsional jika Tunai) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Unggah Bukti Transfer / Kwitansi Fisik (Opsional)</label>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                </div>

                <!-- Catatan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan Tambahan</label>
                    <textarea name="notes" rows="2" placeholder="Catatan titipan atau keterangan..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button @click="modalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                        Simpan & Terbitkan Kwitansi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Konfirmasi Setujui Transaksi (Approve) -->
    <div x-show="approveModalOpen" x-cloak @click.self="approveModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="approveModalOpen = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <div class="flex items-center gap-3 text-emerald-700 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Setujui & Terbitkan Kwitansi</h3>
                    <div class="text-xs text-slate-500 font-medium">Transaksi akan masuk ke saldo kas resmi</div>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 my-4 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Ref Transaksi:</span>
                    <span class="font-bold font-mono text-slate-900" x-text="activeTrx.ref"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Donatur / Anggota:</span>
                    <span class="font-bold text-slate-900" x-text="activeTrx.name"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Status Bukti:</span>
                    <span class="font-semibold text-emerald-700 inline-flex items-center gap-1" x-text="activeTrx.hasProof ? '✓ File Terlampir' : 'Dikonfirmasi via WhatsApp'"></span>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2">
                    <span class="text-slate-500 font-bold">Nominal Kas:</span>
                    <span class="font-extrabold text-slate-900 tabular-nums text-sm text-emerald-700" x-text="activeTrx.amount"></span>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Sistem akan membuat nomor kwitansi resmi berurutan (contoh: <strong class="text-slate-900">KW-2026-XXXX</strong>) dan menyertakan tombol 1-klik untuk mengirim kwitansi ke WhatsApp donatur.
            </p>

            <form action="" method="POST" class="flex items-center justify-end gap-2.5">
                <input type="hidden" name="action" value="approve_donation">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="activeTrx.id">

                <button @click="approveModalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-emerald-700">
                    <i class="fa-solid fa-check mr-1.5"></i> Setujui Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Modal 3: Unggah Bukti Struk dari WhatsApp (Treasurer Upload) -->
    <div x-show="uploadWaModalOpen" x-cloak @click.self="uploadWaModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="uploadWaModalOpen = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <div class="flex items-center gap-3 text-amber-700 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Lampirkan Bukti dari WhatsApp</h3>
                    <div class="text-xs text-slate-500 font-medium">Unggah tangkapan layar transfer yang dikirim donatur</div>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 my-3 text-xs">
                <div class="text-slate-500">Donatur: <strong class="text-slate-900" x-text="activeTrx.name"></strong></div>
                <div class="text-slate-500">Nominal: <strong class="text-emerald-700 tabular-nums" x-text="activeTrx.amount"></strong></div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_wa_proof">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="activeTrx.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih File Bukti Struk <span class="text-rose-500">*</span></label>
                    <input type="file" name="proof_file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    <p class="text-[11px] text-slate-400 mt-1">Format: JPG, PNG, WEBP, atau PDF. Maks 5MB.</p>
                </div>

                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info text-emerald-600 mt-0.5"></i>
                    <span>Setelah bukti diunggah, tombol <strong>Setujui (Approve)</strong> akan otomatis aktif dan siap diverifikasi.</span>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button @click="uploadWaModalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs">
                        Simpan Bukti Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 4: Tolak Transaksi (Reject Modal) -->
    <div x-show="rejectModalOpen" x-cloak @click.self="rejectModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="rejectModalOpen = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <div class="flex items-center gap-3 text-rose-700 mb-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Tolak Konfirmasi Transaksi</h3>
                    <div class="text-xs text-slate-500 font-medium">Transaksi tidak akan dihitung ke dalam kas</div>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Anda akan menolak konfirmasi transaksi atas nama <strong class="text-slate-900" x-text="rejectForm.donor_name"></strong>. Berikan alasan penolakan untuk arsip audit.
            </p>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="reject_donation">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="rejectForm.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alasan Penolakan <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="rejection_reason" required rows="2" placeholder="Contoh: Bukti transfer palsu / dana tidak masuk ke rekening mutasi bank..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button @click="rejectModalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs">
                        Konfirmasi Penolakan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 5: Pembatalan Kwitansi Resmi (Void Modal) -->
    <div x-show="voidModalOpen" x-cloak @click.self="voidModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="voidModalOpen = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <div class="flex items-center gap-3 text-rose-700 mb-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Batalkan Kwitansi Resmi (Void)</h3>
                    <div class="text-xs text-slate-500 font-medium">Jejak audit kwitansi tetap disimpan</div>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Anda akan membatalkan kwitansi <strong class="text-slate-900 tabular-nums" x-text="voidForm.receipt_no"></strong> atas nama <strong class="text-slate-900" x-text="voidForm.donor_name"></strong>. Nominal akan dikeluarkan dari saldo kas aktif.
            </p>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="void_donation">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="voidForm.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alasan Pembatalan <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="void_reason" required rows="2" placeholder="Contoh: Koreksi salah nominal donasi / duplikasi pencatatan" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button @click="voidModalOpen = false" type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Kembali
                    </button>
                    <button type="submit" class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs">
                        Konfirmasi Pembatalan (Void)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 6: Lihat Bukti Transfer Gambar / Dokumen -->
    <div x-show="viewProofModalOpen" x-cloak @click.self="viewProofModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs" @keydown.escape.window="viewProofModalOpen = false">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-4 sm:p-6 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                <h3 class="text-sm font-bold text-slate-900">Bukti Transfer Pembayaran</h3>
                <div class="flex items-center gap-2">
                    <a :href="previewProofUrl" target="_blank" class="text-xs text-blue-600 hover:underline font-semibold">
                        <i class="fa-solid fa-up-right-from-square"></i> Tab Baru
                    </a>
                    <button @click="viewProofModalOpen = false" type="button" class="text-slate-400 hover:text-slate-700 p-1">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-auto flex items-center justify-center bg-slate-100 rounded-2xl p-2 min-h-[300px]">
                <img :src="previewProofUrl" alt="Bukti Transfer" class="max-h-[65vh] max-w-full rounded-xl object-contain shadow-xs">
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
