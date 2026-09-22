<?php
// modules/orgchart/index.php - Sơ Đồ Cơ Cấu Tổ Chức & Mạng Lưới Chi Nhánh Toàn Cơ Quan
$page_title = 'Sơ Đồ Cơ Cấu Tổ Chức';

require_once __DIR__ . '/../../core/auth.php';
require_permission('orgchart', 'view');

// Lọc theo chi nhánh cụ thể (hoặc toàn bộ)
$selected_branch_id = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : 0;
$view_mode = $_GET['view'] ?? 'tree'; // tree hoặc grid

// 1. Lấy danh sách tất cả các chi nhánh
$branches_query = "
    SELECT b.*, 
           m.fullname AS manager_name, 
           m.employee_code AS manager_code, 
           m.avatar AS manager_avatar,
           p.name AS manager_pos_name,
           COUNT(DISTINCT e.id) AS total_employees,
           COUNT(DISTINCT d.id) AS total_departments
    FROM branches b
    LEFT JOIN employees m ON b.manager_id = m.id
    LEFT JOIN positions p ON m.position_id = p.id
    LEFT JOIN employees e ON b.id = e.branch_id AND e.employment_status != 'resigned'
    LEFT JOIN departments d ON b.id = d.branch_id
    WHERE b.status = 'active'
";
if ($selected_branch_id > 0) {
    $branches_query .= " AND b.id = " . $selected_branch_id;
}
$branches_query .= " GROUP BY b.id ORDER BY b.is_headquarter DESC, b.id ASC";

$branches = $pdo->query($branches_query)->fetchAll();

// 2. Lấy toàn bộ phòng ban
$depts_stmt = $pdo->query("
    SELECT d.*, 
           b.name AS branch_name, 
           b.code AS branch_code,
           m.fullname AS manager_name,
           m.employee_code AS manager_code,
           COUNT(e.id) AS total_employees
    FROM departments d
    JOIN branches b ON d.branch_id = b.id
    LEFT JOIN employees m ON d.manager_id = m.id
    LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status != 'resigned'
    GROUP BY d.id
    ORDER BY d.branch_id ASC, d.id ASC
");
$departments_all = $depts_stmt->fetchAll();

// Gom nhóm phòng ban theo branch_id
$departments_by_branch = [];
foreach ($departments_all as $d) {
    $departments_by_branch[$d['branch_id']][] = $d;
}

// 3. Lấy toàn bộ nhân viên đang hoạt động
$emp_stmt = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.avatar, e.branch_id, e.department_id, e.position_id, e.hire_date, e.employment_status,
           p.name AS position_name, p.base_salary,
           d.name AS department_name,
           b.name AS branch_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE e.employment_status != 'resigned'
    ORDER BY p.base_salary DESC, e.fullname ASC
");
$employees_all = $emp_stmt->fetchAll();

// Gom nhóm nhân sự theo department_id
$employees_by_dept = [];
foreach ($employees_all as $emp) {
    $employees_by_dept[$emp['department_id']][] = $emp;
}

// Danh sách tất cả chi nhánh để làm dropdown bộ lọc
$all_branches_filter = $pdo->query("SELECT id, name, code, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();

// Tổng số liệu toàn cơ quan
$total_employees_count = count($employees_all);
$total_branches_count = count($all_branches_filter);
$total_depts_count = count($departments_all);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Thanh Điều Khiển Sơ Đồ -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 mb-2">
                <i class="fa-solid fa-sitemap"></i> Cấu Trúc Đơn Vị & Mạng Lưới Tổ Chức
            </div>
            <h2 class="text-xl font-bold text-slate-800">Cơ Cấu Tổ Chức Toàn Cơ Quan</h2>
            <p class="text-sm text-slate-500 mt-0.5">Trực quan hóa mô hình quản trị đa chi nhánh, phân cấp lãnh đạo, các khối phòng ban và cán bộ chuyên trách.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Bộ lọc chi nhánh -->
            <form method="GET" action="index.php" class="flex items-center gap-2">
                <input type="hidden" name="view" value="<?= e($view_mode) ?>">
                <select name="branch_id" onchange="this.form.submit()"
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="0">🌐 Toàn Bộ Cơ Quan (Tất cả chi nhánh)</option>
                    <?php foreach ($all_branches_filter as $bf): ?>
                        <option value="<?= $bf['id'] ?>" <?= $selected_branch_id == $bf['id'] ? 'selected' : '' ?>>
                            <?= $bf['is_headquarter'] ? '★ ' : '• ' ?><?= e($bf['name']) ?> (<?= e($bf['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Chuyển đổi chế độ xem Cây / Thẻ -->
            <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
                <a href="?branch_id=<?= $selected_branch_id ?>&view=tree" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $view_mode === 'tree' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">
                    <i class="fa-solid fa-diagram-project mr-1"></i> Cây Phân Cấp
                </a>
                <a href="?branch_id=<?= $selected_branch_id ?>&view=grid" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $view_mode === 'grid' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">
                    <i class="fa-solid fa-table-cells-large mr-1"></i> Mạng Lưới Thẻ
                </a>
            </div>

            <!-- Nút Thao Tác Nhanh -->
            <a href="<?= base_url('modules/transfers/create.php') ?>" 
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-people-arrows"></i>
                <span>Thuyên Chuyển Cán Bộ</span>
            </a>

            <button onclick="window.print()" 
                    class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition"
                    title="In Sơ Đồ Tổ Chức">
                <i class="fa-solid fa-print text-sm"></i>
            </button>
        </div>
    </div>

    <!-- Thanh Thống Kê Nhanh -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Chi Nhánh</span>
                <strong class="text-slate-800 font-bold text-sm"><?= $total_branches_count ?> Chi Nhánh</strong>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Khối Phòng Ban</span>
                <strong class="text-slate-800 font-bold text-sm"><?= $total_depts_count ?> Phòng / Ban</strong>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Tổng Cán Bộ</span>
                <strong class="text-slate-800 font-bold text-sm"><?= $total_employees_count ?> Nhân Sự</strong>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-shuffle"></i>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Điều Động Liên Tục</span>
                <a href="<?= base_url('modules/transfers/index.php') ?>" class="text-indigo-600 font-bold text-sm hover:underline">
                    Xem Lịch Sử &rarr;
                </a>
            </div>
        </div>
    </div>

    <?php if ($view_mode === 'tree'): ?>
        <!-- ================= CHẾ ĐỘ 1: CÂY SƠ ĐỒ TỔ CHỨC HIỆN ĐẠI ================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 overflow-x-auto min-h-[600px] select-none">
            
            <div class="min-w-[900px] flex flex-col items-center py-4 space-y-8">

                <!-- 1. Node Đỉnh: BAN LÃNH ĐẠO / CƠ QUAN TRUNG ƯƠNG -->
                <div class="flex flex-col items-center">
                    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white px-8 py-5 rounded-3xl shadow-xl shadow-slate-200 border border-slate-800 flex items-center gap-4 cursor-pointer transform hover:-translate-y-1 transition duration-200"
                         onclick="openDetailDrawer('agency', 0, 'CƠ QUAN TRUNG ƯƠNG', 'Trụ sở điều hành cao nhất', '<?= $total_employees_count ?> cán bộ')">
                        <div class="w-12 h-12 rounded-2xl bg-amber-400 text-slate-950 flex items-center justify-center font-bold text-xl shadow-md">
                            <i class="fa-solid fa-landmark"></i>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-widest text-amber-300 font-bold">Ban Lãnh Đạo & Điều Hành Cao Nhất</div>
                            <div class="text-lg font-black tracking-tight text-white">TRUNG TÂM ĐIỀU HÀNH CƠ QUAN</div>
                            <div class="text-xs text-slate-300 mt-0.5">
                                <?= $total_branches_count ?> Chi Nhánh Toàn Quốc • <?= $total_employees_count ?> Cán Bộ Công Chức
                            </div>
                        </div>
                    </div>

                    <!-- Đường line dọc nối xuống các chi nhánh -->
                    <div class="w-0.5 h-10 bg-indigo-300"></div>
                </div>

                <!-- 2. Tầng Chi Nhánh: Ngang nối nhánh -->
                <div class="w-full relative">
                    <!-- Đường ngang kết nối các chi nhánh -->
                    <?php if (count($branches) > 1): ?>
                        <div class="absolute top-0 left-16 right-16 h-0.5 bg-indigo-200 -z-0"></div>
                    <?php endif; ?>

                    <div class="flex items-start justify-center gap-8 relative z-10">
                        <?php foreach ($branches as $b): ?>
                            <?php 
                                $b_depts = $departments_by_branch[$b['id']] ?? [];
                                $fill_percent = $b['target_headcount'] > 0 ? round(($b['total_employees'] / $b['target_headcount']) * 100) : 0;
                            ?>
                            <div class="flex flex-col items-center flex-1 max-w-sm">
                                
                                <!-- Đường kẻ đứng nối từ đường ngang xuống thẻ chi nhánh -->
                                <div class="w-0.5 h-6 bg-indigo-200"></div>

                                <!-- Thẻ Chi Nhánh (Branch Node) -->
                                <div class="w-full bg-white rounded-2xl border-2 <?= $b['is_headquarter'] ? 'border-indigo-600 shadow-lg shadow-indigo-100' : 'border-slate-300 shadow-sm' ?> p-4 hover:border-indigo-400 hover:shadow-md transition cursor-pointer"
                                     onclick="openBranchDrawer(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                    
                                    <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-100">
                                        <div class="flex items-center gap-2 truncate">
                                            <span class="w-7 h-7 rounded-lg <?= $b['is_headquarter'] ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' ?> flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                <?= $b['is_headquarter'] ? '<i class="fa-solid fa-crown text-[10px] text-amber-300"></i>' : '<i class="fa-solid fa-building text-[10px]"></i>' ?>
                                            </span>
                                            <span class="font-bold text-slate-800 text-xs truncate"><?= e($b['name']) ?></span>
                                        </div>
                                        <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold"><?= e($b['code']) ?></span>
                                    </div>

                                    <div class="pt-2.5 space-y-1.5 text-[11px] text-slate-600">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Trưởng Đơn Vị:</span>
                                            <strong class="text-slate-800 truncate"><?= e($b['manager_name'] ?? 'Chưa bổ nhiệm') ?></strong>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-400">Biên Chế:</span>
                                            <div class="font-mono">
                                                <strong class="text-slate-900"><?= $b['total_employees'] ?></strong> / <?= $b['target_headcount'] ?>
                                                <span class="font-bold ml-1 <?= $fill_percent >= 80 ? 'text-emerald-600' : 'text-amber-600' ?>">(<?= $fill_percent ?>%)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Nút Thao Tác Trên Thẻ Chi Nhánh -->
                                    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                        <span class="text-indigo-600 font-semibold"><?= count($b_depts) ?> Phòng Ban</span>
                                        <button onclick="event.stopPropagation(); quickTransferTo(<?= $b['id'] ?>)" 
                                                class="px-2 py-1 rounded-md bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold transition text-[10px] flex items-center gap-1">
                                            <i class="fa-solid fa-shuffle"></i> Thuyên chuyển đến
                                        </button>
                                    </div>

                                </div>

                                <!-- Đường kẻ xuống các phòng ban -->
                                <?php if (!empty($b_depts)): ?>
                                    <div class="w-0.5 h-6 bg-slate-300"></div>

                                    <!-- Danh Sách Các Phòng Ban Trực Thuộc Chi Nhánh -->
                                    <div class="w-full space-y-3">
                                        <?php foreach ($b_depts as $dept): ?>
                                            <?php $d_emps = $employees_by_dept[$dept['id']] ?? []; ?>
                                            
                                            <div class="w-full bg-slate-50 hover:bg-white rounded-xl border border-slate-200 p-3 hover:border-indigo-300 hover:shadow-sm transition cursor-pointer"
                                                 onclick="openDeptDrawer(<?= htmlspecialchars(json_encode($dept), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($d_emps), ENT_QUOTES, 'UTF-8') ?>)">
                                                
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-semibold text-slate-800 text-xs truncate"><?= e($dept['name']) ?></span>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 flex-shrink-0">
                                                        <?= count($d_emps) ?> NV
                                                    </span>
                                                </div>

                                                <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
                                                    <span>Mã: <strong class="font-mono text-slate-600"><?= e($dept['code']) ?></strong></span>
                                                    <span class="text-indigo-600 hover:underline">Chi tiết & cán bộ &rarr;</span>
                                                </div>

                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>

    <?php else: ?>
        <!-- ================= CHẾ ĐỘ 2: MẠNG LƯỚI THẺ CHI TIẾT (GRID CARDS) ================= -->
        <div class="space-y-6">
            <?php foreach ($branches as $b): ?>
                <?php $b_depts = $departments_by_branch[$b['id']] ?? []; ?>
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl <?= $b['is_headquarter'] ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' ?> flex items-center justify-center font-bold text-base">
                                <i class="fa-solid fa-building-flag"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                                    <span><?= e($b['name']) ?></span>
                                    <?php if ($b['is_headquarter']): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">Trụ Sở Chính</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-xs text-slate-400">Trưởng đơn vị: <strong class="text-slate-700"><?= e($b['manager_name'] ?? 'Chưa bổ nhiệm') ?></strong> • Hiện diện: <strong class="text-indigo-600"><?= $b['total_employees'] ?></strong> cán bộ</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="<?= base_url('modules/transfers/create.php?to_branch_id=' . $b['id']) ?>" 
                               class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition flex items-center gap-1 border border-indigo-100">
                                <i class="fa-solid fa-people-arrows text-[10px]"></i>
                                <span>Thuyên chuyển đến chi nhánh</span>
                            </a>
                        </div>
                    </div>

                    <!-- Grid Các Khối Phòng Ban -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                        <?php if (empty($b_depts)): ?>
                            <div class="col-span-full text-center py-6 text-slate-400 text-xs">Chi nhánh này chưa có phòng ban trực thuộc.</div>
                        <?php endif; ?>
                        
                        <?php foreach ($b_depts as $dept): ?>
                            <?php $d_emps = $employees_by_dept[$dept['id']] ?? []; ?>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 space-y-3 hover:border-indigo-300 transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-xs"><?= e($dept['name']) ?></h4>
                                        <span class="text-[10px] font-mono text-slate-400 font-semibold">Mã: <?= e($dept['code']) ?></span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800">
                                        <?= count($d_emps) ?> NV
                                    </span>
                                </div>

                                <!-- Danh sách cán bộ tiêu biểu -->
                                <div class="space-y-1.5 text-xs max-h-36 overflow-y-auto pr-1 divide-y divide-slate-100">
                                    <?php if (empty($d_emps)): ?>
                                        <div class="text-[11px] text-slate-400 py-1">Chưa có nhân sự phân bổ.</div>
                                    <?php endif; ?>
                                    <?php foreach ($d_emps as $emp): ?>
                                        <div class="flex items-center justify-between py-1.5 text-[11px]">
                                            <div class="flex items-center gap-2 truncate">
                                                <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[9px] flex-shrink-0">
                                                    <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                                                </div>
                                                <a href="<?= base_url('modules/employees/view.php?id=' . $emp['id']) ?>" class="font-semibold text-slate-700 hover:text-indigo-600 truncate">
                                                    <?= e($emp['fullname']) ?>
                                                </a>
                                            </div>
                                            <span class="text-slate-400 text-[10px] font-mono flex-shrink-0"><?= e($emp['position_name']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                                    <a href="<?= base_url('modules/employees/index.php?department_id=' . $dept['id']) ?>" class="text-slate-500 hover:text-slate-800">
                                        Xem tất cả &rarr;
                                    </a>
                                    <a href="<?= base_url('modules/transfers/create.php?to_department_id=' . $dept['id'] . '&to_branch_id=' . $b['id']) ?>" 
                                       class="text-indigo-600 font-semibold hover:underline">
                                        + Điều động vào
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Drawer / Modal Trượt Bên Phải Chi Tiết Đơn Vị & Cán Bộ -->
<div id="orgDrawer" class="fixed inset-0 z-50 overflow-hidden hidden">
    <div class="absolute inset-0 bg-slate-900/30 backdrop-blur-xs transition-opacity" onclick="closeOrgDrawer()"></div>
    <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
        <div class="w-screen max-w-md bg-white shadow-2xl p-6 flex flex-col justify-between overflow-y-auto">
            
            <div class="space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div id="drawerIcon" class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">
                            <i class="fa-solid fa-sitemap"></i>
                        </div>
                        <div>
                            <span id="drawerBadge" class="text-[10px] uppercase font-bold text-indigo-600 block">Thông Tin Đơn Vị</span>
                            <h3 id="drawerTitle" class="font-bold text-slate-800 text-base leading-snug">Chi Tiết</h3>
                        </div>
                    </div>
                    <button onclick="closeOrgDrawer()" class="text-slate-400 hover:text-slate-600 text-xl">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div id="drawerBody" class="space-y-4 text-xs">
                    <!-- Nội dung nạp động từ JS -->
                </div>
            </div>

            <div id="drawerFooter" class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="closeOrgDrawer()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Đóng
                </button>
            </div>

        </div>
    </div>
</div>

<script>
function quickTransferTo(branchId) {
    window.location.href = '<?= base_url('modules/transfers/create.php?to_branch_id=') ?>' + branchId;
}

function openBranchDrawer(branch) {
    document.getElementById('drawerBadge').innerText = branch.is_headquarter ? 'TRỤ SỞ CHÍNH CƠ QUAN' : 'CHI NHÁNH TRỰC THUỘC';
    document.getElementById('drawerTitle').innerText = branch.name;
    
    let html = `
        <div class="p-3 bg-slate-50 rounded-xl space-y-1.5 border border-slate-100">
            <div class="flex justify-between"><span class="text-slate-400">Mã Chi Nhánh:</span><strong class="font-mono text-slate-800">${branch.code}</strong></div>
            <div class="flex justify-between"><span class="text-slate-400">Trưởng Đơn Vị:</span><strong class="text-slate-800">${branch.manager_name || 'Chưa bổ nhiệm'}</strong></div>
            <div class="flex justify-between"><span class="text-slate-400">Địa Chỉ:</span><span class="text-slate-700 text-right">${branch.address || 'Chưa cập nhật'}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Điện Thoại:</span><span class="text-slate-700">${branch.phone || '---'}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Định Biên Nhân Lực:</span><strong class="text-indigo-600 font-mono">${branch.total_employees} / ${branch.target_headcount} cán bộ</strong></div>
        </div>
        
        <div class="pt-3">
            <a href="<?= base_url('modules/transfers/create.php?to_branch_id=') ?>${branch.id}" 
               class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold text-center block shadow-xs transition">
                <i class="fa-solid fa-people-arrows mr-1"></i> Điều Động Cán Bộ Đến Chi Nhánh Này
            </a>
        </div>
    `;
    
    document.getElementById('drawerBody').innerHTML = html;
    document.getElementById('orgDrawer').classList.remove('hidden');
}

function openDeptDrawer(dept, employees) {
    document.getElementById('drawerBadge').innerText = 'KHỐI PHÒNG BAN CHỨC NĂNG';
    document.getElementById('drawerTitle').innerText = dept.name;
    
    let empListHtml = '';
    if (employees && employees.length > 0) {
        employees.forEach(e => {
            empListHtml += `
                <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 border border-slate-100 hover:bg-white transition">
                    <div class="flex items-center gap-2.5 truncate">
                        <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">
                            ${e.fullname.substring(0, 1).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-bold text-slate-800 leading-tight">${e.fullname}</div>
                            <div class="text-[10px] text-slate-400 font-mono">${e.position_name || 'Cán bộ'} • ${e.employee_code}</div>
                        </div>
                    </div>
                    <a href="<?= base_url('modules/transfers/create.php?employee_id=') ?>${e.id}" 
                       class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-[10px] flex-shrink-0"
                       title="Thuyên chuyển nhân viên này">
                        Điều động &rarr;
                    </a>
                </div>
            `;
        });
    } else {
        empListHtml = '<div class="text-center py-4 text-slate-400">Phòng ban này hiện chưa có nhân sự phân bổ.</div>';
    }

    let html = `
        <div class="p-3 bg-slate-50 rounded-xl space-y-1.5 border border-slate-100">
            <div class="flex justify-between"><span class="text-slate-400">Mã Phòng Ban:</span><strong class="font-mono text-slate-800">${dept.code}</strong></div>
            <div class="flex justify-between"><span class="text-slate-400">Chi Nhánh Trực Thuộc:</span><strong class="text-slate-800">${dept.branch_name}</strong></div>
            <div class="flex justify-between"><span class="text-slate-400">Số Lượng Nhân Sự:</span><strong class="text-indigo-600 font-mono">${employees.length} cán bộ</strong></div>
        </div>

        <div class="space-y-2 pt-2">
            <div class="flex justify-between items-center">
                <span class="font-bold text-slate-700 text-xs">Danh Sách Cán Bộ Trực Thuộc (${employees.length})</span>
                <a href="<?= base_url('modules/transfers/create.php?to_department_id=') ?>${dept.id}&to_branch_id=${dept.branch_id}" 
                   class="text-indigo-600 font-semibold text-[11px] hover:underline">
                    + Thêm người vào phòng
                </a>
            </div>
            <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                ${empListHtml}
            </div>
        </div>
    `;

    document.getElementById('drawerBody').innerHTML = html;
    document.getElementById('orgDrawer').classList.remove('hidden');
}

function openDetailDrawer(type, id, title, desc, stat) {
    document.getElementById('drawerBadge').innerText = 'TỔNG QUAN ĐIỀU HÀNH';
    document.getElementById('drawerTitle').innerText = title;
    
    let html = `
        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
            <p class="text-slate-600 leading-relaxed">${desc}</p>
            <div class="p-2.5 bg-white rounded-xl border border-slate-200 flex justify-between items-center">
                <span class="text-slate-500">Quy mô nhân sự:</span>
                <strong class="text-indigo-600 font-mono text-sm">${stat}</strong>
            </div>
        </div>
        <div class="pt-3">
            <a href="<?= base_url('modules/planning/index.php') ?>" 
               class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold text-center block shadow-xs transition">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Xem Kế Hoạch & Đề Xuất Tối Ưu
            </a>
        </div>
    `;
    
    document.getElementById('drawerBody').innerHTML = html;
    document.getElementById('orgDrawer').classList.remove('hidden');
}

function closeOrgDrawer() {
    document.getElementById('orgDrawer').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
