<?php
// includes/header.php - Phần đầu trang và thanh điều hướng Topbar hiện đại
require_once __DIR__ . '/../core/auth.php';
require_login();

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'Quản Trị Nhân Sự') ?> - AURA HRM - Tập Đoàn Aura</title>
    
    <!-- Script đồng bộ theme Dark/Light ngay lập tức để tránh chớp màn hình -->
    <script>
        if (localStorage.getItem('hrms_theme') === 'dark' || (!('hrms_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- HRMS App CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="h-full antialiased text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 flex overflow-hidden transition-colors duration-200">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Khung nội dung chính -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <!-- Topbar Điều Hướng Trên Cùng -->
    <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700/80 flex items-center justify-between px-6 z-10 flex-shrink-0 transition-colors duration-200">
        <!-- Nút menu mobile & Tiêu đề trang -->
        <div class="flex items-center gap-4">
            <button id="mobileMenuBtn" class="lg:hidden text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div>
                <h1 class="text-lg font-bold text-slate-800 dark:text-white leading-tight flex items-center gap-2.5">
                    <?= e($page_title ?? 'Bảng Điều Khiển') ?>
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Nút bật/tắt Dark Mode -->
            <button type="button" onclick="toggleTheme()" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700/70 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 flex items-center justify-center transition" title="Chuyển chế độ Sáng / Tối">
                <i class="fa-regular fa-moon dark:hidden text-sm theme-icon-moon"></i>
                <i class="fa-regular fa-sun hidden dark:inline text-sm theme-icon-sun text-amber-400"></i>
            </button>

            <!-- Khối thông tin User & Avatar Dropdown -->
            <div class="relative" id="userMenuDropdownContainer">
                <button type="button" id="userMenuBtn" class="flex items-center gap-3 p-1.5 rounded-2xl hover:bg-slate-100 dark:hover:bg-slate-700/60 transition focus:outline-none">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 leading-tight">
                            <?= e($user['fullname']) ?>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center justify-end gap-1.5 mt-0.5">
                            <?php if (!empty($user['is_superadmin'])): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300">
                                    <i class="fa-solid fa-crown text-[9px] mr-1"></i> Super Admin
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-100 dark:bg-indigo-950/50 text-indigo-800 dark:text-indigo-300">
                                    <i class="fa-solid fa-user-shield text-[9px] mr-1"></i> <?= e($user['role_name'] ?? 'Admin Phân Hệ') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?= render_avatar($user['fullname'], $user['avatar'] ?? null, 9) ?>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-400 dark:text-slate-500 mr-1 hidden sm:inline"></i>
                </button>

                <!-- Dropdown Menu -->
                <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 py-2 z-50 animate-scale-up">
                    <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700/70 sm:hidden">
                        <div class="font-semibold text-sm text-slate-800 dark:text-white"><?= e($user['fullname']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($user['username']) ?></div>
                    </div>
                    <a href="<?= base_url('profile.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <i class="fa-regular fa-user-circle text-base text-indigo-500 w-5 text-center"></i>
                        <span>Hồ Sơ Cá Nhân</span>
                    </a>
                    <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
                        <a href="<?= base_url('modules/matrix/index.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <i class="fa-solid fa-sliders text-base text-amber-500 w-5 text-center"></i>
                            <span>Phân Quyền Ma Trận</span>
                        </a>
                    <?php endif; ?>
                    <div class="border-t border-slate-100 dark:border-slate-700/70 my-1"></div>
                    <a href="<?= base_url('logout.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition">
                        <i class="fa-solid fa-arrow-right-from-bracket text-base w-5 text-center"></i>
                        <span>Đăng Xuất</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Khu vực nội dung cuộn (Main Body Container) -->
    <main class="flex-1 overflow-y-auto p-6 bg-slate-50 dark:bg-slate-900 transition-colors duration-200">
        <!-- Hiển thị Flash Message nếu có -->
        <?php if ($flash): ?>
            <div class="flash-alert-auto mb-6 p-4 rounded-2xl flex items-center justify-between border shadow-sm animate-fade-in <?= $flash['type'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : ($flash['type'] === 'danger' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300' : 'bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-300') ?>">
                <div class="flex items-center gap-3">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600 dark:text-emerald-400' : ($flash['type'] === 'danger' ? 'fa-circle-exclamation text-rose-600 dark:text-rose-400' : 'fa-circle-info text-sky-600 dark:text-sky-400') ?> text-lg flex-shrink-0"></i>
                    <div class="text-sm font-medium leading-relaxed"><?= e($flash['message']) ?></div>
                </div>
                <button type="button" onclick="this.closest('.flash-alert-auto').remove()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 ml-4 p-1">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        <?php endif; ?>