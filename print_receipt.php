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

// Proteksi Keamanan: Anti-Enumeration & Kontrol Akses (CWE-639)
// 1. Jika kwitansi belum approved, hanya pengurus yang login boleh membuka
if ($donation['status'] !== 'approved' && !isLoggedIn()) {
    requireAuth();
}

// 2. Jika diakses oleh publik tanpa sesi login, WAJIB menyertakan valid security key
if (!isLoggedIn()) {
    $expectedKey = getReceiptSecurityToken($donation['id'], $donation['receipt_no'], $donation['created_at'] ?? '');
    $providedKey = trim($_GET['key'] ?? '');
    if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
        // Kunci token tidak valid atau tidak disertakan -> wajib login untuk mencegah enumerasi
        requireAuth();
    }
}

$isUang = ($donation['type'] === 'uang');
$isIuran = ($donation['transaction_category'] === 'iuran');
$donorName = $donation['is_anonymous'] ? 'Hamba Allah' : ($isIuran && !empty($donation['member_name']) ? $donation['member_name'] : $donation['donor_name']);
$addressDisplay = $isIuran && !empty($donation['member_block']) ? 'Blok ' . $donation['member_block'] . ' (Anggota)' : ($donation['cluster_or_address'] ?: 'Warga Cluster');
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
            background-color: #faf8f5;
            margin: 0;
            padding: 40px 20px;
            color: #1c1917;
        }
        .receipt-card {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e7e5e4;
            position: relative;
            overflow: hidden;
            padding: 36px;
        }
        .top-stripe {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #8b1e1e;
        }
        .kop {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e7e5e4;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .logo-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .org-name {
            font-size: 18px;
            font-weight: 700;
            color: #8b1e1e;
            letter-spacing: -0.3px;
        }
        .org-sub {
            font-size: 11px;
            color: #78716c;
            font-weight: 400;
            margin-top: 1px;
        }
        .receipt-meta {
            text-align: right;
        }
        .receipt-badge {
            display: inline-block;
            background: #fef2f2;
            color: #8b1e1e;
            border: 1px solid #fecaca;
            font-size: 11px;
            font-medium: 600;
            padding: 3px 10px;
            border-radius: 999px;
            margin-bottom: 6px;
        }
        .receipt-no {
            font-family: 'Space Mono', monospace;
            font-size: 14px;
            font-weight: 700;
            color: #1c1917;
        }
        .receipt-date {
            font-size: 11px;
            color: #78716c;
            margin-top: 2px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px;
        }
        .table-data td {
            padding: 8px 6px;
            vertical-align: top;
        }
        .table-data td.label {
            width: 160px;
            font-weight: 500;
            color: #78716c;
        }
        .table-data td.colon {
            width: 15px;
            font-weight: 500;
            color: #a8a29e;
        }
        .table-data td.value {
            color: #1c1917;
            font-weight: 600;
        }
        .amount-box {
            background: #faf8f5;
            border: 1px dashed #d6d3d1;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-val {
            font-family: 'Space Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            color: #8b1e1e;
        }
        .amount-words {
            font-size: 12px;
            font-style: italic;
            color: #57534e;
            margin-top: 4px;
        }
        .footer-sig {
            display: flex;
            justify-content: space-between;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #e7e5e4;
            font-size: 12px;
        }
        .sig-col {
            text-align: center;
            width: 190px;
        }
        .sig-space {
            height: 56px;
        }
        .sig-name {
            font-weight: 600;
            color: #1c1917;
            border-bottom: 1px solid #a8a29e;
            padding-bottom: 2px;
        }
        .sig-title {
            color: #78716c;
            font-size: 11px;
            margin-top: 3px;
        }
        .btn-print {
            display: block;
            max-width: 180px;
            margin: 20px auto 0;
            background: #8b1e1e;
            color: white;
            text-align: center;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: background 0.2s;
        }
        .btn-print:hover {
            background: #731717;
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
