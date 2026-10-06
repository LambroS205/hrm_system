<?php
// modules/evaluations/index.php - Trung Tâm Quản Trị Đánh Giá & KPI (Aura Performance Management)
$page_title = 'Đánh Giá Nhân Viên & Quản Lý KPI';

require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

// Tham số bộ lọc
$filter_period = !empty($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
$filter_dept   = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_status = $_GET['status'] ?? '';
$filter_rating = $_GET['rating'] ?? '';
$search        = trim($_GET['search'] ?? '');
$view_mode     = $_GET['view'] ?? 'kanban'; // 'kanban' hoặc 'table'

// Lấy danh sách kỳ đánh giá cho dropdown lọc
$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY status = 'active' DESC, year DESC, quarter DESC")->fetchAll();

// Mặc định chọn kỳ active đầu tiên nếu chưa chọn kỳ nào
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

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Truy vấn danh sách đánh giá
$sql = "
    SELECT e.*,
           ep.code AS period_code, ep.name AS period_name, ep.self_review_deadline, ep.manager_review_deadline,
           emp.employee_code, emp.fullname AS employee_name, emp.avatar AS employee_avatar,
           d.name AS department_name, p.name AS position_name,
           rev.employee_code AS reviewer_code, rev.fullname AS reviewer_name, rev.avatar AS reviewer_avatar,
           t.name AS template_name, t.code AS template_code
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    LEFT JOIN departments d ON emp.department_id = d.id
    LEFT JOIN positions p ON emp.position_id = p.id
    JOIN employees rev ON e.reviewer_id = rev.id
    JOIN kpi_templates t ON e.template_id = t.id
    WHERE 1=1
";
$params = [];

if ($filter_period > 0) {
    $sql .= " AND e.period_id = ?";
    $params[] = $filter_period;
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

// Thống kê tổng hợp (Theo đợt được chọn hoặc toàn cục)
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
        'title' => 'NV Tự Đánh Giá',
        'badge' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300',
        'border' => 'border-t-sky-500',
        'icon' => 'fa-user-pen',
        'items' => []
    ],
    'manager_review' => [
        'title' => 'Quản Lý Đang Chấm',
        'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        'border' => 'border-t-amber-500',
        'icon' => 'fa-user-tie',
        'items' => []
    ],
    'completed' => [
        'title' => 'Đã Hoàn Tất & Xếp Loại',
        'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        'border' => 'border-t-emerald-500',
        'icon' => 'fa-circle-check',
        'items' => []
    ]
];

foreach ($evaluations as $item) {
    $st = $item['status'];
    if (isset($kanban_columns[$st])) {
        $kanban_columns[$st]['items'][] = $item;
    }
}

// Cấu hình nhãn xếp loại
$rating_labels = [
    'outstanding'   => ['label' => 'Xuất Sắc', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700'],
    'excellent'     => ['label' => 'Tốt', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700'],
    'good'          => ['label' => 'Khá', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700'],
    'average'       => ['label' => 'Trung Bình', 'class' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600'],
    'below_average' => ['label' => 'Yếu', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-300 dark:border-orange-700'],
    'poor'          => ['label' => 'Kém', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <span>Quản Trị Nhân Sự</span>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400">Đánh Giá & KPI</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <span>Trung Tâm Đánh Giá & Quản Lý KPI</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Hệ thống đánh giá hiệu suất 2 chiều (Nhân viên tự chấm & Quản lý chấm) tích hợp biểu đồ mạng nhện và tự động xếp loại.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= base_url('modules/evaluations/kpi_templates.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-layer-group text-indigo-500"></i>
                <span>Bộ Tiêu Chí KPI</span>
            </a>

            <a href="<?= base_url('modules/evaluations/periods.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span>Kỳ Đánh Giá</span>
            </a>

            <?php if (has_permission('evaluations', 'create')): ?>
                <a href="<?= base_url('modules/evaluations/form.php') ?>" 
                   class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tạo Phiếu Đánh Giá</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số KPI Trọng Điểm -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Thống kê 1: Tổng số phiếu -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tổng Phiếu Đánh Giá</span>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-1">
                    <?= $stats['total_count'] ?>
                </div>
                <div class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-file-signature"></i>
                    <span>Kỳ: <?= count($periods) ?> đợt đã thiết lập</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
        </div>

        <!-- Thống kê 2: Tỷ lệ hoàn thành -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Đã Hoàn Tất & Xếp Loại</span>
                <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                    <?= $stats['completed_count'] ?> <span class="text-xs font-normal text-slate-400">/ <?= $stats['total_count'] ?></span>
                </div>
                <div class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Tỷ lệ hoàn thành: <?= $completion_rate ?>%</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-lg shadow-md shadow-emerald-200 dark:shadow-none">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Thống kê 3: Đang chờ quản lý chấm -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Chờ Quản Lý Chấm Điểm</span>
                <div class="text-2xl font-black font-mono text-amber-600 dark:text-amber-400 mt-1">
                    <?= $stats['mgr_review_count'] ?>
                </div>
                <div class="text-xs text-amber-700 dark:text-amber-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-clock"></i>
                    <span><?= $stats['self_review_count'] ?> nhân viên đang tự chấm</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg shadow-md shadow-amber-200 dark:shadow-none">
                <i class="fa-solid fa-user-clock"></i>
            </div>
        </div>

        <!-- Thống kê 4: Điểm TB toàn đợt -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Điểm KPI Trung Bình</span>
                <div class="text-2xl font-black font-mono text-violet-600 dark:text-violet-400 mt-1">
                    <?= $stats['avg_final_score'] ? round($stats['avg_final_score'], 1) : '---' ?>
                    <span class="text-xs font-normal text-slate-400">/ 100</span>
                </div>
                <div class="text-xs text-violet-700 dark:text-violet-300 font-semibold mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-award"></i>
                    <span>Khung điểm chuẩn toàn tập đoàn</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-violet-600 text-white flex items-center justify-center text-lg shadow-md shadow-violet-200 dark:shadow-none">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>

    </div>

    <!-- Thanh Lọc & Bộ Công Cụ Tìm Kiếm + Chuyển Chế Độ Xem (Kanban / Table) -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
        <form method="GET" class="flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="view" value="<?= e($view_mode) ?>">

            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Search input -->
                <div class="relative min-w-[200px] sm:min-w-[240px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Tên NV, mã NV hoặc mã phiếu..."
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Lọc Kỳ đánh giá -->
                <select name="period_id" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500">
                    <option value="0">-- Tất cả các kỳ --</option>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_period == $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (<?= $p['status'] === 'active' ? 'Đang mở' : 'Đã đóng' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Lọc Phòng ban -->
                <select name="department_id" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Lọc Xếp loại -->
                <select name="rating" onchange="this.form.submit()"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả xếp loại --</option>
                    <option value="outstanding" <?= $filter_rating === 'outstanding' ? 'selected' : '' ?>>Xuất sắc (≥95)</option>
                    <option value="excellent" <?= $filter_rating === 'excellent' ? 'selected' : '' ?>>Tốt (85-94.9)</option>
                    <option value="good" <?= $filter_rating === 'good' ? 'selected' : '' ?>>Khá (70-84.9)</option>
                    <option value="average" <?= $filter_rating === 'average' ? 'selected' : '' ?>>Trung bình (55-69.9)</option>
                    <option value="below_average" <?= $filter_rating === 'below_average' ? 'selected' : '' ?>>Yếu (40-54.9)</option>
                    <option value="poor" <?= $filter_rating === 'poor' ? 'selected' : '' ?>>Kém (<40)</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Lọc
                </button>

                <?php if ($filter_dept > 0 || !empty($filter_status) || !empty($filter_rating) || !empty($search)): ?>
                    <a href="<?= base_url('modules/evaluations/index.php?period_id=' . $filter_period) ?>" class="text-xs text-rose-500 hover:underline">
                        Xóa bộ lọc
                    </a>
                <?php endif; ?>
            </div>

            <!-- Switch View (Kanban / Table) -->
            <div class="flex items-center bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-700 flex-shrink-0">
                <a href="<?= base_url('modules/evaluations/index.php?' . http_build_query(array_merge($_GET, ['view' => 'kanban']))) ?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?= $view_mode === 'kanban' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white' ?>">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Kanban</span>
                </a>
                <a href="<?= base_url('modules/evaluations/index.php?' . http_build_query(array_merge($_GET, ['view' => 'table']))) ?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?= $view_mode === 'table' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white' ?>">
                    <i class="fa-solid fa-table-list"></i>
                    <span>Bảng</span>
                </a>
            </div>
        </form>
    </div>

    <!-- ======================================================== -->
    <!-- 1. CHẾ ĐỘ KANBAN BOARD                                   -->
    <!-- ======================================================== -->
    <?php if ($view_mode === 'kanban'): ?>
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
                            <?php $rt = $rating_labels[$item['rating'] ?? ''] ?? null; ?>
                            <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm hover:shadow-md transition space-y-3">
                                
                                <!-- Top: Code + Rating -->
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded-md border border-indigo-200 dark:border-indigo-800">
                                        <?= e($item['evaluation_code']) ?>
                                    </span>

                                    <?php if ($rt): ?>
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= $rt['class'] ?>">
                                            <?= $rt['label'] ?>
                                        </span>
                                    <?php endif; ?>
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
                                            <?= e($item['position_name'] ?: '---') ?> • <?= e($item['department_name'] ?: '---') ?>
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

                                <!-- Footer: Reviewer & Action -->
                                <div class="flex items-center justify-between pt-1 text-[11px] text-slate-500">
                                    <div class="truncate max-w-[130px]" title="Quản lý: <?= e($item['reviewer_name']) ?>">
                                        <i class="fa-solid fa-user-tie mr-1 text-slate-400"></i><?= e($item['reviewer_name']) ?>
                                    </div>

                                    <a href="<?= base_url('modules/evaluations/view.php?id=' . $item['id']) ?>" 
                                       class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold rounded-lg transition flex items-center gap-1">
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

    <!-- ======================================================== -->
    <!-- 2. CHẾ ĐỘ BẢNG DANH SÁCH (TABLE VIEW)                    -->
    <!-- ======================================================== -->
    <?php else: ?>
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-4">Mã Phiếu</th>
                            <th class="py-3.5 px-4">Nhân Sự Được Đánh Giá</th>
                            <th class="py-3.5 px-4">Khối Phòng Ban</th>
                            <th class="py-3.5 px-4">Kỳ Đánh Giá</th>
                            <th class="py-3.5 px-4">Quản Lý Chấm</th>
                            <th class="py-3.5 px-4 text-center">Tự Chấm</th>
                            <th class="py-3.5 px-4 text-center">QL Chấm</th>
                            <th class="py-3.5 px-4 text-center">Điểm Tổng</th>
                            <th class="py-3.5 px-4 text-center">Xếp Loại</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($evaluations)): ?>
                            <tr>
                                <td colspan="11" class="py-12 text-center text-slate-400">
                                    Không tìm thấy hồ sơ đánh giá nào phù hợp với bộ lọc.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($evaluations as $item): ?>
                            <?php $rt = $rating_labels[$item['rating'] ?? ''] ?? null; ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40 transition">
                                
                                <td class="py-3 px-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    <a href="<?= base_url('modules/evaluations/view.php?id=' . $item['id']) ?>" class="hover:underline">
                                        <?= e($item['evaluation_code']) ?>
                                    </a>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <?= render_avatar($item['employee_name'], $item['employee_avatar'], 8) ?>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white"><?= e($item['employee_name']) ?></div>
                                            <div class="text-[11px] text-slate-400"><?= e($item['employee_code']) ?> • <?= e($item['position_name'] ?: '---') ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    <?= e($item['department_name'] ?: '---') ?>
                                </td>

                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    <?= e($item['period_name']) ?>
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
                                           class="p-1.5 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 rounded-lg transition" title="Xem & Chấm điểm">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>

                                        <?php if (has_permission('evaluations', 'edit')): ?>
                                            <a href="<?= base_url('modules/evaluations/form.php?id=' . $item['id']) ?>" 
                                               class="p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white rounded-lg transition" title="Chỉnh sửa">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (has_permission('evaluations', 'delete')): ?>
                                            <form method="POST" action="<?= base_url('modules/evaluations/delete.php') ?>" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa phiếu [<?= e($item['evaluation_code']) ?>]?')">
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
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
