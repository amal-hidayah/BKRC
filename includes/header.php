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
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                        mono: ['"Space Mono"', 'monospace']
                    },
                    colors: {
                        stone: {
                            50: '#faf8f5',
                            100: '#f5f2eb',
                            200: '#e7e2d7',
                            300: '#d5cdbf',
                            400: '#a89f91',
                            500: '#78716c',
                            600: '#57534e',
                            700: '#44403c',
                            800: '#292524',
                            900: '#1c1917',
                            950: '#0c0a09'
                        },
                        bkrc: {
                            crimson: '#8b1e1e',
                            crimsonDark: '#701616',
                            crimsonLight: '#fdf2f2',
                            red: {
                                50: '#fdf2f2',
                                100: '#fde4e4',
                                600: '#c52828',
                                700: '#a31f1f',
                                800: '#8b1e1e',
                                900: '#701616',
                                950: '#400909'
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
<body class="bg-[#faf8f5] text-stone-900 font-sans antialiased flex flex-col min-h-screen selection:bg-stone-200 selection:text-stone-900">

    <?php if (empty($hideTopBar) && !in_array($currentPath, ['donasi.php', 'iuran.php'])): ?>
    <!-- Top Announcement Bar (Quiet, Monochromatic, Informative) -->
    <div class="bg-stone-900 text-stone-300 text-xs py-2 px-4 border-b border-stone-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center gap-2 text-center sm:text-left">
                <span class="text-stone-100 font-semibold tracking-wide">BKRC</span>
                <span class="text-stone-600 hidden sm:inline">&bull;</span>
                <span class="text-stone-300">Bantuan Kemanusiaan Relawan Cluster</span>
            </div>
            <div class="flex items-center gap-4 text-stone-400">
                <span class="hidden md:inline-flex items-center gap-1.5 text-stone-300">
                    <i class="fa-solid fa-location-dot text-[11px] text-stone-400"></i> Posko Pendopo Cluster
                </span>
                <span class="hidden md:inline text-stone-700" aria-hidden="true">&bull;</span>
                <a href="https://wa.me/6281234567891" target="_blank" rel="noopener noreferrer" class="hover:text-stone-100 transition inline-flex items-center gap-1.5 focus-visible:outline-none focus-visible:underline">
                    <i class="fa-brands fa-whatsapp text-[12px] text-stone-400"></i> 0812-3456-7891
                </a>
                <span class="text-stone-700" aria-hidden="true">&bull;</span>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= getBaseUrl() ?>/admin/index.php" class="text-stone-200 hover:text-white font-medium hover:underline inline-flex items-center gap-1">
                        Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= getBaseUrl() ?>/login.php" class="text-stone-400 hover:text-stone-200 transition">
                        Login pengurus
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Navigation Bar (Clean solid surface, quiet chrome) -->
    <nav class="bg-white/95 backdrop-blur-xs sticky top-0 z-50 border-b border-stone-200/80" x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 sm:h-18 items-center">
                <!-- Brand / Logo -->
                <a href="<?= getBaseUrl() ?>/index.php" class="flex items-center gap-3 group focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:outline-none rounded-lg p-1">
                    <img src="<?= getBaseUrl() ?>/assets/img/logo.png" alt="Logo BKRC" class="w-9 h-9 object-contain flex-shrink-0">
                    <div>
                        <div class="font-semibold text-base tracking-tight text-stone-900 group-hover:text-stone-700 transition leading-none">BKRC</div>
                        <div class="text-[11px] text-stone-500 mt-1">Relawan Warga Cluster</div>
                    </div>
                </a>

                <!-- Desktop Menu (Sentence case, subtle active marker, calm spacing) -->
                <div class="hidden md:flex items-center gap-1 lg:gap-2">
                    <a href="<?= getBaseUrl() ?>/index.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'index.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Beranda
                    </a>
                    <a href="<?= getBaseUrl() ?>/agenda.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'agenda.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Agenda
                    </a>
                    <a href="<?= getBaseUrl() ?>/transparansi.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'transparansi.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Transparansi
                    </a>
                    <a href="<?= getBaseUrl() ?>/berita.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'berita.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Berita
                    </a>
                    <a href="<?= getBaseUrl() ?>/tentang.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'tentang.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Tentang
                    </a>
                    <a href="<?= getBaseUrl() ?>/daftar_anggota.php" class="px-3 py-1.5 text-xs font-medium rounded-lg transition <?= $currentPath === 'daftar_anggota.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-50' ?>">
                        Gabung relawan
                    </a>
                </div>

                <!-- Action Button (Restrained crimson accent, no mismatched gold heart) -->
                <div class="hidden md:flex items-center gap-3">
                    <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" class="min-h-[38px] inline-flex items-center gap-1.5 bg-[#8b1e1e] hover:bg-[#701616] text-white font-medium px-4 py-2 rounded-lg text-xs transition shadow-2xs focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:outline-none">
                        <span>Salurkan donasi</span>
                    </a>
                </div>

                <!-- Mobile Hamburger Button with Accessible Label & 44px+ Hit Area -->
                <div class="flex items-center md:hidden">
                    <button @click="mobileOpen = !mobileOpen" type="button" :aria-expanded="mobileOpen.toString()" aria-label="Buka menu navigasi utama" class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-lg border border-stone-200 text-stone-700 hover:text-stone-900 hover:bg-stone-50 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:outline-none">
                        <i :class="mobileOpen ? 'fa-solid fa-xmark text-base' : 'fa-solid fa-bars text-base'"></i>
                        <span class="text-xs font-medium text-stone-800" x-text="mobileOpen ? 'Tutup' : 'Menu'">Menu</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown with Accessible Touch Targets -->
        <div x-show="mobileOpen" x-cloak class="md:hidden border-t border-stone-200 bg-white px-4 pt-3 pb-5 space-y-1 shadow-sm">
            <a href="<?= getBaseUrl() ?>/index.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'index.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Beranda
            </a>
            <a href="<?= getBaseUrl() ?>/agenda.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'agenda.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Agenda kegiatan
            </a>
            <a href="<?= getBaseUrl() ?>/transparansi.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'transparansi.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Transparansi donasi
            </a>
            <a href="<?= getBaseUrl() ?>/berita.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'berita.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Dokumentasi kegiatan
            </a>
            <a href="<?= getBaseUrl() ?>/tentang.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'tentang.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Tentang BKRC
            </a>
            <a href="<?= getBaseUrl() ?>/daftar_anggota.php" class="min-h-[44px] flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= $currentPath === 'daftar_anggota.php' ? 'text-stone-900 bg-stone-100 font-semibold' : 'text-stone-700 hover:bg-stone-50' ?>">
                Gabung relawan
            </a>
            <div class="pt-3 border-t border-stone-100 mt-2">
                <a href="<?= getBaseUrl() ?>/index.php#kanal-donasi" @click="mobileOpen = false" class="min-h-[44px] w-full flex items-center justify-center bg-[#8b1e1e] hover:bg-[#701616] text-white font-medium py-2.5 px-4 rounded-lg text-xs transition">
                    Salurkan donasi
                </a>
            </div>
        </div>
    </nav>
    <main class="flex-grow">
