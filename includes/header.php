<?php
// includes/header.php - Phần đầu trang và thanh điều hướng Topbar
require_once __DIR__ . '/../core/auth.php';
require_login();

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Quản Trị Nhân Sự' ?> - HRMS</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full antialiased text-slate-700 bg-slate-50 flex overflow-hidden">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Khung nội dung chính -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <!-- Topbar Điều Hướng Trên Cùng -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-10 flex-shrink-0">
        <!-- Nút menu mobile & Tiêu đề trang -->
        <div class="flex items-center gap-4">
            <button id="mobileMenuBtn" class="lg:hidden text-slate-500 hover:text-slate-800 p-2 rounded-lg">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
            <h1 class="text-lg font-bold text-slate-800">
                <?= e($page_title ?? 'Bảng Điều Khiển') ?>
            </h1>
        </div>

        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <div class="text-sm font-semibold text-slate-800 leading-tight">
                    <?= e($user['fullname']) ?>
                </div>
                <div class="text-xs text-slate-500 flex items-center justify-end gap-1.5 mt-0.5">
                    <?php if ($user['is_superadmin']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            <i class="fa-solid fa-crown text-[10px] mr-1"></i> Super Admin
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            <i class="fa-solid fa-user-shield text-[10px] mr-1"></i> <?= e($user['role_name'] ?? 'Admin Phân Hệ') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Avatar Dropdown / Đăng xuất -->
            <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                    <?= strtoupper(mb_substr($user['fullname'], 0, 1, 'UTF-8')) ?>
                </div>
                <a href="<?= base_url('logout.php') ?>" 
                   title="Đăng xuất"
                   class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition ml-1">
                    <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Khu vực nội dung cuộn (Main Body Container) -->
    <main class="flex-1 overflow-y-auto p-6 bg-slate-50">
        <!-- Hiển thị Flash Message nếu có -->
        <?php if ($flash): ?>
            <div class="mb-6 p-4 rounded-2xl flex items-center gap-3 border shadow-sm <?= $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : ($flash['type'] === 'danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-sky-50 border-sky-200 text-sky-800') ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-info' ?> text-lg flex-shrink-0"></i>
                <div class="text-sm font-medium"><?= e($flash['message']) ?></div>
            </div>
        <?php endif; ?>