<?php
/**
 * Manajemen Kalender Agenda BKRC (Back-Office)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAuth();
$currentUser = currentUser();

// Handle Tambah / Edit / Hapus Agenda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.');
        header('Location: ' . getBaseUrl() . '/admin/agendas.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM annual_agendas WHERE id = ?");
        $stmt->execute([$delId]);
        setFlash('success', 'Agenda berhasil dihapus.');
        header('Location: ' . getBaseUrl() . '/admin/agendas.php');
        exit;
    }

    $year = (int)($_POST['year'] ?? date('Y'));
    $monthNum = (int)($_POST['month_num'] ?? 1);
    $monthLabel = trim($_POST['month_label'] ?? 'Januari');
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'sosial';
    $description = trim($_POST['description'] ?? '');
    $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $status = $_POST['status'] ?? 'akan_datang';

    if (empty($title)) {
        setFlash('error', 'Judul agenda kegiatan wajib diisi!');
    } else {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO annual_agendas (year, month_num, month_label, title, category, description, event_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$year, $monthNum, $monthLabel, $title, $category, $description, $eventDate, $status]);
            setFlash('success', "Agenda '{$title}' berhasil ditambahkan ke kalender.");
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("
                UPDATE annual_agendas
                SET year = ?, month_num = ?, month_label = ?, title = ?, category = ?, description = ?, event_date = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$year, $monthNum, $monthLabel, $title, $category, $description, $eventDate, $status, $id]);
            setFlash('success', 'Agenda berhasil diperbarui.');
        }
        header('Location: ' . getBaseUrl() . '/admin/agendas.php');
        exit;
    }
}

$agendas = $pdo->query("SELECT * FROM annual_agendas ORDER BY year DESC, month_num ASC")->fetchAll();

$pageTitle = 'Kalender Agenda';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div x-data="{ modalOpen: false, isEdit: false, form: { id: '', year: <?= (int)date('Y') ?>, month_num: 1, month_label: 'Januari', title: '', category: 'sosial', description: '', event_date: '', status: 'akan_datang' } }" @keydown.escape.window="modalOpen = false" class="flex-1 flex flex-col w-full">
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
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Kalender Agenda</h1>
            </div>
        </div>

        <button @click="isEdit = false; form = { id: '', year: <?= (int)date('Y') ?>, month_num: 1, month_label: 'Januari', title: '', category: 'sosial', description: '', event_date: '', status: 'akan_datang' }; modalOpen = true" class="inline-flex items-center justify-center bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl transition shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
            <span>Tambah Agenda</span>
        </button>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-5">
        <?php if ($flash = getFlash('success')): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm p-4 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Table Display -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Bulan</th>
                            <th class="py-3 px-4">Nama Agenda Kegiatan</th>
                            <th class="py-3 px-4 whitespace-nowrap">Kategori</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tanggal Acara</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Status</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($agendas as $ag): ?>
                            <?php $isDone = ($ag['status'] === 'terlaksana'); ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="font-bold text-bkrc-red-800 text-sm"><?= htmlspecialchars($ag['month_label']) ?></span>
                                    <span class="text-xs text-slate-400 font-mono block"><?= $ag['year'] ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($ag['title']) ?></div>
                                    <?php if (!empty($ag['description'])): ?>
                                        <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($ag['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= ucfirst($ag['category']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600 font-medium">
                                    <?= $ag['event_date'] ? formatTanggalIndo($ag['event_date']) : 'Jadwal Ditentukan' ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($isDone): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Terlaksana
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Rencana
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button @click="isEdit = true; form = { id: '<?= $ag['id'] ?>', year: <?= $ag['year'] ?>, month_num: <?= $ag['month_num'] ?>, month_label: '<?= addslashes($ag['month_label']) ?>', title: '<?= addslashes($ag['title']) ?>', category: '<?= $ag['category'] ?>', description: '<?= addslashes($ag['description']) ?>', event_date: '<?= $ag['event_date'] ?>', status: '<?= $ag['status'] ?>' }; modalOpen = true" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-semibold transition" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <form action="" method="POST" class="inline m-0 p-0" onsubmit="return confirm('Hapus agenda ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition" title="Hapus">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form Agenda -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900" x-text="isEdit ? 'Edit Agenda' : 'Tambah Agenda Baru'"></h3>
                <button @click="modalOpen = false" class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" :value="isEdit ? 'update' : 'create'">
                <input type="hidden" name="id" :value="form.id">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tahun</label>
                        <input type="number" name="year" x-model="form.year" required class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bulan</label>
                        <select name="month_num" x-model="form.month_num" @change="form.month_label = $el.options[$el.selectedIndex].text" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                        <input type="hidden" name="month_label" :value="form.month_label">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Judul Agenda Kegiatan <span class="text-rose-600">*</span></label>
                    <input type="text" name="title" x-model="form.title" required placeholder="Contoh: Penyaluran Paket Santunan Yatim" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kategori</label>
                        <select name="category" x-model="form.category" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                            <option value="rutin">Penyaluran Rutin</option>
                            <option value="sosial">Sosial / Kemasyarakatan</option>
                            <option value="internal">Internal Relawan</option>
                            <option value="musyawarah">Musyawarah Tahunan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                        <select name="status" x-model="form.status" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                            <option value="akan_datang">Akan Datang</option>
                            <option value="terlaksana">Terlaksana</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal Acara (Opsional)</label>
                    <input type="date" name="event_date" x-model="form.event_date" class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Deskripsi Singkat</label>
                    <textarea name="description" x-model="form.description" rows="3" placeholder="Rencana detail acara..." class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-base sm:text-sm text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Batal</button>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-bold text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-xl shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">Simpan Agenda</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
