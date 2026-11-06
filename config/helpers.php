<?php
/**
 * Helper Functions Sistem Informasi Manajemen & Portal Transparansi BKRC
 * Standar Kompetensi: J.620100.022.02 (Mengimplementasikan Algoritma Pemrograman)
 */

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Format angka ke format Rupiah (Rp. xx.xxx)
 * Mendukung opsi tanda strip untuk nilai 0 dan format negatif rapi (-Rp xx.xxx)
 */
function formatRupiah($nominal, $zeroAsDash = false) {
    $val = (float)$nominal;
    if ($zeroAsDash && abs($val) < 0.001) {
        return '–';
    }
    if ($val < 0) {
        return '-Rp ' . number_format(abs($val), 0, ',', '.');
    }
    return 'Rp ' . number_format($val, 0, ',', '.');
}

/**
 * Samarkan NIK untuk Kepatuhan UU PDP (Tampilkan 4 digit awal & 4 digit akhir)
 */
function maskNik($nik) {
    $nik = trim($nik);
    if (strlen($nik) < 8) return $nik;
    return substr($nik, 0, 4) . '••••••••' . substr($nik, -4);
}

/**
 * Samarkan Nomor Telepon / WhatsApp
 */
function maskPhone($phone) {
    $phone = trim($phone);
    if (strlen($phone) < 8) return $phone ?: '–';
    return substr($phone, 0, 4) . '••••' . substr($phone, -4);
}

/**
 * CSRF Protection Helpers
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Hitung Kategori Donatur Secara Dinamis dari Riwayat Transaksi
 */
function calculateDonorType($countDonations, $donorName = '', $clusterAddress = '') {
    $str = $donorName . ' ' . $clusterAddress;
    if (stripos($str, 'PT ') !== false || stripos($str, 'PT. ') !== false || stripos($str, 'CV ') !== false || stripos($str, 'Niaga') !== false) {
        return 'Sponsor Lembaga';
    }
    if ((int)$countDonations >= 2) {
        return 'Rutin Bulanan';
    }
    if ((int)$countDonations === 1) {
        return 'Insidental';
    }
    return 'Terdaftar';
}

/**
 * Format tanggal standar (YYYY-MM-DD) ke format Bahasa Indonesia
 */
function formatTanggalIndo($tanggal, $tampilkanHari = false) {
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }

    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $timestamp = strtotime($tanggal);
    $namaHari = $hari[date('w', $timestamp)];
    $tgl = date('j', $timestamp);
    $bln = $bulan[(int)date('n', $timestamp)];
    $thn = date('Y', $timestamp);

    if ($tampilkanHari) {
        return "$namaHari, $tgl $bln $thn";
    }
    return "$tgl $bln $thn";
}

/**
 * Algoritma Menghitung Usia Anak Yatim dan Validasi Batas Usia Santunan
 * Sesuai PRD: Pantau batas usia penerima santunan anak yatim (batas evaluasi: 15 tahun)
 */
function calculateBeneficiaryAge($birthDate) {
    if (empty($birthDate) || $birthDate === '0000-00-00') {
        return [
            'age_years' => 0,
            'age_months' => 0,
            'text' => '-',
            'is_over_age' => false,
            'badge_class' => 'bg-gray-100 text-gray-700'
        ];
    }

    $dob = new DateTime($birthDate);
    $today = new DateTime('today');
    $diff = $dob->diff($today);

    $years = $diff->y;
    $months = $diff->m;
    $isOverAge = ($years >= 15);

    return [
        'age_years' => $years,
        'age_months' => $months,
        'text' => "{$years} tahun {$months} bln",
        'is_over_age' => $isOverAge,
        'badge_class' => $isOverAge 
            ? 'bg-amber-50 text-amber-700 border border-amber-200' 
            : 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        'status_label' => $isOverAge ? 'Perlu Evaluasi (> 15 Th)' : 'Usia Santunan Aktif'
    ];
}

/**
 * Algoritma Generate Nomor Kwitansi Otomatis (KW-YYYY-XXXX)
 */
function generateReceiptNo($pdo) {
    $year = date('Y');
    $prefix = "KW-{$year}-";

    $stmt = $pdo->prepare("SELECT receipt_no FROM donations WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastReceipt = $stmt->fetchColumn();

    if ($lastReceipt) {
        $lastNumber = (int)substr($lastReceipt, strlen($prefix));
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextNumber = '0001';
    }

    return $prefix . $nextNumber;
}

/**
 * Pembersihan Input untuk Sanitasi XSS
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash Message Handler
 */
function setFlash($key, $message, $type = 'success') {
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Cek Sesi Autentikasi Admin
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Pengurus',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'admin'
    ];
}

function requireAuth() {
    if (!isLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu untuk mengakses back-office.', 'error');
        header('Location: ' . getBaseUrl() . '/login.php');
        exit;
    }
}

function requireSuperAdmin() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'superadmin') {
        setFlash('error', 'Akses ditolak. Fitur ini khusus Ketua Umum / Super Admin.', 'error');
        header('Location: ' . getBaseUrl() . '/admin/index.php');
        exit;
    }
}

/**
 * Dapatkan Base URL Dinamis (Otomatis mendeteksi folder localhost/BKRC)
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    
    // Normalisasi direktori untuk root project BKRC
    if (strpos($dir, '/admin') !== false) {
        $dir = substr($dir, 0, strpos($dir, '/admin'));
    }
    $dir = rtrim($dir, '/\\');
    
    return $protocol . $host . $dir;
}

/**
 * Format nomor WhatsApp ke format internasional (misal 0812... -> 62812...)
 * dan bangun URL WhatsApp siap kirim (https://wa.me/...)
 */
function buildWaUrl($phone, $message = '') {
    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($cleanPhone, '0') === 0) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (strpos($cleanPhone, '8') === 0) {
        $cleanPhone = '62' . $cleanPhone;
    }
    
    $url = "https://wa.me/" . $cleanPhone;
    if (!empty($message)) {
        $url .= "?text=" . rawurlencode($message);
    }
    return $url;
}

/**
 * Ambil Nilai Pengaturan dari Tabel Settings
 */
function getSetting($key, $default = '', $pdo = null) {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            if ($pdo === null) {
                global $pdo;
            }
            if ($pdo) {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
                while ($row = $stmt->fetch()) {
                    $settingsCache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // fallback jika tabel settings belum siap
        }
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Informasi Rekening Bank Resmi BKRC (Satu Sumber Data / Single Source of Truth)
 */
function getOfficialBank($pdo = null) {
    return [
        'name' => getSetting('bank_name', 'Bank Syariah Indonesia (BSI)', $pdo),
        'code' => getSetting('bank_code', '451', $pdo),
        'account_formatted' => getSetting('bank_account_number', '718 293 8472', $pdo),
        'account_raw' => getSetting('bank_account_raw', '7182938472', $pdo),
        'holder' => getSetting('bank_account_holder', 'Bantuan Kemanusiaan Relawan Cluster', $pdo)
    ];
}

/**
 * Nomor Kontak Resmi Bendahara BKRC untuk Konfirmasi Pembayaran
 */
function getTreasurerPhone($pdo = null) {
    return getSetting('treasurer_phone', '081234567891', $pdo);
}

function getTreasurerName($pdo = null) {
    return getSetting('treasurer_name', 'Hj. Siti Rohmah', $pdo);
}

/**
 * Konversi Input Nominal Rupiah / Angka Aman
 * Menangani format: '1500000', '1500000.00', '1.500.000', 'Rp 1.500.000', '50.000', dsb.
 * Mencegah bug string float yang kehilangan titik desimal dan terkalikan 100x.
 */
function parseRupiahInput($input) {
    if (is_int($input) || is_float($input)) {
        return (float)$input;
    }

    if (!is_string($input)) {
        return (float)$input;
    }

    $clean = trim($input);
    // Hapus teks 'Rp' dan karakter selain angka, titik, koma
    $clean = preg_replace('/[^0-9,\.]/', '', $clean);
    if ($clean === '') return 0.0;

    // Jika ada koma (format desimal Indonesia, misal: 1.500.000,00 atau 50000,50)
    if (strpos($clean, ',') !== false) {
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        return (float)$clean;
    }

    // Jika hanya ada titik:
    if (substr_count($clean, '.') > 1) {
        // Banyak titik: pasti pemisah ribuan (misal: 1.500.000)
        $clean = str_replace('.', '', $clean);
        return (float)$clean;
    }

    if (substr_count($clean, '.') === 1) {
        $parts = explode('.', $clean);
        // Jika setelah titik ada tepat 3 digit (misal: 50.000 atau 150.000) -> pemisah ribuan
        if (strlen($parts[1]) === 3) {
            $clean = str_replace('.', '', $clean);
            return (float)$clean;
        }
        // Jika setelah titik ada 1 atau 2 digit (misal: 1500000.00) -> desimal float
        return (float)$clean;
    }

    return (float)$clean;
}

/**
 * Rekapitulasi Kas & Sembako (Agregasi Data SQL Sesuai Cetak Biru)
 * Digunakan untuk Counter Transparansi Publik, Dashboard Back-Office & Laporan LPJ
 */
function getKasSummary($pdo, $year = null) {
    // 1. Total Uang Masuk Approved (Dipisahkan Donasi Sukarela vs Iuran Bulanan)
    $queryIn = "SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved'";
    $paramsIn = [];
    if ($year) {
        $queryIn .= " AND YEAR(donation_date) = ?";
        $paramsIn[] = $year;
    }
    $stmtIn = $pdo->prepare($queryIn);
    $stmtIn->execute($paramsIn);
    $totalUangMasuk = (float)$stmtIn->fetchColumn();

    // Donasi Sukarela Approved
    $queryDonasi = "SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'donasi'";
    $stmtDonasi = $pdo->prepare($queryDonasi . ($year ? " AND YEAR(donation_date) = ?" : ""));
    $stmtDonasi->execute($paramsIn);
    $totalDonasiMasuk = (float)$stmtDonasi->fetchColumn();

    // Iuran Bulanan Anggota Approved
    $queryIuran = "SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND status = 'approved' AND transaction_category = 'iuran'";
    $stmtIuran = $pdo->prepare($queryIuran . ($year ? " AND YEAR(donation_date) = ?" : ""));
    $stmtIuran->execute($paramsIn);
    $totalIuranMasuk = (float)$stmtIuran->fetchColumn();

    // Jumlah Transaksi Pending Verifikasi
    $pendingQuery = "SELECT COUNT(*) FROM donations WHERE status = 'pending'";
    $totalPendingDonations = (int)$pdo->query($pendingQuery)->fetchColumn();

    // Jumlah Calon Anggota Baru Pending Approval
    $totalPendingMembers = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'pending'")->fetchColumn();

    // 2. Total Anggaran Kas Keluar (Semua rencana & realisasi)
    $queryAnggaranOut = "SELECT COALESCE(SUM(total_cash_out), 0) FROM distribution_events";
    $paramsAnggaranOut = [];
    if ($year) {
        $queryAnggaranOut .= " WHERE YEAR(event_date) = ?";
        $paramsAnggaranOut[] = $year;
    }
    $stmtAnggaranOut = $pdo->prepare($queryAnggaranOut);
    $stmtAnggaranOut->execute($paramsAnggaranOut);
    $totalAnggaranKeluar = (float)$stmtAnggaranOut->fetchColumn();

    // 3. Total Realisasi Kas Tersalurkan Lapangan
    // Mengambil dari distribution_events yang statusnya 'tersalurkan' 
    // DAN dari distribution_logs yang statusnya 'distributed' untuk event yang masih parsial
    $queryRealisasiOut = "
        SELECT COALESCE(SUM(
            CASE 
                WHEN e.disbursement_status = 'tersalurkan' THEN e.total_cash_out
                ELSE COALESCE(part.log_sum, 0)
            END
        ), 0)
        FROM distribution_events e
        LEFT JOIN (
            SELECT event_id, SUM(cash_amount) as log_sum 
            FROM distribution_logs 
            WHERE status = 'distributed' 
            GROUP BY event_id
        ) part ON e.id = part.event_id
    ";
    $paramsRealisasiOut = [];
    if ($year) {
        $queryRealisasiOut .= " WHERE YEAR(e.event_date) = ?";
        $paramsRealisasiOut[] = $year;
    }
    $stmtRealisasiOut = $pdo->prepare($queryRealisasiOut);
    $stmtRealisasiOut->execute($paramsRealisasiOut);
    $totalRealisasiKeluar = (float)$stmtRealisasiOut->fetchColumn();

    // Rincian Realisasi Pengeluaran per 5 Kategori
    $categories = ['santunan_yatim', 'sembako', 'event', 'pinjaman', 'lainnya'];
    $pengeluaranKategori = [];
    foreach ($categories as $cat) {
        $queryCat = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN e.disbursement_status = 'tersalurkan' THEN e.total_cash_out
                    ELSE COALESCE(part.log_sum, 0)
                END
            ), 0)
            FROM distribution_events e
            LEFT JOIN (
                SELECT event_id, SUM(cash_amount) as log_sum 
                FROM distribution_logs 
                WHERE status = 'distributed' 
                GROUP BY event_id
            ) part ON e.id = part.event_id
            WHERE e.program_type = ?
        ";
        $paramsCat = [$cat];
        if ($year) {
            $queryCat .= " AND YEAR(e.event_date) = ?";
            $paramsCat[] = $year;
        }
        $st = $pdo->prepare($queryCat);
        $st->execute($paramsCat);
        $pengeluaranKategori[$cat] = (float)$st->fetchColumn();
    }

    // Dana Komitmen Pending yang Belum Disalurkan (rencana kegiatan yang belum dicairkan)
    $danaPending = max(0, $totalAnggaranKeluar - $totalRealisasiKeluar);

    // Saldo Kas Riil Tersedia di Rekening Bank (Uang Masuk Approved - Uang yang Benar-Benar Sudah Cair)
    // Sesuai prinsip akuntansi kas: saldo kas aktif adalah kas likuid di rekening
    $saldoKasRiil = max(0, $totalUangMasuk - $totalRealisasiKeluar);

    // Saldo Kas Proyeksi Setelah Cadangan Seluruh Komitmen Anggaran
    $saldoKasKomitmen = $saldoKasRiil - $danaPending;

    // 4. Sembako Masuk & Tersalurkan
    $querySembakoIn = "SELECT COALESCE(SUM(package_qty), 0) FROM donations WHERE type = 'sembako' AND status = 'approved'";
    $paramsSembakoIn = [];
    if ($year) {
        $querySembakoIn .= " AND YEAR(donation_date) = ?";
        $paramsSembakoIn[] = $year;
    }
    $stmtSembakoIn = $pdo->prepare($querySembakoIn);
    $stmtSembakoIn->execute($paramsSembakoIn);
    $totalSembakoMasuk = (int)$stmtSembakoIn->fetchColumn();

    $querySembakoOut = "SELECT COALESCE(SUM(total_packages_out), 0) FROM distribution_events";
    $paramsSembakoOut = [];
    if ($year) {
        $querySembakoOut .= " WHERE YEAR(event_date) = ?";
        $paramsSembakoOut[] = $year;
    }
    $stmtSembakoOut = $pdo->prepare($querySembakoOut);
    $stmtSembakoOut->execute($paramsSembakoOut);
    $totalSembakoTersalurkan = (int)$stmtSembakoOut->fetchColumn();

    // 5. Data Keanggotaan
    $totalRelawan = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'aktif'")->fetchColumn();
    $totalResignMembers = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'resign'")->fetchColumn();

    // Penerima Manfaat
    $totalYatim = (int)$pdo->query("SELECT COUNT(*) FROM beneficiaries WHERE category = 'yatim' AND status = 'layak_aktif'")->fetchColumn();
    $totalWargaDhuafa = (int)$pdo->query("SELECT COUNT(*) FROM beneficiaries WHERE category IN ('sembako', 'dhuafa', 'lansia') AND status = 'layak_aktif'")->fetchColumn();
    $totalPenerimaAktif = $totalYatim + $totalWargaDhuafa;

    // Total Donatur Terdaftar
    $totalDonatur = (int)$pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();

    return [
        'total_uang_masuk' => $totalUangMasuk,
        'total_donasi_masuk' => $totalDonasiMasuk,
        'total_iuran_masuk' => $totalIuranMasuk,
        'total_pending_donations' => $totalPendingDonations,
        'total_pending_members' => $totalPendingMembers,
        'total_uang_keluar' => $totalRealisasiKeluar, // Kas riil yang sudah keluar
        'total_anggaran_keluar' => $totalAnggaranKeluar, // Seluruh anggaran (rencana + realisasi)
        'total_realisasi_keluar' => $totalRealisasiKeluar, // Kas yang terealisasi keluar
        'pengeluaran_kategori' => $pengeluaranKategori,
        'pengeluaran_per_kategori' => $pengeluaranKategori,
        'dana_pending' => $danaPending,
        'dana_komitmen' => $danaPending,
        'saldo_kas' => $saldoKasRiil, // Standar Saldo Kas Aktif = Saldo Kas Riil
        'saldo_kas_riil' => $saldoKasRiil,
        'saldo_kas_komitmen' => $saldoKasKomitmen,
        'total_sembako_masuk' => $totalSembakoMasuk,
        'total_sembako_tersalurkan' => $totalSembakoTersalurkan,
        'total_sembako_keluar' => $totalSembakoTersalurkan,
        'sisa_paket_sembako' => max(0, $totalSembakoMasuk - $totalSembakoTersalurkan),
        'total_relawan' => $totalRelawan,
        'total_resign' => $totalResignMembers,
        'total_yatim' => $totalYatim,
        'total_warga_dhuafa' => $totalWargaDhuafa,
        'total_penerima_aktif' => $totalPenerimaAktif,
        'total_donatur' => $totalDonatur
    ];
}

/**
 * Konversi Angka ke Kata Terbilang Rupiah Bahasa Indonesia
 * Rekursi bersih tanpa perulangan kata 'rupiah' di level internal.
 */
function bilangKata($nilai) {
    $nilai = abs((float)$nilai);
    $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    $temp = '';
    if ($nilai <= 0) {
        return '';
    } elseif ($nilai < 12) {
        $temp = ' ' . $huruf[(int)$nilai];
    } elseif ($nilai < 20) {
        $temp = bilangKata($nilai - 10) . ' belas';
    } elseif ($nilai < 100) {
        $temp = bilangKata((int)($nilai / 10)) . ' puluh' . bilangKata((int)$nilai % 10);
    } elseif ($nilai < 200) {
        $temp = ' seratus' . bilangKata($nilai - 100);
    } elseif ($nilai < 1000) {
        $temp = bilangKata((int)($nilai / 100)) . ' ratus' . bilangKata((int)$nilai % 100);
    } elseif ($nilai < 2000) {
        $temp = ' seribu' . bilangKata($nilai - 1000);
    } elseif ($nilai < 1000000) {
        $temp = bilangKata((int)($nilai / 1000)) . ' ribu' . bilangKata(fmod($nilai, 1000));
    } elseif ($nilai < 1000000000) {
        $temp = bilangKata((int)($nilai / 1000000)) . ' juta' . bilangKata(fmod($nilai, 1000000));
    } elseif ($nilai < 1000000000000) {
        $temp = bilangKata((int)($nilai / 1000000000)) . ' milyar' . bilangKata(fmod($nilai, 1000000000));
    } elseif ($nilai < 1000000000000000) {
        $temp = bilangKata((int)($nilai / 1000000000000)) . ' triliun' . bilangKata(fmod($nilai, 1000000000000));
    }
    return $temp;
}

function terbilang($angka) {
    $angka = (float)$angka;
    if ($angka == 0) {
        return 'Nol rupiah';
    }
    $hasil = preg_replace('/\s+/', ' ', trim(bilangKata($angka)));
    return ucfirst($hasil) . ' rupiah';
}

/**
 * Generate Nomor Referensi Transaksi Unik (TRX-YYYYMM-XXXX)
 * Dilengkapi pengecekan duplikasi ke database untuk menghindari collision.
 */
function generateUniqueTrxRef($pdo, $prefix = 'TRX') {
    $ym = date('Ym');
    for ($i = 0; $i < 20; $i++) {
        $code = $prefix . '-' . $ym . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE receipt_no = ?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    // Fallback digit tambahan jika kuota 4 digit penuh
    return $prefix . '-' . $ym . '-' . random_int(10000, 99999);
}

/**
 * Keamanan Kwitansi: Token HMAC Unguessable untuk Proteksi ID Enumeration (CWE-639)
 */
function getReceiptSecurityToken($donationId, $receiptNo = '', $createdAt = '') {
    $secretSalt = 'BKRC_SECURE_RECEIPT_SALT_2026_PROTECT';
    $payload = "{$donationId}:{$receiptNo}:{$createdAt}:{$secretSalt}";
    return substr(hash('sha256', $payload), 0, 16);
}

/**
 * Generate Tautan Cetak Kwitansi Resmi Dilengkapi Token Pengaman
 */
function getReceiptUrl($donationId, $receiptNo = '', $createdAt = '') {
    $token = getReceiptSecurityToken($donationId, $receiptNo, $createdAt);
    return getBaseUrl() . '/print_receipt.php?id=' . (int)$donationId . '&key=' . $token;
}
