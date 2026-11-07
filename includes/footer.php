    </main>

    <!-- Footer BKRC (Warm Neutral, Quiet Chrome, Clear Typography) -->
    <footer class="bg-stone-900 text-stone-400 pt-12 pb-8 border-t border-stone-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 mb-10">
                <!-- Kolom 1: Profil Organisasi -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-9 h-9 object-contain flex-shrink-0">
                        <div>
                            <span class="font-semibold text-lg text-stone-100 tracking-tight leading-none">BKRC</span>
                            <div class="text-[11px] text-stone-400 font-normal mt-0.5">Relawan Warga Cluster</div>
                        </div>
                    </div>
                    <p class="text-xs text-stone-400 leading-relaxed">
                        Inisiatif kemanusiaan warga cluster untuk santunan rutin anak yatim dan sembako dhuafa di lingkungan sekitar.
                    </p>
                </div>

                <!-- Kolom 2: Program Utama -->
                <div>
                    <h4 class="text-stone-200 font-medium text-xs mb-3 border-b border-stone-800 pb-2">Program utama</h4>
                    <ul class="space-y-2 text-xs text-stone-400">
                        <li class="flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-stone-500"></span>
                            <span>Santunan rutin anak yatim</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-stone-500"></span>
                            <span>Paket sembako keluarga dhuafa</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-stone-500"></span>
                            <span>Buka puasa bersama yatim</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-stone-500"></span>
                            <span>Aksi donor darah berkala</span>
                        </li>
                    </ul>
                </div>

                <!-- Kolom 3: Tautan Cepat & Transparansi -->
                <div>
                    <h4 class="text-stone-200 font-medium text-xs mb-3 border-b border-stone-800 pb-2">Navigasi portal</h4>
                    <ul class="space-y-1.5 text-xs text-stone-400">
                        <li>
                            <a href="<?= getBaseUrl() ?>/transparansi.php" class="hover:text-stone-200 transition py-0.5 inline-block">
                                Transparansi donasi
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/agenda.php" class="hover:text-stone-200 transition py-0.5 inline-block">
                                Agenda kegiatan 2026
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/berita.php" class="hover:text-stone-200 transition py-0.5 inline-block">
                                Dokumentasi kegiatan
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/tentang.php" class="hover:text-stone-200 transition py-0.5 inline-block">
                                Susunan relawan
                            </a>
                        </li>
                        <li>
                            <a href="<?= getBaseUrl() ?>/login.php" class="hover:text-stone-200 transition py-0.5 inline-block">
                                Login pengurus
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom 4: Posko & Layanan Warga -->
                <div>
                    <h4 class="text-stone-200 font-medium text-xs mb-3 border-b border-stone-800 pb-2">Posko & layanan</h4>
                    <div class="text-xs text-stone-400 space-y-2">
                        <p class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot text-stone-400 mt-0.5 shrink-0 text-[11px]"></i>
                            <span>Pendopo Warga Cluster Perumahan</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i class="fa-solid fa-box-archive text-stone-400 mt-0.5 shrink-0 text-[11px]"></i>
                            <span>Menerima titipan beras & sembako fisik di posko.</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i class="fa-brands fa-whatsapp text-stone-400 mt-0.5 shrink-0 text-[12px]"></i>
                            <span>Hotline: <a href="https://wa.me/6281234567891" target="_blank" rel="noopener noreferrer" class="text-stone-300 hover:text-white underline decoration-stone-600">0812-3456-7891</a></span>
                        </p>
                    </div>
                    <div class="pt-3 mt-3 border-t border-stone-800/80">
                        <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="inline-flex items-center gap-1.5 text-xs text-stone-300 hover:text-white transition font-medium">
                            <span>Informasi rekening resmi</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="border-t border-stone-800 pt-5 flex flex-col sm:flex-row justify-between items-center text-xs text-stone-500 gap-2">
                <div>
                    &copy; <?= date('Y') ?> Bantuan Kemanusiaan Relawan Cluster (BKRC).
                </div>
                <div class="text-[11px] text-stone-500">
                    Akuntabel, terbuka, dan mandiri.
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
