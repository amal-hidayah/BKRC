<?php
/**
 * Halaman Tentang BKRC (Sejarah, Visi Misi, & Struktur Relawan Cluster)
 */
$pageTitle = 'Tentang BKRC — Bantuan Kemanusiaan Relawan Cluster';
require_once __DIR__ . '/includes/header.php';

// Ambil Struktur Anggota/Relawan BKRC
$stmtMembers = $pdo->query("
    SELECT * FROM members 
    WHERE status = 'aktif' 
    ORDER BY FIELD(position, 'Ketua Umum', 'Sekretaris', 'Bendahara', 'Koordinator Logistik', 'Divisi Dokumentasi', 'Relawan Lapangan'), id ASC
");
$members = $stmtMembers->fetchAll();
?>

<!-- Header (Solid Slate-900, Clean Civic Header) -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold tracking-normal">Tentang BKRC — Relawan Warga Cluster</h1>
                <p class="text-slate-300 text-sm mt-1">Perjalanan pengabdian sosial warga perumahan cluster menuju 8 tahun kebersamaan.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-300">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-white">Beranda</a>
                <span>/</span>
                <span class="text-bkrc-gold-400 font-semibold">Tentang BKRC</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 space-y-16">
    <!-- Sejarah & Slogan -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-6 space-y-4 text-slate-700 leading-relaxed text-sm sm:text-base">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                Lahir dari Kepedulian Warga, Bertumbuh untuk Kemanusiaan
            </h2>
            <p>
                <strong>Bantuan Kemanusiaan Relawan Cluster (BKRC)</strong> didirikan pada September 2019 oleh inisiatif para warga lingkungan cluster perumahan yang tergerak hatinya melihat anak-anak yatim piatu dan warga pra-sejahtera di kampung-kampung sekitar wilayah perumahan.
            </p>
            <p>
                Dengan memegang teguh slogan <em>"Bersama, Berbagi, untuk Sesama"</em> dan motto <em>"Kecil Bukan Karena Jumlah, Besar Karena Kepedulian"</em>, BKRC telah konsisten melaksanakan santunan bulanan tanpa putus selama hampir 8 tahun.
            </p>
            <div class="p-4 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs">
                <strong>Tonggak Sejarah:</strong> Menuju Anniversary ke-8 pada September 2027 mendatang, BKRC berkomitmen memperluas jangkauan manfaat melalui digitalisasi sistem transparansi donasi dan database terpadu.
            </div>
        </div>

        <div class="lg:col-span-6">
            <div class="bg-slate-900 text-white rounded-2xl p-8 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="space-y-6 relative z-10">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-300">Visi Organisasi</span>
                        <p class="text-base font-semibold text-white mt-1">
                            "Menjadi motor penggerak kepedulian sosial warga lingkungan cluster yang amanah, transparan, dan berkelanjutan bagi kesejahteraan sesama."
                        </p>
                    </div>
                    <div class="border-t border-white/10 pt-4">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-300">Misi Organisasi</span>
                        <ul class="text-xs text-slate-300 space-y-2.5 mt-2">
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-check text-bkrc-gold-400 mt-0.5"></i>
                                <span>Menjamin kelangsungan santunan pendidikan bagi anak-anak yatim binaan setiap bulan.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-check text-bkrc-gold-400 mt-0.5"></i>
                                <span>Meringankan beban pangan warga dhuafa dan lansia melalui distribusi paket sembako rutin.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-check text-bkrc-gold-400 mt-0.5"></i>
                                <span>Menjaga keterbukaan dan akuntabilitas pengelolaan dana kas serta donatur secara real-time.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-check text-bkrc-gold-400 mt-0.5"></i>
                                <span>Mempererat kerukunan dan kekompakan warga cluster melalui kegiatan silaturahmi internal yang positif.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Struktur Relawan / Anggota Cluster -->
    <div>
        <div class="max-w-2xl text-left mb-10">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                Susunan Pengurus & Relawan Cluster
            </h2>
            <p class="text-slate-600 text-xs sm:text-sm mt-1">
                Warga lingkungan cluster yang mengabdikan waktu dan tenaganya secara sukarela untuk mengelola amanah donatur.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($members as $m): ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-bkrc-red-800 text-white flex items-center justify-center text-xl font-bold flex-shrink-0">
                        <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-bkrc-red-800 uppercase tracking-wider mb-0.5">
                            <?= htmlspecialchars($m['position']) ?>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($m['full_name']) ?></h3>
                        <div class="text-xs text-slate-600 mt-0.5">
                            <i class="fa-solid fa-house-chimney text-slate-500 mr-1 text-[10px]"></i> <?= htmlspecialchars($m['cluster_block']) ?>
                        </div>
                        <div class="text-[11px] text-slate-500">
                            Bergabung: <?= date('Y', strtotime($m['joined_date'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
