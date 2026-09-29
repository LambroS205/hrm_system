<?php
// index.php - Màn hình Dashboard phân tích số liệu & trung tâm điều hành HRMS
$page_title = 'Bảng Điều Khiển & Thống Kê Nhân Sự';

require_once __DIR__ . '/core/auth.php';
require_login();

$user = current_user();
$today = date('Y-m-d');
$current_month = (int)date('m');
$current_year = (int)date('Y');

// 1. Thống kê nhân sự tổng quan
$total_employees = (int)($pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status != 'resigned'")->fetchColumn() ?: 0);
$total_official = (int)($pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'official'")->fetchColumn() ?: 0);
$total_probation = (int)($pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'probation'")->fetchColumn() ?: 0);
$total_departments = (int)($pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn() ?: 0);
$total_branches = (int)($pdo->query("SELECT COUNT(*) FROM branches WHERE status = 'active'")->fetchColumn() ?: 0);

// 2. Thống kê chấm công trong ngày hôm nay
$att_today_stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status IN ('late', 'early_leave') THEN 1 END) as late_count,
        COUNT(CASE WHEN status = 'leave_with_permit' THEN 1 END) as leave_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        COUNT(*) as total_logged
    FROM attendance 
    WHERE date = ?
");
$att_today_stmt->execute([$today]);
$att_today = $att_today_stmt->fetch() ?: [
    'present_count' => 0,
    'late_count' => 0,
    'leave_count' => 0,
    'absent_count' => 0,
    'total_logged' => 0
];

$attendance_rate = $total_employees > 0 ? round(($att_today['present_count'] / $total_employees) * 100) : 0;

// 3. Thống kê quỹ lương tháng hiện tại
$payroll_fund_stmt = $pdo->prepare("SELECT SUM(final_salary) as total_fund, SUM(CASE WHEN payment_status = 'paid' THEN final_salary ELSE 0 END) as paid_fund FROM payrolls WHERE month = ? AND year = ?");
$payroll_fund_stmt->execute([$current_month, $current_year]);
$fund_data = $payroll_fund_stmt->fetch();

$current_fund = (float)($fund_data['total_fund'] ?? 0);
$paid_fund = (float)($fund_data['paid_fund'] ?? 0);

// Nếu tháng này chưa bấm tính lương thì lấy mức dự toán theo ngạch bậc chức danh
if ($current_fund <= 0) {
    $current_fund = (float)($pdo->query("
        SELECT SUM(p.base_salary) 
        FROM employees e 
        JOIN positions p ON e.position_id = p.id 
        WHERE e.employment_status != 'resigned'
    ")->fetchColumn() ?: 0);
    $is_projected_fund = true;
} else {
    $is_projected_fund = false;
}

// 4. Cơ cấu nhân sự theo phòng ban (Chart Donut)
$dept_stats = $pdo->query("
    SELECT d.name, COUNT(e.id) as emp_count
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status != 'resigned'
    GROUP BY d.id
    ORDER BY emp_count DESC
")->fetchAll();

$dept_labels = [];
$dept_counts = [];
foreach ($dept_stats as $ds) {
    $dept_labels[] = $ds['name'];
    $dept_counts[] = (int)$ds['emp_count'];
}

// 5. Xu hướng chi trả lương trong 6 tháng gần nhất
$trend_labels = [];
$trend_values = [];

for ($i = 5; $i >= 0; $i--) {
    $t_time = strtotime("-{$i} month");
    $t_m = (int)date('m', $t_time);
    $t_y = (int)date('Y', $t_time);
    $trend_labels[] = "T{$t_m}/" . substr((string)$t_y, 2);

    $t_stmt = $pdo->prepare("SELECT SUM(final_salary) FROM payrolls WHERE month = ? AND year = ?");
    $t_stmt->execute([$t_m, $t_y]);
    $trend_val = (float)($t_stmt->fetchColumn() ?: 0);
    $trend_values[] = round($trend_val / 1000000, 1); // Đơn vị Triệu VNĐ
}

// 6. Danh sách 5 nhân sự mới tuyển dụng gần đây
$recent_employees = $pdo->query("
    SELECT e.*, d.name AS department_name, p.name AS position_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    ORDER BY e.hire_date DESC, e.id DESC
    LIMIT 5
")->fetchAll();

// 7. Thống kê đề xuất chờ phê duyệt
$pending_proposals_count = 0;
$pending_proposals = [];
if (has_permission('proposals', 'view') || has_permission('proposals', 'create')) {
    $pending_proposals_count = (int)($pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'pending'")->fetchColumn() ?: 0);
    $pending_stmt = $pdo->query("
        SELECT p.*, e.fullname, e.employee_code, e.avatar, d.name AS department_name
        FROM proposals p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE p.status = 'pending'
        ORDER BY p.id DESC
        LIMIT 5
    ");
    $pending_proposals = $pending_stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Nạp Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">

    <!-- Banner Chào Mừng Phong Cách Modern UI Cao Cấp -->
    <div class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-violet-700 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-indigo-100 dark:shadow-none flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 backdrop-blur-md mb-3 text-indigo-50">
                <i class="fa-solid fa-sparkles text-amber-300"></i> Trung Tâm Điều Hành Nhân Sự - Aura Retail Group
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Xin chào, <?= e($user['fullname']) ?>!</h2>
            <p class="text-indigo-100 text-xs sm:text-sm mt-1 max-w-2xl leading-relaxed">
                Hôm nay là <strong class="text-white"><?= date('l, d/m/Y') ?></strong>. Hệ thống AURA HRM đang quản lý 
                <strong class="text-amber-200"><?= $total_employees ?></strong> nhân sự trực thuộc 
                <strong class="text-amber-200"><?= $total_branches ?></strong> chi nhánh và 
                <strong class="text-amber-200"><?= $total_departments ?></strong> khối phòng ban chức năng.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0 relative z-10">
            <?php if (has_permission('orgchart', 'view')): ?>
                <a href="<?= base_url('modules/orgchart/index.php') ?>" 
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-md transition">
                    <i class="fa-solid fa-sitemap text-amber-300"></i>
                    <span>Sơ Đồ Cơ Cấu</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('transfers', 'view')): ?>
                <a href="<?= base_url('modules/transfers/index.php') ?>" 
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-md transition">
                    <i class="fa-solid fa-people-arrows text-sky-300"></i>
                    <span>Thuyên Chuyển</span>
                </a>
            <?php endif; ?>

            <?php if (is_superadmin()): ?>
                <a href="<?= base_url('modules/matrix/index.php') ?>" 
                   class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-md transition">
                    <i class="fa-solid fa-network-wired"></i>
                    <span>Ma Trận Quyền</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
                <a href="<?= base_url('modules/proposals/index.php') ?>" 
                   class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-md transition">
                    <i class="fa-solid fa-paper-plane text-amber-300"></i>
                    <span>Đề Xuất</span>
                    <?php if ($pending_proposals_count > 0): ?>
                        <span class="ml-1 px-1.5 py-0.5 rounded-full bg-amber-400 text-slate-900 text-[10px] font-black"><?= $pending_proposals_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission('attendance', 'create')): ?>
                <a href="<?= base_url('modules/attendance/index.php') ?>" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-indigo-700 hover:bg-indigo-50 font-semibold text-xs shadow-md transition">
                    <i class="fa-solid fa-clipboard-user"></i>
                    <span>Chấm Công Hôm Nay</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số KPI Trọng Điểm -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Tổng số nhân sự -->
        <div class="kpi-stripe-indigo bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm flex items-center justify-between card-hover">
            <div>
                <span class="kpi-label">Nhân Sự Đang Làm Việc</span>
                <div class="kpi-value mt-1"><?= $total_employees ?></div>
                <div class="text-xs text-emerald-700 dark:text-emerald-300 font-bold mt-1.5 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                    <span><?= $total_official ?> chính thức • <?= $total_probation ?> thử việc</span>
                </div>
            </div>
            <div class="w-13 h-13 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-md shadow-indigo-300 dark:shadow-none p-3">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <!-- KPI 2: Tỷ lệ đi làm hôm nay -->
        <div class="kpi-stripe-emerald bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm flex items-center justify-between card-hover">
            <div>
                <span class="kpi-label">Tỷ Lệ Có Mặt Hôm Nay</span>
                <div class="kpi-value text-emerald-600 dark:text-emerald-400 mt-1 font-mono"><?= $attendance_rate ?>%</div>
                <div class="text-xs text-slate-700 dark:text-slate-300 font-bold mt-1.5 flex items-center gap-1.5">
                    <i class="fa-regular fa-clock text-slate-500 dark:text-slate-400"></i>
                    <span><?= $att_today['present_count'] ?>/<?= $total_employees ?> nhân sự có mặt</span>
                </div>
            </div>
            <div class="w-13 h-13 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-300 dark:shadow-none p-3">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>

        <!-- KPI 3: Quỹ lương tháng này -->
        <div class="kpi-stripe-amber bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm flex items-center justify-between card-hover">
            <div>
                <span class="kpi-label">
                    <?= $is_projected_fund ? 'Dự Toán Quỹ Lương' : 'Tổng Quỹ Lương T' . $current_month ?>
                </span>
                <div class="kpi-value text-xl mt-1 font-mono"><?= format_money($current_fund) ?></div>
                <div class="text-xs <?= $is_projected_fund ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' ?> mt-1.5 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-coins text-amber-500"></i>
                    <span><?= $is_projected_fund ? 'Theo định mức chức vụ' : ('Đã chi: ' . format_money($paid_fund)) ?></span>
                </div>
            </div>
            <div class="w-13 h-13 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-xl shadow-md shadow-amber-300 dark:shadow-none p-3">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
        </div>

        <!-- KPI 4: Quy mô tổ chức -->
        <div class="kpi-stripe-sky bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm flex items-center justify-between card-hover">
            <div>
                <span class="kpi-label">Khối Phòng Ban</span>
                <div class="kpi-value mt-1"><?= $total_departments ?></div>
                <div class="text-xs text-sky-700 dark:text-sky-300 mt-1.5 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-sitemap text-sky-600 dark:text-sky-400"></i>
                    <span><?= count($dept_stats) ?> đơn vị có nhân sự</span>
                </div>
            </div>
            <div class="w-13 h-13 rounded-2xl bg-sky-600 text-white flex items-center justify-center text-xl shadow-md shadow-sky-300 dark:shadow-none p-3">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>
    </div>

    <!-- Hàng Biểu Đồ Trực Quan 2 Cột -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Biểu đồ 1: Cơ Cấu Nhân Sự Theo Phòng Ban (Donut Chart) -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Cơ Cấu Nhân Sự Theo Khối</h3>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-300">Tỷ trọng phân bổ nhân viên các phòng</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm shadow-xs">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
            </div>

            <div class="relative flex items-center justify-center h-56">
                <canvas id="deptDonutChart"></canvas>
            </div>

            <div class="space-y-1.5 pt-2 max-h-36 overflow-y-auto pr-1 text-xs">
                <?php foreach ($dept_stats as $idx => $d): ?>
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-200 dark:border-slate-700/60">
                        <div class="flex items-center gap-2 truncate">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: <?= ['#4f46e5', '#0284c7', '#059669', '#d97706', '#db2777', '#7c3aed'][$idx % 6] ?>;"></span>
                            <span class="text-slate-800 dark:text-slate-200 font-semibold truncate"><?= e($d['name']) ?></span>
                        </div>
                        <span class="font-extrabold text-slate-900 dark:text-white font-mono ml-2"><?= $d['emp_count'] ?> NV</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Biểu đồ 2: Biến Động Quỹ Lương 6 Tháng Gần Nhất (Bar Chart) -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Biến Động Quỹ Lương (6 Tháng Gần Nhất)</h3>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-300">Đơn vị: Triệu VNĐ &bull; Dựa trên dữ liệu quyết toán thực tế</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-xs">
                    <i class="fa-solid fa-chart-column"></i>
                </div>
            </div>

            <div class="relative h-64">
                <canvas id="payrollTrendChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Hàng Thống Kê Điểm Danh & 5 Nhân Sự Mới Nhất -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Khối Chi Tiết Điểm Danh Hôm Nay -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Tình Hình Có Mặt Hôm Nay</h3>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-300">Ngày <?= date('d/m/Y') ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-[11px] font-extrabold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-900 dark:text-indigo-200 border border-indigo-300 dark:border-indigo-700">
                    <?= $att_today['total_logged'] ?> / <?= $total_employees ?> ghi nhận
                </span>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-emerald-800 dark:text-emerald-300 font-bold flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-xs"></span> Đi Làm Đúng Giờ
                        </span>
                        <span class="font-mono font-extrabold text-slate-900 dark:text-white"><?= $att_today['present_count'] ?> NV</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500" style="width: <?= $total_employees > 0 ? ($att_today['present_count'] / $total_employees) * 100 : 0 ?>%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-amber-800 dark:text-amber-300 font-bold flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-xs"></span> Đi Muộn / Về Sớm
                        </span>
                        <span class="font-mono font-extrabold text-slate-900 dark:text-white"><?= $att_today['late_count'] ?> NV</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-amber-500 h-2.5 rounded-full transition-all duration-500" style="width: <?= $total_employees > 0 ? ($att_today['late_count'] / $total_employees) * 100 : 0 ?>%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-sky-800 dark:text-sky-300 font-bold flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-sky-500 shadow-xs"></span> Nghỉ Có Phép
                        </span>
                        <span class="font-mono font-extrabold text-slate-900 dark:text-white"><?= $att_today['leave_count'] ?> NV</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-sky-500 h-2.5 rounded-full transition-all duration-500" style="width: <?= $total_employees > 0 ? ($att_today['leave_count'] / $total_employees) * 100 : 0 ?>%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-rose-800 dark:text-rose-300 font-bold flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-xs"></span> Vắng Không Phép
                        </span>
                        <span class="font-mono font-extrabold text-slate-900 dark:text-white"><?= $att_today['absent_count'] ?> NV</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-rose-500 h-2.5 rounded-full transition-all duration-500" style="width: <?= $total_employees > 0 ? ($att_today['absent_count'] / $total_employees) * 100 : 0 ?>%"></div>
                    </div>
                </div>
            </div>

            <?php if ($att_today['total_logged'] == 0 && has_permission('attendance', 'create')): ?>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-2xl border-2 border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 text-xs text-center font-medium">
                    <i class="fa-solid fa-bell mr-1 text-amber-600"></i> Hôm nay chưa được lưu điểm danh!
                    <a href="<?= base_url('modules/attendance/index.php') ?>" class="block font-extrabold text-amber-900 dark:text-amber-200 underline mt-1">
                        Nhấn vào đây để chấm công ngay &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Khối 5 Nhân Sự Mới Tuyển Dụng -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Cán Bộ Mới Gia Nhập</h3>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-300">Danh sách nhân sự mới nhất được tiếp nhận vào các phòng ban</p>
                </div>
                <?php if (has_permission('employees', 'view')): ?>
                    <a href="<?= base_url('modules/employees/index.php') ?>" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 flex items-center gap-1 transition">
                        <span>Tất cả hồ sơ</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">Cán Bộ / Nhân Viên</th>
                            <th class="py-3 px-4">Phòng Ban & Vị Trí</th>
                            <th class="py-3 px-4">Ngày Gia Nhập</th>
                            <th class="py-3 px-4 text-center">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                        <?php if (empty($recent_employees)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-slate-500 dark:text-slate-400 font-medium">Chưa có nhân sự nào trong hệ thống.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recent_employees as $emp): ?>
                            <tr class="hover:bg-indigo-50/40 dark:hover:bg-slate-700/50 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <?= render_avatar($emp['fullname'], $emp['avatar'] ?? null, 9) ?>
                                        <div>
                                            <a href="<?= base_url('modules/employees/view.php?id=' . $emp['id']) ?>" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition text-sm">
                                                <?= e($emp['fullname']) ?>
                                            </a>
                                            <div class="mt-0.5">
                                                <span class="code-badge"><?= e($emp['employee_code']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white"><?= e($emp['department_name'] ?? 'Chưa gán') ?></div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-300 font-medium"><?= e($emp['position_name'] ?? 'Chưa bổ nhiệm') ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-800 dark:text-slate-200 font-mono font-semibold">
                                    <?= format_date($emp['hire_date']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($emp['employment_status'] === 'official'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700">Chính thức</span>
                                    <?php elseif ($emp['employment_status'] === 'probation'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700">Thử việc</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-700">Đã nghỉ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php if (has_permission('proposals', 'view') || has_permission('proposals', 'create')): ?>
        <!-- Khối Đề Xuất Chờ Phê Duyệt -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-300 dark:border-slate-700 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg shadow-md shadow-amber-200 dark:shadow-none">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Đề Xuất Chờ Phê Duyệt</h3>
                            <?php if ($pending_proposals_count > 0): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border border-amber-300 dark:border-amber-700 animate-pulse">
                                    <?= $pending_proposals_count ?> chờ duyệt
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-300">Các yêu cầu nghỉ phép, làm thêm, công tác cần ban quản lý giải quyết</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (has_permission('proposals', 'create')): ?>
                        <a href="<?= base_url('modules/proposals/create.php') ?>" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>Tạo đề xuất</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('modules/proposals/index.php') ?>" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 flex items-center gap-1 transition">
                        <span>Xem tất cả đề xuất</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <?php if (empty($pending_proposals)): ?>
                <div class="text-center py-8 text-slate-400 dark:text-slate-500 text-xs">
                    <i class="fa-regular fa-circle-check text-2xl text-emerald-500 mb-2 block"></i>
                    Hiện không có đề xuất nào đang chờ phê duyệt.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <th class="py-2.5 px-4">Mã & Nhân Viên</th>
                                <th class="py-2.5 px-4">Loại Đề Xuất</th>
                                <th class="py-2.5 px-4">Tiêu Đề</th>
                                <th class="py-2.5 px-4">Thời Gian</th>
                                <th class="py-2.5 px-4 text-center">Mức Ưu Tiên</th>
                                <th class="py-2.5 px-4 text-right">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <?php 
                            $type_badge_map = [
                                'leave'         => ['Nghỉ phép',    'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800'],
                                'overtime'      => ['Tăng ca',       'bg-violet-50 text-violet-700 border-violet-300 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800'],
                                'business_trip' => ['Công tác',      'bg-teal-50 text-teal-700 border-teal-300 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-800'],
                                'salary_raise'  => ['Tăng lương',    'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800'],
                                'equipment'     => ['Thiết bị',      'bg-indigo-50 text-indigo-700 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800'],
                                'other'         => ['Khác',          'bg-slate-50 text-slate-700 border-slate-300 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700'],
                            ];
                            $prio_badge_map = [
                                'low'    => ['Thấp',        'text-slate-500'],
                                'normal' => ['Bình thường', 'text-sky-600 dark:text-sky-400 font-semibold'],
                                'high'   => ['Cao',         'text-amber-600 dark:text-amber-400 font-bold'],
                                'urgent' => ['Khẩn cấp',   'text-rose-600 dark:text-rose-400 font-extrabold'],
                            ];
                            ?>
                            <?php foreach ($pending_proposals as $prop): ?>
                                <?php 
                                $tb = $type_badge_map[$prop['type']] ?? ['Khác', 'bg-slate-50 text-slate-700 border-slate-300'];
                                $pb = $prio_badge_map[$prop['priority']] ?? ['Bình thường', 'text-slate-600'];
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <?= render_avatar($prop['fullname'], $prop['avatar'] ?? null, 8) ?>
                                            <div>
                                                <div class="font-bold text-slate-800 dark:text-white"><?= e($prop['fullname']) ?></div>
                                                <span class="code-badge text-[10px]"><?= e($prop['proposal_code']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $tb[1] ?>">
                                            <?= $tb[0] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-800 dark:text-slate-200 max-w-xs truncate" title="<?= e($prop['title']) ?>">
                                        <?= e($prop['title']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                        <?= format_date($prop['created_at']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="text-[11px] <?= $pb[1] ?>"><?= $pb[0] ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="<?= base_url('modules/proposals/view.php?id=' . $prop['id']) ?>" 
                                           class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] transition inline-flex items-center gap-1">
                                            <span>Xem</span>
                                            <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// 1. Vẽ biểu đồ Donut: Cơ cấu nhân sự theo phòng ban
const deptLabels = <?= json_encode($dept_labels) ?>;
const deptCounts = <?= json_encode($dept_counts) ?>;
const isDarkMode = document.documentElement.classList.contains('dark');

const ctxDept = document.getElementById('deptDonutChart').getContext('2d');
new Chart(ctxDept, {
    type: 'doughnut',
    data: {
        labels: deptLabels,
        datasets: [{
            data: deptCounts,
            backgroundColor: ['#4f46e5', '#0284c7', '#059669', '#d97706', '#db2777', '#7c3aed'],
            borderWidth: 2,
            borderColor: isDarkMode ? '#151e2e' : '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        cutout: '68%'
    }
});

// 2. Vẽ biểu đồ Cột: Xu hướng biến động quỹ lương 6 tháng
const trendLabels = <?= json_encode($trend_labels) ?>;
const trendValues = <?= json_encode($trend_values) ?>;

const ctxTrend = document.getElementById('payrollTrendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Quỹ Lương Thực Tế (Triệu VNĐ)',
            data: trendValues,
            backgroundColor: isDarkMode ? 'rgba(99, 102, 241, 0.75)' : 'rgba(79, 70, 229, 0.85)',
            borderColor: '#4338ca',
            borderWidth: 1.5,
            borderRadius: 8,
            barThickness: 34
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' Triệu VNĐ';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: isDarkMode ? '#334155' : '#e2e8f0' },
                ticks: {
                    font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                    color: isDarkMode ? '#cbd5e1' : '#334155'
                }
            },
            x: {
                grid: { display: false },
                ticks: {
                    font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                    color: isDarkMode ? '#cbd5e1' : '#334155'
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>