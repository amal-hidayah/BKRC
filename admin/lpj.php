<?php
/**
 * Modul G: Generator Laporan Musyawarah Tahunan (LPJ) & Ekspor Data
 * Fitur: 
 * - Rekapitulasi Kas Tahunan: Pemasukan (Donasi Sukarela vs Iuran Anggota Approved)
 * - Pengeluaran Realisasi Tersalurkan per 5 Kategori (Yatim, Sembako, Event, Pinjaman, Lainnya)
 * - Arus Kas Bulanan (Jan - Des) Tanpa Rp 0 (Diganti '–') & Tabular-Nums Rata Kanan
 * - Komitmen Anggaran Direncanakan vs Realisasi Kas Keluar Riil
 */
$pageTitle = 'Laporan Musyawarah Tahunan (LPJ)';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$selectedYear = (int)($_GET['tahun'] ?? date('Y'));

// Rekapitulasi Kas Tahun Terpilih
$kasTahun = getKasSummary($pdo, $selectedYear);

// Rekapitulasi Bulanan Kas (Jan - Des)
$monthlyReport = [];
$bulanNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$runningBalance = 0;
for ($m = 1; $m <= 12; $m++) {
    // Donasi Sukarela Approved
    $stmtDonasi = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'donasi' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtDonasi->execute([$selectedYear, $m]);
    $mDonasi = (float)$stmtDonasi->fetchColumn();

    // Iuran Bulanan Approved
    $stmtIuran = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'iuran' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtIuran->execute([$selectedYear, $m]);
    $mIuran = (float)$stmtIuran->fetchColumn();

    $mTotalIn = $mDonasi + $mIuran;

    // Sembako Masuk Bulan Ini
    $stmtMSembakoIn = $pdo->prepare("SELECT COALESCE(SUM(package_qty), 0) FROM donations WHERE type = 'sembako' AND status = 'approved' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtMSembakoIn->execute([$selectedYear, $m]);
    $mSembakoIn = (int)$stmtMSembakoIn->fetchColumn();

    // Penyaluran Realisasi Keluar Bulan Ini (Tersalurkan + Log Distribusi)
    $stmtMOut = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN e.disbursement_status = 'tersalurkan' THEN e.total_cash_out
                ELSE COALESCE(part.log_sum, 0)
            END
        ), 0),
        COALESCE(SUM(
            CASE
                WHEN e.disbursement_status = 'tersalurkan' THEN e.total_packages_out
                ELSE 0
            END
        ), 0)
        FROM distribution_events e
        LEFT JOIN (
            SELECT event_id, SUM(cash_amount) as log_sum 
            FROM distribution_logs 
            WHERE status = 'distributed' 
            GROUP BY event_id
        ) part ON e.id = part.event_id
        WHERE YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?
    ");
    $stmtMOut->execute([$selectedYear, $m]);
    $outRow = $stmtMOut->fetch(PDO::FETCH_NUM);
    $mOutCash = (float)$outRow[0];
    $mOutPackages = (int)$outRow[1];

    $runningBalance += ($mTotalIn - $mOutCash);

    $monthlyReport[$m] = [
        'bulan' => $bulanNames[$m],
        'donasi_masuk' => $mDonasi,
        'iuran_masuk' => $mIuran,
        'total_masuk' => $mTotalIn,
        'uang_keluar' => $mOutCash,
        'saldo_akhir' => $runningBalance,
        'sembako_masuk' => $mSembakoIn,
        'sembako_keluar' => $mOutPackages
    ];
}

// Events Penyaluran di Tahun Tersebut Beserta Realisasinya
$stmtEvents = $pdo->prepare("
    SELECT e.*, 
        COUNT(l.id) as total_recipients,
        SUM(CASE WHEN l.status = 'distributed' THEN 1 ELSE 0 END) as distributed_count,
        SUM(CASE WHEN l.status = 'distributed' THEN l.cash_amount ELSE 0 END) as distributed_cash
    FROM distribution_events e
    LEFT JOIN distribution_logs l ON e.id = l.event_id
    WHERE YEAR(e.event_date) = ?
    GROUP BY e.id
    ORDER BY e.event_date ASC
");
$stmtEvents->execute([$selectedYear]);
$yearEvents = $stmtEvents->fetchAll();
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Laporan Pertanggungjawaban (LPJ)</h1>
                <p class="text-xs text-slate-500 hidden sm:block">Rekapitulasi keuangan organisasi siap cetak untuk Musyawarah Warga Cluster.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <a href="<?= getBaseUrl() ?>/export_excel.php?type=lpj&year=<?= $selectedYear ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm px-3.5 sm:px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-200 transition shadow-xs flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                <i class="fa-solid fa-file-excel text-emerald-600"></i> <span class="hidden sm:inline">Ekspor Excel</span>
            </a>
            <a href="<?= getBaseUrl() ?>/print_lpj.php?year=<?= $selectedYear ?>" target="_blank" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-4 py-2.5 min-h-[44px] rounded-xl transition shadow-xs flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                <i class="fa-solid fa-print text-amber-300"></i> <span>Cetak Dokumen LPJ (PDF)</span>
            </a>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5">
        <!-- Year Selector Bar -->
        <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-600">Pilih Tahun Pembukuan:</span>
                <div class="inline-flex gap-1.5 text-xs font-semibold">
                    <a href="<?= getBaseUrl() ?>/admin/lpj.php?tahun=2026" class="px-3 py-1.5 rounded-lg border transition <?= $selectedYear === 2026 ? 'bg-slate-900 text-white border-slate-900 font-bold shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                        Tahun 2026 (Berjalan)
                    </a>
                    <a href="<?= getBaseUrl() ?>/admin/lpj.php?tahun=2025" class="px-3 py-1.5 rounded-lg border transition <?= $selectedYear === 2025 ? 'bg-slate-900 text-white border-slate-900 font-bold shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                        Tahun 2025
                    </a>
                </div>
            </div>

            <div class="text-xs text-slate-600 flex items-center gap-2">
                <span>Status Dokumen:</span>
                <?php if ($selectedYear === (int)date('Y')): ?>
                    <span class="text-amber-800 font-semibold bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200/80 inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Draft Berjalan (Tahun Buku <?= $selectedYear ?> Sedang Berjalan)
                    </span>
                <?php else: ?>
                    <span class="text-emerald-800 font-semibold bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200/80 inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Tutup Buku (Final Disahkan)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4 Summary Metric Cards LPJ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900 text-white p-5 rounded-2xl border border-slate-800 shadow-xs">
                <div class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Total Penerimaan Kas</div>
                <div class="text-2xl font-extrabold text-amber-300 tabular-nums">
                    <?= formatRupiah($kasTahun['total_uang_masuk']) ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-1 flex justify-between">
                    <span>Donasi: <?= formatRupiah($kasTahun['total_donasi_masuk']) ?></span>
                    <span>Iuran: <?= formatRupiah($kasTahun['total_iuran_masuk']) ?></span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Realisasi Penyaluran Keluar</div>
                <div class="text-2xl font-extrabold text-emerald-700 tabular-nums">
                    <?= formatRupiah($kasTahun['total_realisasi_keluar']) ?>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">Komitmen rencana: <strong class="text-amber-700 tabular-nums"><?= formatRupiah($kasTahun['dana_pending']) ?></strong></div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Sisa Saldo Kas Riil (Bank)</div>
                <div class="text-2xl font-extrabold text-slate-900 tabular-nums">
                    <?= formatRupiah($kasTahun['saldo_kas_riil']) ?>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">Saldo setelah komitmen: <strong class="text-slate-800 tabular-nums"><?= formatRupiah($kasTahun['saldo_kas_komitmen']) ?></strong></div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Logistik Sembako Tersalur</div>
                <div class="text-2xl font-extrabold text-slate-900 tabular-nums">
                    <?= number_format($kasTahun['total_sembako_tersalurkan']) ?> <span class="text-xs font-semibold text-slate-500">Paket</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">Total diterima: <strong><?= number_format($kasTahun['total_sembako_masuk']) ?> paket</strong></div>
            </div>
        </div>

        <!-- Breakdown Pengeluaran per 5 Kategori -->
        <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Rincian Realisasi Pengeluaran Kas per Kategori (<?= $selectedYear ?>)</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
                <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-3">
                    <div class="text-blue-900 font-bold mb-1">Santunan Yatim</div>
                    <div class="text-base font-extrabold text-blue-950 tabular-nums"><?= formatRupiah($kasTahun['pengeluaran_per_kategori']['santunan_yatim'] ?? 0, true) ?></div>
                </div>
                <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-3">
                    <div class="text-amber-900 font-bold mb-1">Bantuan Sembako</div>
                    <div class="text-base font-extrabold text-amber-950 tabular-nums"><?= formatRupiah($kasTahun['pengeluaran_per_kategori']['sembako'] ?? 0, true) ?></div>
                </div>
                <div class="bg-purple-50/70 border border-purple-200 rounded-xl p-3">
                    <div class="text-purple-900 font-bold mb-1">Event Organisasi</div>
                    <div class="text-base font-extrabold text-purple-950 tabular-nums"><?= formatRupiah($kasTahun['pengeluaran_per_kategori']['event'] ?? 0, true) ?></div>
                </div>
                <div class="bg-teal-50/70 border border-teal-200 rounded-xl p-3">
                    <div class="text-teal-900 font-bold mb-1">Pinjaman Qardh</div>
                    <div class="text-base font-extrabold text-teal-950 tabular-nums"><?= formatRupiah($kasTahun['pengeluaran_per_kategori']['pinjaman'] ?? 0, true) ?></div>
                </div>
                <div class="bg-slate-100 border border-slate-200 rounded-xl p-3">
                    <div class="text-slate-700 font-bold mb-1">Lainnya / Khusus</div>
                    <div class="text-base font-extrabold text-slate-900 tabular-nums"><?= formatRupiah($kasTahun['pengeluaran_per_kategori']['lainnya'] ?? 0, true) ?></div>
                </div>
            </div>
        </div>

        <!-- Tabel 1: Rekapitulasi Arus Kas Bulanan (Jan - Des) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Arus Kas Bulanan Tahun <?= $selectedYear ?></h2>
                    <p class="text-xs text-slate-500">Saldo Awal per 1 Januari <?= $selectedYear ?>: <strong>Rp 0</strong> (Kas selalu surplus positif setiap bulan)</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Bulan</th>
                            <th class="py-3 px-4 text-right">Donasi Sukarela</th>
                            <th class="py-3 px-4 text-right">Iuran Bulanan</th>
                            <th class="py-3 px-4 text-right">Total Masuk (Rp)</th>
                            <th class="py-3 px-4 text-right">Realisasi Keluar (Rp)</th>
                            <th class="py-3 px-4 text-right">Saldo Kas Akhir (Rp)</th>
                            <th class="py-3 px-4 text-center">Sembako Masuk</th>
                            <th class="py-3 px-4 text-center">Sembako Keluar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($monthlyReport as $mNum => $rep): ?>
                            <tr class="hover:bg-slate-50 transition <?= ($rep['total_masuk'] > 0 || $rep['uang_keluar'] > 0) ? 'font-medium' : 'text-slate-400' ?>">
                                <td class="py-3 px-4 font-bold text-slate-900"><?= $rep['bulan'] ?></td>
                                <td class="py-3 px-4 text-right tabular-nums text-slate-700">
                                    <?= formatRupiah($rep['donasi_masuk'], true) ?>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums text-purple-900">
                                    <?= formatRupiah($rep['iuran_masuk'], true) ?>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums text-slate-900 font-bold">
                                    <?= formatRupiah($rep['total_masuk'], true) ?>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums <?= $rep['uang_keluar'] > 0 ? 'text-rose-700 font-semibold' : 'text-slate-400' ?>">
                                    <?= formatRupiah($rep['uang_keluar'], true) ?>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums font-extrabold <?= $rep['saldo_akhir'] < 0 ? 'text-rose-600' : 'text-slate-900' ?>">
                                    <?= formatRupiah($rep['saldo_akhir'], false) ?>
                                </td>
                                <td class="py-3 px-4 text-center tabular-nums">
                                    <?= $rep['sembako_masuk'] > 0 ? number_format($rep['sembako_masuk']) . ' Paket' : '–' ?>
                                </td>
                                <td class="py-3 px-4 text-center tabular-nums">
                                    <?= $rep['sembako_keluar'] > 0 ? number_format($rep['sembako_keluar']) . ' Paket' : '–' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-50 border-t-2 border-slate-300 font-bold text-xs sm:text-sm text-slate-900">
                        <tr>
                            <td class="py-3.5 px-4 uppercase tracking-wider">Total Pembukuan <?= $selectedYear ?></td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-slate-700"><?= formatRupiah($kasTahun['total_donasi_masuk']) ?></td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-purple-900"><?= formatRupiah($kasTahun['total_iuran_masuk']) ?></td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-slate-900 font-black"><?= formatRupiah($kasTahun['total_uang_masuk']) ?></td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-rose-700 font-black"><?= formatRupiah($kasTahun['total_realisasi_keluar']) ?></td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-emerald-800 font-black"><?= formatRupiah($kasTahun['saldo_kas_riil']) ?></td>
                            <td class="py-3.5 px-4 text-center"><?= number_format($kasTahun['total_sembako_masuk']) ?> Paket</td>
                            <td class="py-3.5 px-4 text-center"><?= number_format($kasTahun['total_sembako_tersalurkan']) ?> Paket</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Tabel 2: Daftar Kegiatan Penyaluran Kas (5 Kategori) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Realisasi Sesi Penyaluran Kas Tahun <?= $selectedYear ?></h2>
                <p class="text-xs text-slate-500">Perbandingan antara komitmen anggaran dan realisasi serah terima di lapangan.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Nama Sesi Acara</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tanggal</th>
                            <th class="py-3 px-4 whitespace-nowrap">Kategori</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Anggaran Kas</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Realisasi Tersalur</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Status Penyaluran</th>
                            <th class="py-3 px-4">Penerima / Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($yearEvents)): ?>
                            <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada sesi penyaluran tercatat di tahun ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($yearEvents as $ye): ?>
                                <?php
                                $isTersalurkan = ($ye['disbursement_status'] === 'tersalurkan');
                                $tot = (int)$ye['total_recipients'];
                                $dis = (int)$ye['distributed_count'];
                                $pct = $tot > 0 ? round(($dis / $tot) * 100) : ($isTersalurkan ? 100 : 0);
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($ye['event_name']) ?></div>
                                        <?php if ($ye['total_packages_out'] > 0): ?>
                                            <div class="text-xs text-amber-800 font-semibold"><?= number_format($ye['total_packages_out']) ?> Paket Sembako</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-600 font-medium"><?= formatTanggalIndo($ye['event_date']) ?></td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <?= ucwords(str_replace('_', ' ', $ye['program_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right tabular-nums font-bold text-slate-900"><?= formatRupiah($ye['total_cash_out']) ?></td>
                                    <td class="py-3 px-4 text-right tabular-nums font-extrabold text-emerald-700">
                                        <?= formatRupiah($isTersalurkan ? $ye['total_cash_out'] : $ye['distributed_cash']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <?php if ($isTersalurkan): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <i class="fa-solid fa-check text-[10px]"></i> Tersalurkan
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                                <i class="fa-solid fa-clock text-[10px]"></i> Direncanakan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-slate-600 max-w-xs">
                                        <?php if (!empty($ye['disbursed_to'])): ?>
                                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($ye['disbursed_to']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-slate-500"><?= htmlspecialchars($ye['notes'] ?? '–') ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
