<?php
/**
 * Konfigurasi Database Sistem Informasi BKRC
 * Menggunakan PDO (PHP Data Objects) untuk Keamanan & Portabilitas Tinggi
 * Standar Kompetensi: J.620100.021.02 (Menerapkan Akses Basis Data)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_bkrc');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');

// Opsi PDO untuk keamanan dan penanganan error
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Mencegah SQL Injection dengan native prepared statements
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    // Penanganan error jika database gagal terhubung
    die("<div style='font-family: Arial, sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 8px; max-width: 600px; margin: 40px auto;'>
        <h3 style='margin-top: 0;'>Koneksi Database Gagal!</h3>
        <p>Pastikan MySQL di XAMPP Control Panel sudah dalam keadaan <strong>Start</strong> dan database <code>db_bkrc</code> sudah diimpor.</p>
        <p><small>Pesan Teknis: " . htmlspecialchars($e->getMessage()) . "</small></p>
    </div>");
}
