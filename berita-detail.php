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

<!-- Header (Solid Slate-900, Clean Civic Header) -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-2 text-xs text-slate-300 mb-3">
            <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-white">Beranda</a>
            <span>/</span>
            <a href="<?= getBaseUrl() ?>/berita.php" class="hover:text-white">Dokumentasi</a>
            <span>/</span>
            <span class="text-bkrc-gold-400 font-semibold truncate">Detail</span>
        </div>
        
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-normal leading-tight">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-200 mt-4 pt-4 border-t border-white/10">
            <span><i class="fa-regular fa-calendar-check text-bkrc-gold-400 mr-1.5"></i> <?= formatTanggalIndo($post['event_date'] ?? $post['published_at'], true) ?></span>
            <span><i class="fa-solid fa-user-pen text-slate-300 mr-1.5"></i> Oleh: <?= htmlspecialchars($post['author_name'] ?? 'Pengurus BKRC') ?></span>
            <span><i class="fa-solid fa-tag text-slate-300 mr-1.5"></i> Dokumentasi Resmi BKRC</span>
        </div>
    </div>
</div>

<!-- Content Body -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-10">
        <!-- Featured Visual Header -->
        <?php if (!empty($post['thumbnail_url']) && file_exists(__DIR__ . '/' . $post['thumbnail_url'])): ?>
            <div class="w-full h-72 sm:h-96 rounded-xl overflow-hidden mb-8 border border-slate-200 shadow-sm">
                <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
            </div>
        <?php elseif (!empty($post['thumbnail_url']) && filter_var($post['thumbnail_url'], FILTER_VALIDATE_URL)): ?>
            <div class="w-full h-72 sm:h-96 rounded-xl overflow-hidden mb-8 border border-slate-200 shadow-sm">
                <img src="<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
            </div>
        <?php else: ?>
            <div class="w-full h-64 sm:h-80 bg-slate-100 border border-slate-200 rounded-xl mb-8 flex items-center justify-center text-slate-300">
                <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="BKRC" class="w-24 h-24 object-contain opacity-40">
            </div>
        <?php endif; ?>

        <!-- Excerpt Callout -->
        <?php if (!empty($post['excerpt'])): ?>
            <div class="bg-bkrc-red-50 border-l-4 border-bkrc-red-700 p-4 rounded-r-xl mb-8 text-sm text-bkrc-red-950 font-medium leading-relaxed">
                <?= htmlspecialchars($post['excerpt']) ?>
            </div>
        <?php endif; ?>

        <!-- Main Body -->
        <div class="prose max-w-none text-slate-700 leading-relaxed text-sm sm:text-base space-y-4">
            <?= nl2br($post['content']) ?>
        </div>

        <!-- Share & Back -->
        <div class="pt-8 mt-8 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            <a href="<?= getBaseUrl() ?>/berita.php" class="text-xs sm:text-sm font-bold text-slate-700 hover:text-bkrc-red-800 min-h-[44px] inline-flex items-center gap-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Berita
            </a>

            <div class="flex items-center gap-2 text-xs sm:text-sm">
                <span class="text-slate-600 font-medium">Bagikan:</span>
                <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . getBaseUrl() . '/berita-detail.php?slug=' . $post['slug']) ?>" target="_blank" class="bg-emerald-700 text-white px-4 py-2.5 min-h-[44px] rounded-xl font-semibold hover:bg-emerald-800 transition inline-flex items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-700">
                    <i class="fa-brands fa-whatsapp text-base"></i> WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
