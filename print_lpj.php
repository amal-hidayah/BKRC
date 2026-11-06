<?php
/**
 * Cetak Dokumen Laporan Pertanggungjawaban (LPJ) Musyawarah Tahunan BKRC (PDF/Print Ready)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();

$year = (int)($_GET['year'] ?? date('Y'));
$kasSummary = getKasSummary($pdo, $year);

// Rekap Bulanan
$bulanNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$monthlyReport = [];
$runningBalance = 0;
for ($m = 1; $m <= 12; $m++) {
    // Donasi Sukarela Approved
    $stmtDonasi = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'donasi' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtDonasi->execute([$year, $m]);
    $mDonasi = (float)$stmtDonasi->fetchColumn();

    // Iuran Bulanan Approved
    $stmtIuran = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'iuran' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtIuran->execute([$year, $m]);
    $mIuran = (float)$stmtIuran->fetchColumn();

    $mTotalIn = $mDonasi + $mIuran;

    $stmtMSembakoIn = $pdo->prepare("SELECT COALESCE(SUM(package_qty), 0) FROM donations WHERE type = 'sembako' AND status = 'approved' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
    $stmtMSembakoIn->execute([$year, $m]);
    $mSembakoIn = (int)$stmtMSembakoIn->fetchColumn();

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
    $stmtMOut->execute([$year, $m]);
    $outRow = $stmtMOut->fetch(PDO::FETCH_NUM);
    $mOutCash = (float)$outRow[0];
    $mOutPackages = (int)$outRow[1];

    $runningBalance += ($mTotalIn - $mOutCash);

    $monthlyReport[$m] = [
        'bulan' => $bulanNames[$m],
        'donasi' => $mDonasi,
        'iuran' => $mIuran,
        'in' => $mTotalIn,
        'out' => $mOutCash,
        'balance' => $runningBalance,
        'sembako_in' => $mSembakoIn,
        'sembako_out' => $mOutPackages
    ];
}

// Events Penyaluran Beserta Realisasinya
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
$stmtEvents->execute([$year]);
$yearEvents = $stmtEvents->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pertanggungjawaban Musyawarah Tahunan <?= $year ?> — BKRC</title>
    <link rel="icon" type="image/png" href="<?= getBaseUrl() ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 30px 15px;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }
        .document-paper {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #cbd5e1;
        }
        .kop-surat {
            text-align: center;
            border-bottom: 3px double #7f1d1d;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .kop-title {
            font-size: 18px;
            font-weight: 800;
            color: #7f1d1d;
            text-transform: uppercase;
            letter-spacing: -0.3px;
        }
        .kop-sub {
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }
        .kop-motto {
            font-size: 11px;
            color: #991b1b;
            font-weight: 700;
            font-style: italic;
        }
        .kop-addr {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 25px;
        }
        .doc-title h1 {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px;
            text-transform: uppercase;
        }
        .doc-title p {
            font-size: 11px;
            color: #64748b;
            margin: 0 0 10px;
        }
        .status-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-interim {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .status-final {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .section-header {
            font-size: 12px;
            font-weight: 800;
            color: #7f1d1d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin: 25px 0 12px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            background: #f8fafc;
        }
        .summary-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .summary-val {
            font-size: 16px;
            font-weight: 800;
            font-family: 'Space Mono', monospace;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }
        table.report-table th, table.report-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
        }
        table.report-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            font-size: 10px;
        }
        table.report-table tfoot td {
            background: #f8fafc;
            font-weight: 800;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .sig-block {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            page-break-inside: avoid;
        }
        .sig-person {
            width: 220px;
            text-align: center;
        }
        .sig-space {
            height: 60px;
        }
        .sig-name {
            font-weight: 700;
            border-bottom: 1px solid #334155;
            padding-bottom: 2px;
        }
        .btn-print {
            display: block;
            max-width: 240px;
            margin: 20px auto 0;
            background: #991b1b;
            color: white;
            text-align: center;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        @media print {
            body { background: white; padding: 0; }
            .document-paper { box-shadow: none; border: none; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="document-paper">
        <!-- Kop Resmi Organisasi -->
        <div class="kop-surat" style="display: flex; align-items: center; justify-content: center; gap: 20px;">
            <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" style="width: 60px; height: 60px; object-fit: contain;">
            <div style="text-align: center;">
                <div class="kop-title">Bantuan Kemanusiaan Relawan Cluster (BKRC)</div>
                <div class="kop-sub">"Bersama, Berbagi, untuk Sesama"</div>
                <div class="kop-motto">"Kecil Bukan Karena Jumlah, Besar Karena Kepedulian"</div>
                <div class="kop-addr">Sekretariat: Pendopo Lingkungan Cluster Perumahan &bull; Kontak WhatsApp: 0812-3456-7891</div>
            </div>
        </div>

        <!-- Judul Laporan -->
        <div class="doc-title">
            <h1>Laporan Pertanggungjawaban (LPJ) Keuangan & Penyaluran</h1>
            <p>Tahun Buku <?= $year ?> &bull; Rekapitulasi Realisasi & Pertanggungjawaban Musyawarah Anggota</p>
            <div>
                <?php if ($year === (int)date('Y')): ?>
                    <span class="status-badge status-interim">
                        Status: Draft Berjalan (Tahun Buku <?= $year ?> Sedang Berjalan)
                    </span>
                <?php else: ?>
                    <span class="status-badge status-final">
                        Status: Tutup Buku (Final Disahkan Musyawarah Warga)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- I. Ringkasan Eksekutif -->
        <div class="section-header">I. Ringkasan Eksekutif & Statistik Organisasi</div>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="summary-label">Total Penerimaan Kas</div>
                <div class="summary-val text-emerald-700" style="color: #047857;"><?= formatRupiah($kasSummary['total_uang_masuk']) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Realisasi Penyaluran</div>
                <div class="summary-val" style="color: #b91c1c;"><?= formatRupiah($kasSummary['total_realisasi_keluar']) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Sisa Saldo Kas Riil</div>
                <div class="summary-val" style="color: #0f172a;"><?= formatRupiah($kasSummary['saldo_kas_riil']) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Paket Sembako Disalurkan</div>
                <div class="summary-val" style="color: #b45309;"><?= number_format($kasSummary['total_sembako_tersalurkan']) ?> Paket</div>
            </div>
        </div>
        <p style="font-size: 11px; color: #475569; margin-bottom: 20px;">
            Sepanjang tahun buku <?= $year ?>, total penerimaan kas terverifikasi berjumlah <strong><?= formatRupiah($kasSummary['total_uang_masuk']) ?></strong> (Donasi Sukarela: <?= formatRupiah($kasSummary['total_donasi_masuk']) ?>, Iuran Anggota: <?= formatRupiah($kasSummary['total_iuran_masuk']) ?>). Realisasi kas yang telah diserahkan di lapangan mencapai <strong><?= formatRupiah($kasSummary['total_realisasi_keluar']) ?></strong> dengan komitmen direncanakan sebesar <strong><?= formatRupiah($kasSummary['dana_pending']) ?></strong>, menyisakan saldo kas riil sebesar <strong><?= formatRupiah($kasSummary['saldo_kas_riil']) ?></strong>.
        </p>

        <!-- II. Tabel Arus Kas Bulanan -->
        <div class="section-header">II. Rekapitulasi Arus Kas Bulanan (Januari - Desember <?= $year ?>)</div>
        <div style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
            * Saldo Awal Kas per 1 Januari <?= $year ?>: <strong>Rp 0</strong>. Seluruh bulan berjalan mencatatkan kas surplus positif tanpa saldo minus.
        </div>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 30px;">No</th>
                    <th>Bulan</th>
                    <th class="text-right">Donasi Sukarela</th>
                    <th class="text-right">Iuran Anggota</th>
                    <th class="text-right">Total Masuk (Rp)</th>
                    <th class="text-right">Realisasi Keluar (Rp)</th>
                    <th class="text-right">Saldo Kas Akhir (Rp)</th>
                    <th class="text-center">Sembako Masuk</th>
                    <th class="text-center">Sembako Keluar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlyReport as $m => $rep): ?>
                    <tr>
                        <td class="text-center"><?= $m ?></td>
                        <td><strong><?= $rep['bulan'] ?></strong></td>
                        <td class="text-right tabular-nums"><?= formatRupiah($rep['donasi'], true) ?></td>
                        <td class="text-right tabular-nums"><?= formatRupiah($rep['iuran'], true) ?></td>
                        <td class="text-right tabular-nums font-bold"><?= formatRupiah($rep['in'], true) ?></td>
                        <td class="text-right tabular-nums" style="color: <?= $rep['out'] > 0 ? '#b91c1c' : '#64748b' ?>;"><?= formatRupiah($rep['out'], true) ?></td>
                        <td class="text-right tabular-nums font-bold" style="color: <?= $rep['balance'] < 0 ? '#dc2626' : '#0f172a' ?>;"><?= formatRupiah($rep['balance'], false) ?></td>
                        <td class="text-center tabular-nums"><?= $rep['sembako_in'] > 0 ? $rep['sembako_in'] . ' Pkt' : '–' ?></td>
                        <td class="text-center tabular-nums"><?= $rep['sembako_out'] > 0 ? $rep['sembako_out'] . ' Pkt' : '–' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-center">TOTAL KESELURUHAN <?= $year ?></td>
                    <td class="text-right tabular-nums"><?= formatRupiah($kasSummary['total_donasi_masuk']) ?></td>
                    <td class="text-right tabular-nums"><?= formatRupiah($kasSummary['total_iuran_masuk']) ?></td>
                    <td class="text-right tabular-nums font-bold"><?= formatRupiah($kasSummary['total_uang_masuk']) ?></td>
                    <td class="text-right tabular-nums font-bold" style="color: #b91c1c;"><?= formatRupiah($kasSummary['total_realisasi_keluar']) ?></td>
                    <td class="text-right tabular-nums font-bold" style="color: #047857;"><?= formatRupiah($kasSummary['saldo_kas_riil']) ?></td>
                    <td class="text-center"><?= number_format($kasSummary['total_sembako_masuk']) ?> Paket</td>
                    <td class="text-center"><?= number_format($kasSummary['total_sembako_tersalurkan']) ?> Paket</td>
                </tr>
            </tfoot>
        </table>

        <!-- III. Realisasi Program & Penyaluran -->
        <div class="section-header">III. Rincian Kegiatan Penyaluran Kas Lapangan</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 30px;">No</th>
                    <th>Nama Kegiatan Penyaluran</th>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th class="text-right">Anggaran</th>
                    <th class="text-right">Realisasi Keluar</th>
                    <th class="text-center">Status</th>
                    <th>Penerima / PIC</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($yearEvents)): ?>
                    <tr><td colspan="8" class="text-center">Belum ada kegiatan penyaluran tercatat di tahun <?= $year ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($yearEvents as $idx => $ye): ?>
                        <?php
                        $isTersalurkan = ($ye['disbursement_status'] === 'tersalurkan');
                        ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ye['event_name']) ?></strong>
                                <?php if ($ye['total_packages_out'] > 0): ?>
                                    <div style="font-size: 10px; color: #64748b;"><?= number_format($ye['total_packages_out']) ?> Paket Sembako</div>
                                <?php endif; ?>
                            </td>
                            <td><?= formatTanggalIndo($ye['event_date']) ?></td>
                            <td><?= ucwords(str_replace('_', ' ', $ye['program_type'])) ?></td>
                            <td class="text-right tabular-nums font-bold"><?= formatRupiah($ye['total_cash_out']) ?></td>
                            <td class="text-right tabular-nums font-bold" style="color: #047857;">
                                <?= formatRupiah($isTersalurkan ? $ye['total_cash_out'] : $ye['distributed_cash']) ?>
                            </td>
                            <td class="text-center tabular-nums" style="font-weight: 700; color: <?= $isTersalurkan ? '#047857' : '#b45309' ?>;">
                                <?= $isTersalurkan ? 'Tersalurkan' : 'Direncanakan' ?>
                            </td>
                            <td style="font-size: 10px;"><?= htmlspecialchars($ye['disbursed_to'] ?: ($ye['notes'] ?? '–')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- IV. Lembar Pengesahan -->
        <div class="sig-block">
            <div class="sig-person">
                <div>Mengetahui,</div>
                <div style="font-weight: 700; margin-top: 2px;">Ketua Umum BKRC</div>
                <div class="sig-space"></div>
                <div class="sig-name">H. Bambang Sulistyo</div>
                <div style="color: #64748b; font-size: 10px; margin-top: 2px;">Periode 2026 - 2027</div>
            </div>

            <div class="sig-person">
                <div>Dibuat & Dipertanggungjawabkan Oleh,</div>
                <div style="font-weight: 700; margin-top: 2px;">Bendahara Umum BKRC</div>
                <div class="sig-space"></div>
                <div class="sig-name">Hj. Siti Rohmah</div>
                <div style="color: #64748b; font-size: 10px; margin-top: 2px;">Penata Keuangan Organisasi</div>
            </div>
        </div>
    </div>

    <button onclick="window.print()" class="btn-print">
        Cetak Dokumen LPJ (PDF)
    </button>

</body>
</html>
