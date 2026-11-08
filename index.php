<?php
/**
 * Halaman Utama (Front-End Landing Page) - Portal Publik BKRC
 * Modul A PRD: Hero Banner, Program Penyaluran, Agenda Kegiatan, Papan Transparansi, Galeri, & Kanal Donasi
 */
$pageTitle = 'Beranda — Portal Resmi Bantuan Kemanusiaan Relawan Cluster';
require_once __DIR__ . '/includes/header.php';

// Ambil Ringkasan Kas & Metrik
$kasSummary = getKasSummary($pdo);

// Ambil Agenda Kegiatan (Tahun Berjalan & Mendatang)
$stmtAgendaYears = $pdo->query("SELECT DISTINCT year FROM annual_agendas ORDER BY year ASC");
$agendaYears = $stmtAgendaYears->fetchAll(PDO::FETCH_COLUMN);

$stmtAgenda = $pdo->query("SELECT * FROM annual_agendas ORDER BY year ASC, month_num ASC");
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

<!-- 1. HERO SECTION (Editorial, Photographic, Balanced Scrim) -->
<section class="relative min-h-[460px] sm:min-h-[500px] lg:min-h-[540px] flex items-center overflow-hidden bg-stone-950">
    <!-- Hero Background Image (Natural vitality of volunteers preserved) -->
    <div class="absolute inset-0 z-0">
        <img src="<?= getBaseUrl() ?>/assets/img/hero_volunteer_banner.jpeg?v=<?= @filemtime(__DIR__ . '/assets/img/hero_volunteer_banner.jpeg') ?>" alt="Relawan Warga BKRC" class="w-full h-full object-cover object-center sm:object-right">
        <!-- Natural Directional Scrim: Softer, leaving volunteer faces clear while ensuring high text readability -->
        <div class="absolute inset-0 bg-gradient-to-r from-stone-950/90 via-stone-950/65 to-stone-950/20"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-stone-950/50 via-transparent to-transparent"></div>
    </div>

    <!-- Hero Content (Calm editorial typography, factual copy) -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-white w-full">
        <div class="max-w-2xl text-left">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-stone-300 mb-3 tracking-normal">
                <span>Inisiatif mandiri warga cluster</span>
            </div>
            
            <h1 class="text-3xl sm:text-4xl lg:text-[44px] font-semibold text-white leading-tight tracking-tight" style="text-wrap: balance;">
                Setiap rupiah tercatat. Setiap bulan, bantuan langsung sampai ke tangan yang berhak.
            </h1>

            <p class="text-sm sm:text-base text-stone-200 mt-3.5 leading-relaxed max-w-xl" style="text-wrap: balance;">
                Santunan rutin anak yatim dan sembako dhuafa di lingkungan sekitar cluster secara amanah, terbuka, dan akuntabel.
            </p>

            <!-- Action Buttons (One primary crimson action, one quiet secondary) -->
            <div class="flex flex-wrap items-center gap-3 pt-6">
                <a href="#kanal-donasi" class="min-h-[42px] px-5 py-2.5 rounded-lg bg-[#8b1e1e] hover:bg-[#701616] text-white font-medium text-xs sm:text-sm transition shadow-2xs inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                    <span>Salurkan donasi</span>
                    <i class="fa-solid fa-arrow-right text-[10px] text-stone-200"></i>
                </a>
                <a href="#agenda-kegiatan" class="min-h-[42px] px-4 py-2.5 rounded-lg bg-white/10 hover:bg-white/15 text-stone-100 font-medium text-xs sm:text-sm border border-white/20 transition inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <span>Lihat agenda kegiatan</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 2. METRIC STRIP (Full-Width Aligned, Tabular Numbers, Warm Neutral Card) -->
<div class="relative z-20 -mt-6 sm:-mt-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-xl shadow-2xs border border-stone-200/80 p-5 sm:p-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-stone-200/70">
            <!-- Metric 1: Saldo Kas -->
            <div class="pt-3 sm:pt-0 sm:pl-4 first:pt-0 first:pl-0">
                <div class="text-xs text-stone-500 font-normal mb-1">Saldo kas aktif</div>
                <div class="text-lg sm:text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= formatRupiah($kasSummary['saldo_kas']) ?>
                </div>
            </div>

            <!-- Metric 2: Relawan Warga -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-stone-500 font-normal mb-1">Relawan warga terdaftar</div>
                <div class="text-lg sm:text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= $kasSummary['total_relawan'] ?> orang
                </div>
            </div>

            <!-- Metric 3: Donatur Warga -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-stone-500 font-normal mb-1">Donatur terdaftar</div>
                <div class="text-lg sm:text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= $kasSummary['total_donatur'] ?> donatur
                </div>
            </div>

            <!-- Metric 4: Paket Sembako -->
            <div class="pt-3 sm:pt-0 sm:pl-4">
                <div class="text-xs text-stone-500 font-normal mb-1">Sembako tersalurkan</div>
                <div class="text-lg sm:text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> paket
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. PROGRAM UTAMA (Editorial Narrative, Real Figures, Quiet Sub-roles) -->
<section class="py-14 sm:py-16 bg-[#fbf9f5] border-b border-stone-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="max-w-2xl text-left mb-10">
            <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                Program penyaluran rutin
            </h2>
            <p class="text-stone-600 text-xs sm:text-sm mt-1 leading-relaxed">
                Fokus langsung pada kebutuhan pangan dan masa depan anak-anak di perkampungan sekitar cluster.
            </p>
        </div>

        <!-- Editorial 3-Column Narrative Structure -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-10 divide-y md:divide-y-0 md:divide-x divide-stone-200/80">
            <!-- 01: Santunan Yatim -->
            <div class="pt-6 md:pt-0 md:pl-0">
                <div class="text-xs font-medium text-stone-400 font-mono mb-1">01 &bull; Pendidikan & santunan</div>
                <div class="text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= (int)$kasSummary['total_yatim'] ?> anak binaan
                </div>
                <div class="text-xs text-[#8b1e1e] font-medium mt-0.5 mb-2.5">
                    Santunan tunai bulanan
                </div>
                <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                    Bantuan uang pendidikan bulanan diserahkan langsung secara tertutup dan bermartabat ke tangan ibu atau wali anak di awal bulan, tanpa seremonial berlebihan.
                </p>
            </div>

            <!-- 02: Paket Sembako -->
            <div class="pt-6 md:pt-0 md:pl-8 lg:pl-10">
                <div class="text-xs font-medium text-stone-400 font-mono mb-1">02 &bull; Pangan dhuafa</div>
                <div class="text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= number_format($kasSummary['total_sembako_tersalurkan']) ?> paket sembako
                </div>
                <div class="text-xs text-[#8b1e1e] font-medium mt-0.5 mb-2.5">
                    Telah disalurkan ke warga
                </div>
                <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                    Distribusi beras 5kg kualitas baik, minyak goreng, gula pasir, dan bahan pokok dapur untuk keluarga pra-sejahtera, janda, dan lansia di sekitar lingkungan cluster.
                </p>
            </div>

            <!-- 03: Kegiatan Kemanusiaan -->
            <div class="pt-6 md:pt-0 md:pl-8 lg:pl-10">
                <div class="text-xs font-medium text-stone-400 font-mono mb-1">03 &bull; Solidaritas warga</div>
                <div class="text-2xl font-semibold text-stone-900 tabular-nums tracking-tight">
                    <?= count($agendas) ?> agenda sosial
                </div>
                <div class="text-xs text-[#8b1e1e] font-medium mt-0.5 mb-2.5">
                    Kalender aksi tahun 2026
                </div>
                <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                    Aksi donor darah berkala bersama PMI, buka puasa bersama anak yatim di bulan Ramadhan, serta musyawarah tahunan penyampaian Laporan Pertanggungjawaban (LPJ).
                </p>
            </div>
        </div>
    </div>
</section>

<!-- 4. KALENDER AGENDA KEGIATAN (Clean Card Grid, Quiet Status Marker) -->
<section id="agenda-kegiatan" x-data="{ activeYear: 'all' }" class="py-14 sm:py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div class="text-left">
            <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                Agenda kegiatan
            </h2>
            <p class="text-stone-600 text-xs sm:text-sm mt-1">
                Jadwal aksi lapangan, santunan berkala, dan agenda sosial pengurus.
            </p>
        </div>
        <a href="<?= getBaseUrl() ?>/agenda.php" class="text-xs font-medium text-[#8b1e1e] hover:underline inline-flex items-center gap-1 self-start sm:self-auto">
            <span>Lihat seluruh agenda</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>

    <!-- Filter Tab Tahun -->
    <?php if (count($agendaYears) > 1): ?>
        <div class="flex items-center gap-1.5 mb-6 overflow-x-auto pb-1">
            <span class="text-xs text-stone-500 mr-2 flex-shrink-0">Tahun:</span>
            <button type="button" @click="activeYear = 'all'" :class="activeYear === 'all' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer flex-shrink-0">
                Semua tahun
            </button>
            <?php foreach ($agendaYears as $y): ?>
                <button type="button" @click="activeYear = '<?= $y ?>'" :class="activeYear === '<?= $y ?>' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer flex-shrink-0">
                    Tahun <?= $y ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Agenda Grid: 4px aligned, quiet status -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($agendas as $agenda): ?>
            <?php $isDone = ($agenda['status'] === 'terlaksana'); ?>
            <div x-show="activeYear === 'all' || activeYear === '<?= $agenda['year'] ?>'" class="bg-white rounded-lg border border-stone-200/80 p-4 transition-colors duration-150 flex flex-col justify-between <?= $isDone ? 'bg-stone-50/50' : 'hover:border-stone-300' ?>">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-[#8b1e1e]">
                            <?= htmlspecialchars($agenda['month_label']) ?> <?= htmlspecialchars($agenda['year']) ?>
                        </span>
                        
                        <?php if ($isDone): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-stone-500 bg-stone-100 px-2 py-0.5 rounded border border-stone-200">
                                <i class="fa-solid fa-check text-[9px] text-stone-500"></i> Selesai
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-sm font-semibold text-stone-900 mb-2 leading-snug">
                        <?= htmlspecialchars($agenda['title']) ?>
                    </h3>
                </div>

                <div class="pt-3 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500 mt-2">
                    <span>
                        <?= $agenda['event_date'] ? formatTanggalIndo($agenda['event_date']) : 'Jadwal reguler' ?>
                    </span>
                    <span class="capitalize text-stone-500 text-[11px]"><?= $agenda['category'] ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- 5. PAPAN TRANSPARANSI DONASI (High Contrast, Tabular Numbers, Clean Data Layout) -->
<section class="py-14 sm:py-16 bg-[#f5f2eb]/60 border-y border-stone-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div class="text-left">
                <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                    Papan transparansi donasi
                </h2>
                <p class="text-stone-600 text-xs sm:text-sm mt-1">
                    Catatan donasi masuk dan penyaluran terbuka demi menjaga amanah warga cluster.
                </p>
            </div>
            <a href="<?= getBaseUrl() ?>/transparansi.php" class="text-xs font-medium text-[#8b1e1e] hover:underline inline-flex items-center gap-1 self-start sm:self-auto">
                <span>Lihat rekap lengkap</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Table Display: High contrast, uniform row height, clean typography -->
        <div class="bg-white rounded-xl shadow-2xs border border-stone-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-stone-50 border-b border-stone-200 text-stone-600 text-[11px] font-medium tracking-normal">
                        <tr>
                            <th class="py-3 px-4">No. kwitansi</th>
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Nama donatur</th>
                            <th class="py-3 px-4">Jenis donasi</th>
                            <th class="py-3 px-4">Nominal / paket</th>
                            <th class="py-3 px-4">Peruntukan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 text-stone-700">
                        <?php if (empty($recentDonations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-stone-400">Belum ada data donasi tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentDonations as $d): ?>
                                <?php
                                $isAnon = (bool)$d['is_anonymous'];
                                $donorDisplayName = $isAnon ? 'Hamba Allah' : htmlspecialchars($d['donor_name']);
                                $isUang = ($d['type'] === 'uang');
                                ?>
                                <tr class="hover:bg-stone-50/70 transition-colors duration-150">
                                    <td class="py-3.5 px-4 font-mono font-medium text-stone-600 whitespace-nowrap">
                                        <?= htmlspecialchars($d['receipt_no']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-stone-500">
                                        <?= formatTanggalIndo($d['donation_date']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if ($isAnon): ?>
                                            <span class="italic text-stone-600 font-medium">Hamba Allah</span>
                                            <span class="text-[11px] text-stone-400 font-normal ml-1">(Anonim)</span>
                                        <?php else: ?>
                                            <span class="font-medium text-stone-900"><?= $donorDisplayName ?></span>
                                            <?php if (!empty($d['cluster_or_address'])): ?>
                                                <span class="block text-[11px] text-stone-500 font-normal"><?= htmlspecialchars($d['cluster_or_address']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-stone-600 font-normal">
                                        <?= $isUang ? 'Uang kas' : 'Paket sembako' ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-stone-900 tabular-nums whitespace-nowrap">
                                        <?php if ($isUang): ?>
                                            <?= formatRupiah($d['amount']) ?>
                                        <?php else: ?>
                                            <?= number_format($d['package_qty']) ?> Paket 
                                            <?php if (!empty($d['package_details'])): ?>
                                                <span class="block text-[11px] font-normal text-stone-500"><?= htmlspecialchars($d['package_details']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-stone-500">
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

<!-- 6. DOKUMENTASI KEGIATAN (Editorial Story Layout, Natural Transitions) -->
<section class="py-14 sm:py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div class="text-left">
            <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                Dokumentasi kegiatan
            </h2>
            <p class="text-stone-600 text-xs sm:text-sm mt-1">
                Rekam jejak dan transparansi pelaksanaan kegiatan sosial warga cluster.
            </p>
        </div>
        <a href="<?= getBaseUrl() ?>/berita.php" class="text-xs font-medium text-[#8b1e1e] hover:underline inline-flex items-center gap-1 self-start sm:self-auto">
            <span>Semua dokumentasi</span>
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
            <article class="lg:col-span-7 bg-white rounded-xl border border-stone-200/80 overflow-hidden shadow-2xs hover:border-stone-300 transition-colors duration-150 flex flex-col group">
                <div class="h-60 sm:h-68 bg-stone-100 relative overflow-hidden">
                    <?php if (!empty($featuredPost['thumbnail_url']) && file_exists(__DIR__ . '/' . $featuredPost['thumbnail_url'])): ?>
                        <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($featuredPost['thumbnail_url']) ?>" alt="<?= htmlspecialchars($featuredPost['title']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <img src="<?= getBaseUrl() ?>/assets/img/hero_volunteer_banner.jpeg" alt="Dokumentasi BKRC" class="w-full h-full object-cover">
                    <?php endif; ?>
                    <div class="absolute bottom-3 left-3 bg-stone-900/90 text-stone-200 text-xs font-medium px-2.5 py-1 rounded">
                        <?= formatTanggalIndo($featuredPost['event_date'] ?? $featuredPost['published_at']) ?>
                    </div>
                </div>

                <div class="p-6 sm:p-7 flex-grow flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-[#8b1e1e]">Laporan utama</span>
                        <h3 class="text-base sm:text-lg font-semibold text-stone-900 group-hover:text-[#8b1e1e] transition-colors mt-1 leading-snug">
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>">
                                <?= htmlspecialchars($featuredPost['title']) ?>
                            </a>
                        </h3>
                        <p class="text-xs sm:text-sm text-stone-600 mt-2 leading-relaxed line-clamp-3">
                            <?= htmlspecialchars($featuredPost['excerpt'] ?? '') ?>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500 mt-5">
                        <span>Oleh: <?= htmlspecialchars($featuredPost['author_name'] ?? 'Pengurus BKRC') ?></span>
                        <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>" class="font-medium text-[#8b1e1e] hover:underline">
                            Baca selengkapnya &rarr;
                        </a>
                    </div>
                </div>
            </article>

            <!-- 2 Side Stories (5 cols) -->
            <div class="lg:col-span-5 flex flex-col gap-5 justify-between">
                <?php foreach ($sidePosts as $post): ?>
                    <article class="bg-white rounded-xl border border-stone-200/80 p-5 shadow-2xs hover:border-stone-300 transition-colors duration-150 flex flex-col justify-between group flex-1">
                        <div>
                            <div class="text-xs text-stone-400 mb-1">
                                <?= formatTanggalIndo($post['event_date'] ?? $post['published_at']) ?>
                            </div>
                            <h3 class="text-sm font-semibold text-stone-900 group-hover:text-[#8b1e1e] transition-colors leading-snug">
                                <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h3>
                            <p class="text-xs text-stone-600 mt-1.5 leading-relaxed line-clamp-2">
                                <?= htmlspecialchars($post['excerpt'] ?? '') ?>
                            </p>
                        </div>

                        <div class="pt-3 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500 mt-3">
                            <span><?= htmlspecialchars($post['author_name'] ?? 'Pengurus') ?></span>
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>" class="font-medium text-[#8b1e1e] hover:underline">
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
<!-- 7. KANAL DONASI (Quiet Authentic Bank Card, Clean Copy & Confirmation) -->
<section id="kanal-donasi" class="py-14 sm:py-16 bg-[#fbf9f5] border-t border-stone-200/80">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-left mb-6">
            <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                Kanal donasi
            </h2>
            <p class="text-stone-600 text-xs sm:text-sm mt-1 leading-relaxed">
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
        }" class="bg-white rounded-xl border border-stone-200/80 shadow-2xs p-6 sm:p-7">
            
            <div class="space-y-4">
                <!-- Baris 1: Nama Bank & Kode Bank -->
                <div class="flex items-center justify-between text-xs text-stone-500">
                    <div>
                        <span class="font-medium text-stone-800"><?= htmlspecialchars($officialBank['name']) ?></span>
                        <span class="text-stone-300 mx-1.5">&bull;</span>
                        <span>Kode Bank <?= htmlspecialchars($officialBank['code']) ?></span>
                    </div>
                </div>

                <!-- Baris 2: Nomor Rekening & Tombol Salin -->
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-2xl sm:text-3xl font-semibold text-stone-900 font-mono tracking-wider select-all">
                        <?= htmlspecialchars($officialBank['account_formatted']) ?>
                    </span>
                    <button @click="copyAccount()" 
                            type="button" 
                            :class="copied ? 'bg-stone-900 text-white border-stone-900' : 'bg-stone-50 hover:bg-stone-100 text-stone-700 border-stone-200'" 
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                        <template x-if="!copied">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-copy text-stone-400 text-[11px]"></i>
                                <span>Salin rekening</span>
                            </span>
                        </template>
                        <template x-if="copied">
                            <span class="inline-flex items-center gap-1 font-medium">
                                <i class="fa-solid fa-check text-[10px]"></i>
                                <span>Tersalin</span>
                            </span>
                        </template>
                    </button>
                </div>

                <!-- Baris 3: Atas Nama -->
                <div class="text-xs sm:text-sm text-stone-600">
                    Atas nama: <span class="text-stone-900 font-medium"><?= htmlspecialchars($officialBank['holder']) ?></span>
                </div>

                <!-- Pembatas Garis -->
                <div class="pt-2 border-t border-stone-200/70"></div>

                <!-- Baris 4: Konfirmasi Donasi -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                    <span class="text-xs sm:text-sm text-stone-600">
                        Sudah melakukan transfer?
                    </span>
                    <a href="<?= getBaseUrl() ?>/donasi.php" 
                       class="inline-flex items-center justify-center gap-2 bg-[#8b1e1e] hover:bg-[#701616] text-white font-medium text-xs sm:text-sm px-4 py-2 rounded-lg transition shadow-2xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                        <span>Konfirmasi donasi</span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-stone-200"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
