<?php
/**
 * Manajemen Berita & Dokumentasi Kegiatan BKRC (Back-Office)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Tambah / Edit Berita
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
    $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : date('Y-m-d');
    $authorId = $currentUser['id'];

    if (empty($title) || empty($content)) {
        setFlash('error', 'Judul dan isi konten berita wajib diisi!');
    } else {
        // Handle File Upload if provided
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['thumbnail_file']['tmp_name'];
            $fileName = $_FILES['thumbnail_file']['name'];
            $fileSize = $_FILES['thumbnail_file']['size'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($ext, $allowedExts)) {
                if ($fileSize <= 5 * 1024 * 1024) { // 5MB limit
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newFileName = 'berita_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destination = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmp, $destination)) {
                        $thumbnailUrl = 'assets/uploads/' . $newFileName;
                    }
                } else {
                    setFlash('error', 'Ukuran file foto maksimal 5 MB!');
                    header('Location: ' . getBaseUrl() . '/admin/posts.php');
                    exit;
                }
            } else {
                setFlash('error', 'Format file foto harus berupa JPG, PNG, atau WEBP!');
                header('Location: ' . getBaseUrl() . '/admin/posts.php');
                exit;
            }
        }

        // Generate Slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        if ($action === 'create') {
            // Check unique slug
            $stmtSlug = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
            $stmtSlug->execute([$slug]);
            if ($stmtSlug->fetch()) {
                $slug .= '-' . time();
            }

            $stmt = $pdo->prepare("
                INSERT INTO posts (title, slug, excerpt, content, thumbnail_url, event_date, author_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $slug, $excerpt, $content, $thumbnailUrl, $eventDate, $authorId]);
            setFlash('success', 'Dokumentasi berita dan foto berhasil dipublikasikan!');
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET title = ?, excerpt = ?, content = ?, thumbnail_url = ?, event_date = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $excerpt, $content, $thumbnailUrl, $eventDate, $id]);
            setFlash('success', 'Berita dan foto berhasil diperbarui.');
        }
        header('Location: ' . getBaseUrl() . '/admin/posts.php');
        exit;
    }
}

// Handle Hapus
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$delId]);
    setFlash('success', 'Berita berhasil dihapus.');
    header('Location: ' . getBaseUrl() . '/admin/posts.php');
    exit;
}

$posts = $pdo->query("
    SELECT p.*, u.name as author_name 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    ORDER BY p.published_at DESC
")->fetchAll();

$pageTitle = 'Dokumentasi Berita';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ 
    modalOpen: false, 
    isEdit: false, 
    previewUrl: '',
    form: { id: '', title: '', excerpt: '', content: '', thumbnail_url: '', event_date: '<?= date('Y-m-d') ?>' },
    openNew() {
        this.isEdit = false;
        this.form = { id: '', title: '', excerpt: '', content: '', thumbnail_url: 'assets/img/santunan_sample.jpg', event_date: '<?= date('Y-m-d') ?>' };
        this.previewUrl = '<?= getBaseUrl() ?>/assets/img/santunan_sample.jpg';
        this.modalOpen = true;
    },
    openEdit(p) {
        this.isEdit = true;
        this.form = { ...p };
        this.previewUrl = p.thumbnail_url ? ('<?= getBaseUrl() ?>/' + p.thumbnail_url) : '';
        this.modalOpen = true;
    },
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            this.previewUrl = URL.createObjectURL(file);
        }
    }
}" @keydown.escape.window="modalOpen = false" class="flex-1 flex flex-col w-full">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 h-20 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-3">
            <button @click="sidebarOpen = true" 
                    type="button" 
                    class="lg:hidden text-slate-600 hover:text-slate-900 min-h-[44px] min-w-[44px] -ml-2 inline-flex items-center justify-center p-2 rounded-xl hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" 
                    aria-label="Buka Menu Navigasi">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Berita & Dokumentasi</h1>
            </div>
        </div>

        <button @click="openNew()" class="inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
            <span>Tulis Berita Baru</span>
        </button>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5">
        <?php if ($flash = getFlash('success')): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash = getFlash('error')): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4 w-16">Foto</th>
                            <th class="py-3 px-4">Judul Dokumentasi</th>
                            <th class="py-3 px-4">Tanggal Kegiatan</th>
                            <th class="py-3 px-4">Penulis</th>
                            <th class="py-3 px-4">Dipublikasi</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($posts)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada berita dipublikasikan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $p): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center flex-shrink-0">
                                            <?php if (!empty($p['thumbnail_url']) && file_exists(__DIR__ . '/../' . $p['thumbnail_url'])): ?>
                                                <img src="<?= getBaseUrl() ?>/<?= htmlspecialchars($p['thumbnail_url']) ?>" alt="Thumb" class="w-full h-full object-cover">
                                            <?php elseif (!empty($p['thumbnail_url']) && filter_var($p['thumbnail_url'], FILTER_VALIDATE_URL)): ?>
                                                <img src="<?= htmlspecialchars($p['thumbnail_url']) ?>" alt="Thumb" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fa-solid fa-image text-slate-400"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($p['title']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['excerpt'] ?? '') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-xs"><?= formatTanggalIndo($p['event_date']) ?></td>
                                    <td class="py-3.5 px-4 text-xs"><?= htmlspecialchars($p['author_name'] ?? 'Pengurus') ?></td>
                                    <td class="py-3.5 px-4 text-xs text-slate-600 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($p['published_at'])) ?></td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="<?= getBaseUrl() ?>/berita-detail.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition" title="Lihat di Portal Publik">
                                                <i class="fa-solid fa-eye text-xs"></i>
                                            </a>
                                            <button @click="openEdit({ id: '<?= $p['id'] ?>', title: '<?= addslashes($p['title']) ?>', excerpt: '<?= addslashes($p['excerpt'] ?? '') ?>', content: '<?= addslashes(str_replace(["\r", "\n"], ['\\r', '\\n'], $p['content'])) ?>', thumbnail_url: '<?= addslashes($p['thumbnail_url'] ?? '') ?>', event_date: '<?= $p['event_date'] ?>' })" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition" title="Edit Berita">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>
                                            <a href="<?= getBaseUrl() ?>/admin/posts.php?delete=<?= $p['id'] ?>" onclick="return confirm('Hapus berita ini?');" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200/60 transition" title="Hapus Berita">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form Berita -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-xl w-full p-6 sm:p-8 overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Berita Kegiatan' : 'Tulis Berita Kegiatan Baru'"></h3>
                <button @click="modalOpen = false" class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Judul Berita <span class="text-rose-600">*</span></label>
                    <input type="text" name="title" x-model="form.title" required placeholder="Judul liputan kegiatan BKRC..." class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal Kegiatan</label>
                    <input type="date" name="event_date" x-model="form.event_date" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <!-- Unggah Foto Dokumentasi -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-cloud-arrow-up text-bkrc-red-800"></i>
                        <span>Unggah Foto Dokumentasi</span>
                    </label>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <!-- Pratinjau Foto -->
                        <div class="w-24 h-24 rounded-xl bg-slate-200 border border-slate-300 overflow-hidden flex items-center justify-center flex-shrink-0 relative shadow-sm">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Pratinjau" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!previewUrl">
                                <div class="text-center text-slate-500 p-2">
                                    <i class="fa-regular fa-image text-2xl"></i>
                                    <div class="text-[9px] mt-1 font-medium">Belum ada foto</div>
                                </div>
                            </template>
                        </div>

                        <!-- Kontrol File & Presets -->
                        <div class="flex-1 w-full space-y-2">
                            <input type="file" name="thumbnail_file" accept="image/png, image/jpeg, image/webp" @change="handleFileSelect($event)" class="w-full text-xs text-slate-600 file:mr-3 file:py-2.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-bkrc-red-800 hover:file:bg-red-100 cursor-pointer min-h-[44px] flex items-center">
                            <input type="hidden" name="thumbnail_url" x-model="form.thumbnail_url">

                            <p class="text-[11px] text-slate-600 leading-tight">
                                Pilih foto dari komputer/HP (JPG, PNG, WEBP, Maks. 5MB).
                            </p>

                            <div class="pt-1 flex items-center gap-2 flex-wrap text-xs">
                                <span class="text-slate-500 font-medium text-[11px]">Contoh bawaan:</span>
                                <button type="button" @click="form.thumbnail_url = 'assets/img/santunan_sample.jpg'; previewUrl = '<?= getBaseUrl() ?>/assets/img/santunan_sample.jpg'" class="bg-white hover:bg-slate-100 px-3 py-1.5 min-h-[36px] rounded-lg border border-slate-300 text-slate-800 shadow-2xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">Santunan</button>
                                <button type="button" @click="form.thumbnail_url = 'assets/img/catur_sample.jpg'; previewUrl = '<?= getBaseUrl() ?>/assets/img/catur_sample.jpg'" class="bg-white hover:bg-slate-100 px-3 py-1.5 min-h-[36px] rounded-lg border border-slate-300 text-slate-800 shadow-2xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">Catur</button>
                                <button type="button" @click="form.thumbnail_url = 'assets/img/ramadhan_sample.jpg'; previewUrl = '<?= getBaseUrl() ?>/assets/img/ramadhan_sample.jpg'" class="bg-white hover:bg-slate-100 px-3 py-1.5 min-h-[36px] rounded-lg border border-slate-300 text-slate-800 shadow-2xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">Ramadhan</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ringkasan Singkat (Excerpt)</label>
                    <textarea name="excerpt" x-model="form.excerpt" rows="2" placeholder="Ringkasan 1-2 kalimat untuk preview card..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Konten Lengkap Berita <span class="text-rose-600">*</span></label>
                    <textarea name="content" x-model="form.content" rows="6" required placeholder="Tuliskan jalannya kegiatan, ucapan terima kasih kepada donatur, dll..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                        <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Simpan & Publikasikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
