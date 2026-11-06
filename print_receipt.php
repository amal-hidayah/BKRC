<?php
/**
 * Cetak Tanda Terima / Kwitansi Resmi Donasi BKRC (PDF/Print Ready)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT d.*, 
        dn.full_name as donor_name, dn.phone as donor_phone, dn.cluster_or_address, dn.is_anonymous, 
        m.full_name as member_name, m.member_code, m.cluster_block as member_block,
        u.name as recorded_by_name,
        ua.name as approved_by_name
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.id
    LEFT JOIN members m ON d.member_id = m.id
    LEFT JOIN users u ON d.recorded_by = u.id
    LEFT JOIN users ua ON d.approved_by = ua.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$donation = $stmt->fetch();

if (!$donation) {
    die("Kwitansi tidak ditemukan atau tautan tidak valid.");
}

// Keamanan: Jika kwitansi belum approved / masih pending / void, hanya admin yang boleh melihat
if ($donation['status'] !== 'approved' && !isLoggedIn()) {
    requireAuth();
}

$isUang = ($donation['type'] === 'uang');
$isIuran = ($donation['transaction_category'] === 'iuran');
$donorName = $donation['is_anonymous'] ? 'Hamba Allah' : ($isIuran && !empty($donation['member_name']) ? $donation['member_name'] : $donation['donor_name']);
$addressDisplay = $isIuran && !empty($donation['member_block']) ? 'Blok ' . $donation['member_block'] . ' (Anggota)' : ($donation['cluster_or_address'] ?: 'Warga Cluster');


// Algoritma Terbilang Rupiah Sederhana
function terbilang($angka) {
    $angka = abs((float)$angka);
    $baca = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $terbilang = "";

    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } elseif ($angka < 100) {
        $terbilang = terbilang(floor($angka / 10)) . " puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang(floor($angka / 100)) . " ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang(floor($angka / 1000)) . " ribu" . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang(floor($angka / 1000000)) . " juta" . terbilang($angka % 1000000);
    }
    return ucfirst(trim($terbilang)) . " rupiah";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi Donasi — <?= htmlspecialchars($donation['receipt_no']) ?> — Bantuan Kemanusiaan Relawan Cluster (BKRC)</title>
    <link rel="icon" type="image/png" href="<?= getBaseUrl() ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 40px 20px;
            color: #1e293b;
        }
        .receipt-card {
            max-width: 750px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            border: 2px solid #e2e8f0;
            position: relative;
            overflow: hidden;
            padding: 40px;
        }
        .top-stripe {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: #991b1b;
        }
        .kop {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .logo-box {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-circle {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            background: #991b1b;
            color: #fbbf24;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 24px;
        }
        .org-name {
            font-size: 20px;
            font-weight: 800;
            color: #7f1d1d;
            letter-spacing: -0.5px;
        }
        .org-sub {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        .receipt-meta {
            text-align: right;
        }
        .receipt-badge {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .receipt-no {
            font-family: 'Space Mono', monospace;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .receipt-date {
            font-size: 11px;
            color: #64748b;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px;
        }
        .table-data td {
            padding: 10px 6px;
            vertical-align: top;
        }
        .table-data td.label {
            width: 160px;
            font-weight: 600;
            color: #64748b;
        }
        .table-data td.colon {
            width: 15px;
            font-weight: 700;
            color: #94a3b8;
        }
        .table-data td.value {
            color: #0f172a;
            font-weight: 600;
        }
        .amount-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 18px 24px;
            margin: 25px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-val {
            font-family: 'Space Mono', monospace;
            font-size: 26px;
            font-weight: 700;
            color: #047857;
        }
        .amount-words {
            font-size: 12px;
            font-style: italic;
            color: #475569;
            margin-top: 4px;
        }
        .footer-sig {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
        }
        .sig-col {
            text-align: center;
            width: 200px;
        }
        .sig-space {
            height: 60px;
        }
        .sig-name {
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 2px;
        }
        .sig-title {
            color: #64748b;
            font-size: 11px;
            margin-top: 3px;
        }
        .btn-print {
            display: block;
            max-width: 200px;
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
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.2);
        }
        @media print {
            body { background: white; padding: 0; }
            .receipt-card { box-shadow: none; border: none; padding: 20px; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="receipt-card">
        <div class="top-stripe"></div>

        <!-- Kop Surat Kwitansi -->
        <div class="kop">
            <div class="logo-box">
                <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="BKRC" style="width: 55px; height: 55px; object-fit: contain;">
                <div>
                    <div class="org-name">BKRC</div>
                    <div class="org-sub">Bantuan Kemanusiaan Relawan Cluster</div>
                    <div class="org-sub">"Bersama, Berbagi, untuk Sesama"</div>
                </div>
            </div>
            <div class="receipt-meta">
                <div class="receipt-badge"><?= $isIuran ? 'Tanda Terima Iuran Anggota' : 'Tanda Terima Donasi' ?></div>
                <div class="receipt-no"><?= htmlspecialchars($donation['receipt_no']) ?></div>
                <div class="receipt-date"><?= formatTanggalIndo($donation['donation_date']) ?></div>
            </div>
        </div>

        <!-- Detail Kwitansi -->
        <table class="table-data">
            <tr>
                <td class="label">Telah Diterima Dari</td>
                <td class="colon">:</td>
                <td class="value" style="font-size: 15px;"><?= htmlspecialchars($donorName) ?></td>
            </tr>
            <tr>
                <td class="label">Alamat / Cluster</td>
                <td class="colon">:</td>
                <td class="value"><?= htmlspecialchars($addressDisplay) ?></td>
            </tr>
            <tr>
                <td class="label">Jenis Penerimaan</td>
                <td class="colon">:</td>
                <td class="value"><?= $isIuran ? 'Iuran Kas Bulanan Anggota' : ($isUang ? 'Uang Tunai / Transfer Perbankan' : 'Paket Sembako / Barang Logistik') ?></td>
            </tr>
            <tr>
                <td class="label">Peruntukan Program</td>
                <td class="colon">:</td>
                <td class="value" style="color: #991b1b;"><?= htmlspecialchars($donation['program_target']) ?></td>
            </tr>
            <?php if (!empty($donation['notes'])): ?>
            <tr>
                <td class="label">Catatan / Doa Donatur</td>
                <td class="colon">:</td>
                <td class="value" style="font-style: italic; color: #475569;"><?= htmlspecialchars($donation['notes']) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Amount / Package Display Box -->
        <div class="amount-box">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.5px;">
                    <?= $isUang ? 'Jumlah Donasi Terbilang' : 'Rincian Paket Bantuan' ?>
                </div>
                <div class="amount-words">
                    <?= $isUang ? terbilang($donation['amount']) : htmlspecialchars($donation['package_details'] ?? 'Paket Sembako Lengkap') ?>
                </div>
            </div>
            <div class="amount-val">
                <?= $isUang ? formatRupiah($donation['amount']) : number_format($donation['package_qty']) . ' Paket' ?>
            </div>
        </div>

        <div style="font-size: 11px; color: #64748b; line-height: 1.5; font-style: italic;">
            * Dokumen ini merupakan bukti sah tanda terima donasi resmi BKRC yang dicatat ke dalam Papan Transparansi Publik. Semoga menjadi amal jariyah yang berlipat ganda. Aamiin.
        </div>

        <!-- Tanda Tangan -->
        <div class="footer-sig">
            <div class="sig-col">
                <div>Donatur / Penyetor,</div>
                <div class="sig-space"></div>
                <div class="sig-name"><?= htmlspecialchars($donorName) ?></div>
                <div class="sig-title">Warga / Donatur</div>
            </div>

            <div class="sig-col">
                <div>Pengurus BKRC,</div>
                <div class="sig-space"></div>
                <div class="sig-name"><?= htmlspecialchars($donation['approved_by_name'] ?? ($donation['recorded_by_name'] ?? 'Bendahara BKRC')) ?></div>
                <div class="sig-title">Bendahara / Pengurus</div>
            </div>
        </div>
    </div>

    <button onclick="window.print()" class="btn-print">
        <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
    </button>

</body>
</html>
