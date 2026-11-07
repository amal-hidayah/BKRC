<?php
/**
 * Detail Berita & Dokumentasi Kegiatan BKRC
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    WHERE p.slug = ?
    LIMIT 1
");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: ' . getBaseUrl() . '/berita.php');
    exit;
}

$pageTitle = htmlspecialchars($post['title']) . ' — Bantuan Kemanusiaan Relawan Cluster (BKRC)';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="bg-stone-900 text-stone-100 py-12 border-b border-stone-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-2 text-xs text-stone-400 mb-3">
            <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-stone-200 transition-colors">Beranda</a>
            <span>/</span>
            <a href="<?= getBaseUrl() ?>/berita.php" class="hover:text-stone-200 transition-colors">Dokumentasi</a>
            <span>/</span>
            <span class="text-stone-200 font-medium truncate">Detail</span>
        </div>
        
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight leading-tight text-white">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <div class="flex flex-wrap items-center gap-4 text-xs text-stone-300 mt-4 pt-4 border-t border-stone-800">
            <span><i class="fa-regular fa-calendar-check text-stone-400 mr-1.5"></i> <?= formatTanggalIndo($post['event_date'] ?? $post['published_at'], true) ?></span>
            <span><i class="fa-solid fa-user-pen text-stone-400 mr-1.5"></i> Oleh: <?= htmlspecialchars($post['author_name'] ?? 'Pengurus BKRC') ?></span>
            <span><i class="fa-solid fa-tag text-stone-400 mr-1.5"></i> Dokumentasi resmi BKRC</span>
        </div>
    </div>
</div>

<!-- Content Body -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <div class="bg-white rounded-xl border border-stone-200/90 p-6 sm:p-10">
        <!-- Featured Visual Header -->
        <?php if (!empty($post['thumbnail_url']) && file_exists(__DIR__ . '/' . $post['thumbnail_url'])): ?>
            <div class="w-full h-72 sm:h-96 rounded-lg overflow-hidden mb-8 border border-stone-200">
                <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
            </div>
        <?php elseif (!empty($post['thumbnail_url']) && filter_var($post['thumbnail_url'], FILTER_VALIDATE_URL)): ?>
            <div class="w-full h-72 sm:h-96 rounded-lg overflow-hidden mb-8 border border-stone-200">
                <img src="<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
            </div>
        <?php else: ?>
            <div class="w-full h-56 sm:h-72 bg-stone-100 border border-stone-200 rounded-lg mb-8 flex items-center justify-center text-stone-400">
                <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="BKRC" class="w-20 h-20 object-contain opacity-30">
            </div>
        <?php endif; ?>

        <!-- Excerpt Callout -->
        <?php if (!empty($post['excerpt'])): ?>
            <div class="bg-stone-50 border-l-2 border-bkrc-red-800 p-4 rounded-r-lg mb-8 text-sm text-stone-800 leading-relaxed">
                <?= htmlspecialchars($post['excerpt']) ?>
            </div>
        <?php endif; ?>

        <!-- Main Body -->
        <div class="prose max-w-none text-stone-700 leading-relaxed text-sm sm:text-base space-y-4">
            <?= nl2br($post['content']) ?>
        </div>

        <!-- Share & Back -->
        <div class="pt-6 mt-8 border-t border-stone-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            <a href="<?= getBaseUrl() ?>/berita.php" class="text-xs sm:text-sm font-medium text-stone-700 hover:text-bkrc-red-800 min-h-[44px] inline-flex items-center gap-2 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke daftar berita
            </a>

            <div class="flex items-center gap-2.5 text-xs sm:text-sm">
                <span class="text-stone-500">Bagikan:</span>
                <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . getBaseUrl() . '/berita-detail.php?slug=' . $post['slug']) ?>" target="_blank" rel="noopener noreferrer" class="bg-emerald-700 text-white px-3.5 py-2 min-h-[44px] rounded-lg font-medium hover:bg-emerald-800 transition-colors inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-700">
                    <i class="fa-brands fa-whatsapp text-base"></i> WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
