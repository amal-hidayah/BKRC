<?php
/**
 * Halaman Pendaftaran Calon Anggota Relawan Cluster (BKRC)
 * Calon anggota mendaftar secara mandiri, status awal: Pending menunggu approval Admin.
 */
$pageTitle = 'Pendaftaran Anggota Relawan — BKRC';
require_once __DIR__ . '/includes/header.php';

$success = false;
$submittedData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Token keamanan kedaluwarsa. Silakan ulangi pengisian formulir.');
        header('Location: ' . getBaseUrl() . '/daftar_anggota.php');
        exit;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $clusterBlock = trim($_POST['cluster_block'] ?? '');
    $rtRw = trim($_POST['rt_rw'] ?? 'RT 01 / RW 08');
    $interest = trim($_POST['interest'] ?? 'Relawan Lapangan');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($fullName) || empty($nik) || empty($phone) || empty($clusterBlock)) {
        setFlash('error', 'Nama Lengkap, NIK (16 digit), Nomor WhatsApp, dan Blok Rumah wajib diisi.');
    } elseif (!preg_match('/^[0-9]{16}$/', $nik)) {
        setFlash('error', 'Format NIK harus 16 digit angka sesuai KTP.');
    } else {
        // Cek apakah NIK sudah pernah mendaftar
        $chkNik = $pdo->prepare("SELECT id, status, full_name FROM members WHERE nik = ?");
        $chkNik->execute([$nik]);
        $existingNik = $chkNik->fetch();

        if ($existingNik) {
            if ($existingNik['status'] === 'aktif') {
                setFlash('error', "NIK {$nik} sudah terdaftar sebagai Anggota Aktif atas nama '{$existingNik['full_name']}'.");
            } elseif ($existingNik['status'] === 'pending') {
                setFlash('error', "NIK {$nik} sudah dalam antrean pendaftaran (Pending) atas nama '{$existingNik['full_name']}'.");
            } else {
                setFlash('error', "NIK {$nik} tercatat berstatus Resign atas nama '{$existingNik['full_name']}'. Silakan hubungi pengurus untuk pengaktifan kembali.");
            }
        } else {
            // Cek apakah nomor WhatsApp sudah pernah mendaftar
            $chk = $pdo->prepare("SELECT id, status FROM members WHERE phone = ?");
            $chk->execute([$phone]);
            $existing = $chk->fetch();

            if ($existing) {
                if ($existing['status'] === 'aktif') {
                    setFlash('error', 'Nomor WhatsApp ini sudah terdaftar sebagai Anggota Aktif BKRC.');
                } elseif ($existing['status'] === 'pending') {
                    setFlash('error', 'Pendaftaran Anda sedang menunggu persetujuan (Approve) dari Pengurus BKRC.');
                } else {
                    setFlash('error', 'Nomor WhatsApp ini tercatat berstatus Resign. Silakan hubungi Pengurus untuk pengaktifan kembali.');
                }
            } else {
                // Auto-generate nomor antrean registrasi pendaftaran mandiri (Opsi B)
                $prefix = 'REG-' . date('Ym') . '-';
                $stmtLastReg = $pdo->query("SELECT member_code FROM members WHERE member_code LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
                $lastReg = $stmtLastReg->fetchColumn();
                if ($lastReg) {
                    $num = (int)substr($lastReg, strlen($prefix));
                    $regCode = $prefix . str_pad($num + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $regCode = $prefix . '0001';
                }

                // Simpan pendaftaran dengan status 'pending' dan nomor registrasi sementara
                $stmt = $pdo->prepare("
                    INSERT INTO members (member_code, nik, full_name, phone, cluster_block, rt_rw, position, joined_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'pending')
                ");
                $stmt->execute([$regCode, $nik, $fullName, $phone, $clusterBlock, $rtRw, $interest]);
                $newId = $pdo->lastInsertId();

                $success = true;
                $submittedData = [
                    'id' => $newId,
                    'reg_code' => $regCode,
                    'name' => $fullName,
                    'nik' => $nik,
                    'phone' => $phone,
                    'block' => $clusterBlock,
                    'rt_rw' => $rtRw,
                    'interest' => $interest
                ];
            }
        }
    }
}
?>

<!-- Header Banner -->
<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-2">
            <i class="fa-solid fa-users"></i> Pendaftaran Mandiri
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Formulir Calon Anggota Relawan BKRC</h1>
        <p class="text-slate-300 text-xs sm:text-sm mt-2 max-w-xl mx-auto">
            Mari bergandeng tangan bersama warga cluster untuk menyalurkan kepedulian bagi anak yatim dan dhuafa di lingkungan kita.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Flash Error -->
    <?php if ($flash = getFlash('error')): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm p-4 rounded-2xl mb-6 flex items-center gap-2.5">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success && $submittedData): ?>
        <!-- Success State -->
        <?php
        $waMessage = "Halo Pengurus BKRC, saya sudah mengisi formulir pendaftaran anggota relawan di website.\n"
                   . "No. Registrasi: {$submittedData['reg_code']}\n"
                   . "Nama: {$submittedData['name']}\n"
                   . "Alamat: {$submittedData['block']} ({$submittedData['rt_rw']})\n"
                   . "No WA: {$submittedData['phone']}\n"
                   . "Pilihan Bidang: {$submittedData['interest']}\n"
                   . "Mohon ditinjau untuk persetujuan (approval). Terima kasih.";
        $waUrl = buildWaUrl(getTreasurerPhone(), $waMessage);
        ?>
        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-sm text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 mx-auto flex items-center justify-center text-2xl mb-4">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 mb-2">Pendaftaran Berhasil Dikirim!</h2>
            <p class="text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-relaxed mb-6">
                Terima kasih, <strong><?= htmlspecialchars($submittedData['name']) ?></strong>. Data pendaftaran Anda telah tersimpan dengan status <strong>Menunggu Persetujuan (Pending)</strong>. Pengurus BKRC akan segera memverifikasi data Anda.
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left max-w-md mx-auto mb-6 text-xs space-y-2 text-slate-700">
                <div class="flex justify-between border-b border-slate-200/60 pb-1.5">
                    <span class="text-slate-500">No. Registrasi:</span>
                    <strong class="text-amber-800 font-mono font-bold"><?= htmlspecialchars($submittedData['reg_code']) ?></strong>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-1.5">
                    <span class="text-slate-500">Nama:</span>
                    <strong class="text-slate-900"><?= htmlspecialchars($submittedData['name']) ?></strong>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-1.5">
                    <span class="text-slate-500">NIK (KTP):</span>
                    <strong class="text-slate-900 font-mono"><?= htmlspecialchars(maskNik($submittedData['nik'])) ?></strong>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-1.5">
                    <span class="text-slate-500">Blok Rumah:</span>
                    <strong class="text-slate-900"><?= htmlspecialchars($submittedData['block']) ?> (<?= htmlspecialchars($submittedData['rt_rw']) ?>)</strong>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-1.5">
                    <span class="text-slate-500">No. WhatsApp:</span>
                    <strong class="text-slate-900 font-mono"><?= htmlspecialchars($submittedData['phone']) ?></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Pilihan Bidang:</span>
                    <strong class="text-slate-900"><?= htmlspecialchars($submittedData['interest']) ?></strong>
                </div>
            </div>

            <div class="p-3 bg-amber-50 border border-amber-200 rounded-2xl text-[11px] text-amber-800 max-w-md mx-auto mb-6 text-left flex items-start gap-2">
                <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 flex-shrink-0"></i>
                <span>Nomor Registrasi di atas adalah tanda terima antrean verifikasi. Kode Anggota Resmi (<strong>BKRC-M-XXX</strong>) akan otomatis diterbitkan begitu pendaftaran disetujui oleh Pengurus.</span>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto min-h-[48px] bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-xl shadow-xs transition flex items-center justify-center gap-2">
                    <i class="fa-brands fa-whatsapp text-lg"></i>
                    <span>Konfirmasi ke WhatsApp Pengurus</span>
                </a>
                <a href="<?= getBaseUrl() ?>/index.php" class="w-full sm:w-auto min-h-[48px] bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs sm:text-sm px-5 py-3 rounded-xl border border-slate-200 transition flex items-center justify-center">
                    <span>Kembali ke Beranda</span>
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Registration Form Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm">
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-lg font-bold text-slate-900">Isi Data Keanggotaan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Seluruh data hanya digunakan untuk koordinasi kegiatan sosial cluster.</p>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <!-- Nama Lengkap -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Nama Lengkap (Sesuai KTP) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="full_name" required placeholder="Contoh: Bpk. Muhammad Ilham Fauzi" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                </div>

                <!-- NIK & No WhatsApp (2 Kolom) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            NIK (KTP) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nik" required maxlength="16" minlength="16" pattern="[0-9]{16}" inputmode="numeric" placeholder="16 digit NIK KTP" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Nomor WhatsApp Aktif <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" name="phone" required placeholder="Contoh: 081298765432" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <!-- Blok Cluster & RT/RW -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Blok / Nomor Rumah <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="cluster_block" required placeholder="Contoh: Cluster Bougenville Blok C2/04" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            RT / RW <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="rt_rw" required value="RT 02 / RW 08" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                    </div>
                </div>

                <!-- Pilihan Minat / Bidang Relawan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Minat / Bidang Partisipasi
                    </label>
                    <select name="interest" class="w-full px-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:bg-white transition">
                        <option value="Relawan Lapangan">Relawan Lapangan (Distribusi santunan & sembako)</option>
                        <option value="Koordinator Logistik">Logistik & Perlengkapan (Beras, sembako, posko)</option>
                        <option value="Divisi Dokumentasi">Dokumentasi, Foto & Publikasi</option>
                        <option value="Divisi Acara / Humas">Panitia Acara & Humas Warga</option>
                        <option value="Relawan Medis">Bantuan Medis / Donor Darah</option>
                        <option value="Relawan Umum">Relawan Pendukung Umum</option>
                    </select>
                </div>

                <!-- Catatan Privasi & Ketentuan (UU PDP) -->
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-600 flex items-start gap-2.5">
                    <i class="fa-solid fa-shield-halved text-emerald-600 mt-0.5 text-sm flex-shrink-0"></i>
                    <span class="leading-relaxed">
                        Pendaftaran anggota bebas biaya. Sesuai prinsip pelindungan data pribadi (UU PDP), NIK hanya digunakan oleh Pengurus BKRC untuk verifikasi kependudukan warga cluster dan tidak dipublikasikan ke umum. Status awal pendaftaran adalah <strong>Pending</strong> sampai disetujui (Approve) oleh Pengurus.
                    </span>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <a href="<?= getBaseUrl() ?>/index.php" class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition inline-flex items-center">Batal</a>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-xs sm:text-sm px-6 py-2.5 min-h-[44px] rounded-xl shadow-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                        <span>Kirim Pendaftaran</span>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
