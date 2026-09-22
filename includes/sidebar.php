<?php
// includes/sidebar.php - Menu điều hướng thông minh tự động kiểm tra quyền
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];
?>

<aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col flex-shrink-0 transition-all duration-200 lg:static fixed inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0">
    
    <!-- Logo Ứng Dụng -->
    <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-200">
        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-100">
            <i class="fa-solid fa-users-gear text-lg"></i>
        </div>
        <div>
            <span class="font-bold text-slate-800 tracking-tight text-base block">HRMS PORTAL</span>
            <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block -mt-1">Light Edition</span>
        </div>
    </div>

    <!-- Danh sách Menu chính -->
    <div class="flex-1 overflow-y-auto py-5 px-4 space-y-1">

        <div class="px-3 pb-2 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            Bảng Tin & Báo Cáo
        </div>

        <!-- Dashboard: Mọi admin đều xem được -->
        <a href="<?= base_url('index.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'index.php') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
            <i class="fa-solid fa-chart-pie w-5 text-center text-sm <?= ($current_page == 'index.php') ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
            <span>Tổng Quan</span>
        </a>

        <?php if (has_permission('employees', 'view') || has_permission('departments', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Quản Lý Nhân Sự
            </div>
        <?php endif; ?>

        <?php if (has_permission('employees', 'view')): ?>
            <a href="<?= base_url('modules/employees/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/employees/') !== false) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <i class="fa-solid fa-address-card w-5 text-center text-sm <?= (strpos($current_uri, '/employees/') !== false) ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                <span>Hồ Sơ Nhân Viên</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('departments', 'view')): ?>
            <a href="<?= base_url('modules/departments/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/departments/') !== false) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <i class="fa-solid fa-sitemap w-5 text-center text-sm <?= (strpos($current_uri, '/departments/') !== false) ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                <span>Phòng Ban & Chức Vụ</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view') || has_permission('payroll', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Công & Tiền Lương
            </div>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view')): ?>
            <a href="<?= base_url('modules/attendance/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/attendance/') !== false) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center text-sm <?= (strpos($current_uri, '/attendance/') !== false) ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                <span>Bảng Chấm Công</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('payroll', 'view')): ?>
            <a href="<?= base_url('modules/payroll/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/payroll/') !== false) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm <?= (strpos($current_uri, '/payroll/') !== false) ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                <span>Bảng Lương Tháng</span>
            </a>
        <?php endif; ?>

        <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Quản Trị Hệ Thống
            </div>
            
            <a href="<?= base_url('modules/matrix/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/matrix/') !== false) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <i class="fa-solid fa-network-wired w-5 text-center text-sm <?= (strpos($current_uri, '/matrix/') !== false) ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                <span>Ma Trận Phân Quyền</span>
                <span class="ml-auto px-1.5 py-0.5 text-[10px] font-bold rounded-md bg-amber-100 text-amber-800">Admin</span>
            </a>
        <?php endif; ?>

    </div>

    <!-- Thông tin phiên đăng nhập ở chân Sidebar -->
    <div class="p-4 border-t border-slate-200">
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex items-center justify-between">
            <div class="truncate">
                <div class="text-xs font-semibold text-slate-700 truncate"><?= e($_SESSION['user']['username']) ?></div>
                <div class="text-[11px] text-slate-400">XAMPP Local Server</div>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
        </div>
    </div>
</aside>

<!-- Lớp phủ mờ trên mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/20 backdrop-blur-sm z-20 hidden lg:hidden"></div>