<?php
/**
 * Header Admin Dashboard BKRC
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireAuth();
$currentUser = currentUser();
$currentFile = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Dashboard Pengurus';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Back-Office Bantuan Kemanusiaan Relawan Cluster (BKRC)</title>
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
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        bkrc: {
                            red: { 50: '#fef2f2', 100: '#fee2e2', 700: '#b91c1c', 800: '#991b1b', 900: '#7f1d1d', 950: '#450a0a' },
                            gold: { 100: '#fef3c7', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' }
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* Kunci ketinggian sidebar desktop tepat 100vh viewport agar tidak ikut memanjang pada halaman panjang */
        @media (min-width: 1024px) {
            aside.admin-sidebar {
                transform: none !important;
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                height: 100vh !important;
                height: 100dvh !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex" 
      x-data="{ 
          sidebarOpen: false, 
          sidebarHovered: false, 
          isDesktop: window.innerWidth >= 1024,
          init() {
              window.addEventListener('resize', () => {
                  this.isDesktop = window.innerWidth >= 1024;
              });
          },
          get isExpanded() {
              return !this.isDesktop || this.sidebarHovered;
          }
      }" 
      @keydown.escape.window="sidebarOpen = false">

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs lg:hidden" aria-hidden="true"></div>
