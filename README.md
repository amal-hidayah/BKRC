# Sistem Informasi Manajemen & Portal Transparansi BKRC
**Bantuan Kemanusiaan Relawan Cluster (BKRC) — Versi 2.0**  
*"Bersama, Berbagi, untuk Sesama" | "Kecil Bukan Karena Jumlah, Besar Karena Kepedulian"*

---

## 🚀 Panduan Cepat Menjalankan di XAMPP

Sistem ini dirancang **100% Plug-and-Play** untuk lingkungan **XAMPP (Apache + PHP 8.x + MySQL)**.

### Langkah 1: Pastikan Apache & MySQL Aktif
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada **Apache** dan **MySQL** (hingga berwarna hijau).

### Langkah 2: Impor Database MySQL
1. Buka browser dan akses phpMyAdmin:  
   👉 `http://localhost/phpmyadmin`
2. Buat database baru bernama: `db_bkrc`
3. Klik tab **Import** $\rightarrow$ Pilih file `database.sql` yang ada di folder proyek ini $\rightarrow$ Klik **Go / Kirim**.  
*(Catatan: Database sudah berisi skema 9 tabel dan data dummy agenda BKRC 2027 lengkap)*.

### Langkah 3: Buka Aplikasi di Browser
- **Portal Publik (Landing Page, Agenda 2027, Papan Transparansi):**  
  👉 **`http://localhost/BKRC/`**
- **Portal Back-Office Pengurus:**  
  👉 **`http://localhost/BKRC/login.php`**

---

## 🔑 Akun Login Pengurus (Default)
| Peran (Role) | Email | Kata Sandi | Keterangan |
|---|---|---|---|
| **Super Admin** | `admin@bkrc.id` | `admin123` | Akses penuh + LPJ Musyawarah & Akun Pengurus |
| **Admin Bendahara** | `bendahara@bkrc.id` | `admin123` | Akses operasional donasi, sembako, dan kwitansi |
| **Admin Relawan** | `relawan@bkrc.id` | `admin123` | Akses checklist lapangan di HP |

---

## 📋 Fitur Utama Sesuai PRD Versi 2.0
1. **Modul A: Portal Publik & Kalender Agenda 2027**
   - Hero banner dengan slogan & motto resmi BKRC.
   - Papan saldo kas & logistik sembako *real-time*.
   - Kalender Agenda 2027 (Turnamen Catur Januari, Buka Puasa Bersama Februari, Touring April, Donor Darah Agustus, Anniversary Santunan Akbar September, Musyawarah Tahunan Oktober).
   - Transparansi 10 donasi terbaru (opsi anonim *"Hamba Allah"*).
   - Kanal donasi resmi (Rekening BSI, QRIS statis, tombol konfirmasi WhatsApp).
2. **Modul B: Dashboard Eksekutif & Statistik**
   - Kartu metrik keuangan & logistik.
   - Grafik tren donasi bulanan (Chart.js) dan proporsi penerima manfaat.
3. **Modul C: Database Relawan Cluster**
   - CRUD data relawan, nomor induk, jabatan, dan cluster/blok perumahan.
4. **Modul D: Donatur & Pencatatan Donasi (Uang & Sembako)**
   - Log donasi uang tunai/transfer dan paket sembako.
   - Penomoran kwitansi otomatis (`KW-2027-XXXX`).
   - Tombol cetak kwitansi resmi (PDF) & kirim langsung ke WhatsApp donatur (`wa.me`).
5. **Modul E: Database Penerima Manfaat (Yatim & Dhuafa)**
   - Validasi anti-duplikasi NIK.
   - Algoritma pemantauan usia anak yatim dari tanggal lahir (peringatan evaluasi $> 15$ tahun).
6. **Modul F: Penyaluran Lapangan & Checklist HP**
   - Antarmuka *mobile-friendly* untuk pengurus mencentang status santunan saat acara lapangan berlangsung (*Pending* $\rightarrow$ *Distributed*).
   - Cetak lembar absensi tanda tangan basah penerima santunan.
7. **Modul G: Laporan Musyawarah Tahunan (LPJ)**
   - Rekapitulasi 1 tahun penuh: arus kas 12 bulan, sembako, dan evaluasi program.
   - Cetak dokumen LPJ resmi (PDF) dengan kop surat dan lembar pengesahan tanda tangan Ketua & Bendahara.
   - Ekspor Excel (CSV).

---

## 🏆 Pemenuhan Dokumen Uji Kompetensi Analis Program (LSP / BNSP)
Penjelasan teknis detail terkait 10 Unit Kompetensi SKKNI Analis Program (Skalabilitas, SQL, Akses Basis Data, Algoritma, Dokumentasi Kode, Debugging, Profiling, Code Review, Uji Unit, dan Uji Integrasi) dapat dibaca pada file:  
📄 **`DOKUMEN_SISTEM_BKRC.md`**
