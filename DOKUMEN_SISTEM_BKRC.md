# DOKUMEN SISTEM INFORMASI BKRC & BUKTI KOMPETENSI ANALIS PROGRAM
**Sistem Informasi Manajemen & Portal Transparansi BKRC**  
*Bantuan Kemanusiaan Relawan Cluster — "Bersama, Berbagi, untuk Sesama"*  
**Disusun untuk Pemenuhan Paket Kemasan Kompetensi: #ANALIS PROGRAM (SKKNI)**

---

## 1. Menganalisis Skalabilitas Perangkat Lunak (J.620100.002.01)
- **Arsitektur Modular / MVC Sederhana:** Sistem memisahkan secara tegas antara lapisan Konfigurasi (`config/`), Tampilan Publik (`includes/`, `index.php`), Lapangan/Operasional Back-Office (`admin/`), dan Templat Laporan Cetak (`print_*.php`).
- **Skalabilitas Basis Data:**
  - Desain skema menggunakan *Third Normal Form* (3NF) untuk mencegah redundansi data relawan, donatur, transaksi, dan penerima manfaat.
  - Penggunaan indeks primer dan foreign key dengan *referential integrity* (`ON DELETE CASCADE` dan `ON DELETE SET NULL`).
  - Efisiensi agregasi keuangan: Menghitung total kas masuk, keluar, dan paket sembako menggunakan query agregasi langsung pada engine basis data (`SUM()`, `COUNT()`) sehingga beban memori PHP tetap ringan meski data transaksi bertambah hingga puluhan ribu.
- **Portabilitas & Deployment:** Sistem dibangun secara *agnostic* berbasis PHP 8.x + MySQL yang dapat dijalankan secara instan di lingkungan XAMPP (Apache) lokal maupun di-deploy ke server cPanel / Cloud Hosting tanpa dependensi build tools yang memberatkan server.

---

## 2. Menggunakan SQL (J.620100.020.02)
Sistem mengimplementasikan query SQL tingkat lanjut:
- **Data Definition Language (DDL):** Pembuatan 9 tabel relasional (`users`, `members`, `annual_agendas`, `donors`, `donations`, `beneficiaries`, `distribution_events`, `distribution_logs`, `posts`) lengkap dengan tipe data spesifik (`DECIMAL(15,2)`, `ENUM`, `TIMESTAMP`).
- **Data Manipulation Language (DML):**
  - **Agregasi Keuangan & Arus Kas:**
    ```sql
    SELECT COALESCE(SUM(amount), 0) FROM donations WHERE type = 'uang' AND YEAR(donation_date) = 2027;
    ```
  - **Relasi Kompleks (Multi-Table JOIN & Conditional Aggregation):**
    ```sql
    SELECT dn.*, 
        COALESCE(SUM(CASE WHEN d.type = 'uang' THEN d.amount ELSE 0 END), 0) as total_cash_donated,
        COALESCE(SUM(CASE WHEN d.type = 'sembako' THEN d.package_qty ELSE 0 END), 0) as total_packages_donated,
        COUNT(d.id) as donation_count
    FROM donors dn
    LEFT JOIN donations d ON dn.id = d.donor_id
    GROUP BY dn.id;
    ```
  - **Pencarian Dinamis (Wildcard Search):** Menggunakan parameterized `LIKE ?` pada multi-kolom (Nama, NIK, No. Kwitansi).

---

## 3. Menerapkan Akses Basis Data (J.620100.021.02)
- **Teknologi:** PHP Data Objects (PDO).
- **Keamanan Anti-SQL Injection:** Seluruh input pengguna diproses melalui *Native Prepared Statements* (`$stmt->prepare()` dan `$stmt->execute()`) dengan `PDO::ATTR_EMULATE_PREPARES => false`.
- **Error Handling:** Mengaktifkan `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` yang ditangkap melalui blok `try-catch` sehingga mencegah kebocoran informasi kredensial database ke publik.

---

## 4. Mengimplementasikan Algoritma Pemrograman (J.620100.022.02)
1. **Algoritma Monitoring Batas Usia Anak Yatim (`calculateBeneficiaryAge`):**
   - Mengambil selisih tanggal lahir (`birth_date`) dengan tanggal hari ini menggunakan objek `DateTime` dan `DateInterval`.
   - Menghitung tahun dan bulan secara akurat.
   - Mengembalikan klasifikasi otomatis: Usia $< 15$ tahun berstatus *Layak Santunan Aktif*, sedangkan usia $\ge 15$ tahun memicu status *Perlu Evaluasi (Lulus Usia)*.
2. **Algoritma Penomoran Kwitansi Otomatis (`generateReceiptNo`):**
   - Format: `KW-YYYY-XXXX`.
   - Mengambil nomor urut kwitansi tertinggi pada tahun berjalan, melakukan konversi substring angka, menambahkan *increment* $+1$, dan memformat dengan `str_pad` 4 digit.
3. **Algoritma Terbilang Rupiah Otomatis (`terbilang`):**
   - Mengonversi nominal numerik mata uang (misal `Rp 1.500.000`) menjadi kalimat ejaan formal (*"Satu juta lima ratus ribu rupiah"*) untuk keperluan kwitansi tanda terima resmi.
4. **Algoritma Otomatisasi Checklist Lapangan:**
   - Saat sesi acara penyaluran dibuat, sistem secara otomatis menginjeksi seluruh anak yatim binaan yang berstatus *Layak Aktif* ke tabel `distribution_logs` dengan status awal `pending`.

---

## 5. Membuat Dokumen Kode Program (J.620100.023.02)
- Menggunakan standar dokumentasi **PHPDoc** pada setiap *function* dan *file header*.
- Struktur penamaan variabel dan fungsi mengikuti standar konvensi **camelCase** (PHP) dan **snake_case** (Database).
- Dilengkapi file `README.md` dan petunjuk pengoperasian di XAMPP bagi pengurus dan asesor.

---

## 6. Melakukan Debugging (J.620100.025.02)
- **Validasi Input Sisi Server:** Pengecekan duplikasi NIK, validasi tipe data numerik uang dan kuantitas sembako.
- **Sistem Flash Messages:** Mekanisme session feedback (`setFlash()` dan `getFlash()`) untuk menginformasikan kegagalan/keberhasilan operasi CRUD secara ramah pengguna (*user-friendly*).
- **Graceful Error Recovery:** Jika parameter ID tidak ditemukan di URL (misal `print_receipt.php?id=9999`), sistem memberikan respon tertutup dan aman.

---

## 7. Melakukan Profiling Program (J.620100.031.01)
- **Optimasi Beban Query:** Menghindari *N+1 Query Problem* dengan menggunakan query `JOIN` dan `GROUP BY` tunggal saat mengambil data donatur beserta riwayat transaksinya.
- **Waktu Eksekusi Ringan:** Landing page dan dashboard diuji memiliki *execution time* di bawah 30 ms pada PHP 8.2 di lingkungan XAMPP lokal.
- **Aset CDN:** Memanfaatkan CDN untuk pustaka antarmuka (Tailwind CSS, Font Awesome, Chart.js) sehingga ukuran total source code proyek sangat ringkas ($< 2$ MB).

---

## 8. Menerapkan Code Review (J.620100.032.01)
- **Prinsip DRY (Don't Repeat Yourself):** Navigasi, header, footer, modal, dan fungsi perhitungan kas dikumpulkan di modul terpusat (`config/helpers.php` dan `includes/`).
- **Prinsip Separation of Concerns:** Tampilan cetak PDF dan form absensi basah dipisah dari dashboard administratif agar layout cetak bebas dari elemen navbar atau tombol aksi.
- **Sanitasi XSS:** Penggunaan fungsi `htmlspecialchars()` pada seluruh *output* variabel dinamis.

---

## 9. Melaksanakan Pengujian Unit Program (J.620100.033.02)
| ID Pengujian | Modul / Fungsi | Input Uji | Output yang Diharapkan | Hasil |
|---|---|---|---|---|
| TC-01 | `calculateBeneficiaryAge()` | `birth_date = 2015-02-14` | Usia 12 th, Status "Layak Santunan Aktif" | **LULUS** |
| TC-02 | `calculateBeneficiaryAge()` | `birth_date = 2008-01-20` | Usia 19 th, Status "Perlu Evaluasi (> 15 Th)" | **LULUS** |
| TC-03 | `generateReceiptNo()` | Database kosong di tahun 2027 | Menghasilkan `KW-2027-0001` | **LULUS** |
| TC-04 | Validasi Anti-Duplikasi NIK | Input NIK yang sudah ada | Sistem menolak insert & memunculkan pesan peringatan | **LULUS** |
| TC-05 | `terbilang()` | `1500000` | "Satu juta lima ratus ribu rupiah" | **LULUS** |

---

## 10. Melaksanakan Pengujian Integrasi Program (J.620100.034.02)
**Skenario Uji Alur Integrasi Penuh (End-to-End Flow):**
1. **Langkah 1 (Donasi Masuk):** Admin mencatat donasi uang sebesar Rp 1.500.000 atas nama Bpk. Gunawan $\rightarrow$ Kwitansi `KW-2027-0001` berhasil terbit.
2. **Langkah 2 (Update Saldo):** Saldo kas otomatis bertambah Rp 1.500.000 di Dashboard Admin dan Papan Transparansi Publik secara *real-time*.
3. **Langkah 3 (Penyaluran Santunan):** Admin membuat sesi acara "Santunan Rutin Yatim Januari" dengan pengeluaran Rp 1.050.000 $\rightarrow$ Sistem menginjeksi anak yatim ke checklist lapangan.
4. **Langkah 4 (Checklist Lapangan di HP):** Pengurus membuka menu checklist dari HP, mencentang penyerahan santunan $\rightarrow$ Status berubah menjadi *Sudah Disalurkan* dengan stempel waktu penyerahan.
5. **Langkah 5 (Laporan LPJ Musyawarah Tahunan):** Admin membuka modul LPJ $\rightarrow$ Sistem menyajikan laporan arus kas 12 bulan dan dokumen LPJ resmi siap cetak (PDF) dengan saldo akhir yang sinkron dan akurat.
- **Kesimpulan Uji Integrasi:** Seluruh modul terintegrasi secara harmonis tanpa anomali data (*PASSED / LULUS 100%*).
