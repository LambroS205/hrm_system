<?php
// includes/sidebar.php - Menu điều hướng thông minh tự động kiểm tra quyền & hỗ trợ Dark Mode
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];
?>

<aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700/80 flex flex-col flex-shrink-0 transition-all duration-200 lg:static fixed inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0">
    
    <!-- Logo Ứng Dụng -->
    <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-200 dark:border-slate-700/80">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-md shadow-indigo-200 dark:shadow-none">
            <i class="fa-solid fa-users-gear text-base"></i>
        </div>
        <div>
            <span class="font-bold text-slate-800 dark:text-white tracking-tight text-base block leading-none">HRMS PORTAL</span>
            <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400 block mt-1">Enterprise Light</span>
        </div>
    </div>

    <!-- Danh sách Menu chính -->
    <div class="flex-1 overflow-y-auto py-5 px-4 space-y-1">

        <div class="px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Bảng Tin & Báo Cáo
        </div>

        <!-- Dashboard: Mọi admin đều xem được -->
        <a href="<?= base_url('index.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'index.php') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="fa-solid fa-chart-pie w-5 text-center text-sm <?= ($current_page == 'index.php') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
            <span>Tổng Quan</span>
        </a>

        <!-- Khối Cơ Cấu Tổ Chức & Chi Nhánh -->
        <?php if (has_permission('orgchart', 'view') || has_permission('branches', 'view') || has_permission('departments', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Cơ Cấu & Chi Nhánh
            </div>
        <?php endif; ?>

        <?php if (has_permission('orgchart', 'view')): ?>
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/orgchart/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-sitemap w-5 text-center text-sm <?= (strpos($current_uri, '/orgchart/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Sơ Đồ Tổ Chức</span>
                <span class="ml-auto px-1.5 py-0.5 text-[9px] font-bold rounded-md bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">Mới</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('branches', 'view')): ?>
            <a href="<?= base_url('modules/branches/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/branches/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-building-flag w-5 text-center text-sm <?= (strpos($current_uri, '/branches/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Mạng Lưới Chi Nhánh</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('departments', 'view')): ?>
            <a href="<?= base_url('modules/departments/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/departments/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-building-user w-5 text-center text-sm <?= (strpos($current_uri, '/departments/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Phòng Ban & Chức Vụ</span>
            </a>
        <?php endif; ?>

        <!-- Khối Thuyên Chuyển & Quy Hoạch -->
        <?php if (has_permission('transfers', 'view') || has_permission('planning', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Điều Động & Quy Hoạch
            </div>
        <?php endif; ?>

        <?php if (has_permission('transfers', 'view')): ?>
            <a href="<?= base_url('modules/transfers/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/transfers/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-people-arrows w-5 text-center text-sm <?= (strpos($current_uri, '/transfers/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Thuyên Chuyển Công Tác</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('planning', 'view')): ?>
            <a href="<?= base_url('modules/planning/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/planning/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-wand-magic-sparkles w-5 text-center text-sm <?= (strpos($current_uri, '/planning/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Kế Hoạch Tối Ưu</span>
                <span class="ml-auto px-1.5 py-0.5 text-[9px] font-bold rounded-md bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300">Smart</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('employees', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Quản Lý Nhân Sự
            </div>
            
            <a href="<?= base_url('modules/employees/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/employees/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-address-card w-5 text-center text-sm <?= (strpos($current_uri, '/employees/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Hồ Sơ Nhân Viên</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view') || has_permission('payroll', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Công & Tiền Lương
            </div>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view')): ?>
            <a href="<?= base_url('modules/attendance/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/attendance/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center text-sm <?= (strpos($current_uri, '/attendance/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Bảng Chấm Công</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('payroll', 'view')): ?>
            <a href="<?= base_url('modules/payroll/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/payroll/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm <?= (strpos($current_uri, '/payroll/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Bảng Lương Tháng</span>
            </a>
        <?php endif; ?>

        <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Quản Trị Hệ Thống
            </div>
            
            <a href="<?= base_url('modules/matrix/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= (strpos($current_uri, '/matrix/') !== false) ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-network-wired w-5 text-center text-sm <?= (strpos($current_uri, '/matrix/') !== false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
                <span>Ma Trận Phân Quyền</span>
                <span class="ml-auto px-1.5 py-0.5 text-[10px] font-bold rounded-md bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300">Admin</span>
            </a>
        <?php endif; ?>

        <div class="pt-5 px-3 pb-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Cá Nhân
        </div>
        <a href="<?= base_url('profile.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'profile.php') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="fa-regular fa-user-circle w-5 text-center text-sm <?= ($current_page == 'profile.php') ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"></i>
            <span>Hồ Sơ & Bảo Mật</span>
        </a>

    </div>

    <!-- Thông tin phiên đăng nhập ở chân Sidebar -->
    <div class="p-4 border-t border-slate-200 dark:border-slate-700/80">
        <a href="<?= base_url('profile.php') ?>" class="bg-slate-50 dark:bg-slate-700/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-700 flex items-center justify-between hover:bg-slate-100 dark:hover:bg-slate-700 transition group">
            <div class="truncate mr-2">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400"><?= e($_SESSION['user']['username'] ?? 'User') ?></div>
                <div class="text-[11px] text-slate-400 dark:text-slate-400">Phiên làm việc an toàn</div>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse flex-shrink-0"></div>
        </a>
    </div>
</aside>

<!-- Lớp phủ mờ trên mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-20 hidden lg:hidden"></div>