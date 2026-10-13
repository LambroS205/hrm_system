<?php
// includes/sidebar.php - Menu điều hướng thông minh, hỗ trợ Mini Sidebar (Thu gọn) & Dark Mode
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];

$getActiveClass = function($isActive) {
    if ($isActive) {
        return 'bg-indigo-600 text-white font-bold group sidebar-active-item';
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

<aside id="sidebar" class="w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col flex-shrink-0 lg:static fixed inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0">
    
    <!-- Logo Ứng Dụng (Flat Metro UI) -->
    <div class="h-16 flex items-center gap-3 px-4 sm:px-5 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
        <a href="<?= base_url('index.php') ?>" class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-sm bg-indigo-600 text-white flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-users-gear text-base"></i>
            </div>
            <div class="sidebar-logo-text min-w-0">
                <span class="font-extrabold text-slate-900 dark:text-white tracking-tight text-base block leading-none truncate">AURA HRM</span>
                <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-600 dark:text-indigo-400 block mt-1 truncate">Enterprise v3.2</span>
            </div>
        </a>
    </div>

    <!-- Danh sách Menu chính -->
    <div id="sidebarNav" class="flex-1 overflow-y-auto py-4 px-3 space-y-1">

        <div class="sidebar-section-header px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
            Bảng Tin & Báo Cáo
        </div>

        <!-- Dashboard -->
        <?php $is_active = (strpos($current_uri, '/modules/') === false && ($current_page == 'index.php' || rtrim(parse_url($current_uri, PHP_URL_PATH), '/') === '/hrm_system')); ?>
        <a href="<?= base_url('index.php') ?>" 
           data-tooltip="Tổng Quan Dashboard"
           class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-solid fa-chart-pie w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span class="sidebar-text truncate">Tổng Quan</span>
        </a>

        <!-- Tìm Kiếm Toàn Cục -->
        <?php $is_active = (strpos($current_uri, '/search/') !== false); ?>
        <a href="<?= base_url('modules/search/index.php') ?>" 
           data-tooltip="Tìm Kiếm Toàn Cục (⌘K)"
           class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-solid fa-magnifying-glass w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span class="sidebar-text truncate">Tìm Kiếm</span>
            <kbd class="sidebar-badge-text ml-auto px-1.5 py-0.5 text-[9px] font-extrabold rounded <?= $is_active ? 'bg-indigo-700 text-white border border-indigo-500' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-600' ?>">⌘K</kbd>
        </a>

        <!-- Khối Cơ Cấu Tổ Chức & Chi Nhánh -->
        <?php if (has_permission('orgchart', 'view') || has_permission('branches', 'view') || has_permission('departments', 'view')): ?>
            <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Cơ Cấu Tổ Chức
            </div>
        <?php endif; ?>

        <?php if (has_permission('orgchart', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/orgchart/') !== false); ?>
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               data-tooltip="Sơ Đồ Tổ Chức Doanh Nghiệp"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-sitemap w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Sơ Đồ Tổ Chức</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('branches', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/branches/') !== false); ?>
            <a href="<?= base_url('modules/branches/index.php') ?>" 
               data-tooltip="Mạng Lưới Chi Nhánh"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-building-flag w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Mạng Lưới Chi Nhánh</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('departments', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/departments/') !== false); ?>
            <a href="<?= base_url('modules/departments/index.php') ?>" 
               data-tooltip="Phòng Ban & Chức Vụ"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-building-user w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Phòng Ban & Chức Vụ</span>
            </a>
        <?php endif; ?>

        <!-- Khối Nhân Sự & Tuyển Dụng -->
        <?php if (has_permission('employees', 'view') || has_permission('recruitment', 'view') || has_permission('transfers', 'view') || has_permission('planning', 'view')): ?>
            <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Nhân Sự & Tuyển Dụng
            </div>
        <?php endif; ?>

        <?php if (has_permission('employees', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/employees/') !== false); ?>
            <a href="<?= base_url('modules/employees/index.php') ?>" 
               data-tooltip="Hồ Sơ Nhân Viên"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-address-card w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Hồ Sơ Nhân Viên</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('recruitment', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/recruitment/') !== false && strpos($current_uri, '/jobs.php') === false); ?>
            <a href="<?= base_url('modules/recruitment/index.php') ?>" 
               data-tooltip="Pipeline Tuyển Dụng"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-people-roof w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Pipeline Tuyển Dụng</span>
            </a>

            <?php $is_active = (strpos($current_uri, '/jobs.php') !== false); ?>
            <a href="<?= base_url('modules/recruitment/jobs.php') ?>" 
               data-tooltip="Vị Trí Đang Tuyển Dụng"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-bullhorn w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Vị Trí Đang Tuyển</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('transfers', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/transfers/') !== false); ?>
            <a href="<?= base_url('modules/transfers/index.php') ?>" 
               data-tooltip="Thuyên Chuyển Công Tác"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-people-arrows w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Thuyên Chuyển Công Tác</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('planning', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/planning/') !== false); ?>
            <a href="<?= base_url('modules/planning/index.php') ?>" 
               data-tooltip="Kế Hoạch Định Biên Nhân Sự"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-wand-magic-sparkles w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Kế Hoạch Định Biên</span>
            </a>
        <?php endif; ?>

        <!-- Khối Chấm Công & Đề Xuất -->
        <?php if (has_permission('attendance', 'view') || has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
            <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Chấm Công & Đề Xuất
            </div>
        <?php endif; ?>

        <?php if (has_permission('attendance', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/attendance/') !== false); ?>
            <a href="<?= base_url('modules/attendance/index.php') ?>" 
               data-tooltip="Bảng Chấm Công Hàng Tháng"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Bảng Chấm Công</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
            <?php $is_active = (strpos($current_uri, '/proposals/') !== false); ?>
            <?php
            $pending_proposals_count = 0;
            if (has_permission('proposals', 'approve')) {
                try {
                    $pending_proposals_count = (int)($pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'pending'")->fetchColumn() ?: 0);
                } catch (Exception $e) { $pending_proposals_count = 0; }
            }
            ?>
            <a href="<?= base_url('modules/proposals/index.php') ?>" 
               data-tooltip="Trung Tâm Đề Xuất (<?= $pending_proposals_count ?> chờ duyệt)"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-paper-plane w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Trung Tâm Đề Xuất</span>
                <?php if ($pending_proposals_count > 0): ?>
                    <span class="sidebar-badge-text ml-auto px-1.5 py-0.5 text-[10px] font-extrabold rounded-md <?= $is_active ? 'bg-indigo-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700' ?>"><?= $pending_proposals_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- Khối Lương & Đãi Ngộ -->
        <?php if (has_permission('payroll', 'view') || has_permission('rewards', 'view') || has_permission('disciplines', 'view') || has_permission('evaluations', 'view')): ?>
            <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Lương & Đãi Ngộ
            </div>
        <?php endif; ?>

        <?php if (has_permission('payroll', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/payroll/') !== false); ?>
            <a href="<?= base_url('modules/payroll/index.php') ?>" 
               data-tooltip="Bảng Tính Lương Doanh Nghiệp"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Bảng Lương Tháng</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('rewards', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/rewards/') !== false); ?>
            <a href="<?= base_url('modules/rewards/index.php') ?>" 
               data-tooltip="Khen Thưởng & Vinh Danh"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-award w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Khen Thưởng</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('disciplines', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/disciplines/') !== false); ?>
            <a href="<?= base_url('modules/disciplines/index.php') ?>" 
               data-tooltip="Kỷ Luật & Bồi Thường Thiệt Hại"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-scale-unbalanced w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Kỷ Luật & Vi Phạm</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('evaluations', 'view')): ?>
            <?php $is_active = (strpos($current_uri, '/evaluations/') !== false); ?>
            <?php
            $pending_evals_count = 0;
            try {
                $pending_evals_count = (int)($pdo->query("SELECT COUNT(*) FROM evaluations WHERE status IN ('manager_review', 'self_review')")->fetchColumn() ?: 0);
            } catch (Exception $e) { $pending_evals_count = 0; }
            ?>
            <a href="<?= base_url('modules/evaluations/index.php') ?>" 
               data-tooltip="Đánh Giá Hiệu Suất & KPI"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-bullseye w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Đánh Giá & KPI</span>
                <?php if ($pending_evals_count > 0): ?>
                    <span class="sidebar-badge-text ml-auto px-1.5 py-0.5 text-[10px] font-extrabold rounded-md <?= $is_active ? 'bg-indigo-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700' ?>"><?= $pending_evals_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- Khối Quản Trị Hệ Thống -->
        <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
            <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                Quản Trị Hệ Thống
            </div>
            
            <?php $is_active = (strpos($current_uri, '/matrix/') !== false); ?>
            <a href="<?= base_url('modules/matrix/index.php') ?>" 
               data-tooltip="Ma Trận Phân Quyền Chi Tiết"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
                <i class="fa-solid fa-network-wired w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
                <span class="sidebar-text truncate">Ma Trận Phân Quyền</span>
                <span class="sidebar-badge-text ml-auto px-1.5 py-0.5 text-[10px] font-extrabold rounded-md <?= $is_active ? 'bg-indigo-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700' ?>">Admin</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-section-header pt-4 px-3 pb-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
            Cá Nhân
        </div>
        <?php $is_active = ($current_page == 'profile.php'); ?>
        <a href="<?= base_url('profile.php') ?>" 
           data-tooltip="Hồ Sơ Cá Nhân & Bảo Mật"
           class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $getActiveClass($is_active) ?>">
            <i class="fa-regular fa-user-circle w-5 text-center text-sm <?= $getIconClass($is_active) ?>"></i>
            <span class="sidebar-text truncate">Hồ Sơ & Bảo Mật</span>
        </a>

    </div>
    
    <!-- Khôi phục vị trí cuộn Sidebar tức thì (Zero-flicker) -->
    <script>
    (function() {
        try {
            var nav = document.getElementById('sidebarNav');
            if (nav) {
                var pos = sessionStorage.getItem('hrms_sidebar_scroll');
                if (pos === null) {
                    pos = localStorage.getItem('hrms_sidebar_scroll');
                }
                if (pos !== null) {
                    nav.scrollTop = parseInt(pos, 10);
                }
            }
        } catch (e) {}
    })();
    </script>

    <!-- Thông tin phiên đăng nhập ở chân Sidebar -->
    <div class="p-3 border-t-2 border-slate-200 dark:border-slate-700 flex-shrink-0">
        <a href="<?= base_url('profile.php') ?>" 
           data-tooltip="<?= e($_SESSION['user']['username'] ?? 'User') ?>"
           class="sidebar-link bg-slate-50 dark:bg-slate-700/60 p-2.5 rounded-2xl border border-slate-300 dark:border-slate-600 flex items-center justify-between hover:bg-indigo-50 dark:hover:bg-slate-700 transition group shadow-2xs">
            <div class="sidebar-user-details truncate mr-2">
                <div class="text-xs font-bold text-slate-800 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400"><?= e($_SESSION['user']['username'] ?? 'User') ?></div>
                <div class="text-[11px] font-medium text-slate-500 dark:text-slate-300">Phiên làm việc an toàn</div>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-xs shadow-emerald-400 flex-shrink-0 animate-pulse"></div>
        </a>
    </div>
</aside>

<!-- Lớp phủ mờ trên mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-20 hidden lg:hidden"></div>