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
    // 1. Validasi Honeypot Anti-Bot Spam
    if (!empty($_POST['bkr_web_hp'])) {
        header('Location: ' . getBaseUrl() . '/daftar_anggota.php');
        exit;
    }

    // 2. Proteksi Rate Limiting (Minimal 4 detik antar submit)
    $lastSubmit = $_SESSION['last_member_reg_time'] ?? 0;
    if (time() - $lastSubmit < 4) {
        setFlash('error', 'Mohon tunggu beberapa detik sebelum mengirimkan formulir kembali.');
        header('Location: ' . getBaseUrl() . '/daftar_anggota.php');
        exit;
    }

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
                $stmtLastReg = $pdo->prepare("SELECT member_code FROM members WHERE member_code LIKE ? ORDER BY id DESC LIMIT 1");
                $stmtLastReg->execute([$prefix . '%']);
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

                $_SESSION['last_member_reg_time'] = time();
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
<div class="bg-stone-900 text-stone-100 py-12 border-b border-stone-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-400 mb-2">
            <i class="fa-solid fa-users"></i> Pendaftaran mandiri
        </span>
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-white">Formulir pendaftaran relawan</h1>
        <p class="text-stone-300 text-xs sm:text-sm mt-2 max-w-xl mx-auto">
            Mari bergandeng tangan bersama warga cluster untuk menyalurkan kepedulian bagi anak yatim dan dhuafa di lingkungan kita.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Flash Error -->
    <?php if ($flash = getFlash('error')): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm p-4 rounded-xl mb-6 flex items-center gap-2.5">
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
        <div class="bg-white rounded-xl p-6 sm:p-10 border border-stone-200/90 text-center">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-700 mx-auto flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-semibold text-stone-900 mb-2 tracking-tight">Pendaftaran berhasil dikirim</h2>
            <p class="text-xs sm:text-sm text-stone-600 max-w-md mx-auto leading-relaxed mb-6">
                Terima kasih, <strong><?= htmlspecialchars($submittedData['name']) ?></strong>. Data pendaftaran Anda telah tersimpan dengan status <strong>Menunggu persetujuan</strong>. Pengurus BKRC akan segera memverifikasi data Anda.
            </p>

            <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 text-left max-w-md mx-auto mb-6 text-xs space-y-2 text-stone-700">
                <div class="flex justify-between border-b border-stone-200/60 pb-1.5">
                    <span class="text-stone-500">Nomor registrasi:</span>
                    <strong class="text-stone-900 font-mono font-medium"><?= htmlspecialchars($submittedData['reg_code']) ?></strong>
                </div>
                <div class="flex justify-between border-b border-stone-200/60 pb-1.5">
                    <span class="text-stone-500">Nama:</span>
                    <strong class="text-stone-900 font-medium"><?= htmlspecialchars($submittedData['name']) ?></strong>
                </div>
                <div class="flex justify-between border-b border-stone-200/60 pb-1.5">
                    <span class="text-stone-500">NIK (KTP):</span>
                    <strong class="text-stone-900 font-mono"><?= htmlspecialchars(maskNik($submittedData['nik'])) ?></strong>
                </div>
                <div class="flex justify-between border-b border-stone-200/60 pb-1.5">
                    <span class="text-stone-500">Blok rumah:</span>
                    <strong class="text-stone-900 font-medium"><?= htmlspecialchars($submittedData['block']) ?> (<?= htmlspecialchars($submittedData['rt_rw']) ?>)</strong>
                </div>
                <div class="flex justify-between border-b border-stone-200/60 pb-1.5">
                    <span class="text-stone-500">Nomor WhatsApp:</span>
                    <strong class="text-stone-900 font-mono"><?= htmlspecialchars($submittedData['phone']) ?></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-stone-500">Pilihan bidang:</span>
                    <strong class="text-stone-900 font-medium"><?= htmlspecialchars($submittedData['interest']) ?></strong>
                </div>
            </div>

            <div class="p-3 bg-stone-50 border border-stone-200 rounded-lg text-xs text-stone-600 max-w-md mx-auto mb-6 text-left flex items-start gap-2">
                <i class="fa-solid fa-circle-info text-stone-500 mt-0.5 shrink-0"></i>
                <span>Nomor registrasi di atas adalah tanda terima antrean verifikasi. Kode anggota resmi (<strong>BKRC-M-XXX</strong>) akan otomatis diterbitkan begitu pendaftaran disetujui oleh pengurus.</span>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto min-h-[44px] bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-xs sm:text-sm px-5 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <i class="fa-brands fa-whatsapp text-base"></i>
                    <span>Konfirmasi ke WhatsApp pengurus</span>
                </a>
                <a href="<?= getBaseUrl() ?>/index.php" class="w-full sm:w-auto min-h-[44px] bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium text-xs sm:text-sm px-4 py-2.5 rounded-lg border border-stone-200 transition-colors flex items-center justify-center">
                    <span>Kembali ke beranda</span>
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Registration Form Card -->
        <div class="bg-white rounded-xl p-6 sm:p-8 border border-stone-200/90">
            <div class="border-b border-stone-100 pb-4 mb-6">
                <h2 class="text-lg font-semibold text-stone-900 tracking-tight">Isi data keanggotaan</h2>
                <p class="text-xs text-stone-500 mt-0.5">Seluruh data hanya digunakan untuk koordinasi kegiatan sosial cluster.</p>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div style="display:none !important;" aria-hidden="true">
                    <input type="text" name="bkr_web_hp" value="" tabindex="-1" autocomplete="off">
                </div>

                <!-- Nama Lengkap -->
                <div>
                    <label class="block text-xs font-medium text-stone-700 mb-1">
                        Nama lengkap (sesuai KTP) <span class="text-bkrc-red-800">*</span>
                    </label>
                    <input type="text" name="full_name" required placeholder="Contoh: Muhammad Ilham Fauzi" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                </div>

                <!-- NIK & No WhatsApp (2 Kolom) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-stone-700 mb-1">
                            NIK (KTP 16 digit) <span class="text-bkrc-red-800">*</span>
                        </label>
                        <input type="text" name="nik" required maxlength="16" minlength="16" pattern="[0-9]{16}" inputmode="numeric" placeholder="16 digit NIK" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm font-mono text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-700 mb-1">
                            Nomor WhatsApp aktif <span class="text-bkrc-red-800">*</span>
                        </label>
                        <input type="tel" name="phone" required placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm font-mono text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                </div>

                <!-- Blok Cluster & RT/RW -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-stone-700 mb-1">
                            Blok atau nomor rumah <span class="text-bkrc-red-800">*</span>
                        </label>
                        <input type="text" name="cluster_block" required placeholder="Contoh: Cluster Bougenville Blok C2/04" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-700 mb-1">
                            RT / RW <span class="text-bkrc-red-800">*</span>
                        </label>
                        <input type="text" name="rt_rw" required value="RT 02 / RW 08" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                    </div>
                </div>

                <!-- Pilihan Minat / Bidang Relawan -->
                <div>
                    <label class="block text-xs font-medium text-stone-700 mb-1">
                        Minat atau bidang partisipasi
                    </label>
                    <select name="interest" class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                        <option value="Relawan Lapangan">Relawan lapangan (distribusi santunan & sembako)</option>
                        <option value="Koordinator Logistik">Logistik & perlengkapan (beras, sembako, posko)</option>
                        <option value="Divisi Dokumentasi">Dokumentasi, foto & publikasi</option>
                        <option value="Divisi Acara / Humas">Panitia acara & humas warga</option>
                        <option value="Relawan Medis">Bantuan medis & donor darah</option>
                        <option value="Relawan Umum">Relawan pendukung umum</option>
                    </select>
                </div>

                <!-- Catatan Privasi & Ketentuan (UU PDP) -->
                <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-lg text-xs text-stone-600 flex items-start gap-2.5">
                    <i class="fa-solid fa-shield-halved text-stone-500 mt-0.5 text-sm shrink-0"></i>
                    <span class="leading-relaxed">
                        Pendaftaran anggota bebas biaya. Sesuai prinsip pelindungan data pribadi (UU PDP), NIK hanya digunakan oleh Pengurus BKRC untuk verifikasi kependudukan warga cluster dan tidak dipublikasikan ke umum. Status awal pendaftaran adalah <strong>Pending</strong> sampai disetujui (Approve) oleh Pengurus.
                    </span>
                </div>

                <div class="pt-3 border-t border-stone-100 flex items-center justify-end gap-3">
                    <a href="<?= getBaseUrl() ?>/index.php" class="px-4 py-2 min-h-[44px] rounded-lg text-xs sm:text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors inline-flex items-center">Batal</a>
                    <button type="submit" class="bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-medium text-xs sm:text-sm px-5 py-2.5 min-h-[44px] rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-bkrc-red-800">
                        <span>Kirim pendaftaran</span>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
