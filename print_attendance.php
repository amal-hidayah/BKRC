<?php
/**
 * Cetak Lembar Absensi / Tanda Terima Santunan & Sembako (Tanda Tangan Basah di Lapangan)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();

$eventId = (int)($_GET['event_id'] ?? 0);
$stmtEvent = $pdo->prepare("SELECT * FROM distribution_events WHERE id = ?");
$stmtEvent->execute([$eventId]);
$event = $stmtEvent->fetch();

if (!$event) {
    die("Sesi acara tidak ditemukan.");
}

$stmtLogs = $pdo->prepare("
    SELECT l.*, b.full_name, b.nik, b.guardian_name, b.address, b.rt_rw, b.category
    FROM distribution_logs l
    JOIN beneficiaries b ON l.beneficiary_id = b.id
    WHERE l.event_id = ?
    ORDER BY b.category ASC, b.full_name ASC
");
$stmtLogs->execute([$eventId]);
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Tanda Terima Santunan — <?= htmlspecialchars($event['event_name']) ?></title>
    <link rel="icon" type="image/png" href="<?= getBaseUrl() ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 30px 15px;
            color: #0f172a;
            font-size: 11px;
        }
        .paper {
            max-width: 820px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #7f1d1d;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 16px;
            font-weight: 800;
            color: #7f1d1d;
            text-transform: uppercase;
        }
        .sub {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }
        .meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #94a3b8;
            padding: 8px 6px;
            vertical-align: middle;
        }
        th {
            background: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }
        .sig-cell {
            width: 140px;
            height: 38px;
            text-align: left;
            font-size: 9px;
            color: #94a3b8;
        }
        .btn-print {
            display: block;
            max-width: 200px;
            margin: 20px auto 0;
            background: #991b1b;
            color: white;
            text-align: center;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }
        @media print {
            body { background: white; padding: 0; }
            .paper { box-shadow: none; border: none; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="paper">
        <div class="header" style="display: flex; align-items: center; justify-content: center; gap: 15px;">
            <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" style="width: 50px; height: 50px; object-fit: contain;">
            <div style="text-align: center;">
                <div class="title">Bantuan Kemanusiaan Relawan Cluster (BKRC)</div>
                <div class="sub">LEMBAR TANDA TERIMA SANTUNAN & PAKET SEMBAKO</div>
                <div class="meta">
                    Kegiatan: <strong><?= htmlspecialchars($event['event_name']) ?></strong> &bull; 
                    Tanggal: <strong><?= formatTanggalIndo($event['event_date'], true) ?></strong>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">No</th>
                    <th>Nama Penerima / Anak Yatim</th>
                    <th>Wali / Pengasuh</th>
                    <th>Alamat / RT-RW</th>
                    <th>Santunan Uang</th>
                    <th>Paket Sembako</th>
                    <th style="text-align: center;">Tanda Tangan Basah</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">Belum ada daftar penerima di acara ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $idx => $l): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700;"><?= $idx + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($l['full_name']) ?></strong>
                                <span style="display: block; font-size: 9px; color: #64748b;"><?= ucfirst($l['category']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($l['guardian_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($l['address']) ?> (<?= htmlspecialchars($l['rt_rw']) ?>)</td>
                            <td style="font-family: 'Space Mono', monospace; font-weight: 700;"><?= $l['cash_amount'] > 0 ? formatRupiah($l['cash_amount']) : '-' ?></td>
                            <td><?= htmlspecialchars($l['sembako_package_detail'] ?: '-') ?></td>
                            <td class="sig-cell">
                                <span style="font-size: 9px; color: #94a3b8;"><?= $idx + 1 ?>. ..........</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pengesahan Petugas Lapangan -->
        <div style="display: flex; justify-content: space-between; margin-top: 30px; font-size: 11px;">
            <div style="text-align: center; width: 200px;">
                <div>Mengetahui,</div>
                <div style="font-weight: 700;">Koordinator Lapangan</div>
                <div style="height: 55px;"></div>
                <div style="border-bottom: 1px solid #334155; font-weight: 700;">Rizky Pratama</div>
                <div style="font-size: 9px; color: #64748b;">Relawan Cluster BKRC</div>
            </div>

            <div style="text-align: center; width: 200px;">
                <div>Diserahkan Oleh,</div>
                <div style="font-weight: 700;">Petugas Penyaluran</div>
                <div style="height: 55px;"></div>
                <div style="border-bottom: 1px solid #334155; font-weight: 700;">Dedi Kurniawan</div>
                <div style="font-size: 9px; color: #64748b;">Divisi Logistik BKRC</div>
            </div>
        </div>
    </div>

    <button onclick="window.print()" class="btn-print">
        Cetak Lembar Tanda Terima (PDF)
    </button>

</body>
</html>
