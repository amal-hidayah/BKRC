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

<!-- Header (Solid Slate-900, Clean Civic Header) -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold tracking-normal">Dokumentasi & Berita Kegiatan BKRC</h1>
                <p class="text-slate-300 text-sm mt-1">Laporan pelaksanaan kegiatan rutin santunan, aksi sosial, dan kebersamaan relawan.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-300">
                <a href="<?= getBaseUrl() ?>/index.php" class="hover:text-white">Beranda</a>
                <span>/</span>
                <span class="text-bkrc-gold-400 font-semibold">Dokumentasi</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if (empty($posts)): ?>
        <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 text-slate-500">
            <i class="fa-regular fa-newspaper text-4xl text-slate-300 mb-3 block"></i>
            Belum ada dokumentasi atau berita yang dipublikasikan.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($posts as $post): ?>
                <article class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg transition-all flex flex-col group">
                    <!-- Thumbnail Image or Fallback -->
                    <div class="h-52 bg-slate-100 border-b border-slate-200 relative overflow-hidden flex items-center justify-center text-slate-400 group">
                        <?php if (!empty($post['thumbnail_url']) && file_exists(__DIR__ . '/' . $post['thumbnail_url'])): ?>
                            <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php elseif (!empty($post['thumbnail_url']) && filter_var($post['thumbnail_url'], FILTER_VALIDATE_URL)): ?>
                            <img src="<?= htmlspecialchars($post['thumbnail_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="BKRC" class="w-16 h-16 object-contain opacity-40 group-hover:opacity-70 group-hover:scale-105 transition-all duration-200">
                        <?php endif; ?>
                        <div class="absolute bottom-3 left-3 bg-slate-900/90 text-white text-[11px] font-semibold px-2.5 py-1 rounded-md shadow-sm">
                            <i class="fa-regular fa-calendar mr-1"></i> <?= formatTanggalIndo($post['event_date'] ?? $post['published_at']) ?>
                        </div>
                    </div>

                    <div class="p-6 flex-grow flex flex-col justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 group-hover:text-bkrc-red-700 transition leading-snug mb-3">
                                <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h2>
                            <p class="text-xs text-slate-600 leading-relaxed line-clamp-3">
                                <?= htmlspecialchars($post['excerpt'] ?? strip_tags(substr($post['content'], 0, 150))) ?>...
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600 mt-4">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-user-pen text-slate-500"></i> <?= htmlspecialchars($post['author_name'] ?? 'Pengurus BKRC') ?>
                            </span>
                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($post['slug']) ?>" class="font-bold text-bkrc-red-800 hover:text-bkrc-red-950 min-h-[44px] inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                                Baca Selengkapnya <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
