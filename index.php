<?php
/**
 * Halaman Utama (Front-End Landing Page) - Portal Publik BKRC
 * Modul A PRD: Hero Banner, Program Penyaluran, Agenda Kegiatan, Papan Transparansi, Galeri, & Kanal Donasi
 */
$pageTitle = 'Beranda — Portal Resmi Bantuan Kemanusiaan Relawan Cluster';
require_once __DIR__ . '/includes/header.php';

// Ambil Ringkasan Kas & Metrik
$kasSummary = getKasSummary($pdo);

// Ambil Agenda Tahun Berjalan (2026)
$stmtAgenda = $pdo->query("SELECT * FROM annual_agendas WHERE year = 2026 ORDER BY month_num ASC");
$agendas = $stmtAgenda->fetchAll();

// Ambil 10 Donasi Terbaru (Uang & Sembako - Khusus Approved / Sah)
$stmtDonasi = $pdo->query("
    SELECT d.*, dn.full_name as donor_name, dn.is_anonymous, dn.cluster_or_address
    FROM donations d
    JOIN donors dn ON d.donor_id = dn.id
    WHERE d.status = 'approved'
    ORDER BY d.donation_date DESC, d.id DESC
    LIMIT 10
");
$recentDonations = $stmtDonasi->fetchAll();

// Ambil Berita / Dokumentasi Terbaru
$stmtPosts = $pdo->query("
    SELECT p.*, u.name as author_name
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    ORDER BY p.published_at DESC
    LIMIT 3
");
$recentPosts = $stmtPosts->fetchAll();
?>

<!-- 1. HERO SECTION (Editorial, Photographic, Asymmetrical Scrim) -->
<section class="relative min-h-[500px] sm:min-h-[540px] lg:min-h-[580px] flex items-center overflow-hidden bg-slate-950">
    <!-- Hero Background Image (Preserved volunteer faces via directional scrim) -->
    <div class="absolute inset-0 z-0">
        <img src="<?= getBaseUrl() ?>/assets/img/hero_volunteer_banner.jpeg?v=<?= @filemtime(__DIR__ . '/assets/img/hero_volunteer_banner.jpeg') ?>" alt="Relawan Warga BKRC" class="w-full h-full object-cover object-center sm:object-right">
        <!-- Directional Scrim: Dark on the left for text contrast, open on the right for photo vitality -->
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/70 to-slate-950/20"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
    </div>

    <!-- Hero Content (Left-Aligned, Text-Wrap Balance, Factual Copy) -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-24 text-white w-full">
        <div class="max-w-2xl text-left">
            <div class="inline-flex items-center gap-2 text-xs font-semibold text-amber-400 mb-4 tracking-wider uppercase">
                <span>Inisiatif Mandiri Warga Cluster</span>
            </div>
            
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white leading-tight tracking-normal" style="text-wrap: balance;">
                Setiap rupiah tercatat. Setiap bulan, bantuan langsung sampai ke tangan yang berhak.
            </h1>

            <p class="text-sm sm:text-base text-slate-200 mt-4 leading-relaxed max-w-xl" style="text-wrap: balance;">
                Inisiatif kemanusiaan warga cluster perumahan untuk santunan rutin anak yatim dan sembako dhuafa di lingkungan sekitar secara amanah, terbuka, dan akuntabel.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-3 pt-6">
                <a href="#kanal-donasi" class="min-h-[44px] px-6 py-2.5 rounded-xl bg-bkrc-red-800 hover:bg-bkrc-red-700 text-white font-bold text-xs sm:text-sm transition shadow-sm inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    <i class="fa-solid fa-heart text-amber-400"></i>
                    <span>Salurkan Donasi</span>
                </a>
                <a href="#agenda-kegiatan" class="min-h-[44px] px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs sm:text-sm border border-white/20 transition inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <span>Lihat Agenda 2026</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 2. METRIC STRIP (Full-Width Aligned, Tabular Numbers) -->
<div class="relative z-20 -mt-8 sm:-mt-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
            <!-- Metric 1: Saldo Kas -->
            <div class="pt-3 sm:pt-0 sm:pl-4 first:pt-0 first:pl-0">
                <div class="text-xs text-slate-500 font-medium mb-1">Saldo Kas Aktif</div>
                <div class="text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">
                    <?= formatRupiah($kasSummary['saldo_kas']) ?>
                </div>
            </div>

            <!-- Metric 2: Relawan Warga -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-slate-500 font-medium mb-1">Relawan Warga Terdaftar</div>
                <div class="text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">
                    <?= $kasSummary['total_relawan'] ?> Orang
                </div>
            </div>

            <!-- Metric 3: Donatur Warga -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-slate-500 font-medium mb-1">Donatur Terdaftar</div>
                <div class="text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">
                    <?= $kasSummary['total_donatur'] ?> Donatur
                </div>
            </div>

            <!-- Metric 4: Paket Sembako -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-slate-500 font-medium mb-1">Sembako Tersalurkan</div>
                <div class="text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">
                    <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> Paket
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. PROGRAM UTAMA (Editorial Numbered Format, Tanpa Kotak Ikon Pastel, Angka Riil) -->
<section class="py-16 sm:py-20 bg-slate-50 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header: Clean, Left-Aligned -->
        <div class="max-w-2xl text-left mb-12">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                Program Penyaluran Rutin
            </h2>
            <p class="text-slate-600 text-sm mt-2 leading-relaxed">
                Fokus langsung pada kebutuhan pangan dan masa depan anak-anak di perkampungan sekitar lingkungan cluster.
            </p>
        </div>

        <!-- Editorial 3-Column Narrative Structure (Natural height, real numbers) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12 divide-y md:divide-y-0 md:divide-x divide-slate-200">
            <!-- 01: Santunan Yatim -->
            <div class="pt-6 md:pt-0 md:pl-0">
                <div class="text-xs font-bold text-slate-400 font-mono tracking-wider mb-2">01 / PENDIDIKAN & SANTUNAN</div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums">
                    <?= (int)$kasSummary['total_yatim'] ?> Anak Binaan
                </div>
                <div class="text-xs font-semibold text-bkrc-red-800 uppercase tracking-wider mt-1 mb-3">
                    Santunan Uang Tunai / Bulan
                </div>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Bantuan uang pendidikan bulanan diserahkan langsung secara tertutup dan bermartabat ke tangan ibu atau wali anak di awal bulan, tanpa seremonial berlebihan.
                </p>
            </div>

            <!-- 02: Paket Sembako -->
            <div class="pt-6 md:pt-0 md:pl-8 lg:pl-12">
                <div class="text-xs font-bold text-slate-400 font-mono tracking-wider mb-2">02 / PANGAN DHUAFA</div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums">
                    <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> Paket Sembako
                </div>
                <div class="text-xs font-semibold text-bkrc-red-800 uppercase tracking-wider mt-1 mb-3">
                    Telah Disalurkan ke Warga
                </div>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Distribusi beras 5kg kualitas baik, minyak goreng, gula pasir, dan bahan pokok dapur untuk keluarga pra-sejahtera, janda, dan lansia di kampung sekitar cluster.
                </p>
            </div>

            <!-- 03: Kegiatan Kemanusiaan -->
            <div class="pt-6 md:pt-0 md:pl-8 lg:pl-12">
                <div class="text-xs font-bold text-slate-400 font-mono tracking-wider mb-2">03 / SOLIDARITAS WARGA</div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums">
                    <?= count($agendas) ?> Agenda Sosial
                </div>
                <div class="text-xs font-semibold text-bkrc-red-800 uppercase tracking-wider mt-1 mb-3">
                    Kalender Aksi Tahun 2026
                </div>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Aksi donor darah berkala bersama PMI, buka puasa bersama anak yatim di bulan Ramadhan, serta musyawarah tahunan penyampaian Laporan Pertanggungjawaban (LPJ).
                </p>
            </div>
        </div>
    </div>
</section>

<!-- 4. KALENDER AGENDA 2026 (Left-Aligned, Status Penanda Hanya Item Selesai) -->
<section id="agenda-kegiatan" class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div class="text-left">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                Agenda Kegiatan 2026
            </h2>
            <p class="text-slate-600 text-sm mt-1">
                Jadwal aksi lapangan, santunan berkala, dan agenda sosial pengurus.
            </p>
        </div>
        <a href="<?= getBaseUrl() ?>/agenda.php" class="text-xs font-bold text-bkrc-red-800 hover:text-bkrc-red-950 inline-flex items-center gap-1.5 self-start sm:self-auto">
            <span>Lihat Seluruh Agenda</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>

    <!-- Agenda Grid: Clean, Uncluttered, No Repetitive 'Rencana' Pills -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($agendas as $agenda): ?>
            <?php $isDone = ($agenda['status'] === 'terlaksana'); ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 transition flex flex-col justify-between <?= $isDone ? 'bg-slate-50/60' : 'hover:border-slate-300' ?>">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-bkrc-red-800 uppercase tracking-wider">
                            <?= htmlspecialchars($agenda['month_label']) ?> 2026
                        </span>
                        
                        <?php if ($isDone): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                <i class="fa-solid fa-check text-[9px]"></i> Selesai
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-sm font-bold text-slate-900 mb-2 leading-snug">
                        <?= htmlspecialchars($agenda['title']) ?>
                    </h3>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 mt-2">
                    <span>
                        <?= $agenda['event_date'] ? formatTanggalIndo($agenda['event_date']) : 'Jadwal Reguler' ?>
                    </span>
                    <span class="capitalize text-slate-600 font-medium text-[11px]"><?= $agenda['category'] ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- 5. PAPAN TRANSPARANSI DONASI (Clean Data Table, Tabular Numbers, No Icon Noise) -->
<section class="py-16 sm:py-20 bg-slate-100/70 border-y border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div class="text-left">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                    Papan Transparansi Donasi
                </h2>
                <p class="text-slate-600 text-sm mt-1">
                    Catatan donasi masuk dan penyaluran terbuka demi menjaga amanah warga cluster.
                </p>
            </div>
            <a href="<?= getBaseUrl() ?>/transparansi.php" class="text-xs font-bold text-bkrc-red-800 hover:text-bkrc-red-950 inline-flex items-center gap-1.5 self-start sm:self-auto">
                <span>Lihat Rekap Lengkap</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Table Display: High contrast, uniform row height, clean typography -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4">No. Kwitansi</th>
                            <th class="py-3.5 px-4">Tanggal</th>
                            <th class="py-3.5 px-4">Nama Donatur</th>
                            <th class="py-3.5 px-4">Jenis Donasi</th>
                            <th class="py-3.5 px-4">Nominal / Paket</th>
                            <th class="py-3.5 px-4">Peruntukan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($recentDonations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-500">Belum ada data donasi tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentDonations as $d): ?>
                                <?php
                                $isAnon = (bool)$d['is_anonymous'];
                                $donorDisplayName = $isAnon ? 'Hamba Allah' : htmlspecialchars($d['donor_name']);
                                $isUang = ($d['type'] === 'uang');
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4 font-mono font-semibold text-slate-600 whitespace-nowrap">
                                        <?= htmlspecialchars($d['receipt_no']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-slate-600">
                                        <?= formatTanggalIndo($d['donation_date']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if ($isAnon): ?>
                                            <span class="italic text-slate-600 font-semibold">Hamba Allah</span>
                                            <span class="text-[11px] text-slate-400 font-normal ml-1">(Anonim)</span>
                                        <?php else: ?>
                                            <span class="font-bold text-slate-900"><?= $donorDisplayName ?></span>
                                            <?php if (!empty($d['cluster_or_address'])): ?>
                                                <span class="block text-[11px] text-slate-500 font-normal"><?= htmlspecialchars($d['cluster_or_address']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-slate-700 font-medium">
                                        <?= $isUang ? 'Uang Kas' : 'Paket Sembako' ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?php if ($isUang): ?>
                                            <?= formatRupiah($d['amount']) ?>
                                        <?php else: ?>
                                            <?= number_format($d['package_qty']) ?> Paket 
                                            <?php if (!empty($d['package_details'])): ?>
                                                <span class="block text-[11px] font-normal text-slate-500"><?= htmlspecialchars($d['package_details']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-600">
                                        <?= htmlspecialchars($d['program_target']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- 6. DOKUMENTASI KEGIATAN (Asymmetric Editorial Layout) -->
<section class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div class="text-left">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-normal">
                Dokumentasi Kegiatan
            </h2>
            <p class="text-slate-600 text-sm mt-1">
                Rekam jejak dan transparansi pelaksanaan kegiatan sosial warga cluster.
            </p>
        </div>
        <a href="<?= getBaseUrl() ?>/berita.php" class="text-xs font-bold text-bkrc-red-800 hover:text-bkrc-red-950 inline-flex items-center gap-1.5 self-start sm:self-auto">
            <span>Semua Dokumentasi</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>

    <!-- Asymmetric Rhythm: 1 Large Feature Story + 2 Side Stories -->
    <?php if (!empty($recentPosts)): ?>
        <?php 
        $featuredPost = $recentPosts[0];
        $sidePosts = array_slice($recentPosts, 1);
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            <!-- Featured Large Card (7 cols) -->
            <article class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs hover:border-slate-300 transition flex flex-col group">
                <div class="h-64 sm:h-72 bg-slate-100 relative overflow-hidden">
                    <?php if (!empty($featuredPost['thumbnail_url']) && file_exists(__DIR__ . '/' . $featuredPost['thumbnail_url'])): ?>
                        <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($featuredPost['thumbnail_url']) ?>" alt="<?= htmlspecialchars($featuredPost['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <?php else: ?>
                        <img src="<?= getBaseUrl() ?>/assets/img/hero_volunteer_banner.jpeg" alt="Dokumentasi BKRC" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <?php endif; ?>
                    <div class="absolute bottom-3 left-3 bg-slate-900/90 text-white text-xs font-semibold px-2.5 py-1 rounded">
                        <i class="fa-regular fa-calendar mr-1"></i> <?= formatTanggalIndo($featuredPost['event_date'] ?? $featuredPost['published_at']) ?>
                    </div>
                </div>

                <div class="p-6 sm:p-7 flex-grow flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-bkrc-red-800 uppercase tracking-wider">Laporan Utama</span>
                        <h3 class="text-lg sm:text-xl font-bold text-slate-900 group-hover:text-bkrc-red-800 transition mt-1 leading-snug">
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>">
                                <?= htmlspecialchars($featuredPost['title']) ?>
                            </a>
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 mt-2.5 leading-relaxed line-clamp-3">
                            <?= htmlspecialchars($featuredPost['excerpt'] ?? '') ?>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 mt-5">
                        <span>Oleh: <?= htmlspecialchars($featuredPost['author_name'] ?? 'Pengurus BKRC') ?></span>
                        <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>" class="font-bold text-bkrc-red-800 hover:underline">
                            Baca Selengkapnya &rarr;
                        </a>
                    </div>
                </div>
            </article>

            <!-- 2 Side Stories (5 cols) -->
            <div class="lg:col-span-5 flex flex-col gap-6 justify-between">
                <?php foreach ($sidePosts as $post): ?>
                    <article class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs hover:border-slate-300 transition flex flex-col justify-between group flex-1">
                        <div>
                            <div class="text-xs text-slate-500 mb-1">
                                <i class="fa-regular fa-calendar mr-1"></i> <?= formatTanggalIndo($post['event_date'] ?? $post['published_at']) ?>
                            </div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 group-hover:text-bkrc-red-800 transition leading-snug">
                                <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed line-clamp-2">
                                <?= htmlspecialchars($post['excerpt'] ?? '') ?>
                            </p>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 mt-3">
                            <span><?= htmlspecialchars($post['author_name'] ?? 'Pengurus') ?></span>
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>" class="font-semibold text-bkrc-red-800 hover:underline">
                                Baca &rarr;
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php $officialBank = getOfficialBank($pdo); ?>
<!-- 7. KANAL DONASI (Left-Aligned, Single Clean Card, Direct Rekening & Salin) -->
<section id="kanal-donasi" class="py-16 sm:py-20 bg-stone-50 border-t border-slate-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header: Left-Aligned, Matching Other Sections -->
        <div class="text-left mb-6">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Kanal Donasi
            </h2>
            <p class="text-slate-600 text-sm mt-1.5 leading-relaxed">
                Salurkan kepedulian Anda untuk santunan rutin anak yatim dan sembako keluarga dhuafa.
            </p>
        </div>

        <!-- Single Flat Card -->
        <div x-data="{ 
            copied: false,
            copyAccount() {
                navigator.clipboard.writeText('<?= htmlspecialchars($officialBank['account_raw']) ?>');
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }
        }" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            
            <div class="space-y-4">
                <!-- Baris 1: Nama Bank & Kode Bank -->
                <div class="flex items-center justify-between text-xs sm:text-sm font-semibold text-slate-600">
                    <div>
                        <span><?= htmlspecialchars($officialBank['name']) ?></span>
                        <span class="text-slate-300 mx-1.5">·</span>
                        <span>Kode <?= htmlspecialchars($officialBank['code']) ?></span>
                    </div>
                    <!-- Blok QRIS disembunyikan sampai ada file QR asli (Poin #3) -->
                </div>

                <!-- Baris 2: Nomor Rekening & Tombol Salin -->
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tabular-nums tracking-wider select-all">
                        <?= htmlspecialchars($officialBank['account_formatted']) ?>
                    </span>
                    <button @click="copyAccount()" 
                            type="button" 
                            :class="copied ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300'" 
                            class="px-3.5 py-1.5 rounded-lg border text-xs sm:text-sm font-semibold transition inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        <template x-if="!copied">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-copy"></i>
                                <span>Salin</span>
                            </span>
                        </template>
                        <template x-if="copied">
                            <span class="inline-flex items-center gap-1 text-emerald-700 font-bold">
                                <i class="fa-solid fa-check"></i>
                                <span>Tersalin ✓</span>
                            </span>
                        </template>
                    </button>
                </div>

                <!-- Baris 3: Atas Nama -->
                <div class="text-sm text-slate-600">
                    a.n. <strong class="text-slate-900 font-semibold"><?= htmlspecialchars($officialBank['holder']) ?></strong>
                </div>

                <!-- Pembatas Garis -->
                <div class="pt-2 border-t border-slate-200"></div>

                <!-- Baris 4: Konfirmasi Donasi -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                    <span class="text-sm text-slate-700 font-medium">
                        Sudah transfer?
                    </span>
                    <a href="<?= getBaseUrl() ?>/donasi.php" 
                       class="inline-flex items-center justify-center gap-2 bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-sm px-5 py-2.5 rounded-xl transition shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                        <span>Konfirmasi Donasi</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
