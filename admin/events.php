<?php
/**
 * Modul F.1: Manajemen Penyaluran Kas (5 Kategori: Santunan Yatim, Sembako, Event, Pinjaman, Lainnya)
 * Alur 2 Tahap:
 * 1. Direncanakan (Komitmen Anggaran, belum memotong kas riil)
 * 2. Tersalurkan (Dana telah diserahkan fisik / transfer, memotong saldo kas riil)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Tambah / Edit / Update Status Penyaluran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Token keamanan (CSRF) tidak valid. Silakan coba kembali.');
        header('Location: ' . getBaseUrl() . '/admin/events.php');
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $eventName = trim($_POST['event_name'] ?? '');
        $eventDate = $_POST['event_date'] ?: date('Y-m-d');
        $programType = $_POST['program_type'] ?? 'santunan_yatim';
        $totalCashOut = parseRupiahInput($_POST['total_cash_out'] ?? 0);
        $totalPackagesOut = (int)($_POST['total_packages_out'] ?? 0);
        $disbursementStatus = in_array($_POST['disbursement_status'] ?? '', ['direncanakan', 'tersalurkan']) ? $_POST['disbursement_status'] : 'direncanakan';
        $disbursedTo = trim($_POST['disbursed_to'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($eventName)) {
            setFlash('error', 'Nama kegiatan penyaluran wajib diisi!');
        } else {
            if ($action === 'create') {
                $disbursedAt = ($disbursementStatus === 'tersalurkan') ? date('Y-m-d H:i:s') : null;

                $stmt = $pdo->prepare("
                    INSERT INTO distribution_events (
                        event_name, event_date, program_type, total_cash_out, total_packages_out, 
                        disbursement_status, disbursed_at, disbursed_to, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $eventName, $eventDate, $programType, $totalCashOut, $totalPackagesOut,
                    $disbursementStatus, $disbursedAt, $disbursedTo, $notes
                ]);
                $newEventId = $pdo->lastInsertId();

                // Auto-isi daftar penerima yang aktif secara global (tanpa tersekat kategori)
                $selectedBenIds = [];
                if (!empty($_POST['selected_beneficiaries_json'])) {
                    $decoded = json_decode($_POST['selected_beneficiaries_json'], true);
                    if (is_array($decoded)) {
                        $selectedBenIds = array_filter(array_map('intval', $decoded));
                    }
                }
                if (empty($selectedBenIds) && isset($_POST['selected_beneficiaries']) && is_array($_POST['selected_beneficiaries'])) {
                    $selectedBenIds = array_filter(array_map('intval', $_POST['selected_beneficiaries']));
                }
                $hasSelectionControl = isset($_POST['beneficiaries_selection_active']);

                if ($hasSelectionControl) {
                    if (!empty($selectedBenIds)) {
                        $inClause = implode(',', array_fill(0, count($selectedBenIds), '?'));
                        $stmtB = $pdo->prepare("SELECT id, full_name, category FROM beneficiaries WHERE id IN ({$inClause}) AND status = 'layak_aktif'");
                        $stmtB->execute($selectedBenIds);
                        $bens = $stmtB->fetchAll();
                    } else {
                        $bens = [];
                    }
                } else {
                    // Fallback jika form tidak menyertakan kontrol checkbox (default berdasarkan kategori program)
                    if ($programType === 'santunan_yatim') {
                        $bens = $pdo->query("SELECT id, full_name, category FROM beneficiaries WHERE category = 'yatim' AND status = 'layak_aktif'")->fetchAll();
                    } elseif ($programType === 'sembako') {
                        $bens = $pdo->query("SELECT id, full_name, category FROM beneficiaries WHERE status = 'layak_aktif'")->fetchAll();
                    } else {
                        $bens = [];
                    }
                }

                if (!empty($bens)) {
                    $benCount = count($bens);
                    $cashPerPerson = ($totalCashOut > 0 && $benCount > 0) ? round($totalCashOut / $benCount) : 0;
                    
                    if ($totalPackagesOut > 0) {
                        $packageDetail = "1 Paket Bantuan Sembako (Total: {$totalPackagesOut} Paket)";
                    } elseif ($programType === 'santunan_yatim') {
                        $packageDetail = 'Santunan Tunai & Perlengkapan';
                    } elseif ($programType === 'sembako') {
                        $packageDetail = '1 Paket Sembako Lengkap';
                    } else {
                        $packageDetail = 'Bantuan / Santunan Program';
                    }

                    $logStatus = ($disbursementStatus === 'tersalurkan') ? 'distributed' : 'pending';
                    $logTime = ($disbursementStatus === 'tersalurkan') ? date('Y-m-d H:i:s') : null;
                    $insLog = $pdo->prepare("INSERT INTO distribution_logs (event_id, beneficiary_id, cash_amount, sembako_package_detail, status, distributed_at) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($bens as $b) {
                        $insLog->execute([$newEventId, $b['id'], $cashPerPerson, $packageDetail, $logStatus, $logTime]);
                    }
                }

                setFlash('success', "Kegiatan penyaluran '{$eventName}' berhasil dibuat dengan status " . ucfirst($disbursementStatus) . ".");
            } elseif ($action === 'update') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("
                    UPDATE distribution_events
                    SET event_name = ?, event_date = ?, program_type = ?, total_cash_out = ?, total_packages_out = ?, 
                        disbursement_status = ?, disbursed_to = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $eventName, $eventDate, $programType, $totalCashOut, $totalPackagesOut,
                    $disbursementStatus, $disbursedTo, $notes, $id
                ]);
                setFlash('success', 'Data kegiatan penyaluran kas berhasil diperbarui.');
            }
            header('Location: ' . getBaseUrl() . '/admin/events.php');
            exit;
        }
    } elseif ($action === 'mark_disbursed') {
        $id = (int)$_POST['id'];
        $disbursedAt = $_POST['disbursed_at'] ?: date('Y-m-d H:i:s');
        $disbursedTo = trim($_POST['disbursed_to'] ?? '');
        $markAllLogs = isset($_POST['mark_all_logs']) && $_POST['mark_all_logs'] === '1';

        // Cek apakah ada logs pada acara ini, jika ada, sinkronkan total_cash_out & total_packages_out dengan checklist
        $stLogSum = $pdo->prepare("
            SELECT 
                COUNT(*) as cnt, 
                COALESCE(SUM(cash_amount), 0) as total_cash,
                COUNT(CASE WHEN sembako_package_detail IS NOT NULL AND TRIM(sembako_package_detail) != '' THEN 1 END) as total_pkg
            FROM distribution_logs 
            WHERE event_id = ?
        ");
        $stLogSum->execute([$id]);
        $logSummary = $stLogSum->fetch(PDO::FETCH_ASSOC);

        if ($logSummary && (int)$logSummary['cnt'] > 0) {
            $stmt = $pdo->prepare("
                UPDATE distribution_events 
                SET disbursement_status = 'tersalurkan', disbursed_at = ?, disbursed_to = ?, total_cash_out = ?, total_packages_out = ?
                WHERE id = ?
            ");
            $stmt->execute([$disbursedAt, $disbursedTo, (float)$logSummary['total_cash'], (int)$logSummary['total_pkg'], $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE distribution_events 
                SET disbursement_status = 'tersalurkan', disbursed_at = ?, disbursed_to = ?
                WHERE id = ?
            ");
            $stmt->execute([$disbursedAt, $disbursedTo, $id]);
        }

        if ($markAllLogs) {
            $pdo->prepare("
                UPDATE distribution_logs 
                SET status = 'distributed', distributed_at = ?
                WHERE event_id = ? AND status = 'pending'
            ")->execute([$disbursedAt, $id]);
        }

        setFlash('success', 'Kegiatan berhasil ditandai sebagai Tersalurkan dan dicatat ke dalam realisasi kas keluar.');
        header('Location: ' . getBaseUrl() . '/admin/events.php');
        exit;
    } elseif ($action === 'mark_planned') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("
            UPDATE distribution_events 
            SET disbursement_status = 'direncanakan', disbursed_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        setFlash('success', 'Status kegiatan dikembalikan ke Direncanakan.');
        header('Location: ' . getBaseUrl() . '/admin/events.php');
        exit;
    } elseif ($action === 'delete') {
        $delId = (int)$_POST['id'];
        // Cek log penyerahan
        $checkLogs = $pdo->prepare("SELECT COUNT(*) FROM distribution_logs WHERE event_id = ? AND status = 'distributed'");
        $checkLogs->execute([$delId]);
        $alreadyDistributed = (int)$checkLogs->fetchColumn();

        if ($alreadyDistributed > 0) {
            setFlash('error', "Kegiatan tidak dapat dihapus karena sudah ada {$alreadyDistributed} bantuan yang telah diserahkan di lapangan.");
        } else {
            $pdo->prepare("DELETE FROM distribution_logs WHERE event_id = ?")->execute([$delId]);
            $stmt = $pdo->prepare("DELETE FROM distribution_events WHERE id = ?");
            $stmt->execute([$delId]);
            setFlash('success', 'Kegiatan penyaluran berhasil dihapus.');
        }
        header('Location: ' . getBaseUrl() . '/admin/events.php');
        exit;
    }
}

// Filter Status, Program, Periode Bulan, Periode Tahun & Pencarian
$statusFilter = $_GET['status'] ?? 'semua';
$progFilter = $_GET['program'] ?? 'semua';
$monthFilter = (int)($_GET['bulan'] ?? 0);
$yearFilter = trim($_GET['tahun'] ?? 'semua');
if ($yearFilter !== 'semua' && !ctype_digit($yearFilter)) {
    $yearFilter = 'semua';
}
$search = trim($_GET['q'] ?? '');

// Ambil daftar tahun unik dari agenda kegiatan penyaluran
$stmtYears = $pdo->query("SELECT DISTINCT DATE_FORMAT(event_date, '%Y') as yr FROM distribution_events WHERE event_date IS NOT NULL AND event_date != '0000-00-00' ORDER BY yr DESC");
$availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
$currentYear = date('Y');
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
}
$availableYears = array_values(array_filter(array_unique($availableYears), fn($y) => !empty($y) && is_numeric($y)));
rsort($availableYears);

$bulanList = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$whereClauses = ["1=1"];
$params = [];

if (in_array($statusFilter, ['direncanakan', 'tersalurkan'])) {
    $whereClauses[] = "e.disbursement_status = ?";
    $params[] = $statusFilter;
}

if (in_array($progFilter, ['santunan_yatim', 'sembako', 'event', 'pinjaman', 'lainnya'])) {
    $whereClauses[] = "e.program_type = ?";
    $params[] = $progFilter;
}

if ($monthFilter >= 1 && $monthFilter <= 12) {
    $whereClauses[] = "MONTH(e.event_date) = ?";
    $params[] = $monthFilter;
}

if ($yearFilter !== 'semua' && ctype_digit($yearFilter)) {
    $whereClauses[] = "DATE_FORMAT(e.event_date, '%Y') = ?";
    $params[] = $yearFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(e.event_name LIKE ? OR e.disbursed_to LIKE ? OR e.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(" AND ", $whereClauses);

// Data Events + Rekap Log Distribusi
$eventsStmt = $pdo->prepare("
    SELECT e.*, 
        COUNT(l.id) as total_recipients,
        SUM(CASE WHEN l.status = 'distributed' THEN 1 ELSE 0 END) as distributed_count,
        SUM(CASE WHEN l.status = 'distributed' THEN l.cash_amount ELSE 0 END) as distributed_cash
    FROM distribution_events e
    LEFT JOIN distribution_logs l ON e.id = l.event_id
    WHERE {$whereSql}
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.id DESC
");
$eventsStmt->execute($params);
$events = $eventsStmt->fetchAll();

// Total Summary Keseluruhan
$summaryYear = ($yearFilter !== 'semua' && ctype_digit($yearFilter)) ? (int)$yearFilter : date('Y');
$kasSummary = getKasSummary($pdo, $summaryYear);
$totalAnggaranDirencanakan = $kasSummary['dana_pending'] ?? 0;
$totalRealisasiKeluar = $kasSummary['total_realisasi_keluar'] ?? 0;
$totalSembakoKeluar = $kasSummary['total_sembako_tersalurkan'] ?? ($kasSummary['total_sembako_keluar'] ?? 0);

// Ambil data Penerima Manfaat Aktif beserta riwayat berapa kali menerima bantuan
$stmtActiveBens = $pdo->query("
    SELECT b.id, b.full_name, b.category, b.rt_rw, b.address,
        (SELECT COUNT(*) FROM distribution_logs dl 
         JOIN distribution_events de ON dl.event_id = de.id 
         WHERE dl.beneficiary_id = b.id AND (dl.status = 'distributed' OR de.disbursement_status = 'tersalurkan')) as received_count,
        (SELECT MAX(COALESCE(dl.distributed_at, de.event_date)) FROM distribution_logs dl 
         JOIN distribution_events de ON dl.event_id = de.id 
         WHERE dl.beneficiary_id = b.id AND (dl.status = 'distributed' OR de.disbursement_status = 'tersalurkan')) as last_received_at
    FROM beneficiaries b
    WHERE b.status = 'layak_aktif'
    ORDER BY b.category ASC, b.full_name ASC
");
$rawActiveBens = $stmtActiveBens->fetchAll(PDO::FETCH_ASSOC);
$activeBeneficiariesList = array_map(function($b) {
    return [
        'id' => (int)$b['id'],
        'full_name' => $b['full_name'],
        'category' => $b['category'],
        'rt_rw' => $b['rt_rw'],
        'address' => $b['address'],
        'received_count' => (int)$b['received_count'],
        'last_received_formatted' => !empty($b['last_received_at']) ? date('d/m/Y', strtotime($b['last_received_at'])) : null,
    ];
}, $rawActiveBens);

$pageTitle = 'Penyaluran Kas & Bantuan Lapangan';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: false, 
    disbursedModalOpen: false,
    deleteModal: false, 
    isEdit: false,
    allBeneficiaries: <?= htmlspecialchars(json_encode($activeBeneficiariesList, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
    selectedBenIds: [],
    beneficiaryFilter: 'semua',
    disbursedForm: { id: '', event_name: '', disbursed_at: '<?= date('Y-m-d') ?>', disbursed_to: '', hasChecklist: false },
    deleteForm: { id: '', name: '' },
    form: { 
        id: '', 
        event_name: '', 
        event_date: '<?= date('Y-m-d') ?>', 
        program_type: 'santunan_yatim', 
        total_cash_out: '1050000', 
        total_packages_out: '0', 
        disbursement_status: 'direncanakan',
        disbursed_to: '',
        notes: '' 
    },
    getFilteredBeneficiaries() {
        if (this.beneficiaryFilter === 'yatim') {
            return this.allBeneficiaries.filter(b => b.category === 'yatim');
        } else if (this.beneficiaryFilter === 'dhuafa_lansia') {
            return this.allBeneficiaries.filter(b => ['dhuafa', 'lansia'].includes(b.category));
        } else if (this.beneficiaryFilter === 'sembako') {
            return this.allBeneficiaries.filter(b => b.category === 'sembako');
        }
        return this.allBeneficiaries;
    },
    syncBeneficiaries() {
        if (!this.isEdit) {
            this.selectedBenIds = [];
        }
    },
    selectAll() {
        if (this.beneficiaryFilter === 'semua') {
            this.selectedBenIds = this.allBeneficiaries.map(b => Number(b.id));
        } else {
            const filteredIds = this.getFilteredBeneficiaries().map(b => Number(b.id));
            this.selectedBenIds = Array.from(new Set([...this.selectedBenIds, ...filteredIds]));
        }
    },
    unselectAll() {
        if (this.beneficiaryFilter === 'semua') {
            this.selectedBenIds = [];
        } else {
            const filteredIds = this.getFilteredBeneficiaries().map(b => Number(b.id));
            this.selectedBenIds = this.selectedBenIds.filter(id => !filteredIds.includes(Number(id)));
        }
    },
    toggleBeneficiary(id) {
        id = Number(id);
        if (this.selectedBenIds.includes(id)) {
            this.selectedBenIds = this.selectedBenIds.filter(x => x !== id);
        } else {
            this.selectedBenIds.push(id);
        }
    },
    calculatePerRecipient() {
        const count = this.selectedBenIds.length;
        const total = parseFloat(this.form.total_cash_out) || 0;
        if (count > 0 && total > 0) {
            return 'Rp ' + Math.round(total / count).toLocaleString('id-ID');
        }
        return 'Rp 0';
    }
}" class="flex-1 flex flex-col min-w-0 w-full">
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Penyaluran Kas & Bantuan</h1>
                <p class="text-xs text-slate-500 hidden sm:block">Alur 2 tahap: Rencana Anggaran → Realisasi Tersalurkan ke Penerima.</p>
            </div>
        </div>

        <button @click="isEdit = false; beneficiaryFilter = 'semua'; form = { id: '', event_name: '', event_date: '<?= date('Y-m-d') ?>', program_type: 'santunan_yatim', total_cash_out: '1050000', total_packages_out: '0', disbursement_status: 'direncanakan', disbursed_to: '', notes: '' }; syncBeneficiaries(); modalOpen = true" class="min-h-[44px] inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl transition shadow-xs focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
            <i class="fa-solid fa-plus mr-2 text-xs"></i>
            <span>Buat Agenda Penyaluran</span>
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

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Total Kegiatan Penyaluran</div>
                <div class="text-xl font-extrabold text-slate-900 tabular-nums">
                    <?= count($events) ?> Sesi
                </div>
                <div class="text-[11px] text-slate-400 mt-1">
                    <?= ($yearFilter !== 'semua') ? 'Tahun ' . htmlspecialchars($yearFilter) : 'Tahun Berjalan ' . date('Y') ?>
                    <?= ($monthFilter > 0) ? ' • ' . $bulanList[$monthFilter] : '' ?>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider mb-1">Anggaran Direncanakan</div>
                <div class="text-xl font-extrabold text-amber-700 tabular-nums">
                    <?= formatRupiah($totalAnggaranDirencanakan) ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">Belum memotong kas riil (<?= ($yearFilter !== 'semua') ? htmlspecialchars($yearFilter) : date('Y') ?>)</div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider mb-1">Realisasi Tersalurkan</div>
                <div class="text-xl font-extrabold text-emerald-700 tabular-nums">
                    <?= formatRupiah($totalRealisasiKeluar) ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">Telah diserahkan / keluar kas (<?= ($yearFilter !== 'semua') ? htmlspecialchars($yearFilter) : date('Y') ?>)</div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Paket Sembako Disalurkan</div>
                <div class="text-xl font-extrabold text-slate-900 tabular-nums">
                    <?= number_format($totalSembakoKeluar) ?> Paket
                </div>
                <div class="text-[11px] text-slate-400 mt-1">Total realisasi lapangan (<?= ($yearFilter !== 'semua') ? htmlspecialchars($yearFilter) : date('Y') ?>)</div>
            </div>
        </div>

        <!-- Filter Bar & Search Toolbar -->
        <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Status Tabs -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="<?= getBaseUrl() ?>/admin/events.php?status=semua&program=<?= urlencode($progFilter) ?>&bulan=<?= $monthFilter ?>&tahun=<?= urlencode($yearFilter) ?>&q=<?= urlencode($search) ?>" 
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $statusFilter === 'semua' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        Semua Status
                    </a>
                    <a href="<?= getBaseUrl() ?>/admin/events.php?status=direncanakan&program=<?= urlencode($progFilter) ?>&bulan=<?= $monthFilter ?>&tahun=<?= urlencode($yearFilter) ?>&q=<?= urlencode($search) ?>" 
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'direncanakan' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Direncanakan
                    </a>
                    <a href="<?= getBaseUrl() ?>/admin/events.php?status=tersalurkan&program=<?= urlencode($progFilter) ?>&bulan=<?= $monthFilter ?>&tahun=<?= urlencode($yearFilter) ?>&q=<?= urlencode($search) ?>" 
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $statusFilter === 'tersalurkan' ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Tersalurkan
                    </a>
                </div>

                <!-- Form Filter (Kategori, Bulan, Tahun) -->
                <form method="GET" class="inline-flex flex-wrap items-center gap-1.5">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">

                    <!-- Pilihan Program -->
                    <select name="program" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium rounded-xl px-3 py-1.5 min-h-[38px] focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="semua">Semua Program</option>
                        <option value="santunan_yatim" <?= $progFilter === 'santunan_yatim' ? 'selected' : '' ?>>Santunan Yatim</option>
                        <option value="sembako" <?= $progFilter === 'sembako' ? 'selected' : '' ?>>Bantuan Sembako</option>
                        <option value="event" <?= $progFilter === 'event' ? 'selected' : '' ?>>Event Organisasi</option>
                        <option value="pinjaman" <?= $progFilter === 'pinjaman' ? 'selected' : '' ?>>Pinjaman Qardhul Hasan</option>
                        <option value="lainnya" <?= $progFilter === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                    </select>

                    <!-- Pilihan Bulan -->
                    <select name="bulan" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium rounded-xl px-3 py-1.5 min-h-[38px] focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="0">Semua Bulan</option>
                        <?php foreach ($bulanList as $bNum => $bName): ?>
                            <option value="<?= $bNum ?>" <?= $monthFilter === $bNum ? 'selected' : '' ?>><?= $bName ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Pilihan Tahun -->
                    <select name="tahun" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium rounded-xl px-3 py-1.5 min-h-[38px] focus:outline-none focus:ring-1 focus:ring-slate-400">
                        <option value="semua">Semua Tahun</option>
                        <?php foreach ($availableYears as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= $yearFilter === (string)$yr ? 'selected' : '' ?>>Tahun <?= htmlspecialchars($yr) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($monthFilter > 0 || $yearFilter !== 'semua' || !empty($search) || $progFilter !== 'semua' || $statusFilter !== 'semua'): ?>
                        <a href="<?= getBaseUrl() ?>/admin/events.php" class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-xl border border-rose-200 transition" title="Reset semua filter periode & pencarian">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Pencarian Cepat Langsung -->
            <form method="GET" class="w-full lg:w-72 relative flex items-center">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="program" value="<?= htmlspecialchars($progFilter) ?>">
                <input type="hidden" name="bulan" value="<?= $monthFilter ?>">
                <input type="hidden" name="tahun" value="<?= htmlspecialchars($yearFilter) ?>">
                <div class="relative w-full">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama kegiatan / peruntukan..." class="w-full pl-9 pr-3 py-2 min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400">
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
                            <th class="py-3 px-4">Nama Kegiatan & Peruntukan</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tanggal</th>
                            <th class="py-3 px-4 whitespace-nowrap">Program Penyaluran</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Nominal Kas</th>
                            <th class="py-3 px-4 whitespace-nowrap text-center">Status & Progres</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Tindakan Pengurus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-clipboard-list text-3xl mb-2 text-slate-300 block"></i>
                                    Belum ada kegiatan penyaluran yang sesuai filter.<br>
                                    <?php if ($monthFilter > 0 || $yearFilter !== 'semua' || !empty($search) || $progFilter !== 'semua' || $statusFilter !== 'semua'): ?>
                                        <a href="<?= getBaseUrl() ?>/admin/events.php" class="mt-3 mr-2 inline-flex items-center text-xs text-slate-700 font-bold bg-slate-100 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-200 transition">
                                            <i class="fa-solid fa-rotate-left mr-1.5"></i> Reset Filter
                                        </a>
                                    <?php endif; ?>
                                    <button @click="isEdit = false; modalOpen = true" class="mt-3 inline-flex items-center text-xs text-bkrc-red-800 font-bold bg-rose-50 px-3.5 py-2 rounded-xl border border-rose-200 hover:bg-rose-100 transition">
                                        Buat Agenda Penyaluran Baru
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): ?>
                                <?php
                                $isTersalurkan = ($ev['disbursement_status'] === 'tersalurkan');
                                $totalRec = (int)($ev['total_recipients'] ?? 0);
                                $distRec = (int)($ev['distributed_count'] ?? 0);
                                $isChecklistType = ($totalRec > 0) || in_array($ev['program_type'], ['santunan_yatim', 'sembako']);
                                $pct = $totalRec > 0 ? round(($distRec / $totalRec) * 100) : ($isTersalurkan ? 100 : 0);

                                // Category Badge formatting
                                $catLabels = [
                                    'santunan_yatim' => ['Santunan Anak Yatim', 'bg-blue-50 text-blue-800 border-blue-200'],
                                    'sembako' => ['Bantuan Sembako', 'bg-amber-50 text-amber-800 border-amber-200'],
                                    'event' => ['Event Organisasi', 'bg-purple-50 text-purple-800 border-purple-200'],
                                    'pinjaman' => ['Pinjaman Qardh', 'bg-teal-50 text-teal-800 border-teal-200'],
                                    'lainnya' => ['Penyaluran Lainnya', 'bg-slate-100 text-slate-700 border-slate-200']
                                ];
                                $catInfo = $catLabels[$ev['program_type']] ?? [ucwords(str_replace('_', ' ', $ev['program_type'])), 'bg-slate-100 text-slate-700 border-slate-200'];
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <!-- Nama Kegiatan & Penerima -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($ev['event_name']) ?></div>
                                        <?php if (!empty($ev['disbursed_to'])): ?>
                                            <div class="text-xs text-emerald-800 font-medium mt-0.5 flex items-center gap-1">
                                                <i class="fa-solid fa-user-check text-[10px]"></i> Penerima/PIC: <?= htmlspecialchars($ev['disbursed_to']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($ev['notes'])): ?>
                                            <div class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($ev['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600 font-medium">
                                        <?= formatTanggalIndo($ev['event_date']) ?>
                                    </td>

                                    <!-- Kategori Program -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border <?= $catInfo[1] ?>">
                                            <?= $catInfo[0] ?>
                                        </span>
                                    </td>

                                    <!-- Nominal Anggaran Kas -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="font-extrabold text-slate-900 tabular-nums text-sm"><?= formatRupiah($ev['total_cash_out']) ?></div>
                                        <?php if ($ev['total_packages_out'] > 0): ?>
                                            <div class="text-xs text-amber-800 font-bold tabular-nums"><?= number_format($ev['total_packages_out']) ?> Paket</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status & Progres Penyaluran -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($isTersalurkan): ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 px-2.5 py-1 rounded-full">
                                                <i class="fa-solid fa-circle-check text-emerald-600"></i> Tersalurkan
                                            </span>
                                            <?php if ($isChecklistType && $totalRec > 0): ?>
                                                <div class="text-[10px] text-slate-500 font-medium mt-1 tabular-nums"><?= $distRec ?>/<?= $totalRec ?> Penerima Selesai</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-amber-100 border border-amber-300 px-2.5 py-1 rounded-full">
                                                <i class="fa-solid fa-clock text-amber-600"></i> Direncanakan
                                            </span>
                                            <?php if ($isChecklistType && $totalRec > 0): ?>
                                                <div class="w-24 bg-slate-100 h-1.5 rounded-full overflow-hidden border border-slate-200 mx-auto mt-1.5">
                                                    <div class="bg-amber-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                                                </div>
                                                <div class="text-[10px] text-slate-500 mt-0.5 tabular-nums"><?= $distRec ?>/<?= $totalRec ?> (<?= $pct ?>%)</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tindakan Pengurus -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Tombol Checklist HP jika jenisnya Santunan / Sembako -->
                                            <?php if ($isChecklistType): ?>
                                                <a href="<?= getBaseUrl() ?>/admin/distribution_checklist.php?event_id=<?= $ev['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-700 transition shadow-xs" title="Buka Checklist Lapangan di HP">
                                                    <i class="fa-solid fa-mobile-screen"></i> <span class="hidden sm:inline">Checklist</span>
                                                </a>
                                                <a href="<?= getBaseUrl() ?>/print_attendance.php?event_id=<?= $ev['id'] ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition" title="Cetak Absensi / Tanda Tangan Basah">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Tombol Tandai Tersalurkan (Jika masih Direncanakan) -->
                                            <?php if (!$isTersalurkan): ?>
                                                <button type="button" @click="disbursedForm = { id: '<?= $ev['id'] ?>', event_name: '<?= htmlspecialchars(addslashes($ev['event_name'])) ?>', disbursed_at: '<?= date('Y-m-d') ?>', disbursed_to: '<?= htmlspecialchars(addslashes($ev['disbursed_to'] ?? '')) ?>', hasChecklist: <?= $isChecklistType ? 'true' : 'false' ?> }; disbursedModalOpen = true" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-xs" title="Tandai Sudah Tersalurkan">
                                                    <i class="fa-solid fa-check-double"></i> Tandai Tersalur
                                                </button>
                                            <?php else: ?>
                                                <!-- Opsi Kembalikan ke Direncanakan -->
                                                <form action="" method="POST" class="inline" onsubmit="return confirm('Kembalikan status kegiatan ke Direncanakan? Nominal akan dikembalikan ke komitmen.');">
                                                    <input type="hidden" name="action" value="mark_planned">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                                    <button type="submit" class="px-2 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 border border-slate-200 transition" title="Kembalikan ke Direncanakan">
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Edit -->
                                            <button type="button" @click="isEdit = true; form = { id: '<?= $ev['id'] ?>', event_name: '<?= htmlspecialchars(addslashes($ev['event_name'])) ?>', event_date: '<?= $ev['event_date'] ?>', program_type: '<?= $ev['program_type'] ?>', total_cash_out: '<?= (int)$ev['total_cash_out'] ?>', total_packages_out: '<?= (int)$ev['total_packages_out'] ?>', disbursement_status: '<?= $ev['disbursement_status'] ?>', disbursed_to: '<?= htmlspecialchars(addslashes($ev['disbursed_to'] ?? '')) ?>', notes: '<?= htmlspecialchars(addslashes($ev['notes'] ?? '')) ?>' }; modalOpen = true" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition" title="Edit Kegiatan">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>

                                            <!-- Hapus -->
                                            <button type="button" @click="deleteForm = { id: '<?= $ev['id'] ?>', name: '<?= htmlspecialchars(addslashes($ev['event_name'])) ?>' }; deleteModal = true" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition" title="Hapus Kegiatan">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
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

    <!-- Modal Form Sesi Acara (Create / Edit) -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="modalOpen = false">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Agenda Penyaluran' : 'Buat Agenda Penyaluran Baru'"></h3>
                <button @click="modalOpen = false" type="button" aria-label="Tutup form" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Sesi Acara / Penyaluran <span class="text-rose-500">*</span></label>
                    <input type="text" name="event_name" x-model="form.event_name" required placeholder="Contoh: Santunan Yatim & Sembako Dhuafa Maret 2026" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal Pelaksanaan</label>
                        <input type="date" name="event_date" x-model="form.event_date" required class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Program Penyaluran</label>
                        <select name="program_type" x-model="form.program_type" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                            <option value="santunan_yatim">Santunan Anak Yatim</option>
                            <option value="sembako">Bantuan Sembako Warga</option>
                            <option value="event">Event Organisasi (Bukber/Anniversary)</option>
                            <option value="pinjaman">Pinjaman Qardhul Hasan</option>
                            <option value="lainnya">Lainnya / Bantuan Khusus</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nominal Kas (Rp) <span class="text-rose-500">*</span></label>
                        <input type="number" name="total_cash_out" x-model="form.total_cash_out" step="1000" placeholder="1050000" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition tabular-nums font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jumlah Paket Sembako</label>
                        <input type="number" name="total_packages_out" x-model="form.total_packages_out" placeholder="0" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition tabular-nums">
                    </div>
                </div>

                <!-- Blok Pemilihan Penerima Manfaat (Tampil Lengkap / Global pada saat Buat Agenda) -->
                <div x-show="!isEdit" class="border border-slate-200 rounded-2xl p-4 bg-slate-50/70 space-y-3">
                    <input type="hidden" name="beneficiaries_selection_active" value="1">
                    <input type="hidden" name="selected_beneficiaries_json" :value="JSON.stringify(selectedBenIds)">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200/80 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-bkrc-red-100 text-bkrc-red-800 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-users"></i>
                            </span>
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Penerima Manfaat</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-bkrc-red-800 border border-rose-200 tabular-nums" x-text="selectedBenIds.length + ' dari ' + allBeneficiaries.length + ' dipilih'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="selectAll()" class="font-bold text-bkrc-red-800 hover:text-bkrc-red-950 transition hover:underline">
                                Pilih Semua
                            </button>
                            <span class="text-slate-300">|</span>
                            <button type="button" @click="unselectAll()" class="font-medium text-slate-500 hover:text-slate-700 transition hover:underline">
                                Batal Pilih
                            </button>
                        </div>
                    </div>

                    <!-- Filter Kategori Cepat (Global) -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                        <span class="text-[11px] font-semibold text-slate-500 mr-1">Saring:</span>
                        <button type="button" @click="beneficiaryFilter = 'semua'" :class="beneficiaryFilter === 'semua' ? 'bg-slate-800 text-white font-bold' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'" class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer">
                            Semua (<span x-text="allBeneficiaries.length"></span>)
                        </button>
                        <button type="button" @click="beneficiaryFilter = 'yatim'" :class="beneficiaryFilter === 'yatim' ? 'bg-slate-800 text-white font-bold' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'" class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer">
                            Anak Yatim (<span x-text="allBeneficiaries.filter(b => b.category === 'yatim').length"></span>)
                        </button>
                        <button type="button" @click="beneficiaryFilter = 'dhuafa_lansia'" :class="beneficiaryFilter === 'dhuafa_lansia' ? 'bg-slate-800 text-white font-bold' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'" class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer">
                            Dhuafa & Lansia (<span x-text="allBeneficiaries.filter(b => ['dhuafa', 'lansia'].includes(b.category)).length"></span>)
                        </button>
                        <button type="button" @click="beneficiaryFilter = 'sembako'" :class="beneficiaryFilter === 'sembako' ? 'bg-slate-800 text-white font-bold' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'" class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer">
                            Penerima Sembako (<span x-text="allBeneficiaries.filter(b => b.category === 'sembako').length"></span>)
                        </button>
                    </div>

                    <!-- Daftar Checkbox Scrollable -->
                    <div class="max-h-56 overflow-y-auto space-y-1.5 pr-1 divide-y divide-slate-100">
                        <template x-for="b in getFilteredBeneficiaries()" :key="b.id">
                            <label class="flex items-center justify-between p-2.5 hover:bg-white rounded-xl cursor-pointer transition select-none border border-transparent hover:border-slate-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <input type="checkbox" name="selected_beneficiaries[]" :value="b.id" :checked="selectedBenIds.includes(Number(b.id))" @change="toggleBeneficiary(b.id)" class="w-4 h-4 rounded text-bkrc-red-800 focus:ring-bkrc-red-800 focus:ring-offset-0 border-slate-300 cursor-pointer">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs sm:text-sm font-bold text-slate-900 truncate" x-text="b.full_name"></span>
                                            <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-700" x-text="b.category"></span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 truncate mt-0.5">
                                            <span x-text="b.address"></span>
                                            <span x-show="b.rt_rw" x-text="' (' + b.rt_rw + ')'"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Badge Riwayat Penerimaan Bantuan -->
                                <div class="flex-shrink-0 text-right pl-3">
                                    <template x-if="b.received_count > 0">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 whitespace-nowrap" :title="'Terakhir disalurkan: ' + (b.last_received_formatted || '-')">
                                            <i class="fa-solid fa-hand-holding-heart text-emerald-600 text-[10px]"></i>
                                            <span x-text="b.received_count + 'x terima'"></span>
                                        </span>
                                    </template>
                                    <template x-if="b.received_count === 0">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200 whitespace-nowrap" title="Belum pernah tercatat menerima bantuan">
                                            <i class="fa-solid fa-clock-rotate-left text-slate-400 text-[10px]"></i>
                                            <span>0x (Baru)</span>
                                        </span>
                                    </template>
                                </div>
                            </label>
                        </template>
                        <template x-if="getFilteredBeneficiaries().length === 0">
                            <div class="p-6 text-center text-xs text-slate-400">
                                Tidak ditemukan data penerima aktif untuk saringan ini.
                            </div>
                        </template>
                    </div>

                    <!-- Estimasi Alokasi Kas Otomatis -->
                    <div x-show="selectedBenIds.length > 0 && parseFloat(form.total_cash_out || 0) > 0" class="p-2.5 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between text-xs text-amber-900">
                        <span class="flex items-center gap-1.5 font-medium">
                            <i class="fa-solid fa-calculator text-amber-700"></i>
                            Estimasi kas per penerima terpilih:
                        </span>
                        <span class="font-extrabold text-amber-950 tabular-nums text-sm" x-text="calculatePerRecipient()"></span>
                    </div>
                </div>

                <!-- Info untuk Edit Mode -->
                <div x-show="isEdit" class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-blue-600 text-sm"></i>
                        <span>Daftar penerima kegiatan ini dikelola melalui <strong>Lembar Checklist</strong>.</span>
                    </div>
                    <a :href="'<?= getBaseUrl() ?>/admin/distribution_checklist.php?event_id=' + form.id" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition whitespace-nowrap">
                        Buka Checklist &rarr;
                    </a>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Penerima / PIC Bantuan (Opsional)</label>
                    <input type="text" name="disbursed_to" x-model="form.disbursed_to" placeholder="Contoh: 3 Anak Yatim & 3 Dhuafa / Vendor Acara" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status Penyaluran</label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl">
                        <button type="button" @click="form.disbursement_status = 'direncanakan'" :class="form.disbursement_status === 'direncanakan' ? 'bg-white shadow-xs font-bold text-amber-800' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Direncanakan (Pending)
                        </button>
                        <button type="button" @click="form.disbursement_status = 'tersalurkan'" :class="form.disbursement_status === 'tersalurkan' ? 'bg-white shadow-xs font-bold text-emerald-800' : 'text-slate-600'" class="py-2 rounded-lg text-xs transition">
                            Tersalurkan (Realisasi Kas)
                        </button>
                    </div>
                    <input type="hidden" name="disbursement_status" :value="form.disbursement_status">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Lokasi / Pelaksanaan</label>
                    <textarea name="notes" x-model="form.notes" rows="2" placeholder="Pendopo cluster, musholla, no rekening vendor..." class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <template x-if="!isEdit && selectedBenIds.length === 0">
                            <span class="text-[11px] text-amber-700 font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                                <span>0 penerima dipilih (Checklist akan kosong)</span>
                            </span>
                        </template>
                        <template x-if="!isEdit && selectedBenIds.length > 0">
                            <span class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                                <span x-text="selectedBenIds.length + ' penerima siap masuk checklist'"></span>
                            </span>
                        </template>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none">Batal</button>
                        <button type="submit" @click="if(!isEdit && selectedBenIds.length === 0 && !confirm('Perhatian: Anda belum memilih penerima manfaat (0 dipilih). Apakah Anda yakin ingin membuat kegiatan dengan checklist kosong?')) { $event.preventDefault(); }" class="min-h-[44px] bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-xs transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">Simpan Penyaluran</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Tandai Tersalurkan -->
    <div x-show="disbursedModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="disbursedModalOpen = false">
        <div @click.away="disbursedModalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7">
            <div class="flex items-center gap-3 text-emerald-700 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Konfirmasi Penyaluran Kas</h3>
                    <div class="text-xs text-slate-500 font-medium">Bantuan telah diserahkan di lapangan</div>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-3">
                Anda akan menandai kegiatan <strong class="text-slate-900" x-text="disbursedForm.event_name"></strong> sebagai <strong>Tersalurkan</strong>. Nominal kegiatan ini akan resmi tercatat sebagai kas keluar dalam pembukuan LPJ.
            </p>

            <form action="" method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="mark_disbursed">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" :value="disbursedForm.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal Disalurkan</label>
                    <input type="date" name="disbursed_at" x-model="disbursedForm.disbursed_at" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Diterima Oleh / PIC Penerima</label>
                    <input type="text" name="disbursed_to" x-model="disbursedForm.disbursed_to" placeholder="Contoh: Penerima santunan / PIC panitia" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-400">
                </div>

                <div x-show="disbursedForm.hasChecklist" class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-start gap-2">
                    <input type="checkbox" name="mark_all_logs" value="1" id="mark_all_logs" checked class="mt-0.5 rounded text-bkrc-red-800">
                    <label for="mark_all_logs" class="cursor-pointer">Tandai seluruh penerima di dalam checklist lapangan sebagai telah menerima bantuan.</label>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" @click="disbursedModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Batal</button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-xs transition">Konfirmasi Tersalurkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Kegiatan -->
    <div x-show="deleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @keydown.escape.window="deleteModal = false">
        <div @click.away="deleteModal = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 mx-auto flex items-center justify-center text-xl mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">Hapus Kegiatan Penyaluran?</h3>
                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                    Anda akan menghapus kegiatan <strong x-text="deleteForm.name"></strong>. Data checklist yang belum diserahkan akan turut dihapus.
                </p>
                <form action="" method="POST" class="flex items-center justify-center gap-3">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="deleteForm.id">
                    <button type="button" @click="deleteModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">Batal</button>
                    <button type="submit" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-xs transition">Ya, Hapus Kegiatan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
