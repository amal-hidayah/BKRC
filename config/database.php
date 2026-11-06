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
    // Log pesan teknis ke error log server untuk audit internal (mencegah information disclosure ke publik)
    error_log("BKRC Database Connection Error: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
    }
    die("<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Layanan Dalam Pemeliharaan — BKRC</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 36px 28px; max-width: 480px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); }
        .icon { width: 56px; height: 56px; line-height: 56px; background: #fee2e2; color: #991b1b; border-radius: 50%; font-size: 24px; font-weight: bold; margin: 0 auto 16px; }
        h1 { font-size: 1.25rem; font-weight: 800; margin: 0 0 10px; color: #0f172a; }
        p { font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; background: #991b1b; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 10px; font-size: 0.875rem; font-weight: 700; transition: background 0.2s; }
        .btn:hover { background: #7f1d1d; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>!</div>
        <h1>Layanan Sedang Dalam Pemeliharaan</h1>
        <p>Sistem basis data saat ini sedang dalam proses pemeliharaan atau sedang mengalami beban tinggi. Mohon tunggu sejenak lalu muat ulang halaman.</p>
        <a href='javascript:location.reload()' class='btn'>Muat Ulang Halaman</a>
    </div>
</body>
</html>");
}
