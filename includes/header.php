<?php
/**
 * Header Komponen Portal Publik BKRC
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

$currentPath = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Sistem Informasi Manajemen & Portal Transparansi BKRC';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — BKRC</title>
    <meta name="description" content="Portal Resmi Bantuan Kemanusiaan Relawan Cluster (BKRC) - Bersama, Berbagi, untuk Sesama. Papan transparansi donasi, agenda santunan, dan dokumentasi.">
    <link rel="icon" type="image/png" href="<?= getBaseUrl() ?>/assets/img/logo.png">
    
    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        bkrc: {
                            red: {
                                50: '#fef2f2',
                                100: '#fee2e2',
                                600: '#dc2626',
                                700: '#b91c1c',
                                800: '#991b1b',
                                900: '#7f1d1d',
                                950: '#450a0a'
                            },
                            gold: {
                                100: '#fef3c7',
                                400: '#fbbf24',
                                500: '#f59e0b',
                                600: '#d97706',
                                700: '#b45309'
                            }
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex flex-col min-h-screen">

    <?php if (empty($hideTopBar) && !in_array($currentPath, ['donasi.php', 'iuran.php'])): ?>
    <!-- Top Announcement Bar (Disembunyikan di halaman form agar header ramping #29) -->
    <div class="bg-slate-900 text-slate-300 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center gap-2 text-center sm:text-left">
                <span class="text-amber-400 font-semibold">BKRC</span>
                <span class="text-slate-500 hidden sm:inline">&bull;</span>
                <span class="text-slate-200 font-medium">Bantuan Kemanusiaan Relawan Cluster</span>
            </div>
            <div class="flex items-center gap-3 text-slate-300">
                <span class="hidden md:inline-flex items-center gap-1.5 text-slate-400">
                    <i class="fa-solid fa-location-dot text-[11px] text-amber-400"></i> Posko: Pendopo Cluster
                </span>
                <span class="hidden md:inline text-slate-700" aria-hidden="true">|</span>
                <a href="https://wa.me/6281234567891" target="_blank" rel="noopener noreferrer" class="hover:text-white transition inline-flex items-center gap-1.5 py-1 px-2 rounded focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none">
                    <i class="fa-brands fa-whatsapp text-[12px] text-emerald-400"></i> 0812-3456-7891
                </a>
                <span class="text-slate-700" aria-hidden="true">|</span>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= getBaseUrl() ?>/admin/index.php" class="text-amber-400 font-semibold hover:underline inline-flex items-center gap-1.5 py-1 px-2 rounded focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none">
                        Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= getBaseUrl() ?>/login.php" class="hover:text-white transition inline-flex items-center gap-1.5 py-1 px-2 rounded focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:outline-none">
                        Login Pengurus
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Navigation Bar (Clean solid surface, no excessive blur) -->
    <nav class="bg-white sticky top-0 z-50 border-b border-slate-200" x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <!-- Brand / Logo -->
                <a href="<?= getBaseUrl() ?>/index.php" class="flex items-center gap-3 group focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none rounded-xl p-1">
                    <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-10 h-10 object-contain flex-shrink-0">
                    <div>
                        <div class="font-bold text-lg tracking-tight text-slate-900 group-hover:text-red-700 transition leading-none">BKRC</div>
                        <div class="text-xs text-slate-500 font-medium mt-1">Relawan Warga Cluster</div>
                    </div>
                </a>

                <!-- Desktop Menu (Clean, Concise, No Clutter) -->
                <div class="hidden md:flex items-center gap-2 sm:gap-4">
                    <a href="<?= getBaseUrl() ?>/index.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'index.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Beranda
                    </a>
                    <a href="<?= getBaseUrl() ?>/agenda.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'agenda.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Agenda
                    </a>
                    <a href="<?= getBaseUrl() ?>/transparansi.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'transparansi.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Transparansi
                    </a>
                    <a href="<?= getBaseUrl() ?>/berita.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'berita.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Berita
                    </a>
                    <a href="<?= getBaseUrl() ?>/tentang.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'tentang.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Tentang
                    </a>
                    <a href="<?= getBaseUrl() ?>/daftar_anggota.php" class="px-2 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none <?= $currentPath === 'daftar_anggota.php' ? 'text-bkrc-red-800 font-bold border-b-2 border-bkrc-red-800' : 'text-slate-700 hover:text-bkrc-red-800' ?>">
                        Gabung Relawan
                    </a>
                </div>

                <!-- Action Button -->
                <div class="hidden md:flex items-center gap-3">
                    <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="min-h-[44px] inline-flex items-center gap-2 bg-red-800 hover:bg-red-900 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition shadow-sm focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:ring-offset-2 focus-visible:outline-none">
                        <i class="fa-solid fa-heart text-amber-400"></i> Donasi
                    </a>
                </div>

                <!-- Mobile Hamburger Button with Accessible Label & 44px+ Hit Area -->
                <div class="flex items-center md:hidden">
                    <button @click="mobileOpen = !mobileOpen" type="button" :aria-expanded="mobileOpen.toString()" aria-label="Buka menu navigasi utama" class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-xl border border-slate-200 text-slate-700 hover:text-bkrc-red-800 hover:bg-slate-50 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-bkrc-red-800 focus-visible:outline-none">
                        <i :class="mobileOpen ? 'fa-solid fa-xmark text-lg' : 'fa-solid fa-bars text-lg'"></i>
                        <span class="text-xs font-bold text-slate-800" x-text="mobileOpen ? 'Tutup' : 'Menu'">Menu</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown with Accessible Touch Targets -->
        <div x-show="mobileOpen" x-cloak class="md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-5 space-y-1 shadow-lg">
            <a href="<?= getBaseUrl() ?>/index.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'index.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-house w-5 text-slate-500 mr-2"></i> Beranda
            </a>
            <a href="<?= getBaseUrl() ?>/agenda.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'agenda.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-calendar-days w-5 text-slate-500 mr-2"></i> Agenda Kegiatan
            </a>
            <a href="<?= getBaseUrl() ?>/transparansi.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'transparansi.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-scale-balanced w-5 text-slate-500 mr-2"></i> Transparansi Donasi
            </a>
            <a href="<?= getBaseUrl() ?>/berita.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'berita.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-newspaper w-5 text-slate-500 mr-2"></i> Dokumentasi & Berita
            </a>
            <a href="<?= getBaseUrl() ?>/tentang.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'tentang.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-circle-info w-5 text-slate-500 mr-2"></i> Tentang BKRC
            </a>
            <a href="<?= getBaseUrl() ?>/daftar_anggota.php" class="min-h-[44px] flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition <?= $currentPath === 'daftar_anggota.php' ? 'text-red-800 bg-red-50 border border-red-200/60' : 'text-slate-800 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-user-plus w-5 text-slate-500 mr-2"></i> Gabung Relawan
            </a>
            <div class="pt-3 border-t border-slate-100 mt-2">
                <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" @click="mobileOpen = false" class="min-h-[48px] w-full flex items-center justify-center gap-2 bg-red-800 hover:bg-red-900 active:bg-red-950 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-sm transition">
                    <i class="fa-solid fa-heart text-amber-400"></i> Salurkan Donasi Sekarang
                </a>
            </div>
        </div>
    </nav>
    <main class="flex-grow">
