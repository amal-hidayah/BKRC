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
        <div class="p-5 sm:p-6 border-b border-stone-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base sm:text-lg font-semibold text-stone-900">Catatan penerimaan kas</h2>
                <p class="text-xs text-stone-500 mt-0.5">Seluruh data yang ditampilkan telah diverifikasi oleh Bendahara BKRC.</p>
            </div>

            <!-- Filter Buttons -->
            <div class="flex flex-wrap items-center gap-1 text-xs">
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=semua&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-medium transition <?= $typeFilter === 'semua' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                    Semua
                </a>
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=uang&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-medium transition <?= $typeFilter === 'uang' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                    Uang kas
                </a>
                <a href="<?= getBaseUrl() ?>/transparansi.php?tipe=sembako&kategori=<?= urlencode($catFilter) ?>" class="px-3 py-1.5 rounded-lg font-medium transition <?= $typeFilter === 'sembako' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                    Sembako
                </a>
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
                        <tr><td colspan="7" class="py-8 text-center text-stone-400">Tidak ada data donasi/iuran ditemukan.</td></tr>
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
    <div class="bg-white rounded-xl shadow-2xs border border-stone-200/80 overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-stone-200/80">
            <h2 class="text-base sm:text-lg font-semibold text-stone-900">Realisasi penyaluran santunan & program lapangan</h2>
            <p class="text-xs text-stone-500 mt-0.5">Rekapitulasi sesi kegiatan penyaluran yang telah didistribusikan kepada warga dan binaan.</p>
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
                        <tr><td colspan="6" class="py-8 text-center text-stone-400">Belum ada sesi penyaluran tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $isTersalurkan = ($ev['disbursement_status'] === 'tersalurkan');
                            ?>
                            <tr class="hover:bg-stone-50/70 transition-colors duration-150">
                                <td class="py-3 px-4 font-medium text-stone-900">
                                    <?= htmlspecialchars($ev['event_name']) ?>
                                    <?php if (!empty($ev['disbursed_to'])): ?>
                                        <span class="block text-[11px] text-stone-500">Penerima/PIC: <?= htmlspecialchars($ev['disbursed_to']) ?></span>
                                    <?php elseif (!empty($ev['notes'])): ?>
                                        <span class="block text-[11px] text-stone-400"><?= htmlspecialchars($ev['notes']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-stone-500"><?= formatTanggalIndo($ev['event_date']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="text-xs text-stone-600 capitalize">
                                        <?= str_replace('_', ' ', $ev['program_type']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums text-right">
                                    <?= formatRupiah($ev['total_cash_out']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums text-right">
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
