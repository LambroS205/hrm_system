<?php
// modules/evaluations/form.php - Khởi Tạo & Chỉnh Sửa Phiếu Đánh Giá KPI
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('evaluations', 'edit');
    $page_title = 'Chỉnh Sửa Phiếu Đánh Giá KPI';
} else {
    require_permission('evaluations', 'create');
    $page_title = 'Khởi Tạo Phiếu Đánh Giá KPI';
}

// Lấy danh sách nhân viên kèm Vị trí & Phòng ban
$employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.avatar, e.department_id, e.position_id,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

// Lấy danh sách các kỳ đánh giá
$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY status = 'active' DESC, year DESC, quarter DESC")->fetchAll();

// Lấy danh sách bộ tiêu chí KPI kèm Vị trí liên kết
$templates = $pdo->query("
    SELECT t.*, d.name AS department_name, p.name AS position_name,
           (SELECT COUNT(*) FROM kpi_template_items WHERE template_id = t.id) AS items_count
    FROM kpi_templates t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN positions p ON t.position_id = p.id
    WHERE t.status = 'active'
    ORDER BY FIELD(t.hierarchy_level, 'executive', 'manager', 'specialist', 'operational'), t.name ASC
")->fetchAll();

// Lấy toàn bộ tiêu chí của tất cả templates để render client-side tức thì
$raw_items = $pdo->query("SELECT * FROM kpi_template_items ORDER BY template_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$template_items_by_template = [];
foreach ($raw_items as $item) {
    $template_items_by_template[$item['template_id']][] = $item;
}

$hierarchy_names = [
    'executive'   => '1. Cấp Lãnh Đạo (C-Level / Ban Giám Đốc)',
    'manager'     => '2. Cấp Quản Lý Cấp Trung (Manager / Trưởng Phòng)',
    'specialist'  => '3. Cấp Chuyên Viên / Senior (Specialist)',
    'operational' => '4. Cấp Nhân Viên Tuyến Đầu (Frontline / Vận Hành)'
];

// Khởi tạo model
$evaluation = [
    'evaluation_code'   => '',
    'period_id'         => isset($_GET['period_id']) ? (int)$_GET['period_id'] : '',
    'employee_id'       => isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : '',
    'template_id'       => '',
    'custom_items_json' => '',
    'goals_summary'     => '',
    'reviewer_id'       => 1, // Mặc định CEO / Admin
    'status'            => 'pending',
    'promotion_recommendation' => 'none'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy phiếu đánh giá này.');
        redirect('modules/evaluations/index.php');
    }
    $evaluation = $existing;
    // Nếu custom_items_json còn trống đối với phiếu cũ, fallback lấy từ template
    if (empty($evaluation['custom_items_json']) && !empty($evaluation['template_id'])) {
        $existing_items = $template_items_by_template[$evaluation['template_id']] ?? [];
        $evaluation['custom_items_json'] = json_encode($existing_items, JSON_UNESCAPED_UNICODE);
    }
} else {
    // Nếu chưa có period_id, lấy kỳ active đầu tiên
    if (empty($evaluation['period_id']) && !empty($periods)) {
        foreach ($periods as $p) {
            if ($p['status'] === 'active') {
                $evaluation['period_id'] = $p['id'];
                break;
            }
        }
        if (empty($evaluation['period_id'])) {
            $evaluation['period_id'] = $periods[0]['id'];
        }
    }

    // Tự sinh mã phiếu chuẩn DG-YYYY-QN-NNN
    $curr_period = null;
    foreach ($periods as $p) {
        if ($p['id'] == $evaluation['period_id']) {
            $curr_period = $p;
            break;
        }
    }

    $year = $curr_period ? $curr_period['year'] : date('Y');
    $quarter_part = ($curr_period && $curr_period['quarter']) ? '-Q' . $curr_period['quarter'] : '';
    $prefix = "DG-{$year}{$quarter_part}";

    $last_code = $pdo->query("SELECT evaluation_code FROM evaluations WHERE evaluation_code LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_seq = 1;
    if ($last_code && preg_match('/-(\d+)$/', $last_code, $matches)) {
        $next_seq = (int)$matches[1] + 1;
    }
    $evaluation['evaluation_code'] = sprintf('%s-%03d', $prefix, $next_seq);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $period_id = (int)($_POST['period_id'] ?? 0);
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $template_id = (int)($_POST['template_id'] ?? 0);
    $reviewer_id = (int)($_POST['reviewer_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $promotion_rec = $_POST['promotion_recommendation'] ?? 'none';
    $goals_summary = trim($_POST['goals_summary'] ?? '');

    $evaluation['period_id'] = $period_id;
    $evaluation['employee_id'] = $employee_id;
    $evaluation['template_id'] = $template_id;
    $evaluation['reviewer_id'] = $reviewer_id;
    $evaluation['status'] = $status;
    $evaluation['promotion_recommendation'] = $promotion_rec;
    $evaluation['goals_summary'] = $goals_summary;

    if ($period_id <= 0) $errors[] = 'Vui lòng chọn kỳ đánh giá.';
    if ($employee_id <= 0) $errors[] = 'Vui lòng chọn nhân viên được đánh giá.';
    if ($template_id <= 0) $errors[] = 'Vui lòng chọn bộ tiêu chí KPI áp dụng.';
    if ($reviewer_id <= 0) $errors[] = 'Vui lòng chọn quản lý chấm điểm (Reviewer).';

    // Xử lý danh sách tiêu chí tùy biến (Mục tiêu đề ra & trọng số theo vị trí)
    $custom_items = [];
    if (!empty($_POST['item_criteria_name']) && is_array($_POST['item_criteria_name'])) {
        $names = $_POST['item_criteria_name'];
        $p_groups = $_POST['item_priority_group'] ?? [];
        $pillars = $_POST['item_bsc_pillar'] ?? [];
        $descriptions = $_POST['item_description'] ?? [];
        $weights = $_POST['item_weight'] ?? [];
        $targets = $_POST['item_target_value'] ?? [];
        $units = $_POST['item_unit'] ?? [];
        $directions = $_POST['item_calc_direction'] ?? [];
        $datasources = $_POST['item_data_source'] ?? [];
        $ids = $_POST['item_id'] ?? [];

        $total_weight_check = 0;
        foreach ($names as $idx => $c_name) {
            $c_name = trim($c_name);
            if ($c_name === '') continue;

            $item_weight = (float)($weights[$idx] ?? 0);
            $total_weight_check += $item_weight;

            $custom_items[] = [
                'id'             => !empty($ids[$idx]) ? (int)$ids[$idx] : ($idx + 1),
                'criteria_name'  => $c_name,
                'priority_group' => in_array($p_groups[$idx] ?? '', ['A', 'B', 'C']) ? $p_groups[$idx] : 'B',
                'bsc_pillar'     => in_array($pillars[$idx] ?? '', ['financial', 'customer', 'internal_process', 'learning_growth']) ? $pillars[$idx] : 'internal_process',
                'description'    => trim($descriptions[$idx] ?? ''),
                'weight'         => $item_weight,
                'target_type'    => 'percentage',
                'unit'           => trim($units[$idx] ?? '%') ?: '%',
                'target_value'   => trim($targets[$idx] ?? '100%') ?: '100%',
                'calc_direction' => in_array($directions[$idx] ?? '', ['higher_better', 'lower_better', 'boolean']) ? $directions[$idx] : 'higher_better',
                'data_source'    => trim($datasources[$idx] ?? ''),
                'min_threshold'  => 0,
                'max_cap'        => 120,
                'sort_order'     => $idx + 1
            ];
        }
    }

    // Nếu không có custom items nào từ POST, fallback lấy items từ template
    if (empty($custom_items) && !empty($template_id)) {
        $custom_items = $template_items_by_template[$template_id] ?? [];
    }

    if (empty($custom_items)) {
        $errors[] = 'Hồ sơ đánh giá phải có ít nhất 1 tiêu chí KPI.';
    }

    $custom_items_json = !empty($custom_items) ? json_encode($custom_items, JSON_UNESCAPED_UNICODE) : null;
    $evaluation['custom_items_json'] = $custom_items_json;

    // Kiểm tra trùng lặp phiếu đánh giá của nhân viên trong cùng 1 kỳ
    if (empty($errors)) {
        $dup_sql = "SELECT id FROM evaluations WHERE period_id = ? AND employee_id = ?" . ($is_edit ? " AND id != ?" : "");
        $dup_params = [$period_id, $employee_id];
        if ($is_edit) $dup_params[] = $id;
        $dup_stmt = $pdo->prepare($dup_sql);
        $dup_stmt->execute($dup_params);
        if ($dup_stmt->fetch()) {
            $errors[] = 'Nhân sự này đã có phiếu đánh giá trong kỳ này rồi. Không thể tạo trùng lặp.';
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE evaluations
                    SET period_id = ?, employee_id = ?, template_id = ?, custom_items_json = ?, goals_summary = ?, reviewer_id = ?, status = ?, promotion_recommendation = ?
                    WHERE id = ?
                ");
                $stmt->execute([$period_id, $employee_id, $template_id, $custom_items_json, $goals_summary, $reviewer_id, $status, $promotion_rec, $id]);
                set_flash('success', "Đã cập nhật phiếu đánh giá [{$evaluation['evaluation_code']}] và thiết lập mục tiêu thành công!");
                redirect('modules/evaluations/view.php?id=' . $id);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluations (evaluation_code, period_id, employee_id, template_id, custom_items_json, goals_summary, reviewer_id, status, promotion_recommendation, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$evaluation['evaluation_code'], $period_id, $employee_id, $template_id, $custom_items_json, $goals_summary, $reviewer_id, $status, $promotion_rec]);
                $new_id = (int)$pdo->lastInsertId();
                set_flash('success', "Đã khởi tạo phiếu đánh giá [{$evaluation['evaluation_code']}] với mục tiêu đề ra theo vị trí thành công!");
                redirect('modules/evaluations/view.php?id=' . $new_id);
            }
        } catch (Exception $e) {
            $errors[] = 'Lỗi lưu dữ liệu: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Breadcrumbs -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" class="hover:underline">Đánh Giá & KPI</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400"><?= $is_edit ? 'Chỉnh Sửa' : 'Khởi Tạo' ?></span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid <?= $is_edit ? 'fa-pen-to-square' : 'fa-plus' ?>"></i>
                </div>
                <span><?= $is_edit ? 'Chỉnh Sửa Hồ Sơ Đánh Giá' : 'Khởi Tạo Phiếu Đánh Giá Mới' ?></span>
            </h1>
        </div>

        <a href="<?= base_url('modules/evaluations/index.php') ?>" 
           class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Quay Lại Danh Sách</span>
        </a>
    </div>

    <!-- Alert thông báo lỗi nếu có -->
    <?php if (!empty($errors)): ?>
        <div class="p-4 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-2xl text-xs text-rose-700 dark:text-rose-300 space-y-1">
            <div class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> Vui lòng sửa các lỗi sau:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-4">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Main Card Form -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <form method="POST" class="p-6 sm:p-8 space-y-6">
            <?= csrf_field() ?>

            <!-- Hàng 1: Mã phiếu & Kỳ đánh giá -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mã Phiếu Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" value="<?= e($evaluation['evaluation_code']) ?>" readonly
                           class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 cursor-not-allowed">
                    <p class="text-[11px] text-slate-400 mt-1">Mã hồ sơ tự động đồng bộ theo chuẩn AURA HRM</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Kỳ / Đợt Đánh Giá Hiệu Suất <span class="text-rose-500">*</span>
                    </label>
                    <select name="period_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $evaluation['period_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= $p['status'] === 'active' ? 'Đang mở' : 'Đã đóng' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Hàng 2: Nhân viên & Bộ tiêu chí -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Nhân Sự Được Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <select name="employee_id" id="select_employee" required onchange="handleEmployeeChange(this)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Nhân Viên --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" 
                                    data-dept-id="<?= $emp['department_id'] ?>" 
                                    data-dept-name="<?= e($emp['department_name'] ?: 'Chưa phân bổ') ?>"
                                    data-position-id="<?= $emp['position_id'] ?>"
                                    data-position-name="<?= e($emp['position_name'] ?: 'Chưa phân bổ vị trí') ?>"
                                    <?= $evaluation['employee_id'] == $emp['id'] ? 'selected' : '' ?>>
                                <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) - <?= e($emp['position_name'] ?: 'Chưa có vị trí') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Dynamic Position Badge -->
                    <div id="emp_pos_badge" class="mt-2 text-xs p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-between <?= empty($evaluation['employee_id']) ? 'hidden' : '' ?>">
                        <div class="flex items-center gap-2 text-indigo-700 dark:text-indigo-300 font-semibold truncate">
                            <i class="fa-solid fa-id-badge text-indigo-500"></i>
                            <span class="truncate">Vị trí: <strong id="emp_pos_text">...</strong></span>
                        </div>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono ml-2 flex-shrink-0" id="emp_dept_text">...</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Bộ Tiêu Chí KPI Áp Dụng <span class="text-rose-500">*</span>
                    </label>
                    <select name="template_id" id="select_template" required onchange="handleTemplateChange(this)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Bộ Tiêu Chí KPI Phù Hợp --</option>
                        <?php 
                        $grouped_templates = [];
                        foreach ($templates as $t) {
                            $lvl = $t['hierarchy_level'] ?? 'specialist';
                            $grouped_templates[$lvl][] = $t;
                        }
                        foreach ($hierarchy_names as $h_key => $h_label): 
                            if (empty($grouped_templates[$h_key])) continue;
                        ?>
                            <optgroup label="<?= e($h_label) ?>">
                                <?php foreach ($grouped_templates[$h_key] as $t): ?>
                                    <option value="<?= $t['id'] ?>" 
                                            data-dept-id="<?= $t['department_id'] ?>" 
                                            data-position-id="<?= $t['position_id'] ?>"
                                            data-position-name="<?= e($t['position_name']) ?>"
                                            data-level="<?= e($t['hierarchy_level']) ?>"
                                            <?= $evaluation['template_id'] == $t['id'] ? 'selected' : '' ?>>
                                        [<?= e($t['code']) ?>] <?= e($t['name']) ?> 
                                        <?= $t['position_name'] ? '— [' . e($t['position_name']) . ']' : '' ?> 
                                        (<?= $t['items_count'] ?> tiêu chí)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Hệ thống tự động ưu tiên bộ tiêu chí chuyên sâu đúng theo Vị trí công tác</p>
                </div>
            </div>

            <!-- Hàng 3: Quản lý đánh giá (Reviewer) & Trạng thái -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Quản Lý Chấm Điểm (Reviewer) <span class="text-rose-500">*</span>
                    </label>
                    <select name="reviewer_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($employees as $rev): ?>
                            <option value="<?= $rev['id'] ?>" <?= $evaluation['reviewer_id'] == $rev['id'] ? 'selected' : '' ?>>
                                <?= e($rev['fullname']) ?> (<?= e($rev['position_name'] ?: 'Quản lý') ?> - <?= e($rev['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Cán bộ phụ trách xem xét, nhận xét và chấm điểm quản lý</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Trạng Thái Tiến Trình
                    </label>
                    <select name="status"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="pending" <?= $evaluation['status'] === 'pending' ? 'selected' : '' ?>>Chờ thực hiện (Pending)</option>
                        <option value="self_review" <?= $evaluation['status'] === 'self_review' ? 'selected' : '' ?>>Nhân viên đang tự chấm (Self-Review)</option>
                        <option value="manager_review" <?= $evaluation['status'] === 'manager_review' ? 'selected' : '' ?>>Quản lý đang chấm (Manager-Review)</option>
                        <option value="completed" <?= $evaluation['status'] === 'completed' ? 'selected' : '' ?>>Đã hoàn tất đánh giá (Completed)</option>
                    </select>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- KHỐI MỤC TIÊU ĐỀ RA & TIÊU CHÍ THEO VỊ TRÍ (SMART & OKR)   -->
            <!-- ======================================================== -->
            <div class="p-5 sm:p-6 bg-slate-50/90 dark:bg-slate-900/60 rounded-3xl border border-slate-200 dark:border-slate-700 space-y-5">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/80 dark:border-slate-700/80">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs">
                                <i class="fa-solid fa-bullseye"></i>
                            </span>
                            <span>Mục Tiêu Đề Ra & Tiêu Chí Đánh Giá Cho Kỳ Này</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Tiêu chí được gắn liền theo Vị trí. Bạn có thể trực tiếp điều chỉnh <strong>Chỉ tiêu mục tiêu (Target)</strong>, trọng số hoặc thêm nhiệm vụ chiến lược riêng.
                        </p>
                    </div>

                    <!-- Total Weight Status Badge -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span id="badge_total_weight" class="px-3.5 py-1.5 rounded-xl text-xs font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-scale-balanced"></i>
                            <span>Tổng trọng số: <strong id="lbl_total_weight">100</strong>%</span>
                        </span>
                    </div>
                </div>

                <!-- Cam kết mục tiêu đề ra trong kỳ (goals_summary) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-flag-checkered text-amber-500"></i>
                        <span>Cam Kết Mục Tiêu Trọng Tâm Đề Ra Trong Kỳ (Goals Summary / Key Milestone)</span>
                    </label>
                    <textarea name="goals_summary" rows="2" placeholder="Ví dụ: Kỳ này tập trung tối ưu hóa 20% chi phí hạ tầng Cloud, nâng SLA Uptime lên 99.98%, kiểm soát lỗi P1 <= 2 sự cố và hoàn thành dự án nâng cấp bảo mật Zero-Trust..."
                              class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500"><?= e($evaluation['goals_summary'] ?? '') ?></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Bản cam kết mục tiêu định lượng giúp quản lý và nhân sự có định hướng rõ ràng xuyên suốt kỳ làm việc.</p>
                </div>

                <!-- Bảng danh sách tiêu chí & chỉ tiêu đề ra -->
                <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300 border-collapse">
                        <thead class="bg-slate-100/90 dark:bg-slate-900/80 text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-3 px-3 w-10 text-center">#</th>
                                <th class="py-3 px-3 min-w-[220px]">Tiêu Chí Đánh Giá & Phân Nhóm</th>
                                <th class="py-3 px-3 min-w-[150px]">Chỉ Tiêu Mục Tiêu Đề Ra (Target)</th>
                                <th class="py-3 px-3 w-24">Đơn Vị</th>
                                <th class="py-3 px-3 w-36">Chiều Đo</th>
                                <th class="py-3 px-3 w-28 text-center">Trọng Số (%)</th>
                                <th class="py-3 px-3 w-12 text-center">Xóa</th>
                            </tr>
                        </thead>
                        <tbody id="items_tbody" class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                            <!-- Render bằng JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Action buttons dưới bảng -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <button type="button" onclick="addNewCustomCriteriaRow()"
                            class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold text-xs transition flex items-center gap-2 border border-indigo-200 dark:border-indigo-800">
                        <i class="fa-solid fa-plus"></i>
                        <span>+ Thêm Mục Tiêu / Chỉ Tiêu Đột Xuất Riêng Kỳ Này</span>
                    </button>

                    <button type="button" onclick="resetToTemplateDefault()"
                            class="px-3.5 py-2 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800 text-xs transition flex items-center gap-1.5 font-medium">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Khôi phục tiêu chí gốc của Vị trí</span>
                    </button>
                </div>

            </div>

            <!-- Hàng 4: Đề xuất hành động nhân sự -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Định Hướng / Đề Xuất Nhân Sự (Tùy Chọn)
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                    <?php 
                    $recs = [
                        'none' => ['Chưa có', 'fa-minus', 'text-slate-500'],
                        'bonus' => ['Khen thưởng', 'fa-award', 'text-emerald-500'],
                        'salary_increase' => ['Tăng lương', 'fa-arrow-trend-up', 'text-indigo-500'],
                        'promotion' => ['Bổ nhiệm', 'fa-crown', 'text-amber-500'],
                        'training' => ['Đào tạo lại', 'fa-chalkboard-user', 'text-sky-500'],
                        'warning' => ['Nhắc nhở', 'fa-triangle-exclamation', 'text-rose-500']
                    ];
                    foreach ($recs as $key => $rInfo): 
                    ?>
                        <label class="flex flex-col items-center justify-center p-3 rounded-2xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition <?= $evaluation['promotion_recommendation'] === $key ? 'bg-indigo-50/70 dark:bg-indigo-950/50 border-indigo-500 dark:border-indigo-500' : '' ?>">
                            <input type="radio" name="promotion_recommendation" value="<?= $key ?>" <?= $evaluation['promotion_recommendation'] === $key ? 'checked' : '' ?> class="sr-only">
                            <i class="fa-solid <?= $rInfo[1] ?> text-base <?= $rInfo[2] ?> mb-1"></i>
                            <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 text-center"><?= $rInfo[0] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-between pt-6 border-t border-slate-100 dark:border-slate-700">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" 
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition">
                    Hủy Bỏ
                </a>

                <button type="submit" 
                        class="px-7 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-200 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?= $is_edit ? 'Cập Nhật Phiếu Đánh Giá' : 'Lưu & Khởi Tạo Phiếu' ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dữ liệu template items và custom items nạp sẵn
window.TEMPLATE_ITEMS_DATA = <?= json_encode($template_items_by_template, JSON_UNESCAPED_UNICODE) ?>;
window.INITIAL_CUSTOM_ITEMS = <?= !empty($evaluation['custom_items_json']) ? $evaluation['custom_items_json'] : 'null' ?>;

// Khi đổi nhân viên: Nhận diện vị trí và tự động tìm template tương ứng
function handleEmployeeChange(select) {
    const selectedOption = select.options[select.selectedIndex];
    const badge = document.getElementById('emp_pos_badge');
    const posText = document.getElementById('emp_pos_text');
    const deptText = document.getElementById('emp_dept_text');

    if (!selectedOption || !selectedOption.value) {
        if (badge) badge.classList.add('hidden');
        return;
    }

    const posId = selectedOption.getAttribute('data-position-id');
    const posName = selectedOption.getAttribute('data-position-name');
    const deptId = selectedOption.getAttribute('data-dept-id');
    const deptName = selectedOption.getAttribute('data-dept-name');

    if (badge && posText && deptText) {
        posText.textContent = posName || 'Chưa xác định';
        deptText.textContent = deptName || '';
        badge.classList.remove('hidden');
    }

    // Tự động tìm template phù hợp với vị trí của nhân viên
    const templateSelect = document.getElementById('select_template');
    if (templateSelect) {
        let matchedIndex = -1;

        // Ưu tiên 1: Khớp chính xác Vị trí công tác (position_id)
        if (posId) {
            for (let i = 0; i < templateSelect.options.length; i++) {
                const opt = templateSelect.options[i];
                if (opt.getAttribute('data-position-id') == posId) {
                    matchedIndex = i;
                    break;
                }
            }
        }

        // Ưu tiên 2: Khớp theo Phòng ban (department_id) nếu chưa có template vị trí
        if (matchedIndex === -1 && deptId) {
            for (let i = 0; i < templateSelect.options.length; i++) {
                const opt = templateSelect.options[i];
                if (opt.getAttribute('data-dept-id') == deptId) {
                    matchedIndex = i;
                    break;
                }
            }
        }

        if (matchedIndex !== -1) {
            templateSelect.selectedIndex = matchedIndex;
            // Tải danh sách tiêu chí của template đó
            loadItemsForCurrentTemplate();
        }
    }
}

// Khi người dùng tự chọn template
function handleTemplateChange(select) {
    loadItemsForCurrentTemplate();
}

// Lấy danh sách tiêu chí của template đang chọn và render ra bảng
function loadItemsForCurrentTemplate() {
    const templateSelect = document.getElementById('select_template');
    if (!templateSelect || !templateSelect.value) {
        renderItemsTable([]);
        return;
    }
    const tplId = templateSelect.value;
    const items = window.TEMPLATE_ITEMS_DATA[tplId] || [];
    renderItemsTable(items);
}

// Khôi phục tiêu chí gốc của template đang chọn
function resetToTemplateDefault() {
    loadItemsForCurrentTemplate();
}

// Render các dòng tiêu chí ra bảng
function renderItemsTable(items) {
    const tbody = document.getElementById('items_tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!items || items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="py-8 text-center text-slate-400">
                    <i class="fa-solid fa-list-check mr-2"></i>Chưa có tiêu chí nào. Vui lòng chọn nhân viên hoặc bộ tiêu chí ở trên.
                </td>
            </tr>
        `;
        recalcTotalWeight();
        return;
    }

    items.forEach((it, idx) => {
        appendCriteriaRow(it, idx + 1);
    });

    recalcTotalWeight();
}

// Thêm 1 dòng vào bảng
function appendCriteriaRow(it, rowNum) {
    const tbody = document.getElementById('items_tbody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition criteria-row';

    const pGroup = it.priority_group || 'B';
    const bscPillar = it.bsc_pillar || 'internal_process';
    const direction = it.calc_direction || 'higher_better';
    const weight = parseFloat(it.weight || 20);
    const targetVal = it.target_value || '100%';
    const unit = it.unit || '%';
    const cName = it.criteria_name || '';
    const desc = it.description || '';
    const itemId = it.id || (Date.now() + Math.floor(Math.random() * 1000));
    const dataSource = it.data_source || '';

    tr.innerHTML = `
        <td class="py-3 px-3 text-center font-mono font-bold text-slate-400 row-index">
            ${rowNum}
            <input type="hidden" name="item_id[]" value="${escapeHtml(String(itemId))}">
            <input type="hidden" name="item_data_source[]" value="${escapeHtml(dataSource)}">
        </td>

        <td class="py-3 px-3 space-y-1.5">
            <input type="text" name="item_criteria_name[]" value="${escapeHtml(cName)}" required
                   placeholder="Tên tiêu chí / mục tiêu giao..."
                   class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
            <div class="flex items-center gap-2">
                <select name="item_priority_group[]" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                    <option value="A" ${pGroup === 'A' ? 'selected' : ''}>Nhóm A: Cốt lõi (~50%)</option>
                    <option value="B" ${pGroup === 'B' ? 'selected' : ''}>Nhóm B: Vận hành (~30%)</option>
                    <option value="C" ${pGroup === 'C' ? 'selected' : ''}>Nhóm C: Cải tiến (~20%)</option>
                </select>
                <select name="item_bsc_pillar[]" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded text-[11px] text-slate-600 dark:text-slate-400">
                    <option value="financial" ${bscPillar === 'financial' ? 'selected' : ''}>Tài chính</option>
                    <option value="customer" ${bscPillar === 'customer' ? 'selected' : ''}>Khách hàng</option>
                    <option value="internal_process" ${bscPillar === 'internal_process' ? 'selected' : ''}>Quy trình nội bộ</option>
                    <option value="learning_growth" ${bscPillar === 'learning_growth' ? 'selected' : ''}>Đào tạo & phát triển</option>
                </select>
            </div>
            <input type="hidden" name="item_description[]" value="${escapeHtml(desc)}">
        </td>

        <td class="py-3 px-3">
            <input type="text" name="item_target_value[]" value="${escapeHtml(targetVal)}" required
                   placeholder="VD: >= 99.98% hoặc 1.5 tỷ"
                   class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500">
        </td>

        <td class="py-3 px-3">
            <input type="text" name="item_unit[]" value="${escapeHtml(unit)}"
                   placeholder="%"
                   class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 text-center focus:ring-2 focus:ring-indigo-500">
        </td>

        <td class="py-3 px-3">
            <select name="item_calc_direction[]"
                    class="w-full px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500">
                <option value="higher_better" ${direction === 'higher_better' ? 'selected' : ''}>Thuận (Càng cao càng tốt)</option>
                <option value="lower_better" ${direction === 'lower_better' ? 'selected' : ''}>Nghịch (Càng thấp càng tốt)</option>
            </select>
        </td>

        <td class="py-3 px-3 text-center">
            <div class="inline-flex items-center gap-1">
                <input type="number" name="item_weight[]" value="${weight}" min="1" max="100" step="1" required
                       oninput="recalcTotalWeight()"
                       class="weight-input w-20 px-2 py-1.5 bg-indigo-50/70 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 rounded-lg text-xs font-mono font-black text-center text-indigo-700 dark:text-indigo-300 focus:ring-2 focus:ring-indigo-500">
                <span class="text-xs font-bold text-slate-400">%</span>
            </div>
        </td>

        <td class="py-3 px-3 text-center">
            <button type="button" onclick="deleteCriteriaRow(this)"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition flex items-center justify-center text-xs"
                    title="Xóa tiêu chí này">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
}

// Thêm dòng tiêu chí tùy biến mới (Nhiệm vụ đột xuất / OKR)
function addNewCustomCriteriaRow() {
    const tbody = document.getElementById('items_tbody');
    if (!tbody) return;
    const count = tbody.querySelectorAll('.criteria-row').length;

    const newItem = {
        id: Date.now(),
        criteria_name: 'Nhiệm vụ đột xuất / Mục tiêu đặc thù trong kỳ',
        priority_group: 'B',
        bsc_pillar: 'internal_process',
        weight: 10,
        target_value: '100%',
        unit: '%',
        calc_direction: 'higher_better',
        description: 'Mục tiêu giao riêng cho nhân sự trong kỳ đánh giá này.'
    };

    appendCriteriaRow(newItem, count + 1);
    recalcTotalWeight();
}

// Xóa một dòng tiêu chí
function deleteCriteriaRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
        // Cập nhật lại số thứ tự
        const tbody = document.getElementById('items_tbody');
        const rows = tbody.querySelectorAll('.criteria-row');
        rows.forEach((r, idx) => {
            const indexEl = r.querySelector('.row-index');
            if (indexEl) {
                // Chỉ đổi text node số thứ tự
                indexEl.childNodes[0].textContent = (idx + 1) + ' ';
            }
        });
        recalcTotalWeight();
    }
}

// Tính toán lại tổng trọng số và cập nhật màu sắc badge
function recalcTotalWeight() {
    const weightInputs = document.querySelectorAll('.weight-input');
    let total = 0;
    weightInputs.forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        total += val;
    });

    const lbl = document.getElementById('lbl_total_weight');
    const badge = document.getElementById('badge_total_weight');
    if (lbl) lbl.textContent = Math.round(total * 10) / 10;

    if (badge) {
        if (Math.abs(total - 100) < 0.01) {
            badge.className = 'px-3.5 py-1.5 rounded-xl text-xs font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 flex items-center gap-1.5';
        } else {
            badge.className = 'px-3.5 py-1.5 rounded-xl text-xs font-mono font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800 flex items-center gap-1.5';
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Khởi chạy khi load trang
document.addEventListener('DOMContentLoaded', function() {
    const empSelect = document.getElementById('select_employee');
    if (empSelect && empSelect.value) {
        // Cập nhật badge vị trí
        const selectedOption = empSelect.options[empSelect.selectedIndex];
        if (selectedOption) {
            const posName = selectedOption.getAttribute('data-position-name');
            const deptName = selectedOption.getAttribute('data-dept-name');
            const badge = document.getElementById('emp_pos_badge');
            const posText = document.getElementById('emp_pos_text');
            const deptText = document.getElementById('emp_dept_text');
            if (badge && posText && deptText) {
                posText.textContent = posName || 'Chưa xác định';
                deptText.textContent = deptName || '';
                badge.classList.remove('hidden');
            }
        }
    }

    // Nếu đã có INITIAL_CUSTOM_ITEMS (ví dụ chế độ edit hoặc đã có dữ liệu), render từ đó
    if (window.INITIAL_CUSTOM_ITEMS && Array.isArray(window.INITIAL_CUSTOM_ITEMS) && window.INITIAL_CUSTOM_ITEMS.length > 0) {
        renderItemsTable(window.INITIAL_CUSTOM_ITEMS);
    } else {
        // Nạp tiêu chí của template hiện tại nếu đã chọn
        loadItemsForCurrentTemplate();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
