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

<!-- Header Banner -->
<div class="bg-stone-900 text-stone-100 py-12 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-white">Tentang relawan warga cluster</h1>
                <p class="text-stone-300 text-sm mt-1">Perjalanan pengabdian sosial warga perumahan cluster menuju 8 tahun kebersamaan.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-stone-400">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-stone-200 transition-colors">Beranda</a>
                <span>/</span>
                <span class="text-stone-200 font-medium">Tentang BKRC</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 space-y-16">
    <!-- Sejarah & Slogan -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">
        <div class="lg:col-span-6 space-y-4 text-stone-700 leading-relaxed text-sm sm:text-base">
            <h2 class="text-2xl sm:text-3xl font-semibold text-stone-900 tracking-tight">
                Lahir dari kepedulian warga, bertumbuh untuk kemanusiaan
            </h2>
            <p>
                <strong>Bantuan Kemanusiaan Relawan Cluster (BKRC)</strong> didirikan pada September 2019 oleh inisiatif para warga lingkungan cluster perumahan yang tergerak melihat anak-anak yatim piatu dan warga pra-sejahtera di perkampungan sekitar lingkungan perumahan.
            </p>
            <p>
                Dengan memegang teguh semangat <em>"Bersama, berbagi, untuk sesama"</em> dan moto <em>"Kecil bukan karena jumlah, besar karena kepedulian"</em>, BKRC telah konsisten melaksanakan santunan bulanan tanpa putus selama hampir 8 tahun.
            </p>
            <div class="p-4 bg-stone-100 rounded-xl border border-stone-200/80 text-stone-700 text-xs leading-relaxed">
                <strong class="font-semibold text-stone-900">Tonggak sejarah:</strong> Menuju tahun ke-8 pada September 2027 mendatang, BKRC memperluas jangkauan manfaat melalui digitalisasi sistem transparansi donasi dan arsip kas terbuka.
            </div>
        </div>

        <div class="lg:col-span-6">
            <div class="bg-stone-900 text-stone-100 rounded-xl p-7 sm:p-8 border border-stone-800">
                <div class="space-y-6">
                    <div>
                        <span class="text-xs font-medium text-stone-400">Visi organisasi</span>
                        <p class="text-base font-normal text-stone-200 mt-1.5 italic leading-relaxed">
                            "Menjadi motor penggerak kepedulian sosial warga lingkungan cluster yang amanah, transparan, dan berkelanjutan bagi kesejahteraan sesama."
                        </p>
                    </div>
                    <div class="border-t border-stone-800 pt-5">
                        <span class="text-xs font-medium text-stone-400">Misi organisasi</span>
                        <ul class="text-xs text-stone-300 space-y-3 mt-3">
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-stone-400 mt-1.5 shrink-0"></span>
                                <span>Menjamin kelangsungan santunan pendidikan bagi anak-anak yatim binaan setiap bulan.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-stone-400 mt-1.5 shrink-0"></span>
                                <span>Meringankan beban pangan warga dhuafa dan lansia melalui distribusi paket sembako rutin.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-stone-400 mt-1.5 shrink-0"></span>
                                <span>Menjaga keterbukaan dan akuntabilitas pengelolaan kas serta donasi secara berkala.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-stone-400 mt-1.5 shrink-0"></span>
                                <span>Mempererat kerukunan dan kekompakan warga cluster melalui silaturahmi internal yang bersahaja.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Struktur Relawan / Anggota Cluster -->
    <div>
        <div class="max-w-2xl text-left mb-8">
            <h2 class="text-2xl sm:text-3xl font-semibold text-stone-900 tracking-tight">
                Susunan pengurus & relawan
            </h2>
            <p class="text-stone-600 text-xs sm:text-sm mt-1">
                Warga lingkungan cluster yang mengabdikan waktu dan tenaganya secara sukarela untuk mengelola amanah donatur.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            <?php foreach ($members as $m): ?>
                <div class="bg-white rounded-xl p-5 border border-stone-200/90 hover:border-stone-300 transition-colors flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-stone-100 text-stone-700 border border-stone-200 flex items-center justify-center text-base font-semibold shrink-0">
                        <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-bkrc-red-800 mb-0.5">
                            <?= htmlspecialchars($m['position']) ?>
                        </div>
                        <h3 class="text-sm font-semibold text-stone-900"><?= htmlspecialchars($m['full_name']) ?></h3>
                        <div class="text-xs text-stone-500 mt-0.5">
                            <i class="fa-solid fa-house-chimney text-stone-400 mr-1 text-[10px]"></i> <?= htmlspecialchars($m['cluster_block']) ?>
                        </div>
                        <div class="text-[11px] text-stone-400 mt-0.5">
                            Bergabung sejak <?= date('Y', strtotime($m['joined_date'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
