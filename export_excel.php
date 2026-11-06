<?php
/**
 * Ekspor Data ke Format Excel (CSV UTF-8 BOM)
 * Modul G: Generator Laporan Musyawarah Tahunan & Ekspor Data
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();

$type = $_GET['type'] ?? 'lpj';
$year = (int)($_GET['year'] ?? date('Y'));

$filename = "BKRC_Export_{$type}_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Write UTF-8 BOM agar Excel menampilkan aksen karakter dan huruf dengan benar
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'lpj') {
    fputcsv($output, ["LAPORAN PERTANGGUNGJAWABAN (LPJ) KAS BKRC TAHUN $year"]);
    fputcsv($output, ["Bantuan Kemanusiaan Relawan Cluster"]);
    fputcsv($output, []);
    fputcsv($output, ["No", "Bulan", "Donasi Sukarela (Rp)", "Iuran Anggota (Rp)", "Total Masuk (Rp)", "Realisasi Keluar (Rp)", "Saldo Akhir (Rp)", "Sembako Masuk (Paket)", "Sembako Keluar (Paket)"]);

    $bulanNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $running = 0;
    for ($m = 1; $m <= 12; $m++) {
        $stmtDonasi = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'donasi' AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?");
        $stmtDonasi->execute([$year, $m]);
        $mDonasi = (float)$stmtDonasi->fetchColumn();

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

        $running += ($mTotalIn - $mOutCash);

        fputcsv($output, [
            $m,
            $bulanNames[$m],
            $mDonasi,
            $mIuran,
            $mTotalIn,
            $mOutCash,
            $running,
            $mSembakoIn,
            $mOutPackages
        ]);
    }

} elseif ($type === 'donations') {
    fputcsv($output, ["No. Kwitansi", "Kategori", "Tanggal", "Nama Donatur/Anggota", "Tipe", "Nominal (Rp)", "Paket (Qty)", "Rincian", "Peruntukan", "Status", "Alasan Batal/Tolak", "Catatan"]);
    $rows = $pdo->query("
        SELECT d.*, 
            dn.full_name as donor_name, dn.is_anonymous, 
            m.full_name as member_name
        FROM donations d 
        LEFT JOIN donors dn ON d.donor_id = dn.id 
        LEFT JOIN members m ON d.member_id = m.id
        ORDER BY d.donation_date DESC, d.id DESC
    ")->fetchAll();
    foreach ($rows as $r) {
        $name = ($r['transaction_category'] === 'iuran' && !empty($r['member_name'])) ? $r['member_name'] : ($r['is_anonymous'] ? 'Hamba Allah' : ($r['donor_name'] ?: 'Hamba Allah'));
        fputcsv($output, [
            $r['receipt_no'],
            $r['transaction_category'] === 'iuran' ? 'Iuran Bulanan' : 'Donasi Sukarela',
            $r['donation_date'],
            $name,
            $r['type'],
            $r['amount'],
            $r['package_qty'],
            $r['package_details'],
            $r['program_target'],
            $r['status'],
            $r['void_reason'] ?: ($r['rejection_reason'] ?: ''),
            $r['notes']
        ]);
    }

} elseif ($type === 'members') {
    fputcsv($output, ["Kode Anggota", "NIK", "Nama Lengkap", "No WhatsApp", "Cluster / Blok", "RT / RW", "Jabatan", "Tanggal Bergabung", "Status"]);
    $rows = $pdo->query("SELECT * FROM members ORDER BY id ASC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['member_code'],
            "'" . $r['nik'],
            $r['full_name'],
            $r['phone'],
            $r['cluster_block'],
            $r['rt_rw'],
            $r['position'],
            $r['joined_date'],
            $r['status']
        ]);
    }

} elseif ($type === 'beneficiaries') {
    fputcsv($output, ["NIK", "Nomor KK", "Nama Lengkap", "Tanggal Lahir", "Nama Wali", "No HP", "Alamat", "RT/RW", "Kategori", "Status Kelayakan", "Catatan"]);
    $rows = $pdo->query("SELECT * FROM beneficiaries ORDER BY full_name ASC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($output, [
            "'" . $r['nik'],
            "'" . $r['kk_number'],
            $r['full_name'],
            $r['birth_date'],
            $r['guardian_name'],
            $r['phone'],
            $r['address'],
            $r['rt_rw'],
            $r['category'],
            $r['status'],
            $r['notes']
        ]);
    }
}

fclose($output);
exit;
