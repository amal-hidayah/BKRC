<?php
/**
 * Halaman Transparansi Donasi, Kas, & Penyaluran Sembako (Publik)
 * Standar: Hanya menampilkan donasi & iuran yang telah di-Approve, dan penyaluran yang telah Tersalurkan.
 */
$pageTitle = 'Transparansi Donasi & Penyaluran — Bantuan Kemanusiaan Relawan Cluster (BKRC)';
require_once __DIR__ . '/includes/header.php';

$kasSummary = getKasSummary($pdo);

// Ambil Riwayat Donasi & Iuran Masuk (HANYA STATUS APPROVED)
$typeFilter = $_GET['tipe'] ?? 'semua';
$catFilter = $_GET['kategori'] ?? 'semua';
$monthFilter = trim($_GET['bulan'] ?? 'semua');
$searchQuery = trim($_GET['q'] ?? '');

$namaBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Query daftar bulan transaksi yang tersedia untuk filter dropdown
$stmtMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(donation_date, '%Y-%m') as ym, DATE_FORMAT(donation_date, '%m') as m, DATE_FORMAT(donation_date, '%Y') as y
    FROM donations 
    WHERE status = 'approved' AND donation_date IS NOT NULL
    ORDER BY ym DESC
");
$availableMonths = $stmtMonths->fetchAll();

$whereClauses = ["d.status = 'approved'"];
$paramsDonation = [];

if ($typeFilter === 'uang' || $typeFilter === 'sembako') {
    $whereClauses[] = "d.type = ?";
    $paramsDonation[] = $typeFilter;
}

if ($catFilter === 'donasi' || $catFilter === 'iuran') {
    $whereClauses[] = "d.transaction_category = ?";
    $paramsDonation[] = $catFilter;
}

// Filter Periode Bulan (format YYYY-MM)
if (!empty($monthFilter) && $monthFilter !== 'semua') {
    $whereClauses[] = "DATE_FORMAT(d.donation_date, '%Y-%m') = ?";
    $paramsDonation[] = $monthFilter;
}

// Pencarian Berdasarkan: No Kwitansi / Nama Donatur / Nama Bulan
if (!empty($searchQuery)) {
    $indoMonths = [
        'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
        'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
        'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'
    ];
    $qLower = strtolower($searchQuery);

    if (isset($indoMonths[$qLower])) {
        $mNum = $indoMonths[$qLower];
        $term = "%{$searchQuery}%";
        $whereClauses[] = "(DATE_FORMAT(d.donation_date, '%m') = ? OR d.receipt_no LIKE ? OR dn.full_name LIKE ? OR m.full_name LIKE ?)";
        $paramsDonation[] = $mNum;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
    } else {
        $term = "%{$searchQuery}%";
        $whereClauses[] = "(
            d.receipt_no LIKE ? 
            OR dn.full_name LIKE ? 
            OR m.full_name LIKE ? 
            OR (dn.is_anonymous = 1 AND 'hamba allah' LIKE ?)
            OR DATE_FORMAT(d.donation_date, '%Y-%m-%d') LIKE ?
            OR d.program_target LIKE ?
        )";
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
        $paramsDonation[] = $term;
    }
}

$whereSql = implode(" AND ", $whereClauses);

$queryDonations = "
    SELECT d.*, 
        dn.full_name as donor_name, dn.is_anonymous, dn.cluster_or_address,
        m.full_name as member_name, m.cluster_block as member_block
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.id
    LEFT JOIN members m ON d.member_id = m.id
    WHERE {$whereSql}
    ORDER BY d.donation_date DESC, d.id DESC
";

$stmtDonations = $pdo->prepare($queryDonations);
$stmtDonations->execute($paramsDonation);
$donations = $stmtDonations->fetchAll();

// Daftar Kategori Program Penyaluran
$programCategories = [
    'santunan_yatim' => 'Santunan Anak Yatim',
    'sembako'        => 'Bantuan Sembako',
    'event'          => 'Event Organisasi',
    'pinjaman'       => 'Pinjaman Qardh',
    'lainnya'        => 'Penyaluran Lainnya'
];

// Query daftar bulan & tahun yang tersedia untuk filter penyaluran (Events)
$stmtEvMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(event_date, '%Y-%m') as ym, DATE_FORMAT(event_date, '%m') as m, DATE_FORMAT(event_date, '%Y') as y
    FROM distribution_events 
    WHERE event_date IS NOT NULL
    ORDER BY ym DESC
");
$availableEvMonths = $stmtEvMonths->fetchAll();

// Parameter filter untuk Realisasi Penyaluran
$evMonthFilter = trim($_GET['ev_bulan'] ?? 'semua');
$evCatFilter = trim($_GET['ev_kategori'] ?? 'semua');
$evSearchQuery = trim($_GET['ev_q'] ?? '');

$whereEvClauses = [];
$paramsEvents = [];

// Filter Bulan / Periode (format YYYY-MM atau YYYY)
if (!empty($evMonthFilter) && $evMonthFilter !== 'semua') {
    if (strlen($evMonthFilter) === 4) {
        $whereEvClauses[] = "DATE_FORMAT(e.event_date, '%Y') = ?";
        $paramsEvents[] = $evMonthFilter;
    } else {
        $whereEvClauses[] = "DATE_FORMAT(e.event_date, '%Y-%m') = ?";
        $paramsEvents[] = $evMonthFilter;
    }
}

// Filter Kategori Program
if (!empty($evCatFilter) && $evCatFilter !== 'semua' && isset($programCategories[$evCatFilter])) {
    $whereEvClauses[] = "e.program_type = ?";
    $paramsEvents[] = $evCatFilter;
}

// Pencarian Kata Kunci (Nama Acara / Penerima PIC / Catatan)
if (!empty($evSearchQuery)) {
    $termEv = "%{$evSearchQuery}%";
    $whereEvClauses[] = "(e.event_name LIKE ? OR e.disbursed_to LIKE ? OR e.notes LIKE ?)";
    $paramsEvents[] = $termEv;
    $paramsEvents[] = $termEv;
    $paramsEvents[] = $termEv;
}

$whereEvSql = !empty($whereEvClauses) ? "WHERE " . implode(" AND ", $whereEvClauses) : "";

// Ambil Riwayat Penyaluran (Events)
$queryEvents = "
    SELECT e.*, 
        COUNT(l.id) as total_recipients,
        SUM(CASE WHEN l.status = 'distributed' THEN 1 ELSE 0 END) as distributed_recipients
    FROM distribution_events e
    LEFT JOIN distribution_logs l ON e.id = l.event_id
    {$whereEvSql}
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.id DESC
";
$stmtEvents = $pdo->prepare($queryEvents);
$stmtEvents->execute($paramsEvents);
$events = $stmtEvents->fetchAll();
?>

<!-- Header (Warm Stone-900, Clean Civic Header) -->
<div class="bg-stone-900 text-white py-12 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight">Papan transparansi donasi & kas</h1>
                <p class="text-stone-300 text-xs sm:text-sm mt-1">Laporan rekapitulasi dana masuk terverifikasi, paket sembako, dan realisasi penyaluran ke penerima.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-stone-400">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-stone-200">Beranda</a>
                <span>/</span>
                <span class="text-stone-200 font-medium">Transparansi</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Rekapitulasi Kas & Sembako (Metric Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
        <!-- Saldo Kas Riil -->
        <div class="bg-stone-900 text-stone-100 rounded-xl p-5 sm:p-6 shadow-2xs border border-stone-800">
            <div class="text-xs text-stone-400 font-normal mb-1">Saldo kas riil (bank)</div>
            <div class="text-2xl sm:text-3xl font-semibold tabular-nums tracking-tight text-white">
                <?= formatRupiah($kasSummary['saldo_kas_riil']) ?>
            </div>
            <div class="text-[11px] text-stone-400 mt-2 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>Kas likuid tersedia di rekening</span>
            </div>
        </div>

        <!-- Total Penerimaan Kas -->
        <div class="bg-white rounded-xl p-5 sm:p-6 shadow-2xs border border-stone-200/80">
            <div class="text-xs text-stone-500 font-normal mb-1">Total kas terverifikasi</div>
            <div class="text-2xl sm:text-3xl font-semibold text-stone-900 tabular-nums tracking-tight">
                <?= formatRupiah($kasSummary['total_uang_masuk']) ?>
            </div>
            <div class="text-[11px] text-stone-500 mt-2 flex justify-between">
                <span>Donasi: <?= formatRupiah($kasSummary['total_donasi_masuk']) ?></span>
                <span>Iuran: <?= formatRupiah($kasSummary['total_iuran_masuk']) ?></span>
            </div>
        </div>

        <!-- Total Santunan Uang Disalurkan -->
        <div class="bg-white rounded-xl p-5 sm:p-6 shadow-2xs border border-stone-200/80">
            <div class="text-xs text-stone-500 font-normal mb-1">Realisasi kas disalurkan</div>
            <div class="text-2xl sm:text-3xl font-semibold text-stone-900 tabular-nums tracking-tight">
                <?= formatRupiah($kasSummary['total_realisasi_keluar']) ?>
            </div>
            <div class="text-[11px] text-stone-500 mt-2 flex items-center gap-1">
                <span>Komitmen rencana: <?= formatRupiah($kasSummary['dana_pending']) ?></span>
            </div>
        </div>

        <!-- Paket Sembako Disalurkan -->
        <div class="bg-white rounded-xl p-5 sm:p-6 shadow-2xs border border-stone-200/80">
            <div class="text-xs text-stone-500 font-normal mb-1">Paket sembako tersalurkan</div>
            <div class="text-2xl sm:text-3xl font-semibold text-stone-900 tabular-nums tracking-tight">
                <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> <span class="text-sm font-normal text-stone-500">paket</span>
            </div>
            <div class="text-[11px] text-stone-500 mt-2 flex items-center gap-1">
                <span>Total diterima: <?= number_format($kasSummary['total_sembako_masuk']) ?> paket</span>
            </div>
        </div>
    </div>

    <!-- Section 1: Daftar Donasi & Iuran Masuk (Hanya Approved) -->
    <div class="bg-white rounded-xl shadow-2xs border border-stone-200/80 overflow-hidden mb-10">
        <div class="p-5 sm:p-6 border-b border-stone-200/80 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div>
                <h2 class="text-base sm:text-lg font-semibold text-stone-900">Catatan penerimaan kas</h2>
                <p class="text-xs text-stone-500 mt-0.5">
                    Seluruh data yang ditampilkan telah diverifikasi oleh Bendahara BKRC.
                    <?php if (!empty($searchQuery) || ($monthFilter !== 'semua' && !empty($monthFilter))): ?>
                        <span class="font-medium text-stone-700 ml-1">· Ditemukan <?= count($donations) ?> data</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Search Bar & Filters (No Kwitansi / Bulan / Nama Donatur) -->
            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2.5 text-xs">
                <!-- Form Pencarian Terpadu -->
                <form method="GET" action="<?= getBaseUrl() ?>/transparansi.php" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tipe" value="<?= htmlspecialchars($typeFilter) ?>">
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($catFilter) ?>">
                    <input type="hidden" name="ev_bulan" value="<?= htmlspecialchars($evMonthFilter) ?>">
                    <input type="hidden" name="ev_kategori" value="<?= htmlspecialchars($evCatFilter) ?>">
                    <input type="hidden" name="ev_q" value="<?= htmlspecialchars($evSearchQuery) ?>">

                    <!-- 1. Filter Pilihan Bulan -->
                    <select name="bulan" onchange="this.form.submit()" class="bg-white border border-stone-300 rounded-lg text-xs px-2.5 py-1.5 min-h-[36px] text-stone-700 focus:outline-none focus:border-bkrc-red-800 transition">
                        <option value="semua">Semua bulan</option>
                        <?php foreach ($availableMonths as $m): ?>
                            <?php 
                            $labelBln = ($namaBulan[$m['m']] ?? $m['m']) . ' ' . $m['y'];
                            ?>
                            <option value="<?= $m['ym'] ?>" <?= $monthFilter === $m['ym'] ? 'selected' : '' ?>>
                                <?= $labelBln ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- 2. Kotak Input Pencarian (No. Kwitansi / Nama Donatur) -->
                    <div class="relative flex-grow sm:flex-grow-0">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-stone-400 pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                        </span>
                        <input type="text" 
                               name="q" 
                               value="<?= htmlspecialchars($searchQuery) ?>" 
                               placeholder="Cari no. kwitansi / nama..." 
                               class="pl-8 pr-3 py-1.5 min-h-[36px] w-full sm:w-48 md:w-56 bg-white border border-stone-300 rounded-lg text-xs text-stone-900 placeholder:text-stone-400 focus:outline-none focus:border-bkrc-red-800 transition">
                    </div>

                    <!-- Tombol Cari -->
                    <button type="submit" class="bg-stone-900 hover:bg-stone-800 active:bg-black text-white font-medium px-3.5 py-1.5 min-h-[36px] rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>Cari</span>
                    </button>

                    <!-- Tombol Reset / Bersihkan Filter -->
                    <?php if (!empty($searchQuery) || ($monthFilter !== 'semua' && !empty($monthFilter)) || $typeFilter !== 'semua'): ?>
                        <a href="<?= getBaseUrl() ?>/transparansi.php?ev_bulan=<?= urlencode($evMonthFilter) ?>&ev_kategori=<?= urlencode($evCatFilter) ?>&ev_q=<?= urlencode($evSearchQuery) ?>" class="bg-stone-100 hover:bg-stone-200 text-stone-600 font-medium px-2.5 py-1.5 min-h-[36px] rounded-lg text-xs transition inline-flex items-center gap-1" title="Bersihkan pencarian & filter kas">
                            <i class="fa-solid fa-xmark text-[11px]"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>

                <!-- 3. Tombol Filter Kategori (Semua / Uang kas / Sembako) -->
                <div class="flex items-center gap-1 pl-0 sm:pl-2 border-t sm:border-t-0 sm:border-l border-stone-200 pt-2 sm:pt-0">
                    <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=semua&kategori=<?= urlencode($catFilter) ?>&bulan=<?= urlencode($monthFilter) ?>&q=<?= urlencode($searchQuery) ?>&ev_bulan=<?= urlencode($evMonthFilter) ?>&ev_kategori=<?= urlencode($evCatFilter) ?>&ev_q=<?= urlencode($evSearchQuery) ?>" class="px-2.5 py-1.5 min-h-[36px] inline-flex items-center rounded-lg font-medium transition <?= $typeFilter === 'semua' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Semua
                    </a>
                    <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=uang&kategori=<?= urlencode($catFilter) ?>&bulan=<?= urlencode($monthFilter) ?>&q=<?= urlencode($searchQuery) ?>&ev_bulan=<?= urlencode($evMonthFilter) ?>&ev_kategori=<?= urlencode($evCatFilter) ?>&ev_q=<?= urlencode($evSearchQuery) ?>" class="px-2.5 py-1.5 min-h-[36px] inline-flex items-center rounded-lg font-medium transition <?= $typeFilter === 'uang' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Uang kas
                    </a>
                    <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=sembako&kategori=<?= urlencode($catFilter) ?>&bulan=<?= urlencode($monthFilter) ?>&q=<?= urlencode($searchQuery) ?>&ev_bulan=<?= urlencode($evMonthFilter) ?>&ev_kategori=<?= urlencode($evCatFilter) ?>&ev_q=<?= urlencode($evSearchQuery) ?>" class="px-2.5 py-1.5 min-h-[36px] inline-flex items-center rounded-lg font-medium transition <?= $typeFilter === 'sembako' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Sembako
                    </a>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-stone-50 border-b border-stone-200 text-stone-600 text-[11px] font-medium tracking-normal">
                    <tr>
                        <th class="py-3 px-4">No. kwitansi</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Nama donatur / anggota</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Nominal / rincian</th>
                        <th class="py-3 px-4">Peruntukan</th>
                        <th class="py-3 px-4 text-center">Bukti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 text-stone-700">
                    <?php if (empty($donations)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-stone-500">
                                <i class="fa-solid fa-magnifying-glass text-2xl text-stone-300 mb-2 block"></i>
                                Tidak ada data penerimaan kas yang cocok dengan pencarian Anda.
                                <?php if (!empty($searchQuery) || ($monthFilter !== 'semua' && !empty($monthFilter)) || $typeFilter !== 'semua'): ?>
                                    <div class="mt-2">
                                        <a href="<?= getBaseUrl() ?>/transparansi.php" class="text-bkrc-red-800 hover:underline font-medium text-xs">
                                            &larr; Tampilkan seluruh data kas
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($donations as $d): ?>
                            <?php
                            $isIuran = ($d['transaction_category'] === 'iuran');
                            $donorDisplayName = 'Hamba Allah';
                            if ($isIuran && !empty($d['member_name'])) {
                                $donorDisplayName = $d['member_name'];
                            } elseif (!empty($d['donor_name'])) {
                                $donorDisplayName = $d['is_anonymous'] ? 'Hamba Allah' : htmlspecialchars($d['donor_name']);
                            }
                            $isUang = ($d['type'] === 'uang');
                            ?>
                            <tr class="hover:bg-stone-50/70 transition-colors duration-150">
                                <td class="py-3.5 px-4 font-mono font-medium text-stone-600 whitespace-nowrap">
                                    <span class="bg-stone-100 px-2 py-0.5 rounded border border-stone-200 text-xs">
                                        <?= htmlspecialchars($d['receipt_no']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-stone-500"><?= formatTanggalIndo($d['donation_date']) ?></td>
                                <td class="py-3.5 px-4">
                                    <?php if ($d['is_anonymous']): ?>
                                        <span class="italic text-stone-600 font-medium">Hamba Allah</span>
                                        <span class="text-[11px] text-stone-400 font-normal ml-1">(Anonim)</span>
                                    <?php else: ?>
                                        <span class="font-medium text-stone-900"><?= $donorDisplayName ?></span>
                                        <?php if ($isIuran && !empty($d['member_block'])): ?>
                                            <span class="block text-[11px] text-stone-500 font-normal">Warga Blok <?= htmlspecialchars($d['member_block']) ?></span>
                                        <?php elseif (!empty($d['cluster_or_address'])): ?>
                                            <span class="block text-[11px] text-stone-500 font-normal"><?= htmlspecialchars($d['cluster_or_address']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if ($isIuran): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 text-stone-700 border border-stone-200">
                                            Iuran kas
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 text-stone-700 border border-stone-200">
                                            Donasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums whitespace-nowrap text-right">
                                    <?php if ($isUang): ?>
                                        <span><?= formatRupiah($d['amount']) ?></span>
                                    <?php else: ?>
                                        <?= number_format($d['package_qty']) ?> Paket
                                        <?php if (!empty($d['package_details'])): ?>
                                            <span class="block text-[11px] font-normal text-stone-500"><?= htmlspecialchars($d['package_details']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-stone-500"><?= htmlspecialchars($d['program_target']) ?></td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="<?= getReceiptUrl($d['id'], $d['receipt_no'], $d['created_at']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium text-stone-700 bg-stone-50 hover:bg-stone-100 border border-stone-200 transition" title="Lihat Kwitansi Resmi">
                                        <i class="fa-solid fa-receipt text-stone-400 text-[10px]"></i> Bukti
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Realisasi Penyaluran Santunan & Program Lapangan -->
    <div id="realisasi-penyaluran" class="bg-white rounded-xl shadow-2xs border border-stone-200/80 overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-stone-200/80 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div>
                <h2 class="text-base sm:text-lg font-semibold text-stone-900">Realisasi penyaluran santunan & program lapangan</h2>
                <p class="text-xs text-stone-500 mt-0.5">
                    Rekapitulasi sesi kegiatan penyaluran yang telah didistribusikan kepada warga dan binaan.
                    <?php if (!empty($evSearchQuery) || ($evMonthFilter !== 'semua' && !empty($evMonthFilter)) || $evCatFilter !== 'semua'): ?>
                        <span class="font-medium text-stone-700 ml-1">· Ditemukan <?= count($events) ?> sesi</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Search Bar & Filters Realisasi Penyaluran (Bulan/Tahun, Kategori Program, Nama Acara) -->
            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2.5 text-xs">
                <form method="GET" action="<?= getBaseUrl() ?>/transparansi.php#realisasi-penyaluran" class="flex flex-wrap items-center gap-2">
                    <!-- Pertahankan parameter filter tabel donasi kas di atas -->
                    <input type="hidden" name="tipe" value="<?= htmlspecialchars($typeFilter) ?>">
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($catFilter) ?>">
                    <input type="hidden" name="bulan" value="<?= htmlspecialchars($monthFilter) ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">

                    <!-- 1. Filter Pilihan Bulan & Tahun Pelaksanaan -->
                    <select name="ev_bulan" onchange="this.form.submit()" class="bg-white border border-stone-300 rounded-lg text-xs px-2.5 py-1.5 min-h-[36px] text-stone-700 focus:outline-none focus:border-bkrc-red-800 transition">
                        <option value="semua">Semua bulan & tahun</option>
                        <?php foreach ($availableEvMonths as $em): ?>
                            <?php 
                            $labelEvBln = ($namaBulan[$em['m']] ?? $em['m']) . ' ' . $em['y'];
                            ?>
                            <option value="<?= $em['ym'] ?>" <?= $evMonthFilter === $em['ym'] ? 'selected' : '' ?>>
                                <?= $labelEvBln ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- 2. Filter Kategori Program Penyaluran -->
                    <select name="ev_kategori" onchange="this.form.submit()" class="bg-white border border-stone-300 rounded-lg text-xs px-2.5 py-1.5 min-h-[36px] text-stone-700 focus:outline-none focus:border-bkrc-red-800 transition">
                        <option value="semua">Semua program</option>
                        <?php foreach ($programCategories as $pk => $pl): ?>
                            <option value="<?= $pk ?>" <?= $evCatFilter === $pk ? 'selected' : '' ?>>
                                <?= $pl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- 3. Kotak Input Pencarian (Nama Acara / Lokasi / Penerima) -->
                    <div class="relative flex-grow sm:flex-grow-0">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-stone-400 pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                        </span>
                        <input type="text" 
                               name="ev_q" 
                               value="<?= htmlspecialchars($evSearchQuery) ?>" 
                               placeholder="Cari acara / peruntukan..." 
                               class="pl-8 pr-3 py-1.5 min-h-[36px] w-full sm:w-44 md:w-52 bg-white border border-stone-300 rounded-lg text-xs text-stone-900 placeholder:text-stone-400 focus:outline-none focus:border-bkrc-red-800 transition">
                    </div>

                    <!-- Tombol Cari -->
                    <button type="submit" class="bg-stone-900 hover:bg-stone-800 active:bg-black text-white font-medium px-3.5 py-1.5 min-h-[36px] rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>Cari</span>
                    </button>

                    <!-- Tombol Reset / Bersihkan Filter Realisasi -->
                    <?php if (!empty($evSearchQuery) || ($evMonthFilter !== 'semua' && !empty($evMonthFilter)) || $evCatFilter !== 'semua'): ?>
                        <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=<?= urlencode($typeFilter) ?>&kategori=<?= urlencode($catFilter) ?>&bulan=<?= urlencode($monthFilter) ?>&q=<?= urlencode($searchQuery) ?>#realisasi-penyaluran" class="bg-stone-100 hover:bg-stone-200 text-stone-600 font-medium px-2.5 py-1.5 min-h-[36px] rounded-lg text-xs transition inline-flex items-center gap-1" title="Bersihkan pencarian & filter penyaluran">
                            <i class="fa-solid fa-xmark text-[11px]"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-stone-50 border-b border-stone-200 text-stone-600 text-[11px] font-medium tracking-normal">
                    <tr>
                        <th class="py-3 px-4">Nama sesi acara</th>
                        <th class="py-3 px-4">Tanggal pelaksanaan</th>
                        <th class="py-3 px-4">Kategori program</th>
                        <th class="py-3 px-4 text-right">Nominal kas</th>
                        <th class="py-3 px-4 text-right">Paket sembako</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 text-stone-700">
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-stone-500">
                                <i class="fa-solid fa-magnifying-glass text-2xl text-stone-300 mb-2 block"></i>
                                Tidak ada data realisasi penyaluran yang cocok dengan pencarian atau filter Anda.
                                <?php if (!empty($evSearchQuery) || ($evMonthFilter !== 'semua' && !empty($evMonthFilter)) || $evCatFilter !== 'semua'): ?>
                                    <div class="mt-2">
                                        <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=<?= urlencode($typeFilter) ?>&kategori=<?= urlencode($catFilter) ?>&bulan=<?= urlencode($monthFilter) ?>&q=<?= urlencode($searchQuery) ?>#realisasi-penyaluran" class="text-bkrc-red-800 hover:underline font-medium text-xs">
                                            &larr; Tampilkan seluruh data penyaluran
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $isTersalurkan = ($ev['disbursement_status'] === 'tersalurkan');
                            $evCatBadges = [
                                'santunan_yatim' => 'bg-blue-50 text-blue-800 border-blue-200',
                                'sembako'        => 'bg-amber-50 text-amber-800 border-amber-200',
                                'event'          => 'bg-purple-50 text-purple-800 border-purple-200',
                                'pinjaman'       => 'bg-teal-50 text-teal-800 border-teal-200',
                                'lainnya'        => 'bg-stone-100 text-stone-700 border-stone-200'
                            ];
                            $evBadgeClass = $evCatBadges[$ev['program_type']] ?? 'bg-stone-100 text-stone-700 border-stone-200';
                            $evLabel = $programCategories[$ev['program_type']] ?? ucwords(str_replace('_', ' ', $ev['program_type']));
                            ?>
                            <tr class="hover:bg-stone-50/70 transition-colors duration-150">
                                <td class="py-3 px-4 font-medium text-stone-900">
                                    <?= htmlspecialchars($ev['event_name']) ?>
                                    <?php if (!empty($ev['disbursed_to'])): ?>
                                        <span class="block text-[11px] text-stone-500 font-normal">Penerima/PIC: <?= htmlspecialchars($ev['disbursed_to']) ?></span>
                                    <?php elseif (!empty($ev['notes'])): ?>
                                        <span class="block text-[11px] text-stone-400 font-normal"><?= htmlspecialchars($ev['notes']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-stone-500"><?= formatTanggalIndo($ev['event_date']) ?></td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border <?= $evBadgeClass ?>">
                                        <?= $evLabel ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums text-right whitespace-nowrap">
                                    <?= formatRupiah($ev['total_cash_out']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums text-right whitespace-nowrap">
                                    <?= $ev['total_packages_out'] > 0 ? number_format($ev['total_packages_out']) . ' Paket' : '–' ?>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <?php if ($isTersalurkan): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Tersalurkan
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-300">
                                            <i class="fa-solid fa-clock text-amber-600"></i> Direncanakan
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
