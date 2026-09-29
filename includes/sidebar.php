<?php
// includes/sidebar.php - Menu điều hướng thông minh tự động kiểm tra quyền & hỗ trợ Dark Mode
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];

$getActiveClass = function($isActive) {
    if ($isActive) {
        return 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/25 group';
    }
    return 'text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-100 dark:hover:bg-slate-700/70 hover:text-slate-900 dark:hover:text-white group';
};

$getIconClass = function($isActive) {
    if ($isActive) {
        return 'text-white';
    }
    return 'text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400';
};
?>

<aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 border-r-2 border-slate-200 dark:border-slate-700 flex flex-col flex-shrink-0 transition-all duration-200 lg:static fixed inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 shadow-xs">
    
    <!-- Logo Ứng Dụng -->
    <div class="h-16 flex items-center gap-3 px-6 border-b-2 border-slate-200 dark:border-slate-700">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-md shadow-indigo-300 dark:shadow-none">
            <i class="fa-solid fa-users-gear text-base"></i>
        </div>
        <div>
            <span class="font-extrabold text-slate-900 dark:text-white tracking-tight text-base block leading-none">AURA GROUP HRM</span>
            <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400 block mt-1">Enterprise Edition v3.2</span>
        </div>
    </div>

    <!-- Danh sách Menu chính -->
    <div class="flex-1 overflow-y-auto py-5 px-4 space-y-1">

        <div class="px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
            Bảng Tin & Báo Cáo
        </div>

        <!-- Dashboard: Mọi admin đều xem được -->
        <?php $is_active = (strpos($current_uri, '/modules/') === false && ($current_page == 'index.php' || rtrim(parse_url($current_uri, PHP_URL_PATH), '/') === '/hrm_system')); ?>
        <a href="<?= base_url('index.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-solid fa-chart-pie w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span>Tổng Quan</span>
        </a>

        <!-- Tìm Kiếm Toàn Cục -->
        <?php $is_active = (strpos($current_uri, '/search/') !== false); ?>
        <a href="<?= base_url('modules/search/index.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-solid fa-magnifying-glass w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span>Tìm Kiếm</span>
            <kbd class="ml-auto px-1.5 py-0.5 text-[9px] font-extrabold rounded <?= $is_active ? 'bg-indigo-700 text-white border border-indigo-500' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-600' ?>">⌘K</kbd>
        </a>

        <!-- Khối Cơ Cấu Tổ Chức & Chi Nhánh -->
        <?php if (has_permission('orgchart', 'view') || has_permission('branches', 'view') || has_permission('departments', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Cơ Cấu & Chi Nhánh
            </div>
        <?php endif; ?>

        <?php if (has_permission('orgchart', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/orgchart/') !== false); ?>
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-sitemap w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Sơ Đồ Tổ Chức</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('branches', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/branches/') !== false); ?>
            <a href="<?= base_url('modules/branches/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-building-flag w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Mạng Lưới Chi Nhánh</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('departments', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/departments/') !== false); ?>
            <a href="<?= base_url('modules/departments/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-building-user w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Phòng Ban & Chức Vụ</span>
            </a>
        <?php endif; ?>

        <!-- Khối Thuyên Chuyển & Quy Hoạch -->
        <?php if (has_permission('transfers', 'view') || has_permission('planning', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Điều Động & Quy Hoạch
            </div>
        <?php endif; ?>

        <?php if (has_permission('transfers', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/transfers/') !== false); ?>
            <a href="<?= base_url('modules/transfers/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-people-arrows w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Thuyên Chuyển Công Tác</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('planning', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/planning/') !== false); ?>
            <a href="<?= base_url('modules/planning/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-wand-magic-sparkles w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Kế Hoạch Tối Ưu</span>
            </a>
        <?php endif; ?>

        <!-- Khối Tuyển Dụng Nhân Tài -->
        <?php if (has_permission('recruitment', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Tuyển Dụng & Ứng Viên
            </div>
            
            <?php $is_active = (strpos($current_uri, '/recruitment/') !== false && strpos($current_uri, '/jobs.php') === false); ?>
            <a href="<?= base_url('modules/recruitment/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-people-roof w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Pipeline Tuyển Dụng</span>
            </a>

            <?php $is_active = (strpos($current_uri, '/jobs.php') !== false); ?>
            <a href="<?= base_url('modules/recruitment/jobs.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-bullhorn w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Vị Trí Đang Tuyển</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('employees', 'view') || has_permission('rewards', 'view') || has_permission('disciplines', 'view') || has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Quản Lý Nhân Sự
            </div>
            
            <?php if (has_permission('employees', 'view')): ?>
                <?php $is_active = (strpos($current_uri, '/employees/') !== false); ?>
                <a href="<?= base_url('modules/employees/index.php') ?>" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                    <i class="fa-solid fa-address-card w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                    <span>Hồ Sơ Nhân Viên</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
                <?php $is_active = (strpos($current_uri, '/proposals/') !== false); ?>
                <?php
                // Đếm số đề xuất đang chờ duyệt
                $pending_proposals_count = 0;
                if (has_permission('proposals', 'approve')) {
                    try {
                        $pending_proposals_count = (int)($pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'pending'")->fetchColumn() ?: 0);
                    } catch (Exception $e) { $pending_proposals_count = 0; }
                }
                ?>
                <a href="<?= base_url('modules/proposals/index.php') ?>" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                    <i class="fa-solid fa-paper-plane w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                    <span>Trung Tâm Đề Xuất</span>
                    <?php if ($pending_proposals_count > 0): ?>
                        <span class="ml-auto px-1.5 py-0.5 text-[10px] font-extrabold rounded-md <?= $is_active ? 'bg-indigo-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700' ?>"><?= $pending_proposals_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (has_permission('rewards', 'view')): ?>
                <?php $is_active = (strpos($current_uri, '/rewards/') !== false); ?>
                <a href="<?= base_url('modules/rewards/index.php') ?>" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                    <i class="fa-solid fa-award w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                    <span>Khen Thưởng</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('disciplines', 'view')): ?>
                <?php $is_active = (strpos($current_uri, '/disciplines/') !== false); ?>
                <a href="<?= base_url('modules/disciplines/index.php') ?>" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                    <i class="fa-solid fa-scale-unbalanced w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                    <span>Kỷ Luật & Vi Phạm</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view') || has_permission('payroll', 'view')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Công & Tiền Lương
            </div>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/attendance/') !== false); ?>
            <a href="<?= base_url('modules/attendance/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Bảng Chấm Công</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('payroll', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/payroll/') !== false); ?>
            <a href="<?= base_url('modules/payroll/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Bảng Lương Tháng</span>
            </a>
        <?php endif; ?>

        <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
            <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Quản Trị Hệ Thống
            </div>
            
            <?php $is_active = (strpos($current_uri, '/matrix/') !== false); ?>
            <a href="<?= base_url('modules/matrix/index.php') ?>" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-network-wired w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span>Ma Trận Phân Quyền</span>
                <span class="ml-auto px-1.5 py-0.5 text-[10px] font-extrabold rounded-md <?= $is_active ? 'bg-indigo-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700' ?>">Admin</span>
            </a>
        <?php endif; ?>

        <div class="pt-5 px-3 pb-2 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
            Cá Nhân
        </div>
        <?php $is_active = ($current_page == 'profile.php'); ?>
        <a href="<?= base_url('profile.php') ?>" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-regular fa-user-circle w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span>Hồ Sơ & Bảo Mật</span>
        </a>

    </div>

    <!-- Thông tin phiên đăng nhập ở chân Sidebar -->
    <div class="p-4 border-t-2 border-slate-200 dark:border-slate-700">
        <a href="<?= base_url('profile.php') ?>" class="bg-slate-50 dark:bg-slate-700/60 p-3 rounded-2xl border border-slate-300 dark:border-slate-600 flex items-center justify-between hover:bg-indigo-50 dark:hover:bg-slate-700 transition group shadow-2xs">
            <div class="truncate mr-2">
                <div class="text-xs font-bold text-slate-800 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400"><?= e($_SESSION['user']['username'] ?? 'User') ?></div>
                <div class="text-[11px] font-medium text-slate-500 dark:text-slate-300">Phiên làm việc an toàn</div>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-xs shadow-emerald-400 flex-shrink-0 animate-pulse"></div>
        </a>
    </div>
</aside>

<!-- Lớp phủ mờ trên mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-20 hidden lg:hidden"></div>