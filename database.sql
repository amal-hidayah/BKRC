-- ==============================================================================
-- DATABASE SCHEMA: SISTEM INFORMASI MANAJEMEN & PORTAL TRANSPARANSI BKRC
-- Bantuan Kemanusiaan Relawan Cluster (BKRC) - Versi 2.1 (Workflow Approval & Multi-Kategori)
-- Target Engine: MySQL / MariaDB (XAMPP Environment)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS distribution_logs;
DROP TABLE IF EXISTS distribution_events;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS donors;
DROP TABLE IF EXISTS annual_agendas;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. TABEL USERS (Akun Pengurus & Super Admin)
-- ------------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_hint VARCHAR(100) DEFAULT 'admin123',
    role ENUM('superadmin', 'admin') DEFAULT 'admin',
    phone VARCHAR(25) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABEL MEMBERS (Database Relawan / Anggota Cluster BKRC)
-- Pendaftaran mandiri via daftar_anggota.php (Status default: pending)
-- ------------------------------------------------------------------------------
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_code VARCHAR(30) UNIQUE NOT NULL,
    nik VARCHAR(20) NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    cluster_block VARCHAR(100) NOT NULL,
    rt_rw VARCHAR(30) NOT NULL,
    position VARCHAR(100) NOT NULL DEFAULT 'Relawan Anggota',
    volunteer_interest VARCHAR(100) NULL,
    joined_date DATE NOT NULL,
    status ENUM('pending', 'aktif', 'resign') DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABEL ANNUAL_AGENDAS (Kalender Agenda Tahunan BKRC)
-- ------------------------------------------------------------------------------
CREATE TABLE annual_agendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL DEFAULT 2026,
    month_num INT NOT NULL DEFAULT 1,
    month_label VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL,
    category ENUM('rutin', 'sosial', 'internal', 'musyawarah') NOT NULL,
    description TEXT,
    event_date DATE NULL,
    status ENUM('akan_datang', 'terlaksana') DEFAULT 'akan_datang',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABEL DONORS (Database Profil Donatur)
-- ------------------------------------------------------------------------------
CREATE TABLE donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_code VARCHAR(30) UNIQUE NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(25) NULL,
    cluster_or_address VARCHAR(255) NOT NULL,
    donor_type ENUM('rutin', 'insidental', 'sponsor') DEFAULT 'insidental',
    is_anonymous TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABEL DONATIONS (Log Donasi & Iuran Kas Masuk - Uang & Paket Sembako)
-- Alur: Pending (Ref: TRX-YYYYMM-XXXX) -> Bukti Dicek -> Approve (KW-2026-XXXX)
-- ------------------------------------------------------------------------------
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) UNIQUE NOT NULL,
    transaction_category ENUM('donasi', 'iuran') DEFAULT 'donasi',
    donor_id INT NOT NULL,
    member_id INT NULL,
    donor_phone VARCHAR(25) NULL,
    donation_date DATE NOT NULL,
    type ENUM('uang', 'sembako') NOT NULL,
    amount DECIMAL(15, 2) DEFAULT 0.00,
    package_qty INT DEFAULT 0,
    package_details VARCHAR(255) NULL,
    program_target VARCHAR(100) DEFAULT 'Kas Rutin Bulanan',
    status ENUM('pending', 'approved', 'rejected', 'batal') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,
    approved_at DATETIME NULL,
    approved_by INT NULL,
    void_reason VARCHAR(255) NULL,
    voided_at DATETIME NULL,
    voided_by INT NULL,
    notes TEXT NULL,
    proof_url VARCHAR(255) NULL,
    recorded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_donation_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    CONSTRAINT fk_donation_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_donation_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. TABEL BENEFICIARIES (Database Penerima Manfaat - Yatim & Sembako/Dhuafa)
-- ------------------------------------------------------------------------------
CREATE TABLE beneficiaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(20) UNIQUE NOT NULL,
    kk_number VARCHAR(20) NULL,
    full_name VARCHAR(150) NOT NULL,
    birth_date DATE NOT NULL,
    guardian_name VARCHAR(150) NULL,
    phone VARCHAR(25) NULL,
    address TEXT NOT NULL,
    rt_rw VARCHAR(30) NOT NULL,
    category ENUM('yatim', 'sembako', 'dhuafa', 'lansia') NOT NULL,
    photo_url VARCHAR(255) NULL,
    status ENUM('layak_aktif', 'lulus_usia', 'pindah', 'meninggal', 'mampu') DEFAULT 'layak_aktif',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. TABEL DISTRIBUTION_EVENTS (Sesi Penyaluran Kas - 5 Kategori)
-- Alur 2 Tahap: Direncanakan (Komitmen) -> Tersalurkan (Kas Riil Keluar)
-- ------------------------------------------------------------------------------
CREATE TABLE distribution_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agenda_id INT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    program_type ENUM('santunan_yatim', 'sembako', 'event', 'pinjaman', 'lainnya') NOT NULL,
    total_cash_out DECIMAL(15, 2) DEFAULT 0.00,
    total_packages_out INT DEFAULT 0,
    disbursement_status ENUM('direncanakan', 'tersalurkan') DEFAULT 'direncanakan',
    disbursed_at DATETIME NULL,
    disbursed_to VARCHAR(150) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dist_agenda FOREIGN KEY (agenda_id) REFERENCES annual_agendas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. TABEL DISTRIBUTION_LOGS (Checklist Kehadiran & Penyerahan Lapangan)
-- ------------------------------------------------------------------------------
CREATE TABLE distribution_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    beneficiary_id INT NOT NULL,
    cash_amount DECIMAL(15, 2) DEFAULT 0.00,
    sembako_package_detail VARCHAR(255) NULL,
    status ENUM('pending', 'distributed') DEFAULT 'pending',
    distributed_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_log_event FOREIGN KEY (event_id) REFERENCES distribution_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_log_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. TABEL POSTS (Dokumentasi, Galeri & Berita Kegiatan BKRC)
-- ------------------------------------------------------------------------------
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    thumbnail_url VARCHAR(255) NULL,
    event_date DATE NULL,
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    author_id INT NULL,
    CONSTRAINT fk_post_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. TABEL SETTINGS (Konfigurasi Global & Rekening Resmi BKRC)
-- Single Source of Truth untuk rekening bank & narahubung bendahara
-- ------------------------------------------------------------------------------
CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==============================================================================
-- SEED DATA AWAL (BERDASARKAN KALENDER AGENDA 2026 & HISTORI BKRC)
-- ==============================================================================

-- 0. Pengaturan Resmi Organisasi & Rekening Bank
INSERT INTO settings (setting_key, setting_value, description) VALUES
('bank_name', 'Bank Syariah Indonesia (BSI)', 'Nama bank resmi penerimaan donasi BKRC'),
('bank_code', '451', 'Kode transfer bank ATM Bersama / BI-FAST'),
('bank_account_number', '718 293 8472', 'Nomor rekening bank terformat tampilan'),
('bank_account_raw', '7182938472', 'Nomor rekening bank tanpa spasi untuk salin clipboard'),
('bank_account_holder', 'Bantuan Kemanusiaan Relawan Cluster', 'Atas nama rekening resmi lembaga'),
('treasurer_phone', '081234567891', 'Nomor WhatsApp resmi Bendahara untuk konfirmasi kwitansi'),
('treasurer_name', 'Hj. Siti Rohmah', 'Nama Bendahara Pengurus BKRC');

-- 1. Pengurus / Admin (Password: admin123)
INSERT INTO users (id, name, email, password_hash, role, phone) VALUES
(1, 'Ketua Umum BKRC', 'admin@bkrc.id', '$2y$10$/1BpDlzq.9a97nKlGt1LGONDRpzXVng7.HT.LUqVHShAbGowm8W46', 'superadmin', '081234567890'),
(2, 'Bendahara BKRC', 'bendahara@bkrc.id', '$2y$10$/1BpDlzq.9a97nKlGt1LGONDRpzXVng7.HT.LUqVHShAbGowm8W46', 'admin', '081234567891'),
(3, 'Koordinator Lapangan', 'relawan@bkrc.id', '$2y$10$/1BpDlzq.9a97nKlGt1LGONDRpzXVng7.HT.LUqVHShAbGowm8W46', 'admin', '081234567892');

-- 2. Anggota / Relawan Cluster (Status: aktif)
INSERT INTO members (id, member_code, nik, full_name, phone, cluster_block, rt_rw, position, volunteer_interest, joined_date, status) VALUES
(1, 'BKRC-M-001', '3201010101850001', 'H. Bambang Sulistyo', '081234567890', 'Cluster Gardenia Blok A1/05', 'RT 01 / RW 08', 'Ketua Umum', 'Koordinator Umum', '2019-09-15', 'aktif'),
(2, 'BKRC-M-002', '3201010202880002', 'Ahmad Fauzi, S.Kom', '081288991122', 'Cluster Gardenia Blok B3/12', 'RT 02 / RW 08', 'Sekretaris', 'Administrasi & Publikasi', '2020-01-10', 'aktif'),
(3, 'BKRC-M-003', '3201010303900003', 'Hj. Siti Rohmah', '081234567891', 'Cluster Bougenville Blok C2/01', 'RT 03 / RW 08', 'Bendahara', 'Pengelolaan Keuangan', '2019-09-15', 'aktif'),
(4, 'BKRC-M-004', '3201010404920004', 'Rizky Pratama', '081234567892', 'Cluster Gardenia Blok A2/18', 'RT 01 / RW 08', 'Koordinator Logistik', 'Distribusi Sembako Lapangan', '2021-03-20', 'aktif'),
(5, 'BKRC-M-005', '3201010505950005', 'Dedi Kurniawan', '081377889900', 'Cluster Jasmine Blok D1/09', 'RT 04 / RW 08', 'Relawan Lapangan', 'Pendampingan Anak Yatim', '2022-06-15', 'aktif'),
(6, 'BKRC-M-006', '3201010606890006', 'Maya Anggraini', '081211223344', 'Cluster Bougenville Blok C5/10', 'RT 03 / RW 08', 'Divisi Dokumentasi', 'Dokumentasi & Media Sosial', '2023-01-05', 'aktif');

-- 3. Kalender Agenda BKRC 2026
INSERT INTO annual_agendas (id, year, month_num, month_label, title, category, description, event_date, status) VALUES
(1, 2026, 1, 'Januari', 'Turnamen Catur BKRC 2026', 'internal', 'Ajang silaturahmi antar warga cluster dan anggota relawan BKRC.', '2026-01-17', 'terlaksana'),
(2, 2026, 2, 'Februari', 'Buka Puasa Bersama & Santunan Ramadhan 1447 H', 'sosial', 'Event spesial Ramadhan: Berbuka puasa bersama, tausiyah, dan penyerahan santunan.', '2026-02-28', 'terlaksana'),
(3, 2026, 4, 'April', 'Touring & Team Building Relawan', 'internal', 'Penguatan soliditas dan kebersamaan relawan keluarga besar BKRC.', '2026-04-18', 'terlaksana'),
(4, 2026, 8, 'Agustus', 'Bakti Sosial Donor Darah Kemerdekaan', 'sosial', 'Aksi kemanusiaan donor darah bekerja sama dengan PMI cabang setempat.', '2026-08-15', 'terlaksana'),
(5, 2026, 9, 'September', 'Anniversary ke-7 BKRC & Santunan Akbar', 'sosial', 'Puncak perayaan 7 tahun pengabdian BKRC: Santunan Anak Yatim dan Dhuafa.', '2026-09-19', 'terlaksana'),
(6, 2026, 10, 'Oktober', 'Musyawarah Tahunan & LPJ 2026', 'musyawarah', 'Penyampaian Laporan Pertanggungjawaban (LPJ) kas, donatur, dan evaluasi program tahunan.', '2026-10-10', 'terlaksana');

-- 4. Database Profil Donatur
INSERT INTO donors (id, donor_code, full_name, phone, cluster_or_address, donor_type, is_anonymous) VALUES
(1, 'DNR-001', 'Ir. H. Gunawan Wibisono', '081122334455', 'Cluster Gardenia Blok A1/02', 'rutin', 0),
(2, 'DNR-002', 'Hamba Allah', '081299887766', 'Cluster Bougenville', 'insidental', 1),
(3, 'DNR-003', 'Keluarga Bpk. Hendro', '081344556677', 'Cluster Gardenia Blok B1/08', 'insidental', 0),
(4, 'DNR-004', 'PT Sinar Logistik Nusantara', '02188997700', 'Kawasan Niaga Sentra', 'sponsor', 0),
(5, 'DNR-005', 'Ibu Nurhayati (Toko Berkah)', '081566778899', 'Ruko Sentra Blok R/03', 'rutin', 0),
(6, 'DNR-006', 'Hamba Allah (Warga Blok D)', '081233445566', 'Cluster Jasmine', 'insidental', 1);

-- 5. Donasi & Iuran Kas Masuk (Approved)
INSERT INTO donations (id, receipt_no, transaction_category, donor_id, member_id, donor_phone, donation_date, type, amount, package_qty, package_details, program_target, status, approved_at, approved_by, notes, proof_url, recorded_by) VALUES
(1, 'KW-2026-0001', 'iuran', 1, 1, '081234567890', '2026-01-05', 'uang', 1500000.00, 0, NULL, 'Iuran Kas Bulanan Anggota', 'approved', '2026-01-05 10:00:00', 2, 'Iuran kas bulanan pengurus', 'assets/proofs/sample1.jpg', 2),
(2, 'KW-2026-0002', 'donasi', 2, NULL, '081299887766', '2026-01-08', 'uang', 1000000.00, 0, NULL, 'Kas Rutin Bulanan', 'approved', '2026-01-08 09:30:00', 2, 'Hamba Allah sedekah subuh', 'assets/proofs/sample2.jpg', 2),
(3, 'KW-2026-0003', 'donasi', 3, NULL, '081344556677', '2026-01-10', 'uang', 750000.00, 0, NULL, 'Santunan Anak Yatim', 'approved', '2026-01-10 14:15:00', 2, 'Santunan rutin 3 anak asuh', NULL, 2),
(4, 'KW-2026-0004', 'donasi', 5, NULL, '081566778899', '2026-01-12', 'sembako', 0.00, 25, '25 Paket Beras 5kg, Minyak 2L, Gula 1kg', 'Pembagian Sembako', 'approved', '2026-01-12 11:00:00', 2, 'Donasi sembako awal tahun dari Toko Berkah', NULL, 2),
(5, 'KW-2026-0005', 'donasi', 4, NULL, '02188997700', '2026-01-15', 'uang', 5000000.00, 0, NULL, 'Santunan Akbar & Operasional', 'approved', '2026-01-15 16:00:00', 2, 'Sponsorship awal tahun perusahaan', NULL, 1),
(6, 'KW-2026-0006', 'iuran', 1, 1, '081234567890', '2026-02-02', 'uang', 1500000.00, 0, NULL, 'Iuran Kas Bulanan Anggota', 'approved', '2026-02-02 08:30:00', 2, 'Iuran kas bulanan Februari', NULL, 2),
(7, 'KW-2026-0007', 'donasi', 6, NULL, '081233445566', '2026-02-05', 'uang', 500000.00, 0, NULL, 'Buka Puasa Bersama', 'approved', '2026-02-05 13:00:00', 2, 'Titipan sedekah buka puasa', NULL, 2),
(8, 'KW-2026-0008', 'donasi', 5, NULL, '081566778899', '2026-02-10', 'sembako', 0.00, 20, '20 Paket Sembako Ramadhan', 'Pembagian Sembako', 'approved', '2026-02-10 15:30:00', 2, 'Paket sembako Ramadhan 1447 H', NULL, 2);

-- 6. Database Penerima Manfaat (Yatim & Sembako/Dhuafa)
INSERT INTO beneficiaries (nik, kk_number, full_name, birth_date, guardian_name, phone, address, rt_rw, category, status, notes) VALUES
('3201011402150002', '3201011002050011', 'Muhammad Ilham Pratama', '2015-02-14', 'Ibu Maryati (Ibu Kandung)', '085711223344', 'Kp. Rawasari, Dekat Musholla Al-Ikhlas', 'RT 01 / RW 03', 'yatim', 'layak_aktif', 'Ayah meninggal dunia tahun 2021'),
('3201016007140003', '3201011002050012', 'Aisyah Putri Rahmawati', '2014-07-20', 'Ibu Aminah', '085722334455', 'Kp. Bojong Indah, Blok Melati', 'RT 02 / RW 03', 'yatim', 'layak_aktif', 'Kelas 5 SD, berprestasi'),
('3201011010120005', '3201011002050013', 'Farhan Maulana', '2012-10-10', 'Kakek Subarjo', '085733445566', 'Kp. Sawah Baru, Dekat Pos Warga', 'RT 03 / RW 04', 'yatim', 'layak_aktif', 'Yatim piatu, diasuh kakek'),
('3201012001080007', '3201011002050014', 'Bagas Aditya (Lulus Usia)', '2008-01-20', 'Ibu Halimah', '085744556677', 'Kp. Rawasari, Gang Mawar', 'RT 01 / RW 03', 'yatim', 'lulus_usia', 'Sudah tamat SMK, mulai mandiri'),
('3201015205600004', '3201011002050015', 'Ibu Salmah (Janda Dhuafa)', '1960-05-12', '-', '085755667788', 'Kp. Sawah Baru, Depan Lapangan', 'RT 04 / RW 04', 'dhuafa', 'layak_aktif', 'Keluarga pra-sejahtera, buruh cuci'),
('3201011708550001', '3201011002050016', 'Bpk. Warsito (Lansia Sakit)', '1955-08-17', 'Anak: Teguh', '085766778899', 'Kp. Rawasari, Rumah Ujung', 'RT 02 / RW 03', 'lansia', 'layak_aktif', 'Lansia menderita stroke ringan'),
('3201016503750006', '3201011002050017', 'Ibu Rohani (Penerima Sembako)', '1975-03-25', '-', '085777889900', 'Kp. Bojong Indah, Dekat Pos Ronda', 'RT 01 / RW 03', 'sembako', 'layak_aktif', 'Penerima paket beras & minyak bulanan');

-- 7. Sesi Penyaluran Kas (5 Kategori: santunan_yatim, sembako, event, pinjaman, lainnya)
INSERT INTO distribution_events (id, agenda_id, event_name, event_date, program_type, total_cash_out, total_packages_out, disbursement_status, disbursed_at, disbursed_to, notes) VALUES
(1, NULL, 'Santunan Rutin Yatim & Sembako Januari 2026', '2026-01-25', 'santunan_yatim', 1050000.00, 25, 'tersalurkan', '2026-01-25 17:00:00', '3 Anak Yatim & 3 Warga Dhuafa', 'Penyaluran bulanan di pendopo cluster'),
(2, 2, 'Buka Puasa Bersama & Santunan Ramadhan 1447 H', '2026-02-28', 'event', 1500000.00, 20, 'direncanakan', NULL, NULL, 'Event Buka Puasa bersama anak yatim di Masjid Cluster');

-- 8. Checklist Penyaluran Lapangan (Log Distribusi)
INSERT INTO distribution_logs (event_id, beneficiary_id, cash_amount, sembako_package_detail, status, distributed_at, notes) VALUES
(1, 1, 350000.00, 'Paket Beras 5kg & Minyak', 'distributed', '2026-01-25 16:30:00', 'Diterima oleh Ibu Maryati'),
(1, 2, 350000.00, 'Paket Beras 5kg & Minyak', 'distributed', '2026-01-25 16:35:00', 'Diterima oleh Aisyah'),
(1, 3, 350000.00, 'Paket Beras 5kg & Minyak', 'distributed', '2026-01-25 16:40:00', 'Diterima oleh Kakek Subarjo'),
(1, 5, 0.00, 'Paket Beras 5kg, Minyak, Gula', 'distributed', '2026-01-25 16:45:00', 'Diterima Ibu Salmah'),
(1, 6, 0.00, 'Paket Beras 5kg, Minyak, Gula', 'distributed', '2026-01-25 16:50:00', 'Diantar ke rumah Pak Warsito'),
(1, 7, 0.00, 'Paket Beras 5kg, Minyak, Gula', 'distributed', '2026-01-25 16:55:00', 'Diterima Ibu Rohani'),
-- Event 2 (Bulan Februari / Ramadhan - status pending sebagian untuk checklist HP)
(2, 1, 500000.00, 'Paket Kado Ramadhan + Sembako', 'distributed', '2026-02-28 17:45:00', 'Hadir di Masjid didampingi Ibu Maryati'),
(2, 2, 500000.00, 'Paket Kado Ramadhan + Sembako', 'pending', NULL, 'Menunggu penyerahan lapangan / sakit saat acara'),
(2, 3, 500000.00, 'Paket Kado Ramadhan + Sembako', 'pending', NULL, 'Menunggu penyerahan langsung ke rumah kakek'),
(2, 5, 0.00, 'Paket Sembako Ramadhan', 'distributed', '2026-02-28 18:00:00', 'Diterima Ibu Salmah di Masjid'),
(2, 6, 0.00, 'Paket Sembako Ramadhan', 'distributed', '2026-02-28 18:05:00', 'Diantar relawan ke kediaman'),
(2, 7, 0.00, 'Paket Sembako Ramadhan', 'distributed', '2026-02-28 18:10:00', 'Diterima Ibu Rohani di Masjid');

-- 9. Posts / Dokumentasi Berita
INSERT INTO posts (title, slug, excerpt, content, thumbnail_url, event_date, published_at, author_id) VALUES
('Penyaluran Santunan Yatim & Paket Sembako Awal Tahun 2026 Berjalan Lancar', 'penyaluran-santunan-awal-tahun-2026', 'Alhamdulillah, BKRC telah menyalurkan santunan uang tunai dan 25 paket sembako untuk anak yatim dan warga pra-sejahtera di sekitar cluster.', 'Alhamdulillah wa syukurillah, pada hari Minggu, 25 Januari 2026, pengurus dan relawan BKRC telah melaksanakan agenda rutin bulanan yaitu Penyaluran Santunan Anak Yatim dan Pembagian Paket Sembako.<br><br>Sebanyak 3 anak yatim binaan menerima santunan uang tunai pendidikan, serta 25 paket sembako yang berisi beras 5kg, minyak goreng 2 liter, dan gula pasir berhasil didistribusikan kepada warga dhuafa dan lansia di sekitar lingkungan cluster.<br><br>Kami haturkan terima kasih sebesar-besarnya kepada para donatur dan warga cluster atas kepercayaannya. Semoga rezeki yang disisihkan menjadi amal jariyah yang berlipat ganda. Aamiin.', 'assets/img/santunan_sample.jpg', '2026-01-25', '2026-01-26 09:00:00', 1),
('Keseruan Turnamen Catur BKRC 2026: Mempererat Silaturahmi Warga', 'turnamen-catur-bkrc-2026', 'Turnamen catur internal mengawali agenda tahunan BKRC 2026 sebagai ajang keakraban antar warga dan anggota relawan.', 'Mengawali kalender kegiatan tahun 2026, BKRC menggelar Turnamen Catur antar warga cluster pada 17 Januari 2026. Acara ini bukan sekadar kompetisi, melainkan ajang silaturahmi untuk memperkuat kekompakan dan kepedulian antar relawan.<br><br>Dengan suasana yang guyub dan penuh tawa, turnamen ini menjadi bukti bahwa persaudaraan warga cluster tetap terjaga kokoh menuju Anniversary ke-7 BKRC mendatang.', 'assets/img/catur_sample.jpg', '2026-01-17', '2026-01-18 10:30:00', 1);
