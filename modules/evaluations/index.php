<?php
// modules/evaluations/index.php - Trung Tâm Quản Trị Đánh Giá & Giao Mục Tiêu KPI Theo Đợt (WinCommerce KPI Hub)
$page_title = 'Trung Tâm Quản Trị KPI & Giao Mục Tiêu Phòng Ban';

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/KpiService.php';
require_once __DIR__ . '/../../config/kpi_rules.php';
require_permission('evaluations', 'view');

// Cấu hình Cấp bậc Quản trị Nhân sự
$hierarchy_levels = [
    'executive'   => ['label' => 'Lãnh Đạo (C-Level)', 'badge' => 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800', 'icon' => 'fa-crown'],
    'manager'     => ['label' => 'Quản Lý (Manager)', 'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800', 'icon' => 'fa-user-tie'],
    'specialist'  => ['label' => 'Chuyên Viên (Specialist)', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800', 'icon' => 'fa-laptop-code'],
    'operational' => ['label' => 'Tuyến Đầu (Frontline)', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800', 'icon' => 'fa-person-walking-luggage']
];

// Xử lý các POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. GIAO / CẬP NHẬT MỤC TIÊU ĐỢT CHO PHÒNG BAN
    if ($action_type === 'save_department_target') {
        require_permission('evaluations', 'edit');
        $period_id = (int)($_POST['period_id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $target_title = trim($_POST['target_title'] ?? '');
        $target_description = trim($_POST['target_description'] ?? '');
        $auto_cascade = !empty($_POST['auto_cascade']);

        $c_names = $_POST['target_criteria_name'] ?? [];
        $c_values = $_POST['target_criteria_value'] ?? [];
        $c_units = $_POST['target_criteria_unit'] ?? [];
        $c_weights = $_POST['target_criteria_weight'] ?? [];
        $c_bscs = $_POST['target_criteria_bsc'] ?? [];
        $c_priorities = $_POST['target_criteria_priority'] ?? [];

        $kpi_items = [];
        for ($i = 0; $i < count($c_names); $i++) {
            $nm = trim($c_names[$i] ?? '');
            if (empty($nm)) continue;
            $kpi_items[] = [
                'criteria_name'  => $nm,
                'target_value'   => trim($c_values[$i] ?? ''),
                'unit'           => trim($c_units[$i] ?? '%') ?: '%',
                'weight'         => (float)($c_weights[$i] ?? 25),
                'bsc_pillar'     => in_array($c_bscs[$i] ?? '', ['financial', 'customer', 'internal_process', 'learning_growth']) ? $c_bscs[$i] : 'internal_process',
                'priority_group' => in_array($c_priorities[$i] ?? '', ['A', 'B', 'C']) ? $c_priorities[$i] : 'B'
            ];
        }

        try {
            $res = KpiService::saveDepartmentTarget($pdo, $period_id, $department_id, [
                'target_title'       => $target_title,
                'target_description' => $target_description,
                'kpi_items'          => $kpi_items
            ], $current_user['id'], $auto_cascade);

            $msg = "Đã lưu mục tiêu đợt cho phòng ban thành công!";
            if ($auto_cascade && $res['created_evaluations'] > 0) {
                $msg .= " Đồng thời tự động phân bổ tạo mới {$res['created_evaluations']} phiếu đánh giá cho nhân viên.";
            }
            set_flash('success', $msg);
        } catch (Exception $e) {
            set_flash('danger', 'Lỗi: ' . $e->getMessage());
        }
        redirect('modules/evaluations/index.php?period_id=' . $period_id . '&view=department');
    }

    // 2. PHÂN BỔ PHIẾU ĐÁNH GIÁ CHO NHÂN VIÊN PHÒNG BAN
    if ($action_type === 'cascade_department') {
        require_permission('evaluations', 'create');
        $period_id = (int)($_POST['period_id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        try {
            $res = KpiService::cascadeEvaluationsToDepartment($pdo, $period_id, $department_id);
            set_flash('success', "Đã phân bổ thành công {$res['created']} phiếu đánh giá cho nhân viên (Bỏ qua {$res['skipped']} nhân viên đã có phiếu).");
        } catch (Exception $e) {
            set_flash('danger', 'Lỗi phân bổ phiếu: ' . $e->getMessage());
        }
        redirect('modules/evaluations/index.php?period_id=' . $period_id . '&view=department');
    }

    // 3. TẠO NHANH ĐỢT ĐÁNH GIÁ MỚI
    if ($action_type === 'create_period') {
        require_permission('evaluations', 'create');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $year = (int)($_POST['year'] ?? date('Y'));
        $quarter = !empty($_POST['quarter']) ? (int)$_POST['quarter'] : null;
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $self_deadline = !empty($_POST['self_review_deadline']) ? $_POST['self_review_deadline'] : null;
        $mgr_deadline = !empty($_POST['manager_review_deadline']) ? $_POST['manager_review_deadline'] : null;
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'active', 'closed']) ? $_POST['status'] : 'active';
        $desc = trim($_POST['description'] ?? '');

        if (empty($code) || empty($name) || empty($start_date) || empty($end_date)) {
            set_flash('danger', 'Vui lòng nhập đầy đủ các thông tin bắt buộc của đợt đánh giá.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_periods (code, name, type, year, quarter, start_date, end_date, self_review_deadline, manager_review_deadline, status, description)
                    VALUES (?, ?, 'quarterly', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $year, $quarter, $start_date, $end_date, $self_deadline, $mgr_deadline, $status, $desc]);
                $newPeriodId = $pdo->lastInsertId();
                set_flash('success', "Đã tạo đợt đánh giá mới [{$code}] thành công!");
                redirect('modules/evaluations/index.php?period_id=' . $newPeriodId . '&view=department');
            } catch (Exception $e) {
                set_flash('danger', 'Lỗi khi tạo đợt đánh giá: ' . $e->getMessage());
            }
        }
        redirect('modules/evaluations/index.php');
    }
}

// Kiểm tra thông tin nhân viên hiện tại & phân quyền xem KPI
$user = current_user();
$current_emp_stmt = $pdo->prepare("SELECT id, fullname, employee_code, department_id, position_id FROM employees WHERE user_id = ?");
$current_emp_stmt->execute([$user['id']]);
$current_emp = $current_emp_stmt->fetch();
$current_emp_id = $current_emp ? (int)$current_emp['id'] : 0;

// Người có quyền quản trị/xem toàn bộ KPI (Super Admin hoặc Approver/HR Director/Branch Manager có quyền approve/create)
$can_manage_all_kpi = is_superadmin() || has_permission('evaluations', 'approve') || has_permission('evaluations', 'create');
$is_personal_only = !$can_manage_all_kpi;

// Tham số bộ lọc & chế độ xem
$filter_period = !empty($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
$filter_level  = $_GET['hierarchy_level'] ?? '';
$filter_dept   = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_status = $_GET['status'] ?? '';
$filter_rating = $_GET['rating'] ?? '';
$search        = trim($_GET['search'] ?? '');
// Với nhân viên thông thường: chỉ cho phép xem dạng danh sách (table) hoặc kanban cá nhân
$view_mode     = $_GET['view'] ?? ($is_personal_only ? 'table' : 'department');
if ($is_personal_only && $view_mode === 'department') {
    $view_mode = 'table';
}

// Lấy danh sách kỳ đánh giá
$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY status = 'active' DESC, year DESC, quarter DESC")->fetchAll();

// Mặc định chọn kỳ active đầu tiên nếu chưa chọn
if ($filter_period === 0 && !empty($periods)) {
    foreach ($periods as $p) {
        if ($p['status'] === 'active') {
            $filter_period = $p['id'];
            break;
        }
    }
    if ($filter_period === 0) {
        $filter_period = $periods[0]['id'];
    }
}

// Lấy thông tin đợt hiện tại
$current_period = null;
foreach ($periods as $p) {
    if ($p['id'] == $filter_period) {
        $current_period = $p;
        break;
    }
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Lấy ma trận tiến độ theo phòng ban (Chỉ dành cho quản lý)
$dept_matrix = $can_manage_all_kpi ? KpiService::getDepartmentProgressMatrix($pdo, $filter_period) : [];

// Truy vấn danh sách đánh giá (cho View Bảng & View Kanban)
$sql = "
    SELECT e.*,
           ep.code AS period_code, ep.name AS period_name, ep.self_review_deadline, ep.manager_review_deadline,
           emp.employee_code, emp.fullname AS employee_name, emp.avatar AS employee_avatar,
           d.name AS department_name, p.name AS position_name,
           rev.employee_code AS reviewer_code, rev.fullname AS reviewer_name, rev.avatar AS reviewer_avatar,
           t.name AS template_name, t.code AS template_code, t.hierarchy_level AS template_level
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    LEFT JOIN departments d ON emp.department_id = d.id
    LEFT JOIN positions p ON emp.position_id = p.id
    LEFT JOIN employees rev ON e.reviewer_id = rev.id
    LEFT JOIN kpi_templates t ON e.template_id = t.id
    WHERE 1=1
";
$params = [];

// Nếu là nhân viên thông thường: CHỈ xem phiếu của bản thân hoặc phiếu mình là người đánh giá
if ($is_personal_only) {
    $sql .= " AND (e.employee_id = ? OR e.reviewer_id = ?)";
    $params[] = $current_emp_id;
    $params[] = $current_emp_id;
}

if ($filter_period > 0) {
    $sql .= " AND e.period_id = ?";
    $params[] = $filter_period;
}

if (!empty($filter_level) && isset($hierarchy_levels[$filter_level])) {
    $sql .= " AND t.hierarchy_level = ?";
    $params[] = $filter_level;
}

if ($filter_dept > 0) {
    $sql .= " AND emp.department_id = ?";
    $params[] = $filter_dept;
}

if (!empty($filter_status)) {
    $sql .= " AND e.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_rating)) {
    $sql .= " AND e.rating = ?";
    $params[] = $filter_rating;
}

if (!empty($search)) {
    $sql .= " AND (emp.fullname LIKE ? OR emp.employee_code LIKE ? OR e.evaluation_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// Thống kê tổng hợp (Theo cá nhân nếu is_personal_only, hoặc toàn đợt nếu quản lý)
$stat_sql = "
    SELECT 
        COUNT(*) AS total_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
        COUNT(CASE WHEN status = 'manager_review' THEN 1 END) AS mgr_review_count,
        COUNT(CASE WHEN status = 'self_review' THEN 1 END) AS self_review_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        AVG(CASE WHEN status = 'completed' THEN final_score END) AS avg_final_score
    FROM evaluations
    WHERE 1=1
";
$stat_params = [];
if ($is_personal_only) {
    $stat_sql .= " AND (employee_id = ? OR reviewer_id = ?)";
    $stat_params[] = $current_emp_id;
    $stat_params[] = $current_emp_id;
}
if ($filter_period > 0) {
    $stat_sql .= " AND period_id = ?";
    $stat_params[] = $filter_period;
}
$stat_stmt = $pdo->prepare($stat_sql);
$stat_stmt->execute($stat_params);
$stats = $stat_stmt->fetch() ?: [
    'total_count' => 0, 'completed_count' => 0, 'mgr_review_count' => 0,
    'self_review_count' => 0, 'pending_count' => 0, 'avg_final_score' => 0
];

$completion_rate = $stats['total_count'] > 0 ? round(($stats['completed_count'] / $stats['total_count']) * 100) : 0;

// Đếm số phòng ban đã giao mục tiêu
$depts_with_target = 0;
foreach ($dept_matrix as $dm) {
    if ($dm['has_target']) $depts_with_target++;
}

// Phân nhóm cho Kanban View
$kanban_columns = [
    'pending' => [
        'title' => 'Chờ Khởi Động',
        'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'border' => 'border-t-slate-400',
        'icon' => 'fa-clock',
        'items' => []
    ],
    'self_review' => [
        'title' => 'Nhân Viên Tự Đánh Giá',
        'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
        'border' => 'border-t-sky-500',
        'icon' => 'fa-user-pen',
        'items' => []
    ],
    'manager_review' => [
        'title' => 'Quản Lý Thẩm Định',
        'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
        'border' => 'border-t-amber-500',
        'icon' => 'fa-user-tie',
        'items' => []
    ],
    'completed' => [
        'title' => 'Đã Hoàn Tất & Xếp Loại',
        'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
        'border' => 'border-t-emerald-500',
        'icon' => 'fa-circle-check',
        'items' => []
    ]
];

foreach ($evaluations as $e) {
    $st = $e['status'];
    if (isset($kanban_columns[$st])) {
        $kanban_columns[$st]['items'][] = $e;
    } else {
        $kanban_columns['pending']['items'][] = $e;
    }
}

$rating_labels = [
    'outstanding'   => ['label' => 'Xuất Sắc (A+)', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300'],
    'excellent'     => ['label' => 'Tốt (A)', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300'],
    'good'          => ['label' => 'Khá (B)', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-300'],
    'average'       => ['label' => 'Trung Bình (C)', 'class' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200 border border-slate-300'],
    'below_average' => ['label' => 'Yếu (D)', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-300'],
    'poor'          => ['label' => 'Kém (E)', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Navigation & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-red-600 dark:text-red-400 mb-1">
                <a href="<?= base_url('index.php') ?>" class="hover:underline">WinCommerce People</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400">Hiệu Suất & Đánh Giá</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-red-600 text-white flex items-center justify-center text-lg shadow-md shadow-red-200 dark:shadow-none">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <span><?= $is_personal_only ? 'Hồ Sơ Đánh Giá KPI Của Tôi' : 'Trung Tâm Quản Trị KPI & Giao Mục Tiêu Phòng Ban' ?></span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                <?= $is_personal_only 
                    ? 'Theo dõi chỉ tiêu đánh giá cá nhân &bull; Tiến hành tự chấm điểm (Self-Review) &bull; Xem kết quả xếp loại định kỳ.' 
                    : 'Giao mục tiêu chiến lược theo đợt &bull; Phân bổ chỉ tiêu cho các khối phòng ban &bull; Theo dõi tiến độ đánh giá toàn hệ thống.' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <?php if ($can_manage_all_kpi): ?>
                <?php if (has_permission('evaluations', 'create')): ?>
                    <!-- Nút Giao Mục Tiêu Phòng Ban (Nổi bật) -->
                    <button type="button" onclick="openTargetModal()"
                            class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs transition flex items-center gap-2 shadow-sm shadow-red-200 dark:shadow-none">
                        <i class="fa-solid fa-bullseye"></i>
                        <span>Giao Mục Tiêu Phòng Ban</span>
                    </button>

                    <!-- Nút Tạo Đợt Đánh Giá Mới -->
                    <button type="button" onclick="openNewPeriodModal()"
                            class="px-3.5 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-xs">
                        <i class="fa-solid fa-calendar-plus text-red-600"></i>
                        <span>Tạo Đợt Mới</span>
                    </button>
                <?php endif; ?>

                <a href="<?= base_url('modules/evaluations/kpi_templates.php') ?>" 
                   class="px-3.5 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-xs">
                    <i class="fa-solid fa-sitemap text-slate-500"></i>
                    <span>Bộ Tiêu Chí Vị Trí</span>
                </a>

                <a href="<?= base_url('modules/evaluations/form.php') ?>" 
                   class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 font-semibold rounded-xl text-xs transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tạo Phiếu Đơn</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Banner Đợt Đánh Giá & Timeline Quy Trình 4 Giai Đoạn (Campaign Banner) -->
    <?php if ($current_period): ?>
        <div class="bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 rounded-3xl p-5 sm:p-6 text-white shadow-lg space-y-5 relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 opacity-10 text-9xl pointer-events-none">
                <i class="fa-solid fa-chart-pie"></i>
            </div>

            <!-- Header Banner: Thông tin đợt & Chuyển đổi đợt -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 backdrop-blur-sm border border-white/30 text-white">
                            <?= e($current_period['code']) ?>
                        </span>
                        <?php if ($current_period['status'] === 'active'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-400 text-emerald-950 flex items-center gap-1 shadow-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-950 animate-pulse"></span>
                                Đang Diễn Ra
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-white/30 text-white">
                                Đã Chốt Sổ Liệu
                            </span>
                        <?php endif; ?>
                        <span class="text-xs text-white/80">
                            <?= format_date($current_period['start_date']) ?> &rarr; <?= format_date($current_period['end_date']) ?>
                        </span>
                    </div>

                    <h2 class="text-xl sm:text-2xl font-black text-white leading-tight">
                        <?= e($current_period['name']) ?>
                    </h2>
                    <p class="text-xs text-white/90 max-w-2xl line-clamp-2">
                        <?= e($current_period['description'] ?: 'Chiến dịch đánh giá hiệu suất định kỳ toàn bộ các khối phòng ban và chuỗi bán lẻ WinCommerce.') ?>
                    </p>
                </div>

                <!-- Dropdown chuyển đợt nhanh -->
                <div class="flex items-center gap-2 self-start md:self-center">
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="view" value="<?= e($view_mode) ?>">
                        <span class="text-xs text-white/80 font-medium hidden sm:inline">Chọn Đợt:</span>
                        <select name="period_id" onchange="this.form.submit()"
                                class="px-3 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 rounded-xl text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-white">
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= $p['id'] ?>" class="text-slate-800" <?= $p['id'] == $filter_period ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (<?= $p['status'] === 'active' ? 'Đang mở' : 'Đã đóng' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Stepper Timeline 4 Giai Đoạn -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-white/20 relative z-10 text-xs">
                <!-- Bước 1 -->
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/15 space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-bold text-white/80">
                        <span>1. Giao Mục Tiêu Đợt</span>
                        <i class="fa-solid fa-bullseye text-amber-300"></i>
                    </div>
                    <div class="font-extrabold text-sm text-white">
                        <?= $depts_with_target ?> / <?= count($dept_matrix) ?> Khối
                    </div>
                    <div class="text-[10px] text-white/70">
                        Đã giao mục tiêu chiến lược
                    </div>
                </div>

                <!-- Bước 2 -->
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/15 space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-bold text-white/80">
                        <span>2. NV Tự Đánh Giá</span>
                        <i class="fa-solid fa-user-pen text-sky-300"></i>
                    </div>
                    <div class="font-extrabold text-sm text-white">
                        Hạn: <?= $current_period['self_review_deadline'] ? format_date($current_period['self_review_deadline']) : 'Linh hoạt' ?>
                    </div>
                    <div class="text-[10px] text-white/70">
                        <?= $stats['self_review_count'] ?> nhân sự đang tự chấm
                    </div>
                </div>

                <!-- Bước 3 -->
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/15 space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-bold text-white/80">
                        <span>3. Quản Lý Duyệt</span>
                        <i class="fa-solid fa-user-tie text-emerald-300"></i>
                    </div>
                    <div class="font-extrabold text-sm text-white">
                        Hạn: <?= $current_period['manager_review_deadline'] ? format_date($current_period['manager_review_deadline']) : 'Linh hoạt' ?>
                    </div>
                    <div class="text-[10px] text-white/70">
                        <?= $stats['mgr_review_count'] ?> phiếu chờ thẩm định
                    </div>
                </div>

                <!-- Bước 4 -->
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 border border-white/15 space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-bold text-white/80">
                        <span>4. Chốt Thưởng Total Rewards</span>
                        <i class="fa-solid fa-award text-yellow-300"></i>
                    </div>
                    <div class="font-extrabold text-sm text-white">
                        <?= $stats['completed_count'] ?> / <?= $stats['total_count'] ?> Đã Xong
                    </div>
                    <div class="text-[10px] text-white/70">
                        Tỷ lệ hoàn thành: <?= $completion_rate ?>%
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4 Khối Chỉ Số Tổng Quan Toàn Hệ Thống -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Khối 1: Tổng số phòng ban -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Khối Phòng Ban Tham Gia</span>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-1">
                    <?= count($dept_matrix) ?>
                </div>
                <div class="text-xs text-red-600 dark:text-red-400 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-bullseye"></i>
                    <span><?= $depts_with_target ?> khối đã có mục tiêu đợt</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>

        <!-- Khối 2: Tỷ lệ hoàn thành -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Đã Hoàn Tất & Xếp Loại</span>
                <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                    <?= $stats['completed_count'] ?> <span class="text-xs font-normal text-slate-400">/ <?= $stats['total_count'] ?></span>
                </div>
                <div class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Tỷ lệ hoàn tất: <?= $completion_rate ?>%</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Khối 3: Đang thẩm định -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Chờ Quản Lý Thẩm Định</span>
                <div class="text-2xl font-black font-mono text-amber-600 dark:text-amber-400 mt-1">
                    <?= $stats['mgr_review_count'] ?>
                </div>
                <div class="text-xs text-amber-700 dark:text-amber-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-clock"></i>
                    <span><?= $stats['self_review_count'] ?> nhân sự đang tự chấm</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-user-clock"></i>
            </div>
        </div>

        <!-- Khối 4: Điểm TB toàn đợt -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Điểm KPI Trung Bình</span>
                <div class="text-2xl font-black font-mono text-violet-600 dark:text-violet-400 mt-1">
                    <?= $stats['avg_final_score'] ? round($stats['avg_final_score'], 1) : '---' ?>
                    <span class="text-xs font-normal text-slate-400">/ 100</span>
                </div>
                <div class="text-xs text-violet-700 dark:text-violet-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-award"></i>
                    <span>Xếp loại chuẩn toàn tập đoàn</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>

    </div>

    <!-- Thanh Lọc & Bộ Công Cụ Tìm Kiếm + 3 Chế Độ Xem (Department / Table / Kanban) -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
        <form method="GET" class="flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="view" value="<?= e($view_mode) ?>">
            <input type="hidden" name="period_id" value="<?= e($filter_period) ?>">

            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Search input -->
                <div class="relative min-w-[200px] sm:min-w-[240px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Tên NV, mã NV hoặc mã phiếu..."
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>

                <?php if ($can_manage_all_kpi): ?>
                <!-- Lọc Phòng ban -->
                <select name="department_id" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-red-500">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Lọc Cấp bậc nhân sự -->
                <select name="hierarchy_level" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-red-500">
                    <option value="">-- Tất cả cấp bậc --</option>
                    <?php foreach ($hierarchy_levels as $lk => $lv): ?>
                        <option value="<?= $lk ?>" <?= $filter_level === $lk ? 'selected' : '' ?>><?= e($lv['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <!-- Lọc Xếp loại -->
                <select name="rating" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-red-500">
                    <option value="">-- Tất cả xếp loại --</option>
                    <option value="outstanding" <?= $filter_rating === 'outstanding' ? 'selected' : '' ?>>Xuất sắc (A+)</option>
                    <option value="excellent" <?= $filter_rating === 'excellent' ? 'selected' : '' ?>>Tốt (A)</option>
                    <option value="good" <?= $filter_rating === 'good' ? 'selected' : '' ?>>Khá (B)</option>
                    <option value="average" <?= $filter_rating === 'average' ? 'selected' : '' ?>>Trung bình (C)</option>
                    <option value="below_average" <?= $filter_rating === 'below_average' ? 'selected' : '' ?>>Yếu (D)</option>
                    <option value="poor" <?= $filter_rating === 'poor' ? 'selected' : '' ?>>Kém (E)</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Lọc
                </button>

                <?php if ($filter_dept > 0 || !empty($filter_level) || !empty($filter_status) || !empty($filter_rating) || !empty($search)): ?>
                    <a href="<?= base_url('modules/evaluations/index.php?period_id=' . $filter_period . '&view=' . $view_mode) ?>" class="text-xs text-red-500 hover:underline">
                        Xóa lọc
                    </a>
                <?php endif; ?>
            </div>

            <!-- Switch 3 View: Department / Table / Kanban -->
            <div class="flex items-center bg-slate-100 dark:bg-slate-900 p-1 rounded-2xl border border-slate-200 dark:border-slate-700 flex-shrink-0">
                <?php if ($can_manage_all_kpi): ?>
                <a href="<?= base_url('modules/evaluations/index.php?' . http_build_query(array_merge($_GET, ['view' => 'department']))) ?>"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $view_mode === 'department' ? 'bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white' ?>"
                   title="Quản trị mục tiêu và tiến độ theo Khối / Phòng ban">
                    <i class="fa-solid fa-building"></i>
                    <span>Theo Phòng Ban</span>
                </a>
                <?php endif; ?>
                <a href="<?= base_url('modules/evaluations/index.php?' . http_build_query(array_merge($_GET, ['view' => 'table']))) ?>"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $view_mode === 'table' ? 'bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white' ?>"
                   title="Xem danh sách chi tiết từng nhân sự">
                    <i class="fa-solid fa-table-list"></i>
                    <span><?= $is_personal_only ? 'Phiếu Của Tôi' : 'Bảng Nhân Viên' ?></span>
                </a>
                <a href="<?= base_url('modules/evaluations/index.php?' . http_build_query(array_merge($_GET, ['view' => 'kanban']))) ?>"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $view_mode === 'kanban' ? 'bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white' ?>"
                   title="Theo dõi luồng tiến độ đánh giá dạng Kanban">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Kanban</span>
                </a>
            </div>
        </form>
    </div>

    <!-- ======================================================== -->
    <!-- CHẾ ĐỘ 1: THEO KHỐI / PHÒNG BAN (DEPARTMENT HUB) - DEFAULT -->
    <!-- ======================================================== -->
    <?php if ($view_mode === 'department'): ?>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-sitemap text-red-600"></i>
                        <span>Bản Đồ Mục Tiêu & Tiến Độ KPI Cấp Khối / Phòng Ban</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Mỗi phòng ban có mục tiêu chiến lược riêng &bull; Bấm "Giao Mục Tiêu" để cập nhật chỉ tiêu hoặc tự động phân bổ cho đội ngũ.
                    </p>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
                    Hiển thị <strong><?= count($dept_matrix) ?></strong> phòng ban
                </div>
            </div>

            <!-- Grid Thẻ Phòng Ban -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($dept_matrix as $dm): ?>
                    <?php 
                        if ($filter_dept > 0 && $dm['department_id'] != $filter_dept) continue;
                        $hasTarget = $dm['has_target'];
                        $target = $dm['target'];
                    ?>
                    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700/80 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                        
                        <!-- Top Card: Header Phòng Ban & Trưởng Phòng -->
                        <div class="p-5 space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 flex items-center justify-center text-lg font-black flex-shrink-0">
                                        <i class="fa-solid fa-building-user"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                <?= e($dm['department_code']) ?>
                                            </span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                                <?= $dm['total_employees'] ?> nhân sự
                                            </span>
                                        </div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1 leading-snug">
                                            <?= e($dm['department_name']) ?>
                                        </h4>
                                    </div>
                                </div>

                                <!-- Trạng thái có mục tiêu chưa -->
                                <?php if ($hasTarget): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1 flex-shrink-0">
                                        <i class="fa-solid fa-circle-check text-[9px]"></i> Đã Giao
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 flex items-center gap-1 flex-shrink-0">
                                        <i class="fa-solid fa-clock text-[9px]"></i> Chưa Giao
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Trưởng Phòng / Khối Trưởng -->
                            <div class="flex items-center gap-2.5 p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs">
                                <?= render_avatar($dm['manager_name'], $dm['manager_avatar'], 8) ?>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] text-slate-400 block font-medium">Trưởng phòng / Khối trưởng:</span>
                                    <strong class="text-slate-800 dark:text-slate-200 truncate block text-xs">
                                        <?= e($dm['manager_name']) ?>
                                    </strong>
                                </div>
                            </div>

                            <!-- Mục Tiêu Trọng Tâm Đợt Này -->
                            <div class="space-y-2">
                                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-bullseye text-red-500"></i> Mục Tiêu Chiến Lược Đợt:
                                </span>

                                <?php if ($hasTarget): ?>
                                    <div class="p-3 bg-red-50/50 dark:bg-red-950/30 rounded-2xl border border-red-100 dark:border-red-900/40 space-y-2">
                                        <h5 class="text-xs font-bold text-slate-900 dark:text-white leading-snug">
                                            <?= e($target['target_title']) ?>
                                        </h5>
                                        <?php if (!empty($target['target_description'])): ?>
                                            <p class="text-[11px] text-slate-600 dark:text-slate-300 line-clamp-2">
                                                <?= e($target['target_description']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Badge các chỉ tiêu SMART trọng tâm -->
                                        <?php if (!empty($target['kpi_items'])): ?>
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                <?php foreach (array_slice($target['kpi_items'], 0, 3) as $kItem): ?>
                                                    <span class="px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 border border-red-200/80 dark:border-red-800 text-[10px] font-medium text-slate-700 dark:text-slate-200 flex items-center gap-1 shadow-2xs">
                                                        <strong class="text-red-600 font-mono"><?= e($kItem['target_value'] ?? '') ?></strong>
                                                        <span class="truncate max-w-[120px] text-slate-500"><?= e($kItem['criteria_name'] ?? '') ?></span>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($target['kpi_items']) > 3): ?>
                                                    <span class="text-[10px] text-slate-400 font-bold self-center">+<?= count($target['kpi_items']) - 3 ?> chỉ tiêu</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 text-center space-y-1">
                                        <p class="text-xs text-slate-400 font-medium">Chưa thiết lập mục tiêu chiến dịch đợt này</p>
                                        <button type="button" onclick='openTargetModalForDept(<?= json_encode($dm) ?>)'
                                                class="text-xs font-bold text-red-600 hover:text-red-700 dark:text-red-400 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-plus-circle"></i>
                                            <span>Thiết lập mục tiêu ngay</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tiến Độ Đánh Giá Của Phòng Ban -->
                            <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-600 dark:text-slate-400">Tiến Độ Đánh Giá:</span>
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <strong class="text-slate-900 dark:text-white"><?= $dm['completed_count'] ?></strong>
                                        <span class="text-slate-400">/ <?= $dm['total_employees'] ?> nhân sự</span>
                                        <span class="font-bold text-red-600 dark:text-red-400 ml-1">(<?= $dm['completion_rate'] ?>%)</span>
                                    </div>
                                </div>

                                <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden flex">
                                    <div style="width: <?= $dm['completion_rate'] ?>%" class="bg-gradient-to-r from-red-600 to-amber-500 h-full rounded-full transition-all"></div>
                                </div>

                                <div class="flex items-center justify-between text-[10px] text-slate-500 pt-0.5">
                                    <div class="flex items-center gap-2">
                                        <span>Chờ QL: <b><?= $dm['mgr_review_count'] ?></b></span>
                                        <span>•</span>
                                        <span>Tự chấm: <b><?= $dm['self_review_count'] ?></b></span>
                                    </div>
                                    <div>
                                        Điểm TB: <b class="font-mono text-emerald-600 text-xs"><?= $dm['avg_score'] !== null ? $dm['avg_score'] . 'đ' : '---' ?></b>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Card: Nút Thao Tác Cực Kỳ Tiện Dụng -->
                        <div class="px-5 py-3.5 bg-slate-50/80 dark:bg-slate-900/80 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                            <button type="button" onclick='openTargetModalForDept(<?= json_encode($dm) ?>)'
                                    class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-red-600 hover:border-red-300 text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-pen-to-square text-red-600"></i>
                                <span><?= $hasTarget ? 'Sửa Mục Tiêu' : 'Giao Mục Tiêu' ?></span>
                            </button>

                            <div class="flex items-center gap-1.5">
                                <?php if ($dm['total_employees'] > $dm['total_evals'] && has_permission('evaluations', 'create')): ?>
                                    <!-- Nút phân bổ phiếu nhanh -->
                                    <form method="POST"
                                          data-confirm="Phân bổ tạo phiếu đánh giá cho nhân sự chưa có phiếu thuộc phòng [<?= e($dm['department_name']) ?>]?"
                                          data-confirm-title="Xác Nhận Phân Bổ Phiếu"
                                          data-confirm-danger="false">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="cascade_department">
                                        <input type="hidden" name="period_id" value="<?= $filter_period ?>">
                                        <input type="hidden" name="department_id" value="<?= $dm['department_id'] ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 text-xs font-bold transition flex items-center gap-1" title="Tạo phiếu cho nhân sự còn lại">
                                            <i class="fa-solid fa-bolt"></i>
                                            <span>Phân Bổ</span>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Xem danh sách nhân viên phòng ban này -->
                                <a href="<?= base_url('modules/evaluations/index.php?period_id=' . $filter_period . '&department_id=' . $dm['department_id'] . '&view=table') ?>"
                                   class="px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-2xs">
                                    <span>Xem Đội Ngũ (<?= $dm['total_evals'] ?>)</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <!-- ======================================================== -->
    <!-- CHẾ ĐỘ 2: BẢNG DANH SÁCH NHÂN VIÊN (TABLE VIEW)          -->
    <!-- ======================================================== -->
    <?php elseif ($view_mode === 'table'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Bảng Danh Sách Phiếu Đánh Giá Nhân Sự</h3>
                    <p class="text-xs text-slate-500">Dữ liệu đánh giá chi tiết theo đợt và phân loại phòng ban</p>
                </div>
                <span class="text-xs font-mono font-bold text-slate-500">
                    Tổng số: <?= count($evaluations) ?> phiếu
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-4">Mã Phiếu</th>
                            <th class="py-3.5 px-4">Nhân Sự Được Đánh Giá</th>
                            <th class="py-3.5 px-4">Cấp Bậc Nhân Sự</th>
                            <th class="py-3.5 px-4">Khối Phòng Ban</th>
                            <th class="py-3.5 px-4">Quản Lý Thẩm Tra</th>
                            <th class="py-3.5 px-4 text-center">Tự Chấm</th>
                            <th class="py-3.5 px-4 text-center">QL Chấm</th>
                            <th class="py-3.5 px-4 text-center">Điểm Tổng</th>
                            <th class="py-3.5 px-4 text-center">Xếp Loại</th>
                            <th class="py-3.5 px-4 text-center">Hệ Số Thưởng K</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($evaluations)): ?>
                            <tr>
                                <td colspan="12" class="py-12 text-center text-slate-400">
                                    Không tìm thấy hồ sơ đánh giá nào phù hợp với bộ lọc.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($evaluations as $item): ?>
                            <?php 
                                $rt = $rating_labels[$item['rating'] ?? ''] ?? null;
                                $lvl_tbl = $hierarchy_levels[$item['template_level'] ?? 'specialist'] ?? $hierarchy_levels['specialist'];
                                $k_mult = (float)($item['bonus_multiplier'] ?? 1.00);
                            ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40 transition">
                                
                                <td class="py-3 px-4 font-mono font-bold text-red-600 dark:text-red-400">
                                    <a href="<?= base_url('modules/evaluations/view.php?id=' . $item['id']) ?>" class="hover:underline">
                                        <?= e($item['evaluation_code']) ?>
                                    </a>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <?= render_avatar($item['employee_name'], $item['employee_avatar'], 8) ?>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white"><?= e($item['employee_name']) ?></div>
                                            <div class="text-[11px] text-slate-400"><?= e($item['employee_code']) ?> &bull; <?= e($item['position_name'] ?: '---') ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $lvl_tbl['badge'] ?>">
                                        <i class="fa-solid <?= $lvl_tbl['icon'] ?> mr-1"></i><?= e($lvl_tbl['label']) ?>
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    <?= e($item['department_name'] ?: '---') ?>
                                </td>

                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    <?= e($item['reviewer_name']) ?>
                                </td>

                                <td class="py-3 px-4 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    <?= $item['self_score'] !== null ? round($item['self_score'], 1) : '--' ?>
                                </td>

                                <td class="py-3 px-4 text-center font-mono font-bold text-violet-600 dark:text-violet-400">
                                    <?= $item['manager_score'] !== null ? round($item['manager_score'], 1) : '--' ?>
                                </td>

                                <td class="py-3 px-4 text-center font-mono font-black text-emerald-600 dark:text-emerald-400">
                                    <?= $item['final_score'] !== null ? round($item['final_score'], 1) : '--' ?>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <?php if ($rt): ?>
                                        <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full <?= $rt['class'] ?>">
                                            <?= $rt['label'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[11px]">---</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4 text-center font-mono font-bold text-amber-600 dark:text-amber-400">
                                    <?= $item['status'] === 'completed' ? 'x' . number_format($k_mult, 2) : '---' ?>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <?php if ($item['status'] === 'completed'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Hoàn tất
                                        </span>
                                    <?php elseif ($item['status'] === 'manager_review'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Chờ QL chấm
                                        </span>
                                    <?php elseif ($item['status'] === 'self_review'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                            NV tự chấm
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                            Chờ xử lý
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="<?= base_url('modules/evaluations/view.php?id=' . $item['id']) ?>" 
                                           class="px-2.5 py-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/60 rounded-lg transition font-bold" title="Xem & Chấm điểm">
                                            <span>Xem / Chấm</span>
                                        </a>

                                        <?php if (has_permission('evaluations', 'edit')): ?>
                                            <a href="<?= base_url('modules/evaluations/form.php?id=' . $item['id']) ?>" 
                                               class="p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white rounded-lg transition" title="Chỉnh sửa">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (has_permission('evaluations', 'delete')): ?>
                                            <form method="POST" action="<?= base_url('modules/evaluations/delete.php') ?>" class="inline"
                                                  data-confirm="Bạn có chắc muốn xóa phiếu đánh giá [<?= e($item['evaluation_code']) ?>]?"
                                                  data-confirm-title="Xác Nhận Xóa Phiếu Đánh Giá"
                                                  data-confirm-danger="true">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition" title="Xóa">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ======================================================== -->
    <!-- CHẾ ĐỘ 3: KANBAN BOARD                                   -->
    <!-- ======================================================== -->
    <?php elseif ($view_mode === 'kanban'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 items-start">
            <?php foreach ($kanban_columns as $colKey => $col): ?>
                <div class="bg-slate-100/80 dark:bg-slate-900/50 rounded-3xl p-4 border border-slate-200 dark:border-slate-800 border-t-4 <?= $col['border'] ?> space-y-3 min-h-[500px]">
                    
                    <!-- Column Header -->
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid <?= $col['icon'] ?> text-xs text-slate-500"></i>
                            <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= $col['title'] ?></h3>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-black <?= $col['badge'] ?>">
                            <?= count($col['items']) ?>
                        </span>
                    </div>

                    <!-- Column Cards -->
                    <div class="space-y-3">
                        <?php if (empty($col['items'])): ?>
                            <div class="py-8 text-center text-xs text-slate-400">
                                Không có hồ sơ nào
                            </div>
                        <?php endif; ?>

                        <?php foreach ($col['items'] as $item): ?>
                            <?php 
                                $rt = $rating_labels[$item['rating'] ?? ''] ?? null;
                                $lvl_card = $hierarchy_levels[$item['template_level'] ?? 'specialist'] ?? $hierarchy_levels['specialist'];
                                $k_mult = (float)($item['bonus_multiplier'] ?? 1.00);
                            ?>
                            <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm hover:shadow-md transition space-y-3">
                                
                                <!-- Top: Code + Level Badge + Rating -->
                                <div class="flex items-center justify-between gap-1.5 flex-wrap">
                                    <span class="text-[11px] font-mono font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/60 px-2 py-0.5 rounded-md border border-red-200 dark:border-red-800">
                                        <?= e($item['evaluation_code']) ?>
                                    </span>

                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?= $lvl_card['badge'] ?>" title="<?= e($lvl_card['label']) ?>">
                                        <i class="fa-solid <?= $lvl_card['icon'] ?> mr-0.5"></i>
                                        <?= e($lvl_card['label']) ?>
                                    </span>
                                </div>

                                <!-- Employee Profile -->
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <?= render_avatar($item['employee_name'], $item['employee_avatar'], 10) ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                            <?= e($item['employee_name']) ?>
                                        </h4>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                            <?= e($item['position_name'] ?: '---') ?> &bull; <?= e($item['department_name'] ?: '---') ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Score preview row -->
                                <div class="grid grid-cols-3 gap-1 bg-slate-50 dark:bg-slate-900/60 p-2 rounded-xl text-center text-[10px]">
                                    <div>
                                        <span class="text-slate-400 block">Tự chấm</span>
                                        <strong class="font-mono text-indigo-600 dark:text-indigo-400">
                                            <?= $item['self_score'] !== null ? round($item['self_score'], 1) : '--' ?>
                                        </strong>
                                    </div>
                                    <div class="border-x border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-400 block">Quản lý</span>
                                        <strong class="font-mono text-violet-600 dark:text-violet-400">
                                            <?= $item['manager_score'] !== null ? round($item['manager_score'], 1) : '--' ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Tổng kết</span>
                                        <strong class="font-mono text-emerald-600 dark:text-emerald-400">
                                            <?= $item['final_score'] !== null ? round($item['final_score'], 1) : '--' ?>
                                        </strong>
                                    </div>
                                </div>

                                <!-- Xếp loại & Hệ số thưởng -->
                                <?php if ($rt): ?>
                                    <div class="flex items-center justify-between text-[11px] p-2 rounded-xl bg-slate-50 dark:bg-slate-900/60">
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= $rt['class'] ?>">
                                            <?= $rt['label'] ?>
                                        </span>
                                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-[11px]">
                                            Thưởng K = x<?= number_format($k_mult, 2) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Footer: Reviewer & Action -->
                                <div class="flex items-center justify-between pt-1 text-[11px] text-slate-500">
                                    <div class="truncate max-w-[130px]" title="Quản lý: <?= e($item['reviewer_name']) ?>">
                                        <i class="fa-solid fa-user-tie mr-1 text-slate-400"></i><?= e($item['reviewer_name']) ?>
                                    </div>

                                    <a href="<?= base_url('modules/evaluations/view.php?id=' . $item['id']) ?>" 
                                       class="px-2.5 py-1 bg-red-50 hover:bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 font-bold rounded-lg transition flex items-center gap-1">
                                        <span>Xem / Chấm</span>
                                        <i class="fa-solid fa-arrow-right text-[9px]"></i>
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

<!-- ======================================================== -->
<!-- MODAL: GIAO MỤC TIÊU ĐỢT CHO PHÒNG BAN                   -->
<!-- ======================================================== -->
<div id="targetModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-4xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-slate-200 dark:border-slate-700">
        
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-slate-800/95 backdrop-blur-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div>
                    <h3 id="targetModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Giao Mục Tiêu Đợt Cho Phòng Ban</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Thiết lập mục tiêu trọng tâm đợt &bull; Tự động phân bổ cho toàn bộ nhân sự</p>
                </div>
            </div>
            <button type="button" onclick="closeTargetModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" id="targetForm" class="p-6 space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_department_target">
            <input type="hidden" name="period_id" value="<?= $filter_period ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Khối / Phòng Ban Áp Dụng <span class="text-red-500">*</span>
                    </label>
                    <select name="department_id" id="form_dept_id" required onchange="handleDeptSelectChange(this)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Kỳ / Đợt Đánh Giá
                    </label>
                    <input type="text" readonly value="<?= e($current_period['name'] ?? 'Đợt hiện tại') ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Tiêu Đề Mục Tiêu Chiến Lược Phòng Ban Trong Đợt <span class="text-red-500">*</span>
                </label>
                <input type="text" name="target_title" id="form_target_title" required
                       placeholder="VD: Tối Ưu Hóa Năng Suất Bán Lẻ & Chuẩn Mực Dịch Vụ WinMart"
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Mô Tả & Định Hướng Của Ban Giám Đốc
                </label>
                <textarea name="target_description" id="form_target_description" rows="2"
                          placeholder="Mô tả bối cảnh, trọng tâm ưu tiên và cam kết đầu ra của phòng ban..."
                          class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>
            </div>

            <!-- Bảng chỉ tiêu SMART thành phần của phòng ban -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-red-600"></i>
                            <span>Danh Sách Chỉ Tiêu SMART Của Phòng Ban (Tùy Chọn)</span>
                        </h4>
                        <p class="text-[11px] text-slate-400">Các chỉ tiêu trọng tâm này sẽ hiển thị làm kim chỉ nam trên phiếu đánh giá của nhân viên</p>
                    </div>

                    <button type="button" onclick="addDeptTargetRow()" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> Thêm Chỉ Tiêu
                    </button>
                </div>

                <div class="space-y-2.5" id="deptTargetRowsContainer">
                    <!-- Dynamic Target Rows will be added here -->
                </div>
            </div>

            <!-- Checkbox Phân Bổ Tự Động Cho Nhân Viên -->
            <div class="p-3.5 bg-red-50/60 dark:bg-red-950/40 rounded-2xl border border-red-100 dark:border-red-900/60 flex items-start gap-3">
                <input type="checkbox" name="auto_cascade" id="chk_auto_cascade" value="1" checked
                       class="mt-0.5 rounded text-red-600 focus:ring-red-500 border-slate-300 dark:border-slate-700">
                <label for="chk_auto_cascade" class="text-xs text-slate-800 dark:text-slate-200 cursor-pointer">
                    <strong class="text-red-700 dark:text-red-300">Tự động phân bổ & sinh phiếu đánh giá cho nhân viên:</strong>
                    <span class="block text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                        Hệ thống sẽ tự động tìm tất cả nhân sự thuộc phòng ban này chưa có phiếu trong đợt và khởi tạo phiếu đánh giá kèm liên kết mục tiêu phòng ban.
                    </span>
                </label>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeTargetModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-md shadow-red-200 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu Mục Tiêu & Phân Bổ</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: TẠO ĐỢT ĐÁNH GIÁ MỚI                              -->
<!-- ======================================================== -->
<div id="newPeriodModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Khởi Tạo Đợt Đánh Giá KPI Mới</h3>
                    <p class="text-xs text-slate-500">Mở đợt đánh giá hiệu suất định kỳ theo Quý hoặc Năm</p>
                </div>
            </div>
            <button type="button" onclick="closeNewPeriodModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="create_period">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mã Đợt <span class="text-red-500">*</span></label>
                    <input type="text" name="code" required placeholder="VD: PERIOD-2026-Q4" value="PERIOD-<?= date('Y') ?>-Q4"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white uppercase focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Năm <span class="text-red-500">*</span></label>
                    <input type="number" name="year" required value="<?= date('Y') ?>" min="2020" max="2035"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Quý</label>
                    <select name="quarter" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                        <option value="1">Quý 1</option>
                        <option value="2">Quý 2</option>
                        <option value="3">Quý 3</option>
                        <option value="4" selected>Quý 4 (Chiến Dịch Tết)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tên Đợt Đánh Giá <span class="text-red-500">*</span></label>
                <input type="text" name="name" required placeholder="VD: Đánh Giá Hiệu Suất Quý 4/2026 - Chiến Dịch Tết WinMart"
                       value="Đánh Giá Hiệu Suất & KPI Quý 4/<?= date('Y') ?> - Chiến Dịch Phục Vụ Tết"
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Bắt Đầu <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" required value="<?= date('Y') ?>-10-01"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Kết Thúc <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" required value="<?= date('Y') ?>-12-31"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Hạn Chót NV Tự Đánh Giá</label>
                    <input type="date" name="self_review_deadline" value="<?= (int)date('Y') + 1 ?>-01-10"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Hạn Chót Quản Lý Thẩm Tra</label>
                    <input type="date" name="manager_review_deadline" value="<?= (int)date('Y') + 1 ?>-01-20"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mô Tả & Trọng Tâm Chiến Dịch</label>
                <textarea name="description" rows="2" placeholder="Ghi chú mục tiêu thi đua toàn tập đoàn..."
                          class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">Phục vụ cao điểm mua sắm Tết Nguyên Đán, đảm bảo chuỗi cung ứng hàng hóa thông suốt và dịch vụ khách hàng vượt trội.</textarea>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeNewPeriodModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold">
                    Hủy
                </button>
                <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-md shadow-red-200 dark:shadow-none flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i>
                    <span>Tạo Đợt Đánh Giá</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dữ liệu ma trận phòng ban để nạp tự động khi bấm Sửa Mục Tiêu
window.DEPT_MATRIX_DATA = <?= json_encode($dept_matrix, JSON_UNESCAPED_UNICODE) ?>;
let deptRowIndex = 0;

function openTargetModal() {
    document.getElementById('form_dept_id').value = document.getElementById('form_dept_id').options[0].value;
    handleDeptSelectChange(document.getElementById('form_dept_id'));
    document.getElementById('targetModal').classList.remove('hidden');
}

function openTargetModalForDept(dept) {
    document.getElementById('form_dept_id').value = dept.department_id;
    handleDeptSelectChange(document.getElementById('form_dept_id'));
    document.getElementById('targetModal').classList.remove('hidden');
}

function closeTargetModal() {
    document.getElementById('targetModal').classList.add('hidden');
}

function openNewPeriodModal() {
    document.getElementById('newPeriodModal').classList.remove('hidden');
}

function closeNewPeriodModal() {
    document.getElementById('newPeriodModal').classList.add('hidden');
}

function handleDeptSelectChange(select) {
    const deptId = parseInt(select.value);
    const found = (window.DEPT_MATRIX_DATA || []).find(d => d.department_id === deptId);

    const titleInput = document.getElementById('form_target_title');
    const descInput = document.getElementById('form_target_description');
    const container = document.getElementById('deptTargetRowsContainer');
    container.innerHTML = '';
    deptRowIndex = 0;

    if (found && found.has_target && found.target) {
        titleInput.value = found.target.target_title || '';
        descInput.value = found.target.target_description || '';

        if (found.target.kpi_items && found.target.kpi_items.length > 0) {
            found.target.kpi_items.forEach(it => {
                addDeptTargetRow(it.criteria_name, it.target_value, it.unit, it.weight, it.bsc_pillar, it.priority_group);
            });
        } else {
            addDeptTargetRow('Doanh số / Khối lượng hoàn thành', '≥ 100%', '%', 40, 'financial', 'A');
            addDeptTargetRow('Chất lượng chuyên môn & SLA', '≥ 95%', '%', 30, 'internal_process', 'B');
        }
    } else {
        titleInput.value = found ? `Mục Tiêu Chiến Lược Khối ${found.department_name}` : '';
        descInput.value = 'Đảm bảo hoàn thành các chỉ tiêu trọng tâm theo kế hoạch kinh doanh & vận hành.';
        addDeptTargetRow('Doanh số / Khối lượng hoàn thành', '≥ 100%', '%', 40, 'financial', 'A');
        addDeptTargetRow('Chất lượng chuyên môn & SLA', '≥ 95%', '%', 30, 'internal_process', 'B');
        addDeptTargetRow('Cải tiến quy trình & Đào tạo', '≥ 1 sáng kiến', 'Sáng kiến', 30, 'learning_growth', 'C');
    }
}

function addDeptTargetRow(name = '', value = '≥ 100%', unit = '%', weight = 25, bsc = 'internal_process', priority = 'B') {
    deptRowIndex++;
    const container = document.getElementById('deptTargetRowsContainer');
    const rowId = `dept_target_row_${deptRowIndex}`;

    const div = document.createElement('div');
    div.id = rowId;
    div.className = 'p-3 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-2';

    div.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
            <div class="sm:col-span-2">
                <select name="target_criteria_priority[]" class="w-full px-2 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-800 dark:text-white">
                    <option value="A" ${priority === 'A' ? 'selected' : ''}>Nhóm A (Cốt lõi)</option>
                    <option value="B" ${priority === 'B' ? 'selected' : ''}>Nhóm B (Vận hành)</option>
                    <option value="C" ${priority === 'C' ? 'selected' : ''}>Nhóm C (Cải tiến)</option>
                </select>
            </div>

            <div class="sm:col-span-6">
                <input type="text" name="target_criteria_name[]" value="${escapeHtml(name)}" required placeholder="Tên chỉ tiêu (VD: Doanh thu chuỗi, SLA giao hàng...)"
                       class="w-full px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500">
            </div>

            <div class="sm:col-span-2">
                <input type="text" name="target_criteria_value[]" value="${escapeHtml(value)}" required placeholder="Chỉ tiêu (Target)"
                       class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-red-600 dark:text-red-400 focus:ring-2 focus:ring-red-500">
            </div>

            <div class="sm:col-span-1">
                <input type="text" name="target_criteria_unit[]" value="${escapeHtml(unit)}" placeholder="ĐVT"
                       class="w-full px-2 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-center text-slate-700 dark:text-slate-300">
            </div>

            <div class="sm:col-span-1 text-right">
                <button type="button" onclick="document.getElementById('${rowId}').remove()" class="p-1.5 text-rose-500 hover:text-rose-700 transition" title="Xóa">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `;

    container.appendChild(div);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
