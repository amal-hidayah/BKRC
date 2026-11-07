<?php
/**
 * Halaman Berita & Dokumentasi Kegiatan BKRC
 */
$pageTitle = 'Dokumentasi & Berita Kegiatan — Bantuan Kemanusiaan Relawan Cluster (BKRC)';
require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->query("
    SELECT p.*, u.name as author_name
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    ORDER BY p.published_at DESC
");
$posts = $stmt->fetchAll();
?>

<!-- Header Banner -->
<div class="bg-stone-900 text-stone-100 py-12 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-white">Dokumentasi & berita kegiatan</h1>
                <p class="text-stone-300 text-sm mt-1">Laporan pelaksanaan kegiatan santunan, aksi sosial, dan kebersamaan relawan.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-stone-400">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-stone-200 transition-colors">Beranda</a>
                <span>/</span>
                <span class="text-stone-200 font-medium">Dokumentasi</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if (empty($posts)): ?>
        <div class="text-center py-16 bg-white rounded-xl border border-stone-200/90 text-stone-500">
            <i class="fa-regular fa-newspaper text-3xl text-stone-400 mb-3 block"></i>
            <p class="text-sm">Belum ada dokumentasi atau berita yang dipublikasikan.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <?php foreach ($posts as $post): ?>
                <article class="bg-white rounded-xl border border-stone-200/90 overflow-hidden flex flex-col group hover:border-stone-300 transition-colors">
                    <!-- Thumbnail Image or Fallback (no hover zoom) -->
                    <div class="h-48 bg-stone-100 border-b border-stone-200 relative overflow-hidden flex items-center justify-center text-stone-400">
                        <?php if (!empty($post['thumbnail_url']) && file_exists(__DIR__ . '/' . $post['thumbnail_url'])): ?>
                            <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
                        <?php elseif (!empty($post['thumbnail_url']) && filter_var($post['thumbnail_url'], FILTER_VALIDATE_URL)): ?>
                            <img src="<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="BKRC" class="w-14 h-14 object-contain opacity-30">
                        <?php endif; ?>
                        <div class="absolute bottom-3 left-3 bg-stone-900/85 backdrop-blur-xs text-stone-100 text-[11px] font-medium px-2 py-0.5 rounded">
                            <i class="fa-regular fa-calendar mr-1 text-stone-300"></i> <?= formatTanggalIndo($post['event_date'] ?? $post['published_at']) ?>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6 flex-grow flex flex-col justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-stone-900 group-hover:text-bkrc-red-800 transition-colors leading-snug mb-2">
                                <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h2>
                            <p class="text-xs text-stone-600 leading-relaxed line-clamp-3">
                                <?= htmlspecialchars($post['excerpt'] ?? strip_tags(substr($post['content'], 0, 150))) ?>...
                            </p>
                        </div>

                        <div class="pt-3.5 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500 mt-4">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-user-pen text-stone-400"></i> <?= htmlspecialchars($post['author_name'] ?? 'Pengurus BKRC') ?>
                            </span>
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>" class="font-medium text-bkrc-red-800 hover:text-bkrc-red-900 min-h-[44px] inline-flex items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                                Baca selengkapnya <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
