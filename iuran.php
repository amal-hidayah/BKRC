<?php
/**
 * Formulir Khusus Iuran Kas Bulanan Anggota Relawan BKRC
 * Memisahkan alur iuran anggota dari donasi publik sesuai Poin Revisi #13.
 */
$pageTitle = 'Konfirmasi Iuran Kas Anggota — BKRC';
require_once __DIR__ . '/includes/header.php';

$officialBank = getOfficialBank($pdo);

// Ambil daftar Anggota Aktif untuk pilihan dropdown
$stmtMembers = $pdo->query("SELECT id, member_code, full_name, cluster_block, phone FROM members WHERE status = 'aktif' ORDER BY full_name ASC");
$activeMembers = $stmtMembers->fetchAll();

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

    // 3. Anti-Spam: Rate Limiting
    $now = time();
    if (isset($_SESSION['last_iuran_submit_time']) && ($now - $_SESSION['last_iuran_submit_time']) < 4) {
        $errors['general'] = 'Mohon tunggu beberapa detik sebelum mengirim konfirmasi iuran kembali.';
    }

    $old['member_id'] = (int)($_POST['member_id'] ?? 0);
    $old['donor_phone'] = trim($_POST['donor_phone'] ?? '');
    $old['amount'] = $_POST['amount'] ?? '';
    $old['donation_date'] = trim($_POST['donation_date'] ?? date('Y-m-d'));
    $old['notes'] = trim($_POST['notes'] ?? '');

    // 4. Validasi Pilihan Anggota
    $selectedMember = null;
    if ($old['member_id'] <= 0) {
        $errors['member_id'] = 'Harap pilih nama anggota relawan Anda.';
    } else {
        $stMem = $pdo->prepare("SELECT id, member_code, full_name, cluster_block, phone, status FROM members WHERE id = ?");
        $stMem->execute([$old['member_id']]);
        $selectedMember = $stMem->fetch();
        if (!$selectedMember || $selectedMember['status'] !== 'aktif') {
            $errors['member_id'] = 'Anggota tidak ditemukan atau berstatus Resign. Hanya anggota aktif yang dapat menyetorkan iuran.';
        }
    }

    // 5. Validasi WhatsApp
    $cleanPhone = preg_replace('/[^0-9]/', '', $old['donor_phone']);
    if (strpos($cleanPhone, '62') === 0) {
        $cleanPhone = '0' . substr($cleanPhone, 2);
    }
    if (empty($cleanPhone)) {
        $errors['donor_phone'] = 'Nomor WhatsApp wajib diisi.';
    } elseif (strpos($cleanPhone, '08') !== 0 || strlen($cleanPhone) < 10 || strlen($cleanPhone) > 13) {
        $errors['donor_phone'] = 'Nomor WhatsApp harus berformat 08… (10–13 digit).';
    }

    // 6. Validasi Nominal (Minimal Rp 10.000)
    $nominal = parseRupiahInput($old['amount']);
    if ($nominal < 10000) {
        $errors['amount'] = 'Nominal iuran minimal Rp 10.000.';
    }

    // 7. Validasi Tanggal (Tolak tanggal masa depan)
    $today = date('Y-m-d');
    if (empty($old['donation_date'])) {
        $errors['donation_date'] = 'Tanggal transfer wajib dipilih.';
    } elseif ($old['donation_date'] > $today) {
        $errors['donation_date'] = 'Tanggal transfer tidak boleh melebihi hari ini.';
    }

    $safeProofPath = null;

    // Jika semua validasi lulus
    if (empty($errors) && $selectedMember) {
        $_SESSION['last_iuran_submit_time'] = time();

        $memberName = $selectedMember['full_name'];
        $memberCode = $selectedMember['member_code'];
        $programTarget = 'Iuran Kas Bulanan Anggota';
        $notes = $old['notes'];

        // Cari atau hubungkan profil donor
        $stmtChk = $pdo->prepare("SELECT id FROM donors WHERE full_name = ? LIMIT 1");
        $stmtChk->execute([$memberName]);
        $donorId = $stmtChk->fetchColumn();

        if (!$donorId) {
            $donorCode = 'DNR-' . date('ymd') . rand(10, 99);
            $insD = $pdo->prepare("
                INSERT INTO donors (donor_code, full_name, phone, cluster_or_address, donor_type, is_anonymous)
                VALUES (?, ?, ?, ?, 'rutin', 0)
            ");
            $insD->execute([$donorCode, $memberName, $cleanPhone, $selectedMember['cluster_block']]);
            $donorId = $pdo->lastInsertId();
        }

        // Nomor Referensi Transaksi
        $trxRef = 'TRX-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmtIns = $pdo->prepare("
            INSERT INTO donations (
                receipt_no, transaction_category, donor_id, member_id, donor_phone,
                donation_date, type, amount, package_qty, package_details,
                program_target, status, notes, proof_url
            ) VALUES (
                ?, 'iuran', ?, ?, ?,
                ?, 'uang', ?, 0, NULL,
                ?, 'pending', ?, ?
            )
        ");

        $stmtIns->execute([
            $trxRef,
            $donorId,
            $selectedMember['id'],
            $cleanPhone,
            $old['donation_date'],
            $nominal,
            $programTarget,
            $notes,
            $safeProofPath
        ]);

        $submittedTrx = [
            'ref'            => $trxRef,
            'member_name'    => $memberName,
            'member_code'    => $memberCode,
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

<div class="min-h-screen bg-stone-50/60 py-10 sm:py-14">
    <div class="w-full max-w-xl mx-auto px-4 sm:px-6">

        <?php if ($success && $submittedTrx): ?>
            <!-- HALAMAN SUKSES IURAN -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <div class="text-center pb-5 border-b border-slate-100">
                    <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center text-2xl mb-3">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900">
                        Konfirmasi Iuran Kas Berhasil
                    </h1>
                    <p class="text-slate-600 text-xs sm:text-sm mt-1">
                        Terima kasih atas kontribusi rutin Anda untuk kelangsungan program BKRC.
                    </p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            Menunggu Verifikasi Bendahara
                        </span>
                    </div>
                </div>

                <div class="py-5 space-y-3 text-xs sm:text-sm border-b border-slate-100">
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Nomor Referensi</span>
                        <strong class="font-mono font-bold text-slate-900 text-sm sm:text-base select-all">
                            <?= htmlspecialchars($submittedTrx['ref']) ?>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Nama Anggota</span>
                        <strong class="text-slate-900 font-semibold">
                            <?= htmlspecialchars($submittedTrx['member_name']) ?>
                            <span class="text-xs text-slate-500 font-normal">(<?= htmlspecialchars($submittedTrx['member_code']) ?>)</span>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Nominal Iuran</span>
                        <strong class="text-emerald-700 font-extrabold text-sm sm:text-base tabular-nums">
                            <?= $submittedTrx['nominal_text'] ?>
                        </strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Tanggal Transfer</span>
                        <span class="text-slate-900 font-medium">
                            <?= $submittedTrx['date_formatted'] ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Rekening Tujuan</span>
                        <span class="text-slate-900 font-medium">
                            <?= htmlspecialchars($submittedTrx['bank_info']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Bukti Transfer</span>
                        <span class="inline-flex items-center gap-1 font-semibold text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-md text-xs">
                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                            Kirim via WhatsApp
                        </span>
                    </div>
                </div>

                <!-- Petunjuk Langkah Terakhir: Kirim Bukti ke WA -->
                <div class="my-5 p-4 sm:p-5 bg-emerald-50/70 border border-emerald-200 rounded-2xl text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-900 uppercase tracking-wider">
                        <i class="fa-solid fa-arrow-turn-down text-emerald-600"></i>
                        Langkah Terakhir: Kirim Bukti Iuran
                    </div>
                    <p class="text-xs text-emerald-800 leading-relaxed max-w-md mx-auto">
                        Silakan klik tombol hijau di bawah untuk membuka WhatsApp Bendahara. Format pesan otomatis sudah terisi, Anda cukup melampirkan foto bukti transfer.
                    </p>
                    <?php
                    $waMsg = "Assalamualaikum Bendahara BKRC, saya sudah mengisi formulir konfirmasi Iuran Kas Anggota di web:\n\n"
                           . "📌 No. Referensi: " . $submittedTrx['ref'] . "\n"
                           . "👤 Anggota: " . $submittedTrx['member_name'] . " (" . $submittedTrx['member_code'] . ")\n"
                           . "💰 Nominal: " . $submittedTrx['nominal_text'] . "\n"
                           . "📅 Tanggal: " . date('d/m/Y', strtotime($submittedTrx['donation_date'])) . "\n\n"
                           . "Berikut saya lampirkan foto bukti transfernya. Mohon diverifikasi, terima kasih.";
                    $waUrl = buildWaUrl(getTreasurerPhone($pdo), $waMsg);
                    ?>
                    <div class="pt-2">
                        <a href="<?= $waUrl ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="w-full min-h-[48px] bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                            <i class="fa-brands fa-whatsapp text-lg"></i>
                            <span>Kirim Bukti Iuran ke WhatsApp Bendahara</span>
                        </a>
                    </div>
                </div>

                <div class="pt-1">
                    <a href="<?= getBaseUrl() ?>/index.php" 
                       class="w-full min-h-[44px] bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl border border-slate-200 transition flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        <span>Kembali ke Beranda</span>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- FORMULIR IURAN KAS -->
            <div class="space-y-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Iuran Kas Anggota Relawan
                    </h1>
                    <p class="text-slate-600 text-sm mt-1">
                        Form khusus anggota relawan resmi BKRC. Kwitansi dikirim ke WhatsApp.
                    </p>
                </div>

                <!-- Ringkasan Rekening Resmi -->
                <div x-data="{ 
                    copied: false,
                    copyAccount() {
                        navigator.clipboard.writeText('<?= htmlspecialchars($officialBank['account_raw']) ?>');
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                }" class="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 text-xs sm:text-sm text-slate-700 flex flex-wrap items-center justify-between gap-2 shadow-xs">
                    <div class="leading-relaxed">
                        <span>Transfer ke:</span>
                        <strong class="text-slate-900 font-semibold ml-1">
                            <?= htmlspecialchars($officialBank['name']) ?> <?= htmlspecialchars($officialBank['account_formatted']) ?>
                        </strong>
                        <span class="text-slate-500 block sm:inline sm:ml-1">
                            a.n. <?= htmlspecialchars($officialBank['holder']) ?>
                        </span>
                    </div>
                    <button @click="copyAccount()" 
                            type="button" 
                            :class="copied ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300'" 
                            class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        <template x-if="!copied">
                            <span class="inline-flex items-center gap-1">
                                <i class="fa-regular fa-copy"></i>
                                <span>Salin</span>
                            </span>
                        </template>
                        <template x-if="copied">
                            <span class="inline-flex items-center gap-1 text-emerald-700 font-bold">
                                <i class="fa-solid fa-check"></i>
                                <span>Tersalin ✓</span>
                            </span>
                        </template>
                    </button>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm rounded-xl flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span><?= htmlspecialchars($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                    <form action="" method="POST" enctype="multipart/form-data" 
                          x-data="iuranFormLogic()" 
                          class="space-y-5" 
                          novalidate>
                        
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div style="display:none !important;" aria-hidden="true">
                            <input type="text" name="bkr_web_hp" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- 1. Pilih Nama Anggota Aktif -->
                        <div>
                            <label for="member_id" class="block text-xs sm:text-sm font-semibold text-slate-800 mb-1">
                                Pilih nama anggota aktif <span class="text-bkrc-red-800 font-bold">*</span>
                            </label>
                            <select id="member_id" 
                                    name="member_id" 
                                    @change="onMemberSelect($event)" 
                                    required 
                                    class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['member_id']) ? 'border-rose-400' : 'border-slate-300' ?> rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 transition">
                                <option value="">-- Pilih dari Daftar Anggota Resmi --</option>
                                <?php foreach ($activeMembers as $mem): ?>
                                    <option value="<?= $mem['id'] ?>" 
                                            data-phone="<?= htmlspecialchars($mem['phone']) ?>" 
                                            <?= ($old['member_id'] ?? 0) == $mem['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mem['full_name']) ?> (<?= htmlspecialchars($mem['member_code']) ?> · <?= htmlspecialchars($mem['cluster_block']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-slate-500 mt-1">
                                Belum terdaftar sebagai relawan?
                                <a href="<?= getBaseUrl() ?>/daftar_anggota.php" class="font-semibold text-bkrc-red-800 hover:underline ml-0.5">Daftar anggota di sini &rarr;</a>
                            </p>
                            <?php if (!empty($errors['member_id'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['member_id']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Nomor WhatsApp -->
                        <div>
                            <label for="donor_phone" class="block text-xs sm:text-sm font-semibold text-slate-800 mb-1">
                                Nomor WhatsApp <span class="text-bkrc-red-800 font-bold">*</span>
                            </label>
                            <input type="tel" 
                                   id="donor_phone" 
                                   name="donor_phone" 
                                   x-model="memberPhone" 
                                   required 
                                   placeholder="08xxxxxxxxxx" 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['donor_phone']) ? 'border-rose-400' : 'border-slate-300' ?> rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 transition">
                            <p class="text-xs text-slate-500 mt-1">
                                Hanya untuk kwitansi, tidak ditampilkan di publik.
                            </p>
                            <?php if (!empty($errors['donor_phone'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['donor_phone']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 3. Nominal Iuran & Chip -->
                        <div>
                            <label for="display_amount" class="block text-xs sm:text-sm font-semibold text-slate-800 mb-1">
                                Nominal iuran (Rp) <span class="text-bkrc-red-800 font-bold">*</span>
                            </label>
                            <input type="text" 
                                   id="display_amount" 
                                   x-model="displayAmount" 
                                   @input="onAmountInput($event)" 
                                   placeholder="Rp 50.000" 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['amount']) ? 'border-rose-400' : 'border-slate-300' ?> rounded-xl text-base font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 transition">
                            
                            <input type="hidden" name="amount" :value="rawAmount">

                            <div class="flex flex-wrap gap-2 mt-2">
                                <button type="button" 
                                        @click="selectChip(25000)" 
                                        :class="selectedChip === 25000 ? 'bg-rose-50 border-bkrc-red-800 text-bkrc-red-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 font-medium'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition">
                                    25 Rb
                                </button>
                                <button type="button" 
                                        @click="selectChip(50000)" 
                                        :class="selectedChip === 50000 ? 'bg-rose-50 border-bkrc-red-800 text-bkrc-red-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 font-medium'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition">
                                    50 Rb (Standar)
                                </button>
                                <button type="button" 
                                        @click="selectChip(100000)" 
                                        :class="selectedChip === 100000 ? 'bg-rose-50 border-bkrc-red-800 text-bkrc-red-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 font-medium'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition">
                                    100 Rb
                                </button>
                                <button type="button" 
                                        @click="selectChip(200000)" 
                                        :class="selectedChip === 200000 ? 'bg-rose-50 border-bkrc-red-800 text-bkrc-red-900 font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 font-medium'" 
                                        class="px-3 py-1.5 rounded-lg text-xs border transition">
                                    200 Rb
                                </button>
                            </div>

                            <?php if (!empty($errors['amount'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['amount']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Tanggal Transfer -->
                        <div>
                            <label for="donation_date" class="block text-xs sm:text-sm font-semibold text-slate-800 mb-1">
                                Tanggal transfer <span class="text-bkrc-red-800 font-bold">*</span>
                            </label>
                            <input type="date" 
                                   id="donation_date" 
                                   name="donation_date" 
                                   value="<?= htmlspecialchars($old['donation_date'] ?? date('Y-m-d')) ?>" 
                                   max="<?= date('Y-m-d') ?>" 
                                   required 
                                   class="w-full px-3.5 py-2.5 min-h-[44px] bg-white border <?= !empty($errors['donation_date']) ? 'border-rose-400' : 'border-slate-300' ?> rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 transition">
                            <p class="text-xs text-slate-500 mt-1">
                                Hari ini: <?= formatTanggalIndo(date('Y-m-d')) ?>. Tanggal transfer tidak boleh di masa depan.
                            </p>
                            <?php if (!empty($errors['donation_date'])): ?>
                                <p class="text-xs text-rose-600 mt-1.5"><?= htmlspecialchars($errors['donation_date']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- 5. Catatan (Opsional) -->
                        <div>
                            <label for="notes" class="block text-xs sm:text-sm font-semibold text-slate-800 mb-1">
                                Catatan periode iuran <span class="text-xs font-normal text-slate-500">(opsional)</span>
                            </label>
                            <input type="text" 
                                   id="notes" 
                                   name="notes" 
                                   value="<?= htmlspecialchars($old['notes'] ?? '') ?>" 
                                   placeholder="Contoh: Iuran kas bulan <?= date('F Y') ?>" 
                                   class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800 transition">
                        </div>

                        <!-- Pemberitahuan Pengiriman Bukti via WA -->
                        <div class="p-3 sm:p-3.5 bg-amber-50/70 border border-amber-200/80 rounded-xl flex items-start gap-2.5 text-xs text-amber-900">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-base mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <strong class="text-slate-900">Kirim Bukti Iuran:</strong>
                                <span class="text-slate-700 ml-1">Setelah klik tombol di bawah, Anda akan langsung diarahkan untuk mengirimkan foto struk iuran ke WhatsApp Bendahara agar segera diverifikasi.</span>
                            </div>
                        </div>

                        <!-- Tombol Submit -->
                        <div class="pt-1">
                            <button type="submit" 
                                    class="w-full min-h-[48px] bg-bkrc-red-800 hover:bg-bkrc-red-900 active:bg-bkrc-red-950 text-white font-bold text-sm px-6 py-3 rounded-xl transition shadow-xs flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bkrc-red-800">
                                <span>Kirim Konfirmasi Iuran</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Tautan Balik ke Donasi Sukarela -->
                <div class="text-center pt-2 pb-6">
                    <p class="text-xs text-slate-500">
                        Bukan anggota atau ingin donasi sukarela?
                        <a href="<?= getBaseUrl() ?>/donasi.php" class="font-bold text-bkrc-red-800 hover:underline inline-flex items-center gap-1 ml-1">
                            <span>Buka Form Donasi Sukarela</span>
                            <span>&rarr;</span>
                        </a>
                    </p>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function iuranFormLogic() {
    return {
        memberPhone: '<?= htmlspecialchars($old['donor_phone'] ?? '') ?>',
        rawAmount: '<?= htmlspecialchars($old['amount'] ?? '50000') ?>',
        displayAmount: 'Rp 50.000',
        selectedChip: 50000,
        init() {
            if (this.rawAmount) {
                this.formatFromRaw(this.rawAmount);
            }
        },
        onMemberSelect(e) {
            const opt = e.target.selectedOptions[0];
            if (opt && opt.dataset.phone && !this.memberPhone) {
                this.memberPhone = opt.dataset.phone;
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
            const standardChips = [25000, 50000, 100000, 200000];
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
                const standardChips = [25000, 50000, 100000, 200000];
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
