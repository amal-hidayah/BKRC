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

// Ambil Riwayat Penyaluran (Events)
$stmtEvents = $pdo->query("
    SELECT e.*, 
        COUNT(l.id) as total_recipients,
        SUM(CASE WHEN l.status = 'distributed' THEN 1 ELSE 0 END) as distributed_recipients
    FROM distribution_events e
    LEFT JOIN distribution_logs l ON e.id = l.event_id
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$events = $stmtEvents->fetchAll();
?>

<!-- Header (Solid Slate-900, Clean Civic Header) -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight">Papan Transparansi Donasi & Kas</h1>
                <p class="text-slate-300 text-sm mt-1">Laporan rekapitulasi dana masuk terverifikasi, paket sembako, dan realisasi penyaluran ke penerima.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-300">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-white">Beranda</a>
                <span>/</span>
                <span class="text-amber-400 font-semibold">Transparansi</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Rekapitulasi Kas & Sembako (Metric Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <!-- Saldo Kas Riil -->
        <div class="bg-slate-900 text-white rounded-2xl p-6 shadow-sm border border-slate-800">
            <div class="text-xs text-slate-300 uppercase font-bold tracking-wider mb-1">Saldo Kas Riil (Bank)</div>
            <div class="text-2xl sm:text-3xl font-extrabold text-amber-300 tabular-nums">
                <?= formatRupiah($kasSummary['saldo_kas_riil']) ?>
            </div>
            <div class="text-[11px] text-slate-300 mt-2 flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved text-emerald-400"></i> Kas aman, surplus positif
            </div>
        </div>

        <!-- Total Penerimaan Kas -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Total Kas Terverifikasi</div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums">
                <?= formatRupiah($kasSummary['total_uang_masuk']) ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
                <span>Donasi: <?= formatRupiah($kasSummary['total_donasi_masuk']) ?></span>
                <span>Iuran: <?= formatRupiah($kasSummary['total_iuran_masuk']) ?></span>
            </div>
        </div>

        <!-- Total Santunan Uang Disalurkan -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Realisasi Kas Disalurkan</div>
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-700 tabular-nums">
                <?= formatRupiah($kasSummary['total_realisasi_keluar']) ?>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
                <i class="fa-solid fa-hand-holding-heart text-bkrc-red-800"></i> Komitmen rencana: <?= formatRupiah($kasSummary['dana_pending']) ?>
            </div>
        </div>

        <!-- Paket Sembako Disalurkan -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Paket Sembako Tersalurkan</div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums">
                <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> <span class="text-sm font-semibold text-slate-500">Paket</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
                <i class="fa-solid fa-box-open text-amber-600"></i> Total diterima: <?= number_format($kasSummary['total_sembako_masuk']) ?> paket
            </div>
        </div>
    </div>

    <!-- Section 1: Daftar Donasi & Iuran Masuk (Hanya Approved) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-12">
        <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Catatan Penerimaan Kas (Donasi & Iuran Anggota)</h2>
                <p class="text-xs text-slate-500">Seluruh data yang ditampilkan telah diverifikasi oleh Bendahara BKRC. Donatur berhak memilih opsi anonim.</p>
            </div>

            <!-- Filter Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=semua&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-semibold transition <?= $typeFilter === 'semua' ? 'bg-bkrc-red-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    Semua
                </a>
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=uang&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-semibold transition <?= $typeFilter === 'uang' ? 'bg-bkrc-red-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    Uang Kas
                </a>
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=sembako&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-semibold transition <?= $typeFilter === 'sembako' ? 'bg-bkrc-red-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    Sembako
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Kwitansi</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Nama Donatur / Anggota</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Nominal / Rincian</th>
                        <th class="py-3 px-4">Peruntukan</th>
                        <th class="py-3 px-4 text-center">Kwitansi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (empty($donations)): ?>
                        <tr><td colspan="7" class="py-8 text-center text-slate-500">Tidak ada data donasi/iuran ditemukan.</td></tr>
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
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-700 whitespace-nowrap">
                                    <span class="bg-slate-100 px-2 py-1 rounded border border-slate-200 text-xs">
                                        <?= htmlspecialchars($d['receipt_no']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-600"><?= formatTanggalIndo($d['donation_date']) ?></td>
                                <td class="py-3.5 px-4">
                                    <?php if ($d['is_anonymous']): ?>
                                        <span class="italic text-slate-600 font-semibold">Hamba Allah</span>
                                        <span class="text-[11px] text-slate-400 font-normal ml-1">(Anonim)</span>
                                    <?php else: ?>
                                        <span class="font-bold text-slate-900"><?= $donorDisplayName ?></span>
                                        <?php if ($isIuran && !empty($d['member_block'])): ?>
                                            <span class="block text-[11px] text-slate-500 font-normal">Warga Blok <?= htmlspecialchars($d['member_block']) ?></span>
                                        <?php elseif (!empty($d['cluster_or_address'])): ?>
                                            <span class="block text-[11px] text-slate-500 font-normal"><?= htmlspecialchars($d['cluster_or_address']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if ($isIuran): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                            Iuran Kas
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                            Donasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 tabular-nums whitespace-nowrap text-right">
                                    <?php if ($isUang): ?>
                                        <span class="text-emerald-700 font-extrabold"><?= formatRupiah($d['amount']) ?></span>
                                    <?php else: ?>
                                        <?= number_format($d['package_qty']) ?> Paket
                                        <?php if (!empty($d['package_details'])): ?>
                                            <span class="block text-[11px] font-normal text-slate-500"><?= htmlspecialchars($d['package_details']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600"><?= htmlspecialchars($d['program_target']) ?></td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="<?= getReceiptUrl($d['id'], $d['receipt_no'], $d['created_at']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition" title="Lihat Kwitansi Resmi">
                                        <i class="fa-solid fa-receipt text-slate-500"></i> Bukti
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
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Realisasi Penyaluran Santunan & Program Lapangan</h2>
            <p class="text-xs text-slate-500">Rekapitulasi sesi kegiatan penyaluran yang telah didistribusikan kepada warga dan anak yatim binaan.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Nama Sesi Acara</th>
                        <th class="py-3 px-4">Tanggal Pelaksanaan</th>
                        <th class="py-3 px-4">Kategori Program</th>
                        <th class="py-3 px-4 text-right">Nominal Kas</th>
                        <th class="py-3 px-4 text-right">Paket Sembako</th>
                        <th class="py-3 px-4 text-center">Status Lapangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (empty($events)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada sesi penyaluran tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $isTersalurkan = ($ev['disbursement_status'] === 'tersalurkan');
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <?= htmlspecialchars($ev['event_name']) ?>
                                    <?php if (!empty($ev['disbursed_to'])): ?>
                                        <span class="block text-[11px] font-medium text-emerald-800">Penerima/PIC: <?= htmlspecialchars($ev['disbursed_to']) ?></span>
                                    <?php elseif (!empty($ev['notes'])): ?>
                                        <span class="block text-[11px] font-normal text-slate-500"><?= htmlspecialchars($ev['notes']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap"><?= formatTanggalIndo($ev['event_date']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                        <?= str_replace('_', ' ', $ev['program_type']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 tabular-nums text-right">
                                    <?= formatRupiah($ev['total_cash_out']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 tabular-nums text-right">
                                    <?= $ev['total_packages_out'] > 0 ? number_format($ev['total_packages_out']) . ' Paket' : '–' ?>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <?php if ($isTersalurkan): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Tersalurkan
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
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
