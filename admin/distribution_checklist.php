<?php
/**
 * Modul F.2: Checklist Penyaluran di Lapangan (Mobile-Friendly untuk HP Pengurus)
 * Pengurus dapat mencatat penyerahan bantuan secara real-time dengan konfirmasi bukti serah terima.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

$eventId = (int)($_GET['event_id'] ?? 0);
$stmtEvent = $pdo->prepare("SELECT * FROM distribution_events WHERE id = ?");
$stmtEvent->execute([$eventId]);
$event = $stmtEvent->fetch();

if (!$event) {
    setFlash('error', 'Sesi acara penyaluran tidak ditemukan.');
    header('Location: ' . getBaseUrl() . '/admin/events.php');
    exit;
}

// Handle Mutasi Penyerahan / Batalkan (POST dengan CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid. Silakan ulangi kembali.');
        header("Location: " . getBaseUrl() . "/admin/distribution_checklist.php?event_id={$eventId}");
        exit;
    }

    if ($action === 'submit_handover') {
        $logId = (int)$_POST['log_id'];
        $receivedBy = trim($_POST['received_by'] ?? 'Penerima Langsung');
        $handoverNotes = trim($_POST['handover_notes'] ?? '');
        $combinedNotes = "Diterima oleh: {$receivedBy}" . ($handoverNotes ? " | Ket: {$handoverNotes}" : "");

        $stmtUpdate = $pdo->prepare("
            UPDATE distribution_logs 
            SET status = 'distributed', distributed_at = NOW(), notes = ?
            WHERE id = ? AND event_id = ?
        ");
        $stmtUpdate->execute([$combinedNotes, $logId, $eventId]);

        // Cek apakah seluruh penerima pada kegiatan ini sudah tersalurkan
        $chkPending = $pdo->prepare("SELECT COUNT(*) FROM distribution_logs WHERE event_id = ? AND status = 'pending'");
        $chkPending->execute([$eventId]);
        if ((int)$chkPending->fetchColumn() === 0) {
            $pdo->prepare("UPDATE distribution_events SET disbursement_status = 'tersalurkan', disbursed_at = NOW() WHERE id = ?")->execute([$eventId]);
        }

        setFlash('success', 'Bantuan berhasil diserahkan dan tercatat dalam bukti pertanggungjawaban!');
        header("Location: " . getBaseUrl() . "/admin/distribution_checklist.php?event_id={$eventId}");
        exit;
    }

    if ($action === 'cancel_handover') {
        $logId = (int)$_POST['log_id'];
        $stmtUpdate = $pdo->prepare("
            UPDATE distribution_logs 
            SET status = 'pending', distributed_at = NULL, notes = NULL
            WHERE id = ? AND event_id = ?
        ");
        $stmtUpdate->execute([$logId, $eventId]);

        setFlash('success', 'Status penyerahan berhasil dibatalkan dan dikembalikan ke antrean pending.');
        header("Location: " . getBaseUrl() . "/admin/distribution_checklist.php?event_id={$eventId}");
        exit;
    }

    if ($action === 'add_recipient') {
        $benId = (int)$_POST['beneficiary_id'];
        $cashAmount = parseRupiahInput($_POST['cash_amount'] ?? 0);
        $packageDetail = trim($_POST['sembako_package_detail'] ?? '');

        // Cek apakah sudah ada di log acara ini
        $chk = $pdo->prepare("SELECT id FROM distribution_logs WHERE event_id = ? AND beneficiary_id = ?");
        $chk->execute([$eventId, $benId]);
        if ($chk->fetch()) {
            setFlash('error', 'Penerima ini sudah terdaftar di dalam checklist kegiatan ini!');
        } else {
            $ins = $pdo->prepare("
                INSERT INTO distribution_logs (event_id, beneficiary_id, cash_amount, sembako_package_detail, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $ins->execute([$eventId, $benId, $cashAmount, $packageDetail]);
            setFlash('success', 'Penerima berhasil ditambahkan ke checklist kegiatan.');
        }
        header("Location: " . getBaseUrl() . "/admin/distribution_checklist.php?event_id={$eventId}");
        exit;
    }
}

// Ambil Checklist Penerima pada Acara Ini
$stmtLogs = $pdo->prepare("
    SELECT l.*, b.full_name, b.nik, b.birth_date, b.guardian_name, b.address, b.rt_rw, b.category, b.phone
    FROM distribution_logs l
    JOIN beneficiaries b ON l.beneficiary_id = b.id
    WHERE l.event_id = ?
    ORDER BY l.status ASC, b.full_name ASC
");
$stmtLogs->execute([$eventId]);
$logs = $stmtLogs->fetchAll();

// Hitung Progres
$totalBeneficiaries = count($logs);
$distributedCount = 0;
$totalCashAllocated = 0;
$totalCashDistributed = 0;

foreach ($logs as $l) {
    $totalCashAllocated += (float)$l['cash_amount'];
    if ($l['status'] === 'distributed') {
        $distributedCount++;
        $totalCashDistributed += (float)$l['cash_amount'];
    }
}
$pendingCount = $totalBeneficiaries - $distributedCount;
$percentage = $totalBeneficiaries > 0 ? round(($distributedCount / $totalBeneficiaries) * 100) : 0;

// Penerima yang belum ada di acara ini untuk dropdown tambah
$allBeneficiaries = $pdo->query("
    SELECT b.id, b.full_name, b.category, b.address,
        (SELECT COUNT(*) FROM distribution_logs dl 
         JOIN distribution_events de ON dl.event_id = de.id 
         WHERE dl.beneficiary_id = b.id AND (dl.status = 'distributed' OR de.disbursement_status = 'tersalurkan')) as received_count
    FROM beneficiaries b 
    WHERE b.status = 'layak_aktif' 
    ORDER BY b.full_name ASC
")->fetchAll();

$pageTitle = 'Checklist Penyaluran Lapangan';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    addModal: false, 
    handoverModal: false, 
    undoModal: false,
    activeLog: { id: null, name: '', category: '', cashText: '', sembakoText: '', notes: '' },
    openHandover(l) {
        this.activeLog = l;
        this.handoverModal = true;
    },
    openUndo(l) {
        this.activeLog = l;
        this.undoModal = true;
    }
}" @keydown.escape.window="addModal = false; handoverModal = false; undoModal = false" class="flex-1 flex flex-col w-full">

    <!-- Mobile-Optimized Topbar -->
    <header class="bg-white border-b border-slate-200 py-3 px-4 sm:px-6 sticky top-0 z-30 shadow-xs flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <button @click="sidebarOpen = true" 
                    type="button" 
                    class="lg:hidden text-slate-600 hover:text-slate-900 min-h-[44px] min-w-[44px] -ml-2 inline-flex items-center justify-center p-2 rounded-xl hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" 
                    aria-label="Buka Menu Navigasi">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <a href="<?= getBaseUrl() ?>/admin/events.php" class="text-slate-700 hover:text-slate-900 min-w-[44px] min-h-[44px] inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Kembali ke daftar acara">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-sm sm:text-base font-extrabold text-slate-900 truncate max-w-[180px] xs:max-w-xs sm:max-w-md">
                    <?= htmlspecialchars($event['event_name']) ?>
                </h1>
                <div class="text-[11px] text-slate-500 font-medium">Checklist Penyaluran Lapangan</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= getBaseUrl() ?>/print_attendance.php?event_id=<?= $eventId ?>" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2.5 min-h-[44px] rounded-xl inline-flex items-center gap-1.5 border border-slate-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Lembar Absensi</span>
            </a>
            <button @click="addModal = true" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white text-xs sm:text-sm font-semibold px-4 py-2.5 min-h-[44px] rounded-xl inline-flex items-center justify-center shadow-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                <span>Tambah Penerima</span>
            </button>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5 max-w-5xl mx-auto w-full">
        <!-- Flash Messages -->
        <?php if ($flash = getFlash('success')): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash = getFlash('error')): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Progress Card (Solid Matte Hero) -->
        <div class="bg-slate-900 text-white p-5 rounded-2xl border border-slate-800 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs text-slate-300 font-medium">
                    <i class="fa-regular fa-calendar mr-1 text-amber-400"></i> <?= formatTanggalIndo($event['event_date'], true) ?>
                </div>
                <span class="text-xs font-bold text-amber-300 tabular-nums"><?= $distributedCount ?> dari <?= $totalBeneficiaries ?> Penerima Diserahkan</span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden p-0.5 mb-4">
                <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: <?= $percentage ?>%"></div>
            </div>

            <!-- 3 Mini Counters -->
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="bg-white/10 rounded-xl p-2.5">
                    <div class="text-lg sm:text-xl font-extrabold text-white tabular-nums"><?= $totalBeneficiaries ?></div>
                    <div class="text-[10px] text-slate-300 font-medium">Total Terdaftar</div>
                </div>
                <div class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-2.5">
                    <div class="text-lg sm:text-xl font-extrabold text-emerald-300 tabular-nums"><?= $distributedCount ?></div>
                    <div class="text-[10px] text-emerald-200 font-semibold">Sudah Diserahkan</div>
                </div>
                <div class="bg-amber-500/20 border border-amber-500/30 rounded-xl p-2.5">
                    <div class="text-lg sm:text-xl font-extrabold text-amber-300 tabular-nums"><?= $pendingCount ?></div>
                    <div class="text-[10px] text-amber-200 font-semibold">Belum / Pending</div>
                </div>
            </div>
        </div>

        <!-- Section Title -->
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">
                Daftar Penerima Santunan
            </h2>
            <span class="text-xs text-slate-500 font-medium">Konfirmasi serah terima sebelum mencatat bukti</span>
        </div>

        <!-- Beneficiary Checklist Cards -->
        <div class="space-y-3">
            <?php if (empty($logs)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-slate-400 border border-slate-200">
                    <i class="fa-solid fa-list-check text-4xl mb-2 text-slate-300 block"></i>
                    Belum ada penerima di checklist kegiatan ini. Klik tombol <strong>Tambah Penerima</strong> di atas.
                </div>
            <?php else: ?>
                <?php foreach ($logs as $idx => $l): ?>
                    <?php
                    $isDistributed = ($l['status'] === 'distributed');
                    $ageData = calculateBeneficiaryAge($l['birth_date']);
                    
                    // Bersihkan alamat agar tidak ada duplikasi RT/RW
                    $cleanAddress = htmlspecialchars($l['address']);
                    if (!empty($l['rt_rw']) && stripos($cleanAddress, 'RT') === false && stripos($cleanAddress, 'RW') === false) {
                        $cleanAddress .= ' (' . htmlspecialchars($l['rt_rw']) . ')';
                    }

                    $jsLogObj = json_encode([
                        'id' => (int)$l['id'],
                        'name' => $l['full_name'],
                        'category' => ucfirst($l['category']),
                        'cashAmount' => (float)$l['cash_amount'],
                        'cashText' => formatRupiah($l['cash_amount']),
                        'sembakoText' => $l['sembako_package_detail'] ?: '–',
                        'notes' => $l['notes'] ?? ''
                    ]);
                    ?>
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border transition shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 <?= $isDistributed ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 hover:border-slate-300' ?>">
                        <div class="flex items-start gap-3.5">
                            <!-- Number / Status Icon -->
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0 <?= $isDistributed ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                <?php if ($isDistributed): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php else: ?>
                                    <span class="tabular-nums"><?= $idx + 1 ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Penerima Info -->
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= ucfirst($l['category']) ?>
                                    </span>
                                    <?php if ($l['category'] === 'yatim'): ?>
                                        <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-md <?= $ageData['badge_class'] ?>">
                                            <?= $ageData['text'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-base font-bold text-slate-900">
                                    <?= htmlspecialchars($l['full_name']) ?>
                                </h3>

                                <div class="text-xs text-slate-600 mt-0.5 space-y-0.5">
                                    <?php if (!empty($l['guardian_name']) && $l['guardian_name'] !== '-'): ?>
                                        <div><i class="fa-solid fa-user-shield text-slate-400 mr-1 text-[10px]"></i> Wali: <strong><?= htmlspecialchars($l['guardian_name']) ?></strong></div>
                                    <?php endif; ?>
                                    <div><i class="fa-solid fa-location-dot text-slate-400 mr-1 text-[10px]"></i> <?= $cleanAddress ?></div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        NIK: <?= maskNik($l['nik']) ?>
                                    </div>
                                </div>

                                <!-- Detail Bantuan yang Diterima -->
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <?php if ($l['cash_amount'] > 0): ?>
                                        <span class="bg-slate-100 text-slate-800 text-xs font-bold tabular-nums px-2.5 py-1 rounded-md border border-slate-200">
                                            <?= formatRupiah($l['cash_amount']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($l['sembako_package_detail'])): ?>
                                        <span class="bg-amber-100 text-amber-900 text-xs font-medium px-2.5 py-1 rounded-md">
                                            <i class="fa-solid fa-box text-[10px] mr-1"></i> <?= htmlspecialchars($l['sembako_package_detail']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($l['notes'])): ?>
                                    <div class="text-[11px] text-emerald-800 mt-2 bg-emerald-50 px-2 py-1 rounded border border-emerald-200/60 inline-flex items-center gap-1">
                                        <i class="fa-solid fa-circle-info text-emerald-600 text-[10px]"></i>
                                        <span><?= htmlspecialchars($l['notes']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Button: Trigger Modal Konfirmasi Penyerahan atau Undo -->
                        <div class="flex items-center justify-between sm:justify-end gap-3 border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                            <?php if ($isDistributed): ?>
                                <div class="text-right">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1.5 rounded-md border border-emerald-200/60">
                                        <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i> Sudah Diserahkan
                                    </span>
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        <?= $l['distributed_at'] ? date('H:i \W\I\B', strtotime($l['distributed_at'])) : '' ?>
                                    </div>
                                </div>
                                <button type="button" @click="openUndo(<?= htmlspecialchars($jsLogObj) ?>)" class="min-w-[44px] min-h-[44px] inline-flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 text-sm rounded-xl border border-transparent hover:border-rose-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500" title="Batalkan status serah" aria-label="Batalkan penyerahan bantuan">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" @click="openHandover(<?= htmlspecialchars($jsLogObj) ?>)" class="w-full sm:w-auto min-h-[44px] bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl shadow-xs transition flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                                    <i class="fa-solid fa-hand-holding-heart"></i>
                                    <span>Serahkan Bantuan</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Konfirmasi Penyerahan Bantuan (Pertanggungjawaban & Bukti) -->
    <div x-show="handoverModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div @click.away="handoverModal = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <div class="flex items-center gap-2 text-bkrc-red-800 font-bold text-base">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    <span>Konfirmasi Serah Terima</span>
                </div>
                <button @click="handoverModal = false" class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="submit_handover">
                <input type="hidden" name="log_id" :value="activeLog.id">

                <!-- Ringkasan Target Penyerahan -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="text-xs text-slate-500 font-medium">Penerima Bantuan:</div>
                    <div class="text-sm font-extrabold text-slate-900 mt-0.5" x-text="activeLog.name"></div>
                    <div class="text-xs text-slate-600 mt-1 flex items-center gap-2">
                        <span class="font-bold text-slate-800 tabular-nums" x-text="activeLog.cashText"></span>
                        <span>&bull;</span>
                        <span x-text="activeLog.sembakoText"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Diterima Oleh <span class="text-rose-500">*</span></label>
                    <select name="received_by" required class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                        <option value="Penerima Langsung (Ybs)">Penerima Langsung (Ybs)</option>
                        <option value="Wali / Orang Tua">Wali / Orang Tua</option>
                        <option value="Perwakilan Keluarga Serumah">Perwakilan Keluarga Serumah</option>
                        <option value="Pengurus RT / Lingkungan">Pengurus RT / Lingkungan</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Penyerahan (Opsional)</label>
                    <input type="text" name="handover_notes" placeholder="Contoh: Diserahkan di pendopo, kondisi baik" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5"></i>
                    <span>Tindakan ini akan mencatat tanggal, jam, dan petugas yang menyerahkan bantuan ke dalam log audit resmi.</span>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="handoverModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl shadow-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">Konfirmasi Penyerahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Batalkan Status Penyerahan (Undo) -->
    <div x-show="undoModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div @click.away="undoModal = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 mx-auto flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-rotate-left"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Batalkan Status Penyerahan?</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Bantuan untuk <strong x-text="activeLog.name"></strong> akan dikembalikan menjadi status <strong>Belum Diserahkan (Pending)</strong>.
            </p>
            <form action="" method="POST" class="flex items-center justify-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="cancel_handover">
                <input type="hidden" name="log_id" :value="activeLog.id">
                <button type="button" @click="undoModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">Kembali</button>
                <button type="submit" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-xs transition">Ya, Batalkan Status</button>
            </form>
        </div>
    </div>

    <!-- Modal Tambah Penerima ke Acara -->
    <div x-show="addModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div @click.away="addModal = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900">Tambah Penerima ke Acara Ini</h3>
                <button @click="addModal = false" class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="add_recipient">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Pilih Warga / Anak Yatim <span class="text-rose-500">*</span></label>
                    <select name="beneficiary_id" required class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                        <option value="">-- Pilih dari Database --</option>
                        <?php foreach ($allBeneficiaries as $ben): ?>
                            <option value="<?= $ben['id'] ?>">
                                <?= htmlspecialchars($ben['full_name']) ?> (<?= ucfirst($ben['category']) ?> &bull; <?= (int)$ben['received_count'] > 0 ? (int)$ben['received_count'] . 'x terima' : '0x belum pernah' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nominal Uang Santunan (Rp)</label>
                    <input type="number" name="cash_amount" value="350000" step="1000" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rincian Paket Sembako / Bingkisan</label>
                    <input type="text" name="sembako_package_detail" value="Paket Beras 5kg & Minyak" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="addModal = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl shadow-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">Masukkan Checklist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
