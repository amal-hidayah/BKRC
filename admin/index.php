<?php
/**
 * Dashboard Eksekutif Pengurus BKRC (Back-Office)
 * Modul B PRD: Kartu Metrik Keuangan & Logistik, Daftar Perlu Tindakan Real-Time, Visualisasi Bar Chart Bulanan
 */
$pageTitle = 'Dashboard Ringkasan Eksekutif';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$kasSummary = getKasSummary($pdo);

// Data untuk Bar Chart Tren Penerimaan Kas Bulanan (Tahun Berjalan 2026 s/d Bulan Berjalan)
$currentYear = (int)date('Y');
$currentMonth = (int)date('n'); // Bulan berjalan
$monthlyDonations = [];
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// Query kas masuk approved per bulan untuk tahun berjalan
$stmtMonthly = $pdo->prepare("
    SELECT MONTH(donation_date) as bln, SUM(amount) as total 
    FROM donations 
    WHERE type = 'uang' AND status = 'approved' AND YEAR(donation_date) = ?
    GROUP BY MONTH(donation_date)
");
$stmtMonthly->execute([$currentYear]);
$dbMonthly = [];
while ($row = $stmtMonthly->fetch()) {
    $dbMonthly[(int)$row['bln']] = (float)$row['total'];
}

// Hanya isi data hingga bulan berjalan (memotong bulan yang belum terjadi)
$chartLabels = [];
$chartData = [];
for ($m = 1; $m <= $currentMonth; $m++) {
    $chartLabels[] = $monthLabels[$m - 1];
    $chartData[] = $dbMonthly[$m] ?? 0;
}

// Action Items (Perlu Tindakan) Real-Time
$pendingDonationsCount = $kasSummary['total_pending_donations'];
$pendingMembersCount = $kasSummary['total_pending_members'];

// Agenda penyaluran kas yang masih direncanakan
$stmtPlannedEvents = $pdo->query("
    SELECT id, event_name, event_date, total_cash_out, program_type 
    FROM distribution_events 
    WHERE disbursement_status = 'direncanakan'
    ORDER BY event_date ASC
");
$plannedEvents = $stmtPlannedEvents->fetchAll();

// Anak yatim yang perlu evaluasi batas usia (>= 15 tahun)
$stmtOverAge = $pdo->query("
    SELECT id, full_name, birth_date, category
    FROM beneficiaries
    WHERE category = 'yatim' AND status = 'layak_aktif' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 15
");
$overAgeKids = $stmtOverAge->fetchAll();

// Total akumulasi antrean tindakan aktif yang membutuhkan respon pengurus
$totalActionItems = $pendingDonationsCount + $pendingMembersCount + count($plannedEvents) + count($overAgeKids);

// 5 Donasi/Iuran Terakhir (Approved)
$recentDonations = $pdo->query("
    SELECT d.*, 
        dn.full_name as donor_name, dn.is_anonymous,
        m.full_name as member_name
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.id
    LEFT JOIN members m ON d.member_id = m.id
    WHERE d.status = 'approved'
    ORDER BY d.donation_date DESC, d.id DESC
    LIMIT 5
")->fetchAll();

// 5 Penyaluran Terakhir
$recentEvents = $pdo->query("
    SELECT e.*, 
        COUNT(l.id) as total_recipients,
        SUM(CASE WHEN l.status = 'distributed' THEN 1 ELSE 0 END) as distributed_count
    FROM distribution_events e
    LEFT JOIN distribution_logs l ON e.id = l.event_id
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.id DESC
    LIMIT 5
")->fetchAll();
?>

<div class="flex-1 flex flex-col w-full">
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Ringkasan Eksekutif</h1>
                <p class="text-xs text-slate-500 hidden sm:block">Pemantauan keuangan, donasi, pendaftaran anggota, dan penyaluran lapangan BKRC.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <a href="<?= getBaseUrl() ?>/admin/donations.php" class="inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-4 py-2.5 min-h-[44px] rounded-xl transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                <i class="fa-solid fa-plus mr-1.5 text-xs"></i> <span>Catat Kas Masuk</span>
            </a>
            <a href="<?= getBaseUrl() ?>/admin/lpj.php" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                <i class="fa-solid fa-print text-slate-500"></i> <span>Cetak LPJ</span>
            </a>
        </div>
    </header>

    <!-- Page Body -->
    <main class="flex-1 p-4 sm:p-6 space-y-6">

        <!-- Flash Messages -->
        <?php if ($flash = getFlash('success')): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm p-4 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($flash = getFlash('error')): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm p-4 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-xmark text-rose-600 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section: Perlu Tindakan Pengurus (Live Real-Time Action Items) -->
        <div class="bg-amber-50/60 border border-amber-200/80 rounded-2xl p-4 sm:p-5">
            <div class="flex items-center justify-between pb-3 border-b border-amber-200/60 mb-3">
                <div class="flex items-center gap-2 text-amber-900 font-bold text-sm">
                    <i class="fa-solid fa-bell text-amber-600"></i>
                    <span>Perlu Tindakan Pengurus</span>
                </div>
                <span class="text-xs font-semibold bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full border border-amber-200">
                    <?= $totalActionItems ?> antrean tindakan aktif
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <!-- Action Item 1: Verifikasi Donasi / Iuran Pending -->
                <div class="bg-white p-3.5 rounded-xl border border-amber-200/80 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800 mb-1">
                            <span class="w-2 h-2 rounded-full <?= $pendingDonationsCount > 0 ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500' ?>"></span>
                            <span>Verifikasi Donasi & Iuran</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <?php if ($pendingDonationsCount > 0): ?>
                                Ada <strong><?= $pendingDonationsCount ?> konfirmasi pembayaran</strong> menunggu verifikasi bukti transfer untuk diterbitkan kwitansi resmi.
                            <?php else: ?>
                                Seluruh konfirmasi donasi & iuran telah diverifikasi dan berkwitansi.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-semibold <?= $pendingDonationsCount > 0 ? 'text-rose-700' : 'text-emerald-700' ?>">
                            <?= $pendingDonationsCount ?> Pending
                        </span>
                        <a href="<?= getBaseUrl() ?>/admin/donations.php?status=pending" class="text-xs font-bold text-bkrc-red-800 hover:underline inline-flex items-center gap-1">
                            Verifikasi Sekarang &rarr;
                        </a>
                    </div>
                </div>

                <!-- Action Item 2: Pendaftaran Anggota Baru Pending -->
                <div class="bg-white p-3.5 rounded-xl border border-amber-200/80 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800 mb-1">
                            <span class="w-2 h-2 rounded-full <?= $pendingMembersCount > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' ?>"></span>
                            <span>Pendaftaran Anggota Mandiri</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <?php if ($pendingMembersCount > 0): ?>
                                Ada <strong><?= $pendingMembersCount ?> calon relawan baru</strong> mengisi formulir pendaftaran mandiri dan menunggu approval.
                            <?php else: ?>
                                Tidak ada antrean calon anggota baru. Seluruh pendaftar telah diproses.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-amber-700"><?= $pendingMembersCount ?> Calon Anggota</span>
                        <a href="<?= getBaseUrl() ?>/admin/members.php?status=pending" class="text-xs font-bold text-bkrc-red-800 hover:underline inline-flex items-center gap-1">
                            Review Anggota &rarr;
                        </a>
                    </div>
                </div>

                <!-- Action Item 3: Agenda Penyaluran Kas Direncanakan -->
                <div class="bg-white p-3.5 rounded-xl border border-amber-200/80 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800 mb-1">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span>Komitmen Penyaluran Lapangan</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <?php if (!empty($plannedEvents)): ?>
                                Ada <strong><?= count($plannedEvents) ?> agenda direncanakan</strong> (Komitmen: <strong><?= formatRupiah($kasSummary['dana_pending']) ?></strong>) menunggu serah terima.
                            <?php else: ?>
                                Seluruh agenda penyaluran telah ditandai tersalurkan penuh ke penerima.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-blue-700"><?= count($plannedEvents) ?> Agenda</span>
                        <a href="<?= getBaseUrl() ?>/admin/events.php?status=direncanakan" class="text-xs font-bold text-bkrc-red-800 hover:underline inline-flex items-center gap-1">
                            Kelola Penyaluran &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Key Financial & Logistic Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Saldo Kas Riil -->
            <div class="bg-slate-900 text-white p-5 rounded-2xl border border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Saldo Kas Riil (Bank)</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-400/20 text-amber-400 flex items-center justify-center">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-amber-300 tabular-nums">
                        <?= formatRupiah($kasSummary['saldo_kas_riil']) ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1">
                        Saldo kas riil setelah realisasi penyaluran keluar
                    </div>
                </div>
                <div class="text-xs text-slate-300 border-t border-slate-800 pt-3 mt-3 flex items-center justify-between">
                    <span>Masuk: <strong class="text-emerald-400 tabular-nums"><?= formatRupiah($kasSummary['total_uang_masuk']) ?></strong></span>
                    <span>Realisasi: <strong class="text-rose-400 tabular-nums"><?= formatRupiah($kasSummary['total_realisasi_keluar']) ?></strong></span>
                </div>
            </div>

            <!-- Paket Sembako -->
            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Sembako Tersalur</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900 tabular-nums">
                        <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> <span class="text-xs font-semibold text-slate-500">Paket</span>
                    </div>
                </div>
                <div class="text-xs text-slate-500 border-t border-slate-100 pt-3 mt-3">
                    Total Diterima: <strong><?= number_format($kasSummary['total_sembako_masuk']) ?> paket</strong>
                </div>
            </div>

            <!-- Penerima Manfaat Binaan -->
            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Anak Yatim Binaan</span>
                        <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center">
                            <i class="fa-solid fa-child-reaching"></i>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900 tabular-nums">
                        <?= $kasSummary['total_yatim'] ?> <span class="text-xs font-semibold text-slate-500">Anak</span>
                    </div>
                </div>
                <div class="text-xs text-slate-500 border-t border-slate-100 pt-3 mt-3">
                    + <strong><?= $kasSummary['total_warga_dhuafa'] ?> dhuafa & lansia</strong>
                </div>
            </div>

            <!-- Relawan Cluster -->
            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Relawan Warga</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900 tabular-nums">
                        <?= $kasSummary['total_relawan'] ?> <span class="text-xs font-semibold text-slate-500">Orang</span>
                    </div>
                </div>
                <div class="text-xs text-slate-500 border-t border-slate-100 pt-3 mt-3">
                    Status: <strong><?= $kasSummary['total_relawan'] ?> Aktif</strong>
                </div>
            </div>
        </div>

        <!-- Visualisasi Statistik Bulanan -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Bar Chart: Donasi Masuk Bulanan -->
            <div class="lg:col-span-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight">Penerimaan Donasi & Kas Bulanan (Tahun <?= $currentYear ?>)</h2>
                        <p class="text-xs text-slate-500">Menampilkan realisasi dana masuk terverifikasi s/d <?= $monthLabels[$currentMonth - 1] ?> <?= $currentYear ?></p>
                    </div>
                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200">
                        Jan – Nov <?= $currentYear ?>
                    </span>
                </div>
                <div class="h-64 sm:h-72">
                    <canvas id="donationBarChart"></canvas>
                </div>
            </div>

            <!-- Komposisi Penerima Manfaat -->
            <div class="lg:col-span-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight">Komposisi Penerima Manfaat</h2>
                        <p class="text-xs text-slate-500">Distribusi sasaran santunan warga cluster</p>
                    </div>

                    <div class="space-y-4 pt-2">
                        <!-- Yatim -->
                        <div>
                            <div class="flex justify-between text-xs font-bold text-slate-800 mb-1.5">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                                    <span>Anak Yatim Binaan</span>
                                </span>
                                <span class="tabular-nums font-extrabold text-rose-700"><?= $kasSummary['total_yatim'] ?> Anak (50%)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-rose-600 h-full rounded-full" style="width: 50%"></div>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">Santunan rutin bulanan & kado Ramadhan</div>
                        </div>

                        <!-- Dhuafa / Sembako -->
                        <div>
                            <div class="flex justify-between text-xs font-bold text-slate-800 mb-1.5">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span>
                                    <span>Warga Dhuafa & Lansia</span>
                                </span>
                                <span class="tabular-nums font-extrabold text-amber-700"><?= $kasSummary['total_warga_dhuafa'] ?> Warga (50%)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-amber-600 h-full rounded-full" style="width: 50%"></div>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">Paket beras, minyak goreng, dan sembako</div>
                        </div>
                    </div>
                </div>

                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-600 mt-4">
                    <div class="font-bold text-slate-800 mb-0.5">Total: <?= $kasSummary['total_penerima_aktif'] ?> Penerima Aktif</div>
                    <div class="text-[11px] text-slate-500">Terverifikasi layak menerima santunan oleh pengurus cluster.</div>
                </div>
            </div>
        </div>

        <!-- 2 Column Tables: Donasi Masuk Terbaru & Penyaluran Santunan -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Table 1: Donasi Terbaru -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Donasi & Iuran Terverifikasi Terbaru</h3>
                    </div>
                    <a href="<?= getBaseUrl() ?>/admin/donations.php" class="text-xs text-bkrc-red-800 font-bold hover:underline">
                        Lihat Semua &rarr;
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="py-2.5 px-3">Tanggal</th>
                                <th class="py-2.5 px-3">No. Kwitansi</th>
                                <th class="py-2.5 px-3">Donatur / Anggota</th>
                                <th class="py-2.5 px-3 text-right">Nominal</th>
                                <th class="py-2.5 px-3 text-center">Kwitansi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($recentDonations as $d): ?>
                                <?php
                                $isIuran = ($d['transaction_category'] === 'iuran');
                                $name = $isIuran && !empty($d['member_name']) ? $d['member_name'] : ($d['is_anonymous'] ? 'Hamba Allah' : ($d['donor_name'] ?: 'Hamba Allah'));
                                ?>
                                <tr>
                                    <td class="py-3 px-3 whitespace-nowrap text-slate-500 font-medium">
                                        <?= date('d/m/Y', strtotime($d['donation_date'])) ?>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded border border-slate-200 text-slate-700 inline-block font-semibold">
                                            <?= htmlspecialchars($d['receipt_no']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-900">
                                        <div class="flex items-center gap-1.5">
                                            <span><?= htmlspecialchars($name) ?></span>
                                            <?php if ($isIuran): ?>
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-100 text-purple-800">Iuran</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= $d['type'] === 'uang' ? formatRupiah($d['amount']) : number_format($d['package_qty']) . ' Paket' ?>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <a href="<?= getBaseUrl() ?>/print_receipt.php?id=<?= $d['id'] ?>" target="_blank" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition" title="Cetak Kwitansi">
                                            <i class="fa-solid fa-print text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table 2: Penyaluran Kas -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Penyaluran Kas & Bantuan Lapangan</h3>
                    </div>
                    <a href="<?= getBaseUrl() ?>/admin/events.php" class="text-xs text-bkrc-red-800 font-bold hover:underline">
                        Lihat Semua &rarr;
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="py-2.5 px-3">Nama Kegiatan</th>
                                <th class="py-2.5 px-3 whitespace-nowrap">Tanggal</th>
                                <th class="py-2.5 px-3 text-right">Nominal Kas</th>
                                <th class="py-2.5 px-3 text-center whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($recentEvents as $ev): ?>
                                <?php $isTersalurkan = ($ev['disbursement_status'] === 'tersalurkan'); ?>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-slate-900">
                                        <?= htmlspecialchars($ev['event_name']) ?>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap text-slate-500 font-medium">
                                        <?= date('d/m/Y', strtotime($ev['event_date'])) ?>
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= formatRupiah($ev['total_cash_out']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <?php if ($isTersalurkan): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-full">
                                                <i class="fa-solid fa-check text-[9px]"></i> Tersalurkan
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-300 px-2 py-0.5 rounded-full">
                                                <i class="fa-solid fa-clock text-[9px]"></i> Direncanakan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Chart.js Configuration: Bar Chart Capped at November 2026 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const barCtx = document.getElementById('donationBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Kas Masuk (Rp)',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: '#991b1b',
                hoverBackgroundColor: '#7f1d1d',
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + Number(context.raw).toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (value === 0) return '0';
                            return 'Rp ' + (value / 1000000) + ' Jt';
                        },
                        font: { size: 10 }
                    },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
