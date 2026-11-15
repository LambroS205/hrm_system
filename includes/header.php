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
    <title><?= e($page_title ?? 'Quản Trị Nhân Sự') ?> - <?= e(brand('product_name')) ?> - <?= e(brand('company_short')) ?></title>
    <meta name="description" content="<?= e(brand('product_name')) ?> - Hệ thống Quản trị Nhân sự & Chuỗi Bán Lẻ WinCommerce (WinMart / Masan Group)">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/brand/favicon.svg') ?>">
    
    <!-- Script đồng bộ theme Dark/Light & trạng thái Mini Sidebar ngay lập tức để tránh chớp màn hình -->
    <script>
        if (localStorage.getItem('hrms_theme') === 'dark' || (!('hrms_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        if (localStorage.getItem('hrms_sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>

    <!-- Tailwind CSS CDN & Flat Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= asset('js/tailwind-flat.config.js') ?>"></script>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- HRMS App CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="h-full antialiased text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 flex overflow-hidden transition-colors duration-200">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Khung nội dung chính -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <!-- Topbar Điều Hướng Trên Cùng (Flat Metro UI) -->
    <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 sm:px-6 z-10 flex-shrink-0 transition-colors duration-200">
        <!-- Nút menu mobile, Toggle Mini Sidebar & Breadcrumb Tiêu Đề -->
        <div class="flex items-center gap-2 sm:gap-3.5 min-w-0">
            <button id="mobileMenuBtn" class="lg:hidden text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white p-2 rounded-sm hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Mở menu">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>

            <!-- Nút Toggle Thu Gọn / Mở Rộng Sidebar (Desktop) -->
            <button type="button" id="toggleSidebarBtn" onclick="toggleSidebarCollapse()" 
                    class="hidden lg:flex w-9 h-9 rounded-sm items-center justify-center text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700/80 transition border border-transparent hover:border-slate-200 dark:hover:border-slate-600 flex-shrink-0" 
                    title="Thu gọn / Mở rộng Sidebar (Alt+S)">
                <i class="fa-solid fa-bars-staggered text-sm"></i>
            </button>

            <div class="hidden lg:block w-px h-6 bg-slate-200 dark:bg-slate-700 flex-shrink-0"></div>

            <div class="flex flex-col min-w-0">
                <?= render_breadcrumbs($breadcrumbs ?? null) ?>
                <h1 class="text-sm sm:text-base md:text-lg font-extrabold text-slate-900 dark:text-white leading-tight flex items-center gap-2 truncate mt-0.5">
                    <?= e($page_title ?? 'Bảng Điều Khiển') ?>
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-1.5 sm:gap-2.5 flex-shrink-0">
            <!-- Nút Tìm Kiếm Toàn Cục (Ctrl+K) -->
            <button type="button" id="openSearchModalBtn" 
                    class="hidden sm:flex items-center gap-2 px-2.5 xl:px-3 py-2 rounded-sm bg-slate-100 dark:bg-slate-700/80 hover:bg-indigo-50/70 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition group border border-slate-300 dark:border-slate-600"
                    title="Tìm kiếm toàn cục (Ctrl+K)">
                <i class="fa-solid fa-magnifying-glass text-sm text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-slate-600 dark:text-slate-300 font-medium hidden xl:inline">Tìm kiếm...</span>
                <kbd class="ml-1 px-1.5 py-0.5 bg-white dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 rounded-sm text-[10px] font-extrabold border border-slate-300 dark:border-slate-500 hidden xl:inline">⌘K</kbd>
            </button>

            <!-- Nút Trợ Giúp & Cẩm Nang Đào Tạo (F1) -->
            <button type="button" onclick="openHelpModal()" 
                    class="hidden sm:flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-sm bg-indigo-50/80 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition border border-indigo-200/80 dark:border-indigo-800/80 group"
                    title="Cẩm nang hướng dẫn sử dụng & đào tạo nhân viên (F1)">
                <i class="fa-solid fa-circle-question text-sm text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform"></i>
                <span class="hidden xl:inline">Trợ Giúp</span>
                <kbd class="ml-1 px-1.5 py-0.5 bg-white dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 rounded-sm text-[10px] font-extrabold border border-indigo-300 dark:border-indigo-700 hidden xl:inline">F1</kbd>
            </button>
            <button type="button" id="openSearchModalBtnMobile" 
                    class="sm:hidden w-9 h-9 rounded-sm bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 flex items-center justify-center transition"
                    title="Tìm kiếm">
                <i class="fa-solid fa-magnifying-glass text-sm text-indigo-600 dark:text-indigo-400"></i>
            </button>

            <!-- Nút bật/tắt Dark Mode -->
            <button type="button" onclick="toggleTheme()" class="w-9 h-9 rounded-sm bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 flex items-center justify-center transition" title="Chuyển chế độ Sáng / Tối">
                <i class="fa-regular fa-moon dark:hidden text-sm theme-icon-moon"></i>
                <i class="fa-regular fa-sun hidden dark:inline text-sm theme-icon-sun text-amber-400"></i>
            </button>

            <?php if (is_demo_mode()): ?>
                <!-- Bộ Chuyển Nhanh Persona Trình Diễn (1-Click Switcher) -->
                <div class="relative" id="personaSwitcherContainer">
                    <button type="button" id="personaSwitcherBtn" 
                            class="h-9 flex items-center gap-1.5 px-3 rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 dark:hover:bg-amber-900/70 text-amber-900 dark:text-amber-200 text-xs font-bold border border-amber-300 dark:border-amber-700/80 transition shadow-2xs cursor-pointer"
                            title="Chuyển nhanh vai trò trình diễn (Demo Persona)">
                        <i class="fa-solid fa-masks-theater text-amber-600 dark:text-amber-400 text-sm"></i>
                        <span class="hidden md:inline font-extrabold text-[11px] uppercase tracking-wide text-amber-800 dark:text-amber-300">Persona</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-500"></i>
                    </button>

                    <div id="personaSwitcherDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white dark:bg-slate-800 rounded-sm border border-slate-300 dark:border-slate-700 py-1.5 z-50 shadow-xl animate-scale-up">
                        <div class="px-3 py-1.5 border-b border-slate-200 dark:border-slate-700 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center justify-between">
                            <span>7 Persona Trình Diễn (6 Vai Trò)</span>
                            <span class="text-amber-600 dark:text-amber-400"><i class="fa-solid fa-bolt"></i> 1-Click</span>
                        </div>
                        <?php
                        $personas_list = [
                            ['email' => 'admin@wincommerce.vn', 'name' => 'Nguyễn Hoàng Nam', 'role' => 'CEO / Super Admin', 'scope' => 'ALL', 'badge_color' => 'amber', 'icon' => 'fa-crown'],
                            ['email' => 'phuong.le@wincommerce.vn', 'name' => 'Lê Mai Phương', 'role' => 'CHRO (HR Director)', 'scope' => 'ALL', 'badge_color' => 'teal', 'icon' => 'fa-user-tie'],
                            ['email' => 'giang.tran@wincommerce.vn', 'name' => 'Trần Thị Hương Giang', 'role' => 'Chuyên Viên Nhân Sự (Che Lương)', 'scope' => 'ALL', 'badge_color' => 'indigo', 'icon' => 'fa-id-card-clip'],
                            ['email' => 'cb@wincommerce.vn', 'name' => 'Phạm Thu Thảo', 'role' => 'Chuyên Viên C&B Chuỗi', 'scope' => 'ALL', 'badge_color' => 'emerald', 'icon' => 'fa-money-bill-wave'],
                            ['email' => 'mienbac@wincommerce.vn', 'name' => 'Trần Quốc Bảo', 'role' => 'GĐ Chi Nhánh Miền Bắc', 'scope' => 'BRANCH', 'badge_color' => 'sky', 'icon' => 'fa-building-user'],
                            ['email' => 'long.vu@wincommerce.vn', 'name' => 'Vũ Hoàng Long', 'role' => 'Trưởng Phòng CNTT & Số Hóa', 'scope' => 'DEPT', 'badge_color' => 'orange', 'icon' => 'fa-sitemap'],
                            ['email' => 'ngoc.do@wincommerce.vn', 'name' => 'Đỗ Thị Bích Ngọc', 'role' => 'Nhân Viên Tech Lead (ESS)', 'scope' => 'SELF', 'badge_color' => 'purple', 'icon' => 'fa-user'],
                        ];
                        $curr_user_email = $_SESSION['user']['email'] ?? '';
                        $curr_user_name = $_SESSION['user']['username'] ?? '';
                        $current_redirect = urlencode($_SERVER['REQUEST_URI']);
                        ?>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/60 max-h-80 overflow-y-auto">
                            <?php foreach ($personas_list as $persona_item): 
                                $is_current = ($curr_user_email === $persona_item['email'] || $curr_user_name === $persona_item['email']);
                            ?>
                                <a href="<?= base_url('demo_switch.php?account=' . urlencode($persona_item['email']) . '&redirect=' . $current_redirect) ?>" 
                                   class="block px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/70 transition <?= $is_current ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-l-2 border-indigo-600' : '' ?>">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <i class="fa-solid <?= $persona_item['icon'] ?> text-[11px] text-slate-500"></i>
                                            <?= e($persona_item['name']) ?>
                                        </span>
                                        <span class="text-[9px] px-1 py-0.2 rounded font-extrabold uppercase font-mono bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300"><?= $persona_item['scope'] ?></span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center justify-between">
                                        <span><?= e($persona_item['role']) ?></span>
                                        <?php if ($is_current): ?>
                                            <span class="text-indigo-600 dark:text-indigo-400 font-bold text-[10px] flex items-center gap-0.5">
                                                <i class="fa-solid fa-check"></i> Đang xem
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; 
                            unset($persona_item, $personas_list, $curr_user_email, $curr_user_name, $current_redirect, $is_current);
                            ?>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-700 px-3 py-2 bg-slate-50 dark:bg-slate-900/60">
                            <form method="POST" action="<?= base_url('demo_switch.php') ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action_type" value="reset_demo">
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                                <button type="submit"
                                        class="w-full py-1.5 px-2.5 rounded bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-rotate"></i>
                                    <span>Khôi Phục Dữ Liệu Demo</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Khối thông tin User & Avatar Dropdown -->
            <div class="relative" id="userMenuDropdownContainer">
                <button type="button" id="userMenuBtn" class="flex items-center gap-2.5 p-1.5 rounded-sm hover:bg-slate-100 dark:hover:bg-slate-700/60 transition focus:outline-none border border-transparent hover:border-slate-200 dark:hover:border-slate-600">
                    <div class="text-right hidden lg:block">
                        <div class="text-sm font-bold text-slate-900 dark:text-white leading-tight">
                            <?= e($user['fullname']) ?>
                        </div>
                        <div class="text-xs text-slate-600 dark:text-slate-300 flex items-center justify-end gap-1.5 mt-0.5">
                            <?php if (!empty($user['is_superadmin'])): ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-extrabold bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
                                    <i class="fa-solid fa-crown text-[9px] mr-1 text-amber-600 dark:text-amber-400"></i> Super Admin
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-900 dark:text-indigo-200 border border-indigo-300 dark:border-indigo-700">
                                    <i class="fa-solid fa-user-shield text-[9px] mr-1 text-indigo-600 dark:text-indigo-400"></i> <?= e($user['role_name'] ?? 'Admin Phân Hệ') ?>
                                </span>
                            <?php endif; ?>
                            <?php 
                            $curr_scope = data_scope();
                            $scope_badge = [
                                'all' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/60', 'text' => 'text-emerald-800 dark:text-emerald-200', 'border' => 'border-emerald-300 dark:border-emerald-700', 'label' => 'ALL'],
                                'branch' => ['bg' => 'bg-sky-100 dark:bg-sky-900/60', 'text' => 'text-sky-800 dark:text-sky-200', 'border' => 'border-sky-300 dark:border-sky-700', 'label' => 'BRANCH'],
                                'department' => ['bg' => 'bg-blue-100 dark:bg-blue-900/60', 'text' => 'text-blue-800 dark:text-blue-200', 'border' => 'border-blue-300 dark:border-blue-700', 'label' => 'DEPT'],
                                'self' => ['bg' => 'bg-purple-100 dark:bg-purple-900/60', 'text' => 'text-purple-800 dark:text-purple-200', 'border' => 'border-purple-300 dark:border-purple-700', 'label' => 'SELF']
                            ][$curr_scope] ?? ['bg' => 'bg-slate-100 dark:bg-slate-700', 'text' => 'text-slate-800 dark:text-slate-200', 'border' => 'border-slate-300', 'label' => strtoupper($curr_scope)];
                            ?>
                            <span class="inline-flex items-center px-1 py-0.2 rounded-sm text-[9px] font-extrabold uppercase font-mono <?= $scope_badge['bg'] ?> <?= $scope_badge['text'] ?> border <?= $scope_badge['border'] ?>" title="Phạm vi dữ liệu: <?= $curr_scope ?>">
                                <?= $scope_badge['label'] ?>
                            </span>
                        </div>
                    </div>

                    <?= render_avatar($user['fullname'], $user['avatar'] ?? null, 9) ?>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-500 dark:text-slate-400 mr-1 hidden lg:inline"></i>
                </button>

                <!-- Dropdown Menu (Flat Metro) -->
                <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-sm border border-slate-300 dark:border-slate-700 py-2 z-50 animate-scale-up">
                    <div class="px-4 py-2 border-b border-slate-200 dark:border-slate-700 sm:hidden">
                        <div class="font-bold text-sm text-slate-900 dark:text-white"><?= e($user['fullname']) ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400"><?= e($user['username']) ?></div>
                    </div>
                    <a href="<?= base_url('profile.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-slate-700/70 transition">
                        <i class="fa-regular fa-user-circle text-base text-indigo-600 dark:text-indigo-400 w-5 text-center"></i>
                        <span>Hồ Sơ Cá Nhân</span>
                    </a>
                    <?php if (is_superadmin() || has_permission('matrix', 'manage')): ?>
                        <a href="<?= base_url('modules/matrix/index.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-slate-700/70 transition">
                            <i class="fa-solid fa-sliders text-base text-amber-600 dark:text-amber-400 w-5 text-center"></i>
                            <span>Phân Quyền Ma Trận</span>
                        </a>
                    <?php endif; ?>
                    <div class="border-t border-slate-200 dark:border-slate-700 my-1"></div>
                    <a href="<?= base_url('logout.php') ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition">
                        <i class="fa-solid fa-arrow-right-from-bracket text-base w-5 text-center"></i>
                        <span>Đăng Xuất</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Khu vực nội dung cuộn (Main Body Container) -->
    <main class="flex-1 overflow-y-auto px-4 sm:px-6 py-5 bg-slate-50 dark:bg-slate-900 transition-colors duration-200">
        <?php if (is_demo_mode()):
            $demoRoleId = (int)($user['role_id'] ?? 0);
            $demoScope = data_scope();
            if (is_superadmin()) {
                $demoBadge = 'Super Admin • Scope ALL';
                $demoPitch = 'Toàn cảnh 170+ nhân sự, quỹ lương toàn chuỗi, phê duyệt tối cao & Ma trận phân quyền RBAC.';
                $demoShortcuts = [
                    ['label' => 'Quy Hoạch Nhân Sự', 'url' => base_url('modules/planning/index.php'), 'icon' => 'fa-compass-drafting', 'tone' => 'indigo'],
                    ['label' => 'Bảng Lương Chuỗi', 'url' => base_url('modules/payroll/index.php'), 'icon' => 'fa-money-check-dollar', 'tone' => 'emerald'],
                    ['label' => 'Ma Trận RBAC', 'url' => base_url('modules/matrix/index.php'), 'icon' => 'fa-shield-halved', 'tone' => 'amber'],
                ];
            } elseif ($demoRoleId === 3) {
                $demoBadge = 'HR Executive • Scope ALL (Che Lương)';
                $demoPitch = 'Phân tách nhiệm vụ (SoD): HR xem 172 hồ sơ, hợp đồng & tuyển dụng nhưng KHÔNG truy cập được dữ liệu lương.';
                $demoShortcuts = [
                    ['label' => 'Hồ Sơ Nhân Sự', 'url' => base_url('modules/employees/index.php'), 'icon' => 'fa-users', 'tone' => 'indigo'],
                    ['label' => 'Tuyển Dụng', 'url' => base_url('modules/recruitment/index.php'), 'icon' => 'fa-user-plus', 'tone' => 'emerald'],
                    ['label' => 'Test Chặn Lương 403', 'url' => base_url('modules/payroll/index.php'), 'icon' => 'fa-ban', 'tone' => 'rose'],
                ];
            } elseif ($demoRoleId === 5) {
                $demoBadge = 'C&B Specialist • Scope ALL';
                $demoPitch = 'Phân hệ C&B: tính lương Net, thuế TNCN 5 bậc (NQ 110/2025), BHXH đa vùng I-IV & khóa kỳ lương.';
                $demoShortcuts = [
                    ['label' => 'Tính Bảng Lương', 'url' => base_url('modules/payroll/index.php'), 'icon' => 'fa-calculator', 'tone' => 'emerald'],
                    ['label' => 'Bảng Chấm Công', 'url' => base_url('modules/attendance/index.php'), 'icon' => 'fa-calendar-check', 'tone' => 'indigo'],
                    ['label' => 'Test Chặn RBAC 403', 'url' => base_url('modules/matrix/index.php'), 'icon' => 'fa-ban', 'tone' => 'rose'],
                ];
            } elseif ($demoScope === 'branch') {
                $demoBadge = 'Quản Lý Chi Nhánh • Scope BRANCH';
                $demoPitch = 'Cách ly theo Chi nhánh: chỉ thấy nhân sự, chấm công, quy hoạch & KPI thuộc Chi nhánh Miền Bắc.';
                $demoShortcuts = [
                    ['label' => 'Nhân Sự Miền Bắc', 'url' => base_url('modules/employees/index.php'), 'icon' => 'fa-building-user', 'tone' => 'indigo'],
                    ['label' => 'Đánh Giá KPI Team', 'url' => base_url('modules/evaluations/index.php'), 'icon' => 'fa-chart-line', 'tone' => 'emerald'],
                    ['label' => 'Test Chặn 403 (Xem HS CEO)', 'url' => base_url('modules/employees/view.php?id=1'), 'icon' => 'fa-shield-cat', 'tone' => 'rose'],
                ];
            } elseif ($demoScope === 'department') {
                $demoBadge = 'Trưởng Phòng Ban • Scope DEPT';
                $demoPitch = 'Cách ly theo Phòng ban: chỉ thấy 14 nhân sự Khối CNTT & Số Hóa, duyệt đơn & chấm điểm KPI cấp phòng.';
                $demoShortcuts = [
                    ['label' => 'Team Khối CNTT', 'url' => base_url('modules/employees/index.php'), 'icon' => 'fa-sitemap', 'tone' => 'indigo'],
                    ['label' => 'Duyệt Đơn Phòng', 'url' => base_url('modules/proposals/index.php'), 'icon' => 'fa-inbox', 'tone' => 'emerald'],
                    ['label' => 'Test Chặn 403 (Xem HS CEO)', 'url' => base_url('modules/employees/view.php?id=1'), 'icon' => 'fa-shield-cat', 'tone' => 'rose'],
                ];
            } else {
                $demoBadge = 'Nhân Viên ESS • Scope SELF';
                $demoPitch = 'Cổng tự phục vụ ESS: Check-in hàng ngày, xem phiếu lương cá nhân, nộp đơn & tự đánh giá KPI.';
                $demoShortcuts = [
                    ['label' => 'Chấm Công Cá Nhân', 'url' => base_url('modules/attendance/index.php'), 'icon' => 'fa-fingerprint', 'tone' => 'indigo'],
                    ['label' => 'Phiếu Lương Của Tôi', 'url' => base_url('modules/payroll/index.php'), 'icon' => 'fa-file-invoice-dollar', 'tone' => 'emerald'],
                    ['label' => 'Test Chặn 403 (Xem HS CEO)', 'url' => base_url('modules/employees/view.php?id=1'), 'icon' => 'fa-shield-cat', 'tone' => 'rose'],
                ];
            }
            $toneClasses = [
                'indigo' => 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/70 dark:text-indigo-300 dark:border-indigo-800',
                'emerald' => 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/70 dark:text-emerald-300 dark:border-emerald-800',
                'amber' => 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:hover:bg-amber-900/70 dark:text-amber-300 dark:border-amber-700',
                'rose' => 'bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:hover:bg-rose-900/70 dark:text-rose-300 dark:border-rose-800',
            ];
        ?>
            <!-- Interactive Demo Presenter Bar -->
            <div id="demoPresenterBar" class="mb-4 rounded-xl bg-amber-50/90 dark:bg-slate-800/95 border border-amber-200 dark:border-amber-700/60 px-3.5 py-2.5 shadow-2xs transition-all">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500 text-white text-[10px] font-extrabold uppercase tracking-wider flex-shrink-0">
                            <i class="fa-solid fa-tv"></i> Live Demo
                        </span>
                        <span class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= e($demoBadge) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="toggleDemoPresenterBarBtn"
                                class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 hover:underline flex items-center gap-1 cursor-pointer">
                            <span id="demoPresenterBarToggleText">Thu gọn</span>
                            <i id="demoPresenterBarToggleIcon" class="fa-solid fa-chevron-up text-[10px]"></i>
                        </button>
                    </div>
                </div>
                <div id="demoPresenterBarBody" class="mt-2 pt-2 border-t border-amber-200/70 dark:border-slate-700 flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
                    <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                        <i class="fa-solid fa-microphone-lines text-amber-600 dark:text-amber-400 mr-1"></i>
                        <?= e($demoPitch) ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-1.5 flex-shrink-0">
                        <?php foreach ($demoShortcuts as $sc): ?>
                            <a href="<?= e($sc['url']) ?>"
                               class="px-2.5 py-1 rounded-lg border text-[11px] font-bold transition inline-flex items-center gap-1 <?= $toneClasses[$sc['tone']] ?? $toneClasses['indigo'] ?>">
                                <i class="fa-solid <?= e($sc['icon']) ?> text-[10px]"></i>
                                <span><?= e($sc['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <script>
            (function() {
                var body = document.getElementById('demoPresenterBarBody');
                var btn = document.getElementById('toggleDemoPresenterBarBtn');
                var txt = document.getElementById('demoPresenterBarToggleText');
                var icon = document.getElementById('demoPresenterBarToggleIcon');
                if (!body || !btn) return;
                function applyState(collapsed) {
                    body.classList.toggle('hidden', collapsed);
                    if (txt) txt.textContent = collapsed ? 'Mở hướng dẫn' : 'Thu gọn';
                    if (icon) icon.className = collapsed ? 'fa-solid fa-chevron-down text-[10px]' : 'fa-solid fa-chevron-up text-[10px]';
                }
                applyState(localStorage.getItem('hrms_demo_bar_collapsed') === '1');
                btn.addEventListener('click', function() {
                    var next = !body.classList.contains('hidden');
                    localStorage.setItem('hrms_demo_bar_collapsed', next ? '1' : '0');
                    applyState(next);
                });
            })();
            </script>
        <?php endif; ?>

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