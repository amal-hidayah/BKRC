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

<!-- Header Page (Solid Slate-900, Clean Civic Header) -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight">Kalender Agenda BKRC 2026</h1>
                <p class="text-slate-300 text-sm mt-1">Rangkaian program sosial, silaturahmi relawan, santunan akbar, dan evaluasi tahunan.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= getBaseUrl() ?>/index.php" class="text-xs text-slate-300 hover:text-white flex items-center gap-1">
                    <i class="fa-solid fa-home"></i> Beranda
                </a>
                <span class="text-slate-500">/</span>
                <span class="text-xs text-bkrc-gold-400 font-semibold">Agenda 2026</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Category Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 border-b border-slate-200 pb-4">
        <span class="text-xs font-bold text-slate-500 uppercase mr-2"><i class="fa-solid fa-filter mr-1"></i> Filter:</span>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=semua" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $selectedCategory === 'semua' ? 'bg-bkrc-red-800 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Semua Agenda
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=rutin" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $selectedCategory === 'rutin' ? 'bg-bkrc-red-800 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Penyaluran Rutin
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=sosial" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $selectedCategory === 'sosial' ? 'bg-bkrc-red-800 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Event Sosial & Santunan
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=internal" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $selectedCategory === 'internal' ? 'bg-bkrc-red-800 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Internal & Silaturahmi
        </a>
        <a href="<?= getBaseUrl() ?>/agenda.php?kategori=musyawarah" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $selectedCategory === 'musyawarah' ? 'bg-bkrc-red-800 text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            Musyawarah & LPJ
        </a>
    </div>

    <!-- Timeline List View -->
    <div class="space-y-6">
        <?php if (empty($agendas)): ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 text-slate-500">
                <i class="fa-regular fa-calendar-xmark text-4xl text-slate-300 mb-3 block"></i>
                Tidak ada agenda pada kategori ini.
            </div>
        <?php else: ?>
            <?php foreach ($agendas as $agenda): ?>
                <?php
                $isDone = ($agenda['status'] === 'terlaksana');
                $badgeColor = match($agenda['category']) {
                    'rutin' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'sosial' => 'bg-rose-100 text-rose-800 border-rose-300',
                    'internal' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                    'musyawarah' => 'bg-amber-100 text-amber-800 border-amber-300',
                    default => 'bg-slate-100 text-slate-800 border-slate-300'
                };
                ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start gap-5">
                        <!-- Month Badge Box (Solid Matte) -->
                        <div class="w-20 h-20 rounded-2xl bg-slate-900 text-white flex flex-col items-center justify-center flex-shrink-0 border border-slate-800 shadow-sm">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400">2026</span>
                            <span class="text-sm font-extrabold tracking-tight"><?= htmlspecialchars(substr($agenda['month_label'], 0, 3)) ?></span>
                            <span class="text-[9px] text-slate-400">Bulan #<?= $agenda['month_num'] ?></span>
                        </div>

                        <!-- Details -->
                        <div>
                            <div class="flex items-center gap-3 text-xs mb-1.5">
                                <span class="font-bold uppercase tracking-wider text-slate-500">
                                    <?= ucfirst($agenda['category']) ?>
                                </span>
                                <span class="text-slate-300">&bull;</span>
                                <?php if ($isDone): ?>
                                    <span class="font-semibold text-emerald-700 flex items-center gap-1">
                                        <i class="fa-solid fa-check text-[10px]"></i> Terlaksana
                                    </span>
                                <?php else: ?>
                                    <span class="font-semibold text-slate-600 flex items-center gap-1">
                                        <i class="fa-regular fa-clock text-[10px]"></i> Rencana
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 mb-1">
                                <?= htmlspecialchars($agenda['title']) ?>
                            </h3>

                            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                                <?= htmlspecialchars($agenda['description']) ?>
                            </p>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600 mt-3 font-medium">
                                <span><i class="fa-regular fa-calendar-check text-bkrc-red-700 mr-1.5"></i> <?= $agenda['event_date'] ? formatTanggalIndo($agenda['event_date'], true) : 'Tanggal menyesuaikan kalender BKRC' ?></span>
                                <span><i class="fa-solid fa-location-dot text-slate-500 mr-1.5"></i> Wilayah Cluster BKRC</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex md:flex-col items-center justify-end gap-2 border-t md:border-t-0 pt-4 md:pt-0 border-slate-100 flex-shrink-0">
                        <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="bg-bkrc-red-50 hover:bg-bkrc-red-100 text-bkrc-red-800 font-bold text-xs sm:text-sm px-4 py-2.5 min-h-[44px] rounded-xl transition border border-bkrc-red-200 inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                            <i class="fa-solid fa-heart text-bkrc-red-700"></i> Dukung Acara
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
