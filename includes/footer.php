    </main>

    <!-- Footer BKRC -->
    <footer class="bg-slate-900 text-slate-300 pt-16 pb-8 border-t-4 border-bkrc-gold-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
                <!-- Kolom 1: Profil Organisasi -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-10 h-10 object-contain flex-shrink-0">
                        <div>
                            <span class="font-extrabold text-xl text-white tracking-tight">BKRC</span>
                            <div class="text-[11px] text-bkrc-gold-400 font-semibold uppercase tracking-wider">Relawan Cluster</div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Inisiatif kemanusiaan warga cluster untuk santunan rutin anak yatim dan sembako warga dhuafa.
                    </p>
                </div>

                <!-- Kolom 2: Program Utama -->
                <div>
                    <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-3 border-b border-slate-800 pb-2">Program Utama</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-angle-right text-amber-400 text-[10px]"></i> Santunan Rutin Anak Yatim
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-angle-right text-amber-400 text-[10px]"></i> Paket Sembako Dhuafa
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-angle-right text-amber-400 text-[10px]"></i> Buka Puasa Bersama Yatim
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-angle-right text-amber-400 text-[10px]"></i> Donor Darah PMI
                        </li>
                    </ul>
                </div>

                <!-- Kolom 3: Tautan Cepat & Transparansi -->
                <div>
                    <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-3 border-b border-slate-800 pb-2">Navigasi</h4>
                    <ul class="space-y-1 text-xs text-slate-400">
                        <li>
                            <a href="<?= getBaseUrl() ?>/transparansi.php" class="hover:text-amber-300 transition flex items-center gap-1.5 py-1">
                                Transparansi Donasi
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/agenda.php" class="hover:text-amber-300 transition flex items-center gap-1.5 py-1">
                                Agenda Kegiatan
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/berita.php" class="hover:text-amber-300 transition flex items-center gap-1.5 py-1">
                                Dokumentasi
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/tentang.php" class="hover:text-amber-300 transition flex items-center gap-1.5 py-1">
                                Susunan Relawan
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/login.php" class="hover:text-white transition flex items-center gap-1.5 py-1">
                                Login Pengurus
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom 4: Posko & Layanan Warga -->
                <div>
                    <h4 class="text-white font-bold text-xs uppercase tracking-wider mb-3 border-b border-slate-800 pb-2">Posko & Layanan Warga</h4>
                    <div class="text-xs text-slate-400 space-y-2.5">
                        <p class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot text-amber-400 mt-0.5 shrink-0"></i>
                            <span>Pendopo Warga Cluster Perumahan</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i class="fa-solid fa-box-open text-slate-500 mt-0.5 shrink-0"></i>
                            <span>Menerima titipan beras & sembako fisik di posko sekretariat.</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i class="fa-brands fa-whatsapp text-emerald-400 mt-0.5 shrink-0"></i>
                            <span>Hotline Pengurus: <a href="https://wa.me/6281234567891" target="_blank" rel="noopener noreferrer" class="text-slate-300 hover:text-white underline decoration-slate-600">0812-3456-7891</a></span>
                        </p>
                    </div>
                    <div class="pt-3 mt-3 border-t border-slate-800/80">
                        <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="inline-flex items-center gap-1.5 text-xs text-bkrc-gold-400 hover:text-bkrc-gold-300 transition font-medium">
                            <span>Informasi Rekening & QRIS Resmi</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="border-t border-slate-800 pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500 gap-3">
                <div>
                    &copy; <?= date('Y') ?> <strong>Bantuan Kemanusiaan Relawan Cluster (BKRC)</strong>. Seluruh Hak Cipta Dilindungi.
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
