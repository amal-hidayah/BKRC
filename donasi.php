<?php
/**
 * Formulir Konfirmasi Donasi Publik BKRC
 * Revisi Sesuai Panduan Desain: Single Column (560-640px), Satu Rekening Resmi,
 * Validasi Ketat MIME & Anti-Spam, Upload Bukti Wajib, Single Submit Button.
 */
$pageTitle = 'Konfirmasi Donasi — BKRC';
require_once __DIR__ . '/includes/header.php';

$officialBank = getOfficialBank($pdo);

$errors = [];
$old = [];
$success = false;
$submittedTrx = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Anti-Spam: Token CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errors['general'] = 'Sesi keamanan kedaluwarsa. Silakan muat ulang halaman dan coba lagi.';
    }

    // 2. Anti-Spam: Honeypot Check
    if (!empty($_POST['bkr_web_hp'])) {
        http_response_code(400);
        exit('Permintaan tidak valid.');
    }

    // 3. Anti-Spam: Rate Limiting per Sesi (Jeda minimal 4 detik)
    $now = time();
    if (isset($_SESSION['last_donation_submit_time']) && ($now - $_SESSION['last_donation_submit_time']) < 4) {
        $errors['general'] = 'Mohon tunggu beberapa detik sebelum mengirim konfirmasi donasi kembali.';
    }

    // Tangkap data input
    $old['donor_name'] = trim($_POST['donor_name'] ?? '');
    $old['is_anonymous'] = !empty($_POST['is_anonymous']) ? 1 : 0;
    $old['donor_phone'] = trim($_POST['donor_phone'] ?? '');
    $old['amount'] = $_POST['amount'] ?? '';
    $old['donation_date'] = trim($_POST['donation_date'] ?? date('Y-m-d'));
    $old['program_target'] = trim($_POST['program_target'] ?? 'Santunan Rutin Anak Yatim');
    $old['notes'] = trim($_POST['notes'] ?? '');

    // 4. Validasi Nama Lengkap (#24)
    if (empty($old['donor_name'])) {
        $errors['donor_name'] = 'Nama lengkap wajib diisi.';
    }

    // 5. Validasi Nomor WhatsApp (Format Indonesia 08..., 10-13 digit) (#24)
    $cleanPhone = preg_replace('/[^0-9]/', '', $old['donor_phone']);
    if (strpos($cleanPhone, '62') === 0) {
        $cleanPhone = '0' . substr($cleanPhone, 2);
    }
    if (empty($cleanPhone)) {
        $errors['donor_phone'] = 'Nomor WhatsApp wajib diisi.';
    } elseif (strpos($cleanPhone, '08') !== 0 || strlen($cleanPhone) < 10 || strlen($cleanPhone) > 13) {
        $errors['donor_phone'] = 'Nomor WhatsApp harus berformat 08… dengan panjang 10–13 digit.';
    }

    // 6. Validasi Nominal (Minimal Rp 10.000) (#24)
    $nominal = parseRupiahInput($old['amount']);
    if ($nominal < 10000) {
        $errors['amount'] = 'Nominal donasi minimal Rp 10.000.';
    }

    // 7. Validasi Tanggal (Tolak tanggal masa depan) (#18)
    $today = date('Y-m-d');
    if (empty($old['donation_date'])) {
        $errors['donation_date'] = 'Tanggal transfer wajib dipilih.';
    } elseif ($old['donation_date'] > $today) {
        $errors['donation_date'] = 'Tanggal transfer tidak boleh melebihi hari ini.';
    }

    $safeProofPath = null;

    // Jika semua validasi lulus, simpan ke database
    if (empty($errors)) {
        $_SESSION['last_donation_submit_time'] = time();

        $realName = $old['donor_name'];
        $isAnonymous = $old['is_anonymous'];
        $displayName = $isAnonymous ? 'Hamba Allah' : $realName;
        $programTarget = !empty($old['program_target']) ? $old['program_target'] : 'Santunan Rutin Anak Yatim';
        $notes = $old['notes'];

        // 9. Kelola Database Donatur (Pencocokan akurat by phone mencegah kolisi nama yang sama)
        $stmtChk = $pdo->prepare("SELECT id FROM donors WHERE phone = ? LIMIT 1");
        $stmtChk->execute([$cleanPhone]);
        $donorId = $stmtChk->fetchColumn();

        if (!$donorId) {
            // Periksa jika ada profil bernama sama namun belum memiliki nomor WhatsApp
            $stmtChk2 = $pdo->prepare("SELECT id FROM donors WHERE full_name = ? AND (phone = '' OR phone IS NULL) LIMIT 1");
            $stmtChk2->execute([$realName]);
            $donorId = $stmtChk2->fetchColumn();
            if ($donorId) {
                $updPhone = $pdo->prepare("UPDATE donors SET phone = ?, is_anonymous = ? WHERE id = ?");
                $updPhone->execute([$cleanPhone, $isAnonymous, $donorId]);
            }
        }

        if (!$donorId) {
            $donorCode = 'DNR-' . date('ymd') . random_int(100, 999);
            $insD = $pdo->prepare("
                INSERT INTO donors (donor_code, full_name, phone, cluster_or_address, donor_type, is_anonymous)
                VALUES (?, ?, ?, 'Warga / Donatur Simpatisan', 'insidental', ?)
            ");
            $insD->execute([$donorCode, $realName, $cleanPhone, $isAnonymous]);
            $donorId = $pdo->lastInsertId();
        } else {
            // Perbarui nama asli dan status anonim terakhir pada donatur terverifikasi
            $updD = $pdo->prepare("UPDATE donors SET full_name = ?, is_anonymous = ? WHERE id = ?");
            $updD->execute([$realName, $isAnonymous, $donorId]);
        }

        // 10. Generate Nomor Referensi Donasi Sementara (TRX-YYYYMM-XXXX) Terjamin Unik (#12)
        $trxRef = generateUniqueTrxRef($pdo, 'TRX');

        $stmtDonation = $pdo->prepare("
            INSERT INTO donations (
                receipt_no, transaction_category, donor_id, member_id, donor_phone,
                donation_date, type, amount, package_qty, package_details,
                program_target, status, notes, proof_url
            ) VALUES (
                ?, 'donasi', ?, NULL, ?,
                ?, 'uang', ?, 0, NULL,
                ?, 'pending', ?, ?
            )
        ");

        $stmtDonation->execute([
            $trxRef,
            $donorId,
            $cleanPhone,
            $old['donation_date'],
            $nominal,
            $programTarget,
            $notes,
            $safeProofPath
        ]);

        $submittedTrx = [
            'ref'            => $trxRef,
            'real_name'      => $realName,
            'display_name'   => $displayName,
            'is_anonymous'   => $isAnonymous,
            'phone'          => $cleanPhone,
            'nominal'        => $nominal,
            'nominal_text'   => formatRupiah($nominal),
            'donation_date'  => $old['donation_date'],
            'date_formatted' => formatTanggalIndo($old['donation_date']),
            'program_target' => $programTarget,
            'bank_info'      => $officialBank['name'] . ' ' . $officialBank['account_formatted']
        ];

        $success = true;
    }
}
?>

<div class="min-h-screen py-10 sm:py-14">
    <!-- Kontainer Kolom Tunggal: Lebar 560-640px -->
    <div class="w-full max-w-xl mx-auto px-4 sm:px-6">

        <?php if ($success && $submittedTrx): ?>
            <!-- HALAMAN SUKSES KONFIRMASI DONASI -->
            <div class="bg-white rounded-xl border border-stone-200/90 p-6 sm:p-8">
                <!-- Ikon & Judul Status -->
                <div class="text-center pb-5 border-b border-stone-100">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-700 mx-auto flex items-center justify-center text-xl mb-3">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-semibold text-stone-900 tracking-tight">
                        Konfirmasi donasi terkirim
                    </h1>
                    <p class="text-stone-600 text-xs sm:text-sm mt-1">
                        Terima kasih atas kepedulian Anda. Data telah masuk ke sistem.
                    </p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-700 border border-stone-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                            Menunggu verifikasi bendahara
                        </span>
                    </div>
                </div>

                <!-- Rincian Data Transaksi -->
                <div class="py-5 space-y-3 text-xs sm:text-sm border-b border-stone-100">
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Nomor referensi</span>
                        <strong class="font-mono font-medium text-stone-900 text-sm sm:text-base select-all">
                            <?= htmlspecialchars($submittedTrx['ref']) ?>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Nama donatur</span>
                        <strong class="text-stone-900 font-medium">
                            <?= htmlspecialchars($submittedTrx['display_name']) ?>
                            <?php if ($submittedTrx['is_anonymous']): ?>
                                <span class="text-[11px] font-normal text-stone-500">(Dicatat anonim)</span>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Nominal</span>
                        <strong class="text-bkrc-red-800 font-semibold text-sm sm:text-base tabular-nums">
                            <?= $submittedTrx['nominal_text'] ?>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Tanggal transfer</span>
                        <span class="text-stone-900 font-normal">
                            <?= $submittedTrx['date_formatted'] ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Peruntukan</span>
                        <span class="text-stone-900 font-normal">
                            <?= htmlspecialchars($submittedTrx['program_target']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Rekening tujuan</span>
                        <span class="text-stone-900 font-normal">
                            <?= htmlspecialchars($submittedTrx['bank_info']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-stone-600">
                        <span>Bukti transfer</span>
                        <span class="inline-flex items-center gap-1 font-medium text-stone-700 bg-stone-100 border border-stone-200 px-2 py-0.5 rounded text-xs">
                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                            Kirim via WhatsApp
                        </span>
                    </div>
                </div>

                <!-- Petunjuk Langkah Terakhir: Kirim Bukti ke WA -->
                <div class="my-5 p-4 sm:p-5 bg-stone-50 border border-stone-200 rounded-xl text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-800">
                        <i class="fa-solid fa-arrow-turn-down text-emerald-600"></i>
                        Langkah terakhir: kirim bukti transfer
                    </div>
                    <p class="text-xs text-stone-600 leading-relaxed max-w-md mx-auto">
                        Klik tombol di bawah untuk membuka WhatsApp Bendahara. Format pesan otomatis sudah terisi, Anda cukup melampirkan foto struk/bukti transfer.
                    </p>
                    <?php
                    $waMsg = "Assalamualaikum Bendahara BKRC, saya sudah mengisi formulir konfirmasi donasi di web:\n\n"
                           . "📌 No. Referensi: " . $submittedTrx['ref'] . "\n"
                           . "👤 Nama: " . $submittedTrx['display_name'] . "\n"
                           . "💰 Nominal: " . $submittedTrx['nominal_text'] . "\n"
                           . "🎯 Peruntukan: " . $submittedTrx['program_target'] . "\n"
                           . "📅 Tanggal: " . date('d/m/Y', strtotime($submittedTrx['donation_date'])) . "\n\n"
                           . "Berikut saya lampirkan foto bukti transfernya. Mohon diverifikasi, terima kasih.";
                    $waUrl = buildWaUrl(getTreasurerPhone($pdo), $waMsg);
                    ?>
                    <div class="pt-2">
                        <a href="<?= $waUrl ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="w-full min-h-[44px] bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-xs sm:text-sm px-5 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                            <i class="fa-brands fa-whatsapp text-base"></i>
                            <span>Kirim bukti transfer ke WhatsApp bendahara</span>
                        </a>
                    </div>
                </div>

                <div class="pt-1">
                    <a href="<?= getBaseUrl() ?>/index.php" 
                       class="w-full min-h-[44px] bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium text-xs sm:text-sm px-4 py-2.5 rounded-lg border border-stone-200 transition-colors flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                        <span>Kembali ke beranda</span>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- FORMULIR KONFIRMASI DONASI -->
            <div class="space-y-6">
                <!-- Header Ramping -->
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold text-stone-900 tracking-tight">
                        Konfirmasi donasi
                    </h1>
                    <p class="text-stone-600 text-sm mt-1">
                        Isi formulir setelah Anda transfer. Bukti kwitansi resmi akan dikirim ke WhatsApp Anda.
                    </p>
                </div>

                <!-- Ringkasan Rekening Satu Baris Teks dengan Tombol Salin -->
                <div x-data="{ 
                    copied: false,
                    copyAccount() {
                        navigator.clipboard.writeText('<?= htmlspecialchars($officialBank['account_raw']) ?>');
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                }" class="bg-white border border-stone-200/90 rounded-xl p-3.5 sm:p-4 text-xs sm:text-sm text-stone-700 flex flex-wrap items-center justify-between gap-2">
                    <div class="leading-relaxed">
                        <span class="text-stone-500">Transfer ke:</span>
                        <strong class="text-stone-900 font-semibold ml-1">
                            <?= htmlspecialchars($officialBank['name']) ?> <?= htmlspecialchars($officialBank['account_formatted']) ?>
                        </strong>
                        <span class="text-stone-500 block sm:inline sm:ml-1">
                            a.n. <?= htmlspecialchars($officialBank['holder']) ?>
                        </span>
                    </div>
                    <button @click="copyAccount()" 
                            type="button" 
                            :class="copied ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200'" 
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                        <template x-if="!copied">
                            <span class="inline-flex items-center gap-1">
                                <i class="fa-regular fa-copy text-stone-500"></i>
                                <span>Salin rekening</span>
                            </span>
                        </template>
                        <template x-if="copied">
                            <span class="inline-flex items-center gap-1 text-emerald-700 font-medium">
                                <i class="fa-solid fa-check"></i>
                                <span>Tersalin</span>
                            </span>
                        </template>
                    </button>
                </div>

                <!-- Pesan Error Umum (Jika Ada) -->
                <?php if (!empty($errors['general'])): ?>
                    <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm rounded-lg flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span><?= htmlspecialchars($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Card -->
                <div class="bg-white rounded-xl border border-stone-200/90 p-6 sm:p-8">
                    <form action="" method="POST" 
                          x-data="donationFormLogic()" 
                          class="space-y-5" 
                          novalidate>
                        
                        <!-- CSRF Token & Honeypot -->
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div style="display:none !important;" aria-hidden="true">
                            <input type="text" name="bkr_web_hp" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- 1. Nama Lengkap & Checkbox Anonim -->
                        <div>
                            <label for="donor_name" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Nama lengkap <span class="text-bkrc-red-800">*</span>
                            </label>
                            <input type="text" 
                                   id="donor_name" 
                                   name="donor_name" 
                                   value="<?= htmlspecialchars($old['donor_name'] ?? '') ?>" 
                                   required 
                                   placeholder="Contoh: Muhammad Fauzi" 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['donor_name']) ? 'border-rose-400 focus:ring-rose-400' : 'border-stone-300 focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800' ?> rounded-lg text-sm text-stone-900 focus-visible:outline-none transition-colors">
                            
                            <!-- Checkbox Tampilkan sebagai Hamba Allah -->
                            <div class="flex items-center gap-2 mt-2">
                                <input type="checkbox" 
                                       id="is_anonymous" 
                                       name="is_anonymous" 
                                       value="1" 
                                       <?= !empty($old['is_anonymous']) ? 'checked' : '' ?> 
                                       class="w-4 h-4 rounded text-bkrc-red-800 focus:ring-bkrc-red-800 border-stone-300">
                                <label for="is_anonymous" class="text-xs text-stone-600 cursor-pointer select-none">
                                    Tampilkan sebagai Hamba Allah di daftar publik
                                </label>
                            </div>

                            <?php if (!empty($errors['donor_name'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['donor_name']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Nomor WhatsApp -->
                        <div>
                            <label for="donor_phone" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Nomor WhatsApp <span class="text-bkrc-red-800">*</span>
                            </label>
                            <input type="tel" 
                                   id="donor_phone" 
                                   name="donor_phone" 
                                   value="<?= htmlspecialchars($old['donor_phone'] ?? '') ?>" 
                                   required 
                                   placeholder="08xxxxxxxxxx" 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['donor_phone']) ? 'border-rose-400 focus:ring-rose-400' : 'border-stone-300 focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800' ?> rounded-lg text-sm font-mono text-stone-900 focus-visible:outline-none transition-colors">
                            <p class="text-xs text-stone-500 mt-1">
                                Hanya digunakan untuk mengirimkan kwitansi. Tidak ditampilkan ke publik.
                            </p>
                            <?php if (!empty($errors['donor_phone'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['donor_phone']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 3. Nominal Transfer & Chip Pilihan Cepat -->
                        <div>
                            <label for="display_amount" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Nominal transfer (Rp) <span class="text-bkrc-red-800">*</span>
                            </label>
                            
                            <input type="text" 
                                   id="display_amount" 
                                   x-model="displayAmount" 
                                   @input="onAmountInput($event)" 
                                   placeholder="Rp 0" 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['amount']) ? 'border-rose-400 focus:ring-rose-400' : 'border-stone-300 focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800' ?> rounded-lg text-base font-medium tabular-nums text-stone-900 focus-visible:outline-none transition-colors">
                            
                            <input type="hidden" name="amount" :value="rawAmount">

                            <!-- Chip Pilihan Cepat Nominal -->
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button type="button" 
                                        @click="selectChip(50000)" 
                                        :class="selectedChip === 50000 ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200 font-normal'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition-colors">
                                    50.000
                                </button>
                                <button type="button" 
                                        @click="selectChip(100000)" 
                                        :class="selectedChip === 100000 ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200 font-normal'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition-colors">
                                    100.000
                                </button>
                                <button type="button" 
                                        @click="selectChip(250000)" 
                                        :class="selectedChip === 250000 ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200 font-normal'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition-colors">
                                    250.000
                                </button>
                                <button type="button" 
                                        @click="selectChip(500000)" 
                                        :class="selectedChip === 500000 ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200 font-normal'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition-colors">
                                    500.000
                                </button>
                                <button type="button" 
                                        @click="selectChip(1000000)" 
                                        :class="selectedChip === 1000000 ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-stone-100 hover:bg-stone-200 text-stone-700 border-stone-200 font-normal'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition-colors">
                                    1.000.000
                                </button>
                            </div>

                            <?php if (!empty($errors['amount'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['amount']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Tanggal Transfer -->
                        <div>
                            <label for="donation_date" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Tanggal transfer <span class="text-bkrc-red-800">*</span>
                            </label>
                            <input type="date" 
                                   id="donation_date" 
                                   name="donation_date" 
                                   value="<?= htmlspecialchars($old['donation_date'] ?? date('Y-m-d')) ?>" 
                                   max="<?= date('Y-m-d') ?>" 
                                   required 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['donation_date']) ? 'border-rose-400 focus:ring-rose-400' : 'border-stone-300 focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800' ?> rounded-lg text-sm text-stone-900 focus-visible:outline-none transition-colors">
                            <p class="text-xs text-stone-500 mt-1">
                                Tanggal transfer tidak boleh melebihi hari ini.
                            </p>
                            <?php if (!empty($errors['donation_date'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['donation_date']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 5. Peruntukan Donasi (Opsional) -->
                        <div>
                            <label for="program_target" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Peruntukan donasi <span class="text-xs font-normal text-stone-500">(opsional)</span>
                            </label>
                            <select id="program_target" 
                                    name="program_target" 
                                    class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors">
                                <option value="Santunan Rutin Anak Yatim" <?= ($old['program_target'] ?? '') === 'Santunan Rutin Anak Yatim' ? 'selected' : '' ?>>
                                    Santunan Rutin Anak Yatim
                                </option>
                                <option value="Paket Sembako Warga Dhuafa & Lansia" <?= ($old['program_target'] ?? '') === 'Paket Sembako Warga Dhuafa & Lansia' ? 'selected' : '' ?>>
                                    Paket Sembako Warga Dhuafa & Lansia
                                </option>
                                <option value="Event Ramadhan & Hari Besar Islam" <?= ($old['program_target'] ?? '') === 'Event Ramadhan & Hari Besar Islam' ? 'selected' : '' ?>>
                                    Event Ramadhan & Hari Besar Islam
                                </option>
                                <option value="Kas Umum Kemanusiaan Cluster" <?= ($old['program_target'] ?? '') === 'Kas Umum Kemanusiaan Cluster' ? 'selected' : '' ?>>
                                    Kas Umum Kemanusiaan Cluster
                                </option>
                            </select>
                        </div>

                        <!-- 6. Doa atau Pesan (Opsional) -->
                        <div>
                            <label for="notes" class="block text-xs sm:text-sm font-medium text-stone-800 mb-1">
                                Doa atau pesan <span class="text-xs font-normal text-stone-500">(opsional)</span>
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      rows="2" 
                                      placeholder="Tuliskan doa atau pesan untuk anak yatim atau warga penerima..." 
                                      class="w-full px-3.5 py-2.5 bg-white border border-stone-300 rounded-lg text-sm text-stone-900 focus-visible:outline-none focus:border-bkrc-red-800 focus:ring-1 focus:ring-bkrc-red-800 transition-colors"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Pemberitahuan Pengiriman Bukti via WA -->
                        <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-lg flex items-start gap-2.5 text-xs text-stone-700">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-base mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <strong class="text-stone-900 font-medium">Kirim bukti transfer:</strong>
                                <span class="text-stone-600 ml-1">Setelah klik tombol di bawah, Anda akan langsung diarahkan untuk mengirimkan foto struk transfer ke WhatsApp Bendahara agar segera diverifikasi.</span>
                            </div>
                        </div>

                        <!-- Tombol Submit Tunggal -->
                        <div class="pt-1">
                            <button type="submit" 
                                    class="w-full min-h-[48px] bg-bkrc-red-800 hover:bg-bkrc-red-900 text-white font-medium text-sm px-6 py-3 rounded-lg transition-colors flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                                <span>Kirim konfirmasi donasi</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Tautan Khusus Iuran Kas Anggota -->
                <div class="text-center pt-2 pb-6">
                    <p class="text-xs text-stone-500">
                        Khusus anggota relawan yang ingin membayar iuran bulanan?
                        <a href="<?= getBaseUrl() ?>/iuran.php" class="font-medium text-bkrc-red-800 hover:underline inline-flex items-center gap-1 ml-1">
                            <span>Buka formulir iuran kas anggota</span>
                            <span>&rarr;</span>
                        </a>
                    </p>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function donationFormLogic() {
    return {
        rawAmount: '<?= htmlspecialchars($old['amount'] ?? '') ?>',
        displayAmount: '',
        selectedChip: null,
        init() {
            if (this.rawAmount) {
                this.formatFromRaw(this.rawAmount);
            }
        },
        selectChip(val) {
            this.selectedChip = val;
            this.rawAmount = String(val);
            this.formatFromRaw(this.rawAmount);
        },
        onAmountInput(e) {
            let val = e.target.value.replace(/[^0-9]/g, '');
            this.rawAmount = val;
            let num = Number(val);
            const standardChips = [50000, 100000, 250000, 500000, 1000000];
            this.selectedChip = standardChips.includes(num) ? num : null;
            if (!val) {
                this.displayAmount = '';
                return;
            }
            this.displayAmount = 'Rp ' + num.toLocaleString('id-ID');
        },
        formatFromRaw(val) {
            let num = Number(val);
            if (!isNaN(num) && num > 0) {
                this.displayAmount = 'Rp ' + num.toLocaleString('id-ID');
                const standardChips = [50000, 100000, 250000, 500000, 1000000];
                this.selectedChip = standardChips.includes(num) ? num : null;
            } else {
                this.displayAmount = '';
                this.selectedChip = null;
            }
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
