<?php
$sbPendingDonations = (int)($pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'pending'")->fetchColumn() ?: 0);
$sbPendingMembers = (int)($pdo->query("SELECT COUNT(*) FROM members WHERE status = 'pending'")->fetchColumn() ?: 0);
?>
<!-- Desktop Layout Spacer: Menjaga margin konten utama hanya di layar desktop -->
<div class="hidden lg:block w-20 flex-shrink-0"></div>

<!-- Sidebar Navigation -->
<aside 
    @mouseenter="if (isDesktop) sidebarHovered = true"
    @mouseleave="if (isDesktop) sidebarHovered = false"
    :class="{
        'translate-x-0': sidebarOpen,
        '-translate-x-full lg:translate-x-0': !sidebarOpen,
        'w-72 sm:w-80 lg:w-64 shadow-2xl': isExpanded,
        'w-72 sm:w-80 lg:w-20 shadow-none': !isExpanded
    }"
    class="admin-sidebar fixed inset-y-0 left-0 z-50 lg:z-40 h-screen max-h-screen lg:transform-none bg-slate-900 text-slate-300 transition-all duration-300 ease-in-out flex flex-col justify-between border-r border-slate-800 flex-shrink-0 select-none overflow-hidden">
    
    <!-- 1. Header & User Info Area (Fixed at top of sidebar) -->
    <div class="flex-shrink-0">
        <!-- Brand Header with Logo, Text, and Mobile Close -->
        <div class="h-20 flex items-center justify-between px-4 bg-slate-950 border-b border-slate-800 transition-all duration-300">
            <div class="flex items-center gap-3 overflow-hidden">
                <a href="<?= getBaseUrl() ?>/admin/index.php" class="flex-shrink-0 block" :class="isExpanded ? '' : 'mx-auto'" title="BKRC Pengurus">
                    <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-10 h-10 object-contain flex-shrink-0">
                </a>
                <div x-show="isExpanded" x-transition.opacity.duration.200ms class="overflow-hidden whitespace-nowrap">
                    <span class="font-extrabold text-base text-white tracking-tight block">BKRC Pengurus</span>
                    <span class="block text-[11px] text-amber-400 font-semibold tracking-wide">Panel Kelola</span>
                </div>
            </div>

            <!-- Mobile Close Button (Khusus Mobile) -->
            <button @click="sidebarOpen = false" type="button" class="lg:hidden text-slate-400 hover:text-white min-h-[44px] min-w-[44px] inline-flex items-center justify-center p-2 rounded-xl focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none" aria-label="Tutup Navigasi">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- User Quick Card -->
        <div class="py-3.5 bg-slate-800/40 border-b border-slate-800/80 flex items-center transition-all duration-300"
             :class="isExpanded ? 'px-5 gap-3 justify-start' : 'px-0 justify-center'">
            <div class="w-9 h-9 rounded-full bg-bkrc-red-800 text-white flex items-center justify-center font-bold text-sm uppercase shadow flex-shrink-0" title="<?= htmlspecialchars($currentUser['name']) ?>">
                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
            </div>
            <div x-show="isExpanded" x-transition.opacity.duration.200ms class="overflow-hidden whitespace-nowrap">
                <div class="text-xs font-bold text-white truncate max-w-[170px]"><?= htmlspecialchars($currentUser['name']) ?></div>
                <div class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                    <span class="w-2 h-2 rounded-full <?= $currentUser['role'] === 'superadmin' ? 'bg-amber-400' : 'bg-emerald-400' ?>"></span>
                    <?= $currentUser['role'] === 'superadmin' ? 'Super Admin' : 'Admin Pengurus' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Navigation Links Area (Bisa scroll independen di layar kecil, tidak melar ke halaman luar) -->
    <nav class="flex-1 p-2.5 space-y-1 text-xs overflow-y-auto overflow-x-hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <!-- Header: Aktivitas Utama -->
        <div x-show="isExpanded" x-transition.opacity.duration.200ms class="text-[10px] font-bold uppercase tracking-wider text-slate-500 px-3 py-1.5 whitespace-nowrap">
            Aktivitas Utama
        </div>
        <div x-show="!isExpanded" class="h-px bg-slate-800/80 my-2 mx-2"></div>
        
        <!-- Ringkasan -->
        <a href="<?= getBaseUrl() ?>/admin/index.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'index.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Ringkasan Dashboard">
            <i class="fa-solid fa-house w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'index.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Ringkasan</span>
        </a>

        <!-- Catat Donasi -->
        <a href="<?= getBaseUrl() ?>/admin/donations.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'donations.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center relative'"
           title="Pencatatan Donasi (Uang & Sembako)">
            <i class="fa-solid fa-hand-holding-dollar w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'donations.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden flex-1">Catat Donasi</span>
            <?php if ($sbPendingDonations > 0): ?>
                <span x-show="isExpanded" class="bg-rose-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold ml-auto"><?= $sbPendingDonations ?></span>
                <span x-show="!isExpanded" class="absolute top-2 right-2 w-2 h-2 rounded-full bg-rose-500"></span>
            <?php endif; ?>
        </a>

        <!-- Data Donatur -->
        <a href="<?= getBaseUrl() ?>/admin/donors.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'donors.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Data Profil Donatur">
            <i class="fa-solid fa-users-line w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'donors.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Data Donatur</span>
        </a>

        <!-- Penerima Manfaat -->
        <a href="<?= getBaseUrl() ?>/admin/beneficiaries.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'beneficiaries.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Penerima Manfaat (Yatim & Dhuafa)">
            <i class="fa-solid fa-child-reaching w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'beneficiaries.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Penerima Manfaat</span>
        </a>

        <!-- Penyaluran Santunan -->
        <a href="<?= getBaseUrl() ?>/admin/events.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= in_array($currentFile, ['events.php', 'distribution_checklist.php']) ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Penyaluran Santunan & Checklist HP">
            <i class="fa-solid fa-clipboard-check w-5 text-center text-sm flex-shrink-0 <?= in_array($currentFile, ['events.php', 'distribution_checklist.php']) ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Penyaluran Santunan</span>
        </a>

        <!-- Header: Program & Laporan -->
        <div x-show="isExpanded" x-transition.opacity.duration.200ms class="text-[10px] font-bold uppercase tracking-wider text-slate-500 px-3 pt-3 pb-1 whitespace-nowrap">
            Program & Laporan
        </div>
        <div x-show="!isExpanded" class="h-px bg-slate-800/80 my-2 mx-2"></div>

        <!-- Data Relawan -->
        <a href="<?= getBaseUrl() ?>/admin/members.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'members.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center relative'"
           title="Database Relawan Cluster">
            <i class="fa-solid fa-id-badge w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'members.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden flex-1">Data Relawan</span>
            <?php if ($sbPendingMembers > 0): ?>
                <span x-show="isExpanded" class="bg-amber-500 text-slate-950 text-[10px] px-1.5 py-0.5 rounded-full font-bold ml-auto"><?= $sbPendingMembers ?></span>
                <span x-show="!isExpanded" class="absolute top-2 right-2 w-2 h-2 rounded-full bg-amber-400"></span>
            <?php endif; ?>
        </a>

        <!-- Agenda Kegiatan -->
        <a href="<?= getBaseUrl() ?>/admin/agendas.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'agendas.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Kalender Agenda Kegiatan">
            <i class="fa-solid fa-calendar-days w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'agendas.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Agenda Kegiatan</span>
        </a>

        <!-- Berita & Dokumentasi -->
        <a href="<?= getBaseUrl() ?>/admin/posts.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'posts.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Dokumentasi & Berita Kegiatan">
            <i class="fa-solid fa-newspaper w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'posts.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Berita & Dokumentasi</span>
        </a>

        <!-- Laporan Tahunan (LPJ) -->
        <a href="<?= getBaseUrl() ?>/admin/lpj.php" 
           class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'lpj.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
           :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
           title="Laporan Musyawarah Tahunan (LPJ)">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'lpj.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Laporan Tahunan (LPJ)</span>
        </a>

        <!-- Super Admin Section -->
        <?php if ($currentUser['role'] === 'superadmin'): ?>
            <div x-show="isExpanded" x-transition.opacity.duration.200ms class="text-[10px] font-bold uppercase tracking-wider text-slate-500 px-3 pt-3 pb-1 whitespace-nowrap">
                Pengaturan
            </div>
            <div x-show="!isExpanded" class="h-px bg-slate-800/80 my-2 mx-2"></div>

            <a href="<?= getBaseUrl() ?>/admin/users.php" 
               class="group flex items-center min-h-[44px] rounded-xl font-medium transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentFile === 'users.php' ? 'bg-bkrc-red-800 text-white font-bold shadow' : 'hover:bg-slate-800 hover:text-white text-slate-300' ?>"
               :class="isExpanded ? 'px-3.5 gap-3 justify-start' : 'px-0 justify-center'"
               title="Kelola Akun Pengurus">
                <i class="fa-solid fa-user-shield w-5 text-center text-sm flex-shrink-0 <?= $currentFile === 'users.php' ? 'text-white' : 'text-slate-400 group-hover:text-white transition' ?>"></i>
                <span x-show="isExpanded" x-transition.opacity.duration.200ms class="text-sm whitespace-nowrap overflow-hidden">Kelola Akun</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- 3. Bottom Actions Area (Terkunci stabil di dasar sidebar / viewport) -->
    <div class="flex-shrink-0 p-2.5 border-t border-slate-800 space-y-1.5 transition-all duration-300 bg-slate-950/60">
        <a href="<?= getBaseUrl() ?>/index.php" target="_blank" 
           class="flex items-center min-h-[44px] rounded-xl text-xs text-slate-300 hover:bg-slate-800 hover:text-white transition focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none"
           :class="isExpanded ? 'px-3.5 justify-between' : 'px-0 justify-center'"
           title="Lihat Website">
            <span class="flex items-center gap-2 overflow-hidden">
                <i class="fa-solid fa-arrow-up-right-from-square w-5 text-center flex-shrink-0"></i>
                <span x-show="isExpanded" x-transition.opacity.duration.200ms class="whitespace-nowrap">Lihat Website</span>
            </span>
            <i x-show="isExpanded" class="fa-solid fa-external-link text-[10px] text-slate-500"></i>
        </a>
        <a href="<?= getBaseUrl() ?>/logout.php" 
           class="flex items-center min-h-[44px] rounded-xl text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300 transition focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:outline-none"
           :class="isExpanded ? 'px-3.5 gap-2 justify-start' : 'px-0 justify-center'"
           title="Keluar">
            <i class="fa-solid fa-right-from-bracket w-5 text-center flex-shrink-0"></i>
            <span x-show="isExpanded" x-transition.opacity.duration.200ms class="whitespace-nowrap">Keluar</span>
        </a>
    </div>
</aside>

<!-- Main Content Wrapper (Mengambil seluruh sisa lebar layar secara penuh) -->
<div class="flex-1 flex flex-col min-h-screen min-w-0 w-full overflow-x-hidden bg-slate-100">
