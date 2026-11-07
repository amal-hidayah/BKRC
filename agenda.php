<?php
/**
 * Halaman Agenda Tahunan BKRC 2026
 */
$pageTitle = 'Kalender Agenda 2026 — Bantuan Kemanusiaan Relawan Cluster';
require_once __DIR__ . '/includes/header.php';

// Filter Kategori
$selectedCategory = $_GET['kategori'] ?? 'semua';
$query = "SELECT * FROM annual_agendas WHERE year = 2026";
$params = [];

if ($selectedCategory !== 'semua' && in_array($selectedCategory, ['rutin', 'sosial', 'internal', 'musyawarah'])) {
    $query .= " AND category = ?";
    $params[] = $selectedCategory;
}
$query .= " ORDER BY month_num ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$agendas = $stmt->fetchAll();
?>

<!-- Header Page (Warm Stone-900, Clean Civic Header) -->
<div class="bg-stone-900 text-white py-12 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight">Kalender agenda BKRC 2026</h1>
                <p class="text-stone-300 text-xs sm:text-sm mt-1">Rangkaian program sosial, silaturahmi relawan, santunan akbar, dan evaluasi tahunan.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-stone-400">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-stone-200">Beranda</a>
                <span>/</span>
                <span class="text-stone-200 font-medium">Agenda 2026</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Category Filter Tabs -->
    <div class="flex flex-wrap items-center gap-1.5 mb-8 border-b border-stone-200/80 pb-4">
        <span class="text-xs text-stone-500 mr-2">Filter:</span>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=semua" class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $selectedCategory === 'semua' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200' ?>">
            Semua agenda
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=rutin" class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $selectedCategory === 'rutin' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200' ?>">
            Penyaluran rutin
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=sosial" class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $selectedCategory === 'sosial' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200' ?>">
            Event sosial & santunan
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=internal" class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $selectedCategory === 'internal' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200' ?>">
            Internal & silaturahmi
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=musyawarah" class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $selectedCategory === 'musyawarah' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100 border border-stone-200' ?>">
            Musyawarah & LPJ
        </a>
    </div>

    <!-- Timeline List View -->
    <div class="space-y-4 sm:space-y-5">
        <?php if (empty($agendas)): ?>
            <div class="text-center py-16 bg-white rounded-xl border border-stone-200 text-stone-500">
                <i class="fa-regular fa-calendar-xmark text-3xl text-stone-300 mb-2 block"></i>
                Tidak ada agenda pada kategori ini.
            </div>
        <?php else: ?>
            <?php foreach ($agendas as $agenda): ?>
                <?php
                $isDone = ($agenda['status'] === 'terlaksana');
                ?>
                <div class="bg-white rounded-xl p-5 sm:p-6 border border-stone-200/80 shadow-2xs hover:border-stone-300 transition-colors duration-150 flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="flex items-start gap-4 sm:gap-5">
                        <!-- Month Badge Box (Solid Matte) -->
                        <div class="w-16 h-16 rounded-xl bg-stone-900 text-stone-100 flex flex-col items-center justify-center flex-shrink-0 border border-stone-800">
                            <span class="text-[10px] font-medium text-stone-400">2026</span>
                            <span class="text-sm font-semibold tracking-tight text-white"><?= htmlspecialchars(substr($agenda['month_label'], 0, 3)) ?></span>
                            <span class="text-[9px] text-stone-400">#<?= $agenda['month_num'] ?></span>
                        </div>

                        <!-- Details -->
                        <div>
                            <div class="flex items-center gap-2.5 text-xs mb-1.5">
                                <span class="text-stone-500 capitalize font-medium">
                                    <?= htmlspecialchars($agenda['category']) ?>
                                </span>
                                <span class="text-stone-300">&bull;</span>
                                <?php if ($isDone): ?>
                                    <span class="font-medium text-stone-600 flex items-center gap-1">
                                        <i class="fa-solid fa-check text-[10px] text-stone-400"></i> Terlaksana
                                    </span>
                                <?php else: ?>
                                    <span class="text-stone-500 flex items-center gap-1">
                                        <i class="fa-regular fa-clock text-[10px]"></i> Rencana
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-base sm:text-lg font-semibold text-stone-900 mb-1">
                                <?= htmlspecialchars($agenda['title']) ?>
                            </h3>

                            <p class="text-xs sm:text-sm text-stone-600 max-w-3xl leading-relaxed">
                                <?= htmlspecialchars($agenda['description']) ?>
                            </p>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-stone-500 mt-2.5">
                                <span><i class="fa-regular fa-calendar text-stone-400 mr-1"></i> <?= $agenda['event_date'] ? formatTanggalIndo($agenda['event_date'], true) : 'Jadwal reguler' ?></span>
                                <span><i class="fa-solid fa-location-dot text-stone-400 mr-1"></i> Posko Pendopo Cluster</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex md:flex-col items-center justify-end gap-2 border-t md:border-t-0 pt-3 md:pt-0 border-stone-100 flex-shrink-0">
                        <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="bg-stone-50 hover:bg-stone-100 text-stone-800 font-medium text-xs px-3.5 py-2 min-h-[38px] rounded-lg transition border border-stone-200 inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                            <span>Dukung agenda</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
