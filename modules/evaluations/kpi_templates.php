<?php
// modules/evaluations/kpi_templates.php - Quản lý Bộ Tiêu Chí KPI Theo Vị Trí & Phân Tầng Cấp Bậc (BSC & SMART)
$page_title = 'Quản Lý Bộ Mẫu Tiêu Chí KPI Theo Vị Trí & Cấp Bậc';

require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

// Cấu hình Cấp bậc Quản trị Nhân sự (Hierarchy Levels)
$hierarchy_levels = [
    'executive'   => [
        'label'  => 'Lãnh Đạo Cấp Cao (C-Level)',
        'badge'  => 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800',
        'icon'   => 'fa-crown',
        'target' => 'P&L, Chiến lược toàn khối & Rủi ro (Nhóm A: 60%, B: 30%, C: 10%)'
    ],
    'manager'     => [
        'label'  => 'Quản Lý Cấp Trung (Manager)',
        'badge'  => 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        'icon'   => 'fa-user-tie',
        'target' => 'Hiệu suất đội ngũ, Tiến độ SLA, Kèm cặp (Nhóm A: 50%, B: 30%, C: 20%)'
    ],
    'specialist'  => [
        'label'  => 'Chuyên Viên / Senior',
        'badge'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'icon'   => 'fa-laptop-code',
        'target' => 'Năng suất cá nhân, Chất lượng chuyên môn (Nhóm A: 50%, B: 30%, C: 20%)'
    ],
    'operational' => [
        'label'  => 'Nhân Viên Tuyến Đầu (Frontline)',
        'badge'  => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        'icon'   => 'fa-person-walking-luggage',
        'target' => 'Định mức ca, Tuân thủ SOP, Chuyên cần (Nhóm A: 50%, B: 30%, C: 20%)'
    ]
];

// Cấu hình Trụ cột BSC (Balanced Scorecard Pillars)
$bsc_pillars = [
    'financial'        => ['label' => 'Tài Chính & Doanh Thu', 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/50', 'icon' => 'fa-coins'],
    'customer'         => ['label' => 'Khách Hàng & Thị Trường', 'color' => 'text-sky-600 dark:text-sky-400', 'bg' => 'bg-sky-50 dark:bg-sky-950/50', 'icon' => 'fa-users'],
    'internal_process' => ['label' => 'Quy Trình & Vận Hành', 'color' => 'text-indigo-600 dark:text-indigo-400', 'bg' => 'bg-indigo-50 dark:bg-indigo-950/50', 'icon' => 'fa-gears'],
    'learning_growth'  => ['label' => 'Đào Tạo & Phát Triển', 'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-950/50', 'icon' => 'fa-graduation-cap']
];

// Cấu hình Nhóm Mức độ Ưu tiên (Link Power Priority Matrix)
$priority_groups = [
    'A' => ['label' => 'Nhóm A: Cốt Lõi (~50%)', 'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border-rose-200 dark:border-rose-800', 'bar' => 'bg-rose-500'],
    'B' => ['label' => 'Nhóm B: Vận Hành (~30%)', 'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800', 'bar' => 'bg-indigo-500'],
    'C' => ['label' => 'Nhóm C: Cải Tiến (~20%)', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 border-amber-200 dark:border-amber-800', 'bar' => 'bg-amber-500']
];

// Lấy danh sách phòng ban và vị trí chức danh cho dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT id, name FROM positions ORDER BY name ASC")->fetchAll();

// Xử lý các POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. TẠO HOẶC CẬP NHẬT BỘ TIÊU CHÍ (TEMPLATE + ITEMS)
    if ($action_type === 'save_template') {
        require_permission('evaluations', 'edit');
        $template_id = (int)($_POST['template_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $hierarchy_level = in_array($_POST['hierarchy_level'] ?? '', ['executive', 'manager', 'specialist', 'operational']) ? $_POST['hierarchy_level'] : 'specialist';
        $description = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        
        $item_names = $_POST['criteria_name'] ?? [];
        $item_priorities = $_POST['criteria_priority_group'] ?? [];
        $item_bscs = $_POST['criteria_bsc_pillar'] ?? [];
        $item_descs = $_POST['criteria_desc'] ?? [];
        $item_weights = $_POST['criteria_weight'] ?? [];
        $item_types = $_POST['criteria_target_type'] ?? [];
        $item_targets = $_POST['criteria_target_value'] ?? [];
        $item_units = $_POST['criteria_unit'] ?? [];
        $item_calc_dirs = $_POST['criteria_calc_direction'] ?? [];
        $item_sources = $_POST['criteria_data_source'] ?? [];

        if (empty($code) || empty($name)) {
            set_flash('danger', 'Vui lòng nhập đầy đủ mã và tên bộ tiêu chí KPI.');
            redirect('modules/evaluations/kpi_templates.php');
        }

        // Tính tổng trọng số
        $total_weight = 0;
        $items_to_save = [];
        for ($i = 0; $i < count($item_names); $i++) {
            $c_name = trim($item_names[$i] ?? '');
            if (empty($c_name)) continue;
            $c_weight = (float)($item_weights[$i] ?? 0);
            $total_weight += $c_weight;
            $items_to_save[] = [
                'criteria_name'  => $c_name,
                'priority_group' => in_array($item_priorities[$i] ?? '', ['A', 'B', 'C']) ? $item_priorities[$i] : 'B',
                'bsc_pillar'     => in_array($item_bscs[$i] ?? '', ['financial', 'customer', 'internal_process', 'learning_growth']) ? $item_bscs[$i] : 'internal_process',
                'description'    => trim($item_descs[$i] ?? ''),
                'weight'         => $c_weight,
                'target_type'    => in_array($item_types[$i] ?? '', ['percentage', 'numeric', 'rating', 'boolean']) ? $item_types[$i] : 'percentage',
                'target_value'   => trim($item_targets[$i] ?? ''),
                'unit'           => trim($item_units[$i] ?? '%') ?: '%',
                'calc_direction' => in_array($item_calc_dirs[$i] ?? '', ['higher_better', 'lower_better', 'boolean']) ? $item_calc_dirs[$i] : 'higher_better',
                'data_source'    => trim($item_sources[$i] ?? ''),
                'sort_order'     => $i + 1
            ];
        }

        if (empty($items_to_save)) {
            set_flash('danger', 'Bộ tiêu chí phải có ít nhất một tiêu chí thành phần.');
            redirect('modules/evaluations/kpi_templates.php');
        }

        // Kiểm tra tổng trọng số phải xấp xỉ 100%
        if (abs($total_weight - 100.0) > 0.1) {
            set_flash('danger', 'Tổng trọng số các tiêu chí phải đạt đúng 100% (Hiện tại là ' . round($total_weight, 1) . '%).');
            redirect('modules/evaluations/kpi_templates.php');
        }

        $pdo->beginTransaction();
        try {
            if ($template_id > 0) {
                // Kiểm tra trùng mã với bản ghi khác
                $chk = $pdo->prepare("SELECT id FROM kpi_templates WHERE code = ? AND id != ?");
                $chk->execute([$code, $template_id]);
                if ($chk->fetch()) {
                    throw new Exception("Mã bộ tiêu chí [{$code}] đã tồn tại trong hệ thống.");
                }

                $stmt = $pdo->prepare("
                    UPDATE kpi_templates 
                    SET code = ?, name = ?, department_id = ?, position_id = ?, hierarchy_level = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $department_id, $position_id, $hierarchy_level, $description, $status, $template_id]);

                // Xóa items cũ và tạo lại
                $del = $pdo->prepare("DELETE FROM kpi_template_items WHERE template_id = ?");
                $del->execute([$template_id]);
            } else {
                // Kiểm tra trùng mã
                $chk = $pdo->prepare("SELECT id FROM kpi_templates WHERE code = ?");
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    throw new Exception("Mã bộ tiêu chí [{$code}] đã tồn tại trong hệ thống.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO kpi_templates (code, name, department_id, position_id, hierarchy_level, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $department_id, $position_id, $hierarchy_level, $description, $status]);
                $template_id = (int)$pdo->lastInsertId();
            }

            // Lưu các tiêu chí con với đầy đủ chuẩn SMART & Link Power
            $item_stmt = $pdo->prepare("
                INSERT INTO kpi_template_items (
                    template_id, criteria_name, priority_group, bsc_pillar, 
                    description, weight, target_type, target_value, unit, 
                    calc_direction, data_source, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items_to_save as $item) {
                $item_stmt->execute([
                    $template_id,
                    $item['criteria_name'],
                    $item['priority_group'],
                    $item['bsc_pillar'],
                    $item['description'],
                    $item['weight'],
                    $item['target_type'],
                    $item['target_value'],
                    $item['unit'],
                    $item['calc_direction'],
                    $item['data_source'],
                    $item['sort_order']
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Đã lưu bộ mẫu tiêu chí KPI [' . e($code) . '] theo vị trí thành công!');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi khi lưu bộ tiêu chí: ' . $e->getMessage());
        }
        redirect('modules/evaluations/kpi_templates.php');
    }

    // 2. NHÂN BẢN BỘ TIÊU CHÍ (CLONE)
    if ($action_type === 'clone_template') {
        require_permission('evaluations', 'create');
        $source_id = (int)($_POST['source_id'] ?? 0);
        
        $src_stmt = $pdo->prepare("SELECT * FROM kpi_templates WHERE id = ?");
        $src_stmt->execute([$source_id]);
        $src = $src_stmt->fetch();

        if ($src) {
            $new_code = $src['code'] . '-CP-' . strtoupper(substr(md5(uniqid()), 0, 4));
            $new_name = $src['name'] . ' (Bản Sao)';

            $pdo->beginTransaction();
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO kpi_templates (code, name, department_id, position_id, hierarchy_level, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $ins_stmt->execute([$new_code, $new_name, $src['department_id'], $src['position_id'], $src['hierarchy_level'], $src['description']]);
                $new_id = (int)$pdo->lastInsertId();

                // Sao chép toàn bộ tiêu chí
                $items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
                $items_stmt->execute([$source_id]);
                $src_items = $items_stmt->fetchAll();

                $copy_item = $pdo->prepare("
                    INSERT INTO kpi_template_items (
                        template_id, criteria_name, priority_group, bsc_pillar, 
                        description, weight, target_type, target_value, unit, 
                        calc_direction, data_source, sort_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($src_items as $si) {
                    $copy_item->execute([
                        $new_id,
                        $si['criteria_name'],
                        $si['priority_group'] ?? 'B',
                        $si['bsc_pillar'] ?? 'internal_process',
                        $si['description'],
                        $si['weight'],
                        $si['target_type'],
                        $si['target_value'],
                        $si['unit'] ?? '%',
                        $si['calc_direction'] ?? 'higher_better',
                        $si['data_source'] ?? '',
                        $si['sort_order']
                    ]);
                }

                $pdo->commit();
                set_flash('success', "Đã nhân bản thành công bộ tiêu chí mới: [{$new_code}].");
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Lỗi khi nhân bản bộ tiêu chí: ' . $e->getMessage());
            }
        }
        redirect('modules/evaluations/kpi_templates.php');
    }

    // 3. XÓA BỘ TIÊU CHÍ
    if ($action_type === 'delete_template') {
        require_permission('evaluations', 'delete');
        $del_id = (int)($_POST['template_id'] ?? 0);

        if ($del_id > 0) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE template_id = ?");
            $cnt->execute([$del_id]);
            $used_count = (int)$cnt->fetchColumn();

            if ($used_count > 0) {
                set_flash('danger', "Không thể xóa bộ tiêu chí này vì đang có {$used_count} phiếu đánh giá sử dụng.");
            } else {
                $del = $pdo->prepare("DELETE FROM kpi_templates WHERE id = ?");
                $del->execute([$del_id]);
                set_flash('success', 'Đã xóa bộ tiêu chí thành công.');
            }
        }
        redirect('modules/evaluations/kpi_templates.php');
    }
}

// Lọc & tìm kiếm
$filter_level = $_GET['hierarchy_level'] ?? '';
$filter_dept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_pos  = !empty($_GET['position_id']) ? (int)$_GET['position_id'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT t.*, d.name AS department_name, pos.name AS position_name,
           (SELECT COUNT(*) FROM kpi_template_items WHERE template_id = t.id) AS items_count,
           (SELECT COALESCE(SUM(weight), 0) FROM kpi_template_items WHERE template_id = t.id) AS total_weight,
           (SELECT COUNT(*) FROM evaluations WHERE template_id = t.id) AS evaluations_count
    FROM kpi_templates t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN positions pos ON t.position_id = pos.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_level) && isset($hierarchy_levels[$filter_level])) {
    $sql .= " AND t.hierarchy_level = ?";
    $params[] = $filter_level;
}

if ($filter_dept > 0) {
    $sql .= " AND t.department_id = ?";
    $params[] = $filter_dept;
}

if ($filter_pos > 0) {
    $sql .= " AND t.position_id = ?";
    $params[] = $filter_pos;
}

if (!empty($search)) {
    $sql .= " AND (t.name LIKE ? OR t.code LIKE ? OR pos.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY FIELD(t.hierarchy_level, 'executive', 'manager', 'specialist', 'operational'), t.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Lấy tất cả items của từng template
$template_items_map = [];
$all_items = $pdo->query("SELECT * FROM kpi_template_items ORDER BY template_id ASC, sort_order ASC")->fetchAll();
foreach ($all_items as $ai) {
    $template_items_map[$ai['template_id']][] = $ai;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header & Navigation Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" class="hover:underline">Đánh Giá & KPI</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400">Tiêu Chí Theo Vị Trí & Cấp Bậc</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <span>Bộ Tiêu Chí KPI Theo Vị Trí Chức Danh</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Tự động hóa tiêu chí và mục tiêu phù hợp với từng vị trí công việc &bull; Phân nhóm ưu tiên Link Power (A: 50%, B: 30%, C: 20%).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= base_url('modules/evaluations/periods.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span>Kỳ Đánh Giá</span>
            </a>
            <a href="<?= base_url('modules/evaluations/index.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-chart-pie text-indigo-500"></i>
                <span>Danh Sách Đánh Giá</span>
            </a>
            <?php if (has_permission('evaluations', 'create')): ?>
                <button type="button" onclick="openTemplateModal()" 
                        class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tạo Bộ Tiêu Chí Mới</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Level Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
        <?php foreach ($hierarchy_levels as $lvl_key => $lvl): ?>
            <?php 
                $count_lvl = 0;
                foreach ($templates as $tmpl) {
                    if ($tmpl['hierarchy_level'] === $lvl_key) $count_lvl++;
                }
                $is_active_filter = ($filter_level === $lvl_key);
            ?>
            <a href="<?= base_url('modules/evaluations/kpi_templates.php?hierarchy_level=' . ($is_active_filter ? '' : $lvl_key)) ?>"
               class="p-4 rounded-2xl border transition relative overflow-hidden flex flex-col justify-between <?= $is_active_filter ? 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-400 dark:border-indigo-600 ring-2 ring-indigo-500' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600' ?>">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm <?= $lvl['badge'] ?>">
                        <i class="fa-solid <?= $lvl['icon'] ?>"></i>
                    </div>
                    <span class="text-lg font-black font-mono text-slate-800 dark:text-white"><?= $count_lvl ?></span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= e($lvl['label']) ?></h4>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5"><?= e($lvl['target']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter & Search Toolbar (Lọc Cấp Bậc, Phòng Ban & Vị Trí) -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative flex-1 md:w-56">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Tên KPI, vị trí hoặc mã..." 
                       class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Lọc Vị Trí Công Việc -->
            <select name="position_id" onchange="this.form.submit()"
                    class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 max-w-[200px] truncate">
                <option value="">-- Tất cả vị trí chức danh --</option>
                <?php foreach ($positions as $pos): ?>
                    <option value="<?= $pos['id'] ?>" <?= $filter_pos == $pos['id'] ? 'selected' : '' ?>><?= e($pos['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Lọc Cấp Bậc -->
            <select name="hierarchy_level" onchange="this.form.submit()"
                    class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">-- Tất cả cấp bậc --</option>
                <?php foreach ($hierarchy_levels as $k => $hl): ?>
                    <option value="<?= $k ?>" <?= $filter_level === $k ? 'selected' : '' ?>><?= e($hl['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Lọc Phòng Ban -->
            <select name="department_id" onchange="this.form.submit()"
                    class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500">
                <option value="">-- Tất cả phòng ban --</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                Lọc
            </button>
            <?php if (!empty($search) || !empty($filter_level) || $filter_dept > 0 || $filter_pos > 0): ?>
                <a href="<?= base_url('modules/evaluations/kpi_templates.php') ?>" class="text-xs text-rose-500 hover:underline">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">
            Hiển thị: <strong class="text-indigo-600 dark:text-indigo-400"><?= count($templates) ?></strong> bộ tiêu chí
        </div>
    </div>

    <!-- Grid Danh Sách Bộ Mẫu Tiêu Chí Theo Vị Trí -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php if (empty($templates)): ?>
            <div class="col-span-full bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-slate-200 dark:border-slate-700">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-500 flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white">Không Tìm Thấy Bộ Tiêu Chí Phù Hợp</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    Thử chọn lại bộ lọc hoặc tạo mới bộ tiêu chí theo vị trí công việc cụ thể.
                </p>
                <?php if (has_permission('evaluations', 'create')): ?>
                    <button type="button" onclick="openTemplateModal()" class="mt-4 px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-xl text-xs hover:bg-indigo-700 transition">
                        <i class="fa-solid fa-plus mr-1.5"></i> Tạo Mới Ngay
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($templates as $t): ?>
            <?php 
                $items = $template_items_map[$t['id']] ?? [];
                $is_weight_valid = (abs((float)$t['total_weight'] - 100.0) < 0.1);
                $lvl_info = $hierarchy_levels[$t['hierarchy_level']] ?? $hierarchy_levels['specialist'];

                // Tính tổng trọng số theo nhóm ưu tiên Link Power
                $weight_A = 0; $weight_B = 0; $weight_C = 0;
                foreach ($items as $it) {
                    $p = $it['priority_group'] ?? 'B';
                    if ($p === 'A') $weight_A += (float)$it['weight'];
                    elseif ($p === 'B') $weight_B += (float)$it['weight'];
                    elseif ($p === 'C') $weight_C += (float)$it['weight'];
                }
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div class="p-5 sm:p-6 space-y-4">
                    <!-- Top row: Code + Vị trí + Level Badge + Status -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-bold text-xs border border-indigo-200 dark:border-indigo-800">
                                    <?= e($t['code']) ?>
                                </span>

                                <!-- Badge Vị Trí Chức Danh Áp Dụng -->
                                <?php if (!empty($t['position_name'])): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-300 border border-violet-200 dark:border-violet-800 flex items-center gap-1">
                                        <i class="fa-solid fa-id-card text-[10px]"></i>
                                        <span><?= e($t['position_name']) ?></span>
                                    </span>
                                <?php endif; ?>

                                <!-- Badge Cấp Bậc -->
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border flex items-center gap-1.5 <?= $lvl_info['badge'] ?>">
                                    <i class="fa-solid <?= $lvl_info['icon'] ?>"></i>
                                    <span><?= e($lvl_info['label']) ?></span>
                                </span>

                                <?php if ($t['status'] === 'active'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                        Tạm Dừng
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mt-2 leading-snug">
                                <?= e($t['name']) ?>
                            </h3>

                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                                <?= e($t['description'] ?: 'Áp dụng đo lường hiệu suất chuyên sâu cho vị trí ' . ($t['position_name'] ?: 'chức danh liên quan') . '.') ?>
                            </p>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                                <i class="fa-solid fa-building text-indigo-500"></i>
                                <?= e($t['department_name'] ?: 'Toàn Tập Đoàn') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Priority Allocation Bar (Ma trận phân bổ trọng số A/B/C theo Link Power) -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800/80 space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-bold">
                            <span class="text-slate-600 dark:text-slate-400">Phân Bổ Trọng Số Link Power:</span>
                            <div class="flex items-center gap-2">
                                <span class="text-rose-600 font-mono">A: <?= round($weight_A, 0) ?>%</span>
                                <span class="text-indigo-600 font-mono">B: <?= round($weight_B, 0) ?>%</span>
                                <span class="text-amber-600 font-mono">C: <?= round($weight_C, 0) ?>%</span>
                            </div>
                        </div>

                        <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden flex">
                            <div style="width: <?= $weight_A ?>%" class="bg-rose-500 h-full" title="Nhóm A (Cốt lõi): <?= $weight_A ?>%"></div>
                            <div style="width: <?= $weight_B ?>%" class="bg-indigo-500 h-full" title="Nhóm B (Vận hành): <?= $weight_B ?>%"></div>
                            <div style="width: <?= $weight_C ?>%" class="bg-amber-500 h-full" title="Nhóm C (Cải tiến/Đào tạo): <?= $weight_C ?>%"></div>
                        </div>
                    </div>

                    <!-- Danh sách tiêu chí con Preview -->
                    <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-3.5 space-y-2 border border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">
                            <span>Chi Tiết Tiêu Chí Theo Vị Trí (<?= count($items) ?> mục)</span>
                            <span class="text-[11px] text-slate-400 font-normal">Trọng số %</span>
                        </div>
                        <?php foreach ($items as $idx => $it): ?>
                            <?php 
                                $p_group = $it['priority_group'] ?? 'B';
                                $p_info = $priority_groups[$p_group] ?? $priority_groups['B'];
                                $pillar = $bsc_pillars[$it['bsc_pillar'] ?? 'internal_process'] ?? $bsc_pillars['internal_process'];
                            ?>
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-200/50 dark:border-slate-800/80 last:border-none">
                                <div class="flex items-center gap-2 truncate pr-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-black border <?= $p_info['badge'] ?>" title="<?= e($p_info['label']) ?>">
                                        <?= $p_group ?>
                                    </span>
                                    <span class="text-[11px] <?= $pillar['color'] ?>" title="<?= e($pillar['label']) ?>">
                                        <i class="fa-solid <?= $pillar['icon'] ?>"></i>
                                    </span>
                                    <span class="text-slate-800 dark:text-slate-200 font-medium truncate" title="<?= e($it['criteria_name']) ?>">
                                        <?= e($it['criteria_name']) ?>
                                    </span>
                                    <?php if (!empty($it['target_value'])): ?>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono hidden sm:inline">
                                            Mục tiêu: <?= e($it['target_value']) ?> <?= e($it['unit'] ?? '') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="font-bold font-mono text-indigo-600 dark:text-indigo-400 text-xs flex-shrink-0">
                                    <?= round($it['weight'], 1) ?>%
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Usage info -->
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-1">
                        <div>
                            Đang dùng: <strong class="text-slate-700 dark:text-slate-200"><?= $t['evaluations_count'] ?></strong> phiếu đánh giá
                        </div>
                        <div>
                            Cập nhật: <?= format_date($t['updated_at'] ?? $t['created_at']) ?>
                        </div>
                    </div>
                </div>

                <!-- Footer Card Actions -->
                <div class="px-5 py-3 bg-slate-50/80 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-end gap-2">
                    <?php if (has_permission('evaluations', 'create')): ?>
                        <!-- Nút Clone -->
                        <form method="POST"
                              data-confirm="Bạn có chắc muốn nhân bản bộ tiêu chí [<?= e($t['code']) ?>]?"
                              data-confirm-title="Xác Nhận Nhân Bản Bộ Tiêu Chí"
                              data-confirm-danger="false">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_type" value="clone_template">
                            <input type="hidden" name="source_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 hover:bg-white dark:hover:bg-slate-700 text-xs font-medium transition" title="Nhân bản bộ tiêu chí">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (has_permission('evaluations', 'edit')): ?>
                        <!-- Nút Edit -->
                        <button type="button" onclick='editTemplate(<?= json_encode($t) ?>, <?= json_encode($items) ?>)'
                                class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300 font-semibold rounded-xl text-xs transition flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Chỉnh Sửa</span>
                        </button>
                    <?php endif; ?>

                    <?php if (has_permission('evaluations', 'delete') && $t['evaluations_count'] == 0): ?>
                        <!-- Nút Delete -->
                        <form method="POST"
                              data-confirm="Bạn có chắc muốn xóa bộ tiêu chí [<?= e($t['code']) ?>]?"
                              data-confirm-title="Xác Nhận Xóa Bộ Tiêu Chí"
                              data-confirm-danger="true">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_type" value="delete_template">
                            <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="p-2 rounded-xl text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs transition" title="Xóa bộ tiêu chí">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: TẠO / CHỈNH SỬA BỘ TIÊU CHÍ (KPI TEMPLATE MODAL)   -->
<!-- ======================================================== -->
<div id="templateModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-5xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-slate-200 dark:border-slate-700">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Tạo Mới Bộ Tiêu Chí Theo Vị Trí</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Thiết lập tiêu chí đo lường chuyên biệt cho vị trí công việc & mục tiêu đề ra</p>
                </div>
            </div>
            <button type="button" onclick="closeTemplateModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" id="templateForm" class="p-6 space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_template">
            <input type="hidden" name="template_id" id="form_template_id" value="0">

            <!-- Thông tin chung -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mã Bộ KPI <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="code" id="form_code" required placeholder="VD: KPI-POS-DEV"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white uppercase focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Tên Bộ Tiêu Chí <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="form_name" required placeholder="VD: Bộ Tiêu Chí Kỹ Sư Phần Mềm & DevOps"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Cấp Bậc Nhân Sự <span class="text-rose-500">*</span>
                    </label>
                    <select name="hierarchy_level" id="form_hierarchy_level" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($hierarchy_levels as $hl_key => $hl): ?>
                            <option value="<?= $hl_key ?>"><?= e($hl['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chọn Vị Trí Chức Danh Áp Dụng -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Vị Trí Công Việc Áp Dụng
                    </label>
                    <select name="position_id" id="form_position_id"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Dùng Chung Cho Nhiều Vị Trí --</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= $pos['id'] ?>"><?= e($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Khối Phòng Ban
                    </label>
                    <select name="department_id" id="form_department_id"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Toàn Tập Đoàn (Chung) --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Trạng Thái
                    </label>
                    <select name="status" id="form_status"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="active">Hoạt động (Active)</option>
                        <option value="inactive">Tạm ngưng (Inactive)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mô Tả & Trọng Tâm Hiệu Suất
                    </label>
                    <input type="text" name="description" id="form_description" placeholder="Mục đích, vai trò và phạm vi áp dụng..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Hướng dẫn phân bổ trọng số Link Power -->
            <div class="p-3.5 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-2xl border border-indigo-100 dark:border-indigo-900/60 text-xs text-indigo-900 dark:text-indigo-200 flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-indigo-500 text-sm mt-0.5"></i>
                <div class="space-y-1">
                    <div class="font-bold">Quy tắc phân bổ trọng số theo vị trí (Phương pháp luận Link Power):</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px] text-indigo-800 dark:text-indigo-300">
                        <div>&bull; <strong class="text-rose-600 dark:text-rose-400">Nhóm A (Cốt lõi ~50%):</strong> Doanh số, P&L, Output công việc chính của vị trí.</div>
                        <div>&bull; <strong class="text-indigo-600 dark:text-indigo-400">Nhóm B (Vận hành ~30%):</strong> Độ chính xác, SLA chất lượng, Kiểm soát rủi ro.</div>
                        <div>&bull; <strong class="text-amber-600 dark:text-amber-400">Nhóm C (Cải tiến ~20%):</strong> Sáng kiến Kaizen, Đào tạo, Phối hợp đội ngũ.</div>
                    </div>
                </div>
            </div>

            <!-- Bảng danh sách tiêu chí con & Trọng số -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Danh Sách Tiêu Chí Đánh Giá Thành Phần (SMART)</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Thiết lập chỉ tiêu mục tiêu kỳ vọng và công thức đo lường</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div id="weightSummary" class="px-3 py-1.5 rounded-xl font-bold font-mono text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5">
                            <span>Tổng Trọng Số:</span>
                            <span id="currentWeightVal">100%</span>
                        </div>

                        <button type="button" onclick="addCriteriaRow()" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:hover:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                            <i class="fa-solid fa-plus"></i> Thêm Tiêu Chí
                        </button>
                    </div>
                </div>

                <div class="space-y-3" id="criteriaRowsContainer">
                    <!-- Dynamic Rows will be inserted here -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeTemplateModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Hủy Bỏ
                </button>
                <button type="submit" id="btnSubmitTemplate" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-200 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu Bộ Tiêu Chí Vị Trí</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let criteriaIndex = 0;

function openTemplateModal() {
    document.getElementById('form_template_id').value = '0';
    document.getElementById('modalTitle').textContent = 'Tạo Mới Bộ Tiêu Chí Theo Vị Trí';
    document.getElementById('form_code').value = '';
    document.getElementById('form_name').value = '';
    document.getElementById('form_hierarchy_level').value = 'specialist';
    document.getElementById('form_position_id').value = '';
    document.getElementById('form_department_id').value = '';
    document.getElementById('form_status').value = 'active';
    document.getElementById('form_description').value = '';
    
    const container = document.getElementById('criteriaRowsContainer');
    container.innerHTML = '';
    criteriaIndex = 0;
    
    addCriteriaRow('Khối lượng & Tiến độ công việc theo cam kết', 'A', 'internal_process', 'Hoàn thành các đầu việc đúng deadline cam kết', 50, 'percentage', '≥ 95%', '% Hoàn thành', 'higher_better', 'Jira / Báo cáo nội bộ');
    addCriteriaRow('Chất lượng chuyên môn & Độ chính xác đầu ra', 'B', 'internal_process', 'Đảm bảo tiêu chuẩn không lỗi nghiêm trọng', 30, 'percentage', '≥ 90%', '% Chuẩn xác', 'higher_better', 'QA Review');
    addCriteriaRow('Sáng kiến cải tiến & Kỷ luật phối hợp', 'C', 'learning_growth', 'Đóng góp giải pháp mới và tuân thủ kỷ luật', 20, 'numeric', '≥ 1 lần/quý', 'Sáng kiến', 'higher_better', 'Biên bản họp');
    
    calculateTotalWeight();
    document.getElementById('templateModal').classList.remove('hidden');
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.add('hidden');
}

function addCriteriaRow(name = '', priority = 'B', bsc = 'internal_process', desc = '', weight = 20, type = 'percentage', target = '', unit = '%', calc_dir = 'higher_better', data_src = '') {
    criteriaIndex++;
    const container = document.getElementById('criteriaRowsContainer');
    const rowId = `criteria_row_${criteriaIndex}`;

    const div = document.createElement('div');
    div.id = rowId;
    div.className = 'p-4 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-700/80 space-y-3';
    
    div.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Nhóm Ưu Tiên</label>
                <select name="criteria_priority_group[]" class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white">
                    <option value="A" ${priority === 'A' ? 'selected' : ''} class="text-rose-600 font-bold">Nhóm A (Cốt lõi ~50%)</option>
                    <option value="B" ${priority === 'B' ? 'selected' : ''} class="text-indigo-600 font-bold">Nhóm B (Vận hành ~30%)</option>
                    <option value="C" ${priority === 'C' ? 'selected' : ''} class="text-amber-600 font-bold">Nhóm C (Cải tiến ~20%)</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Trụ Cột BSC</label>
                <select name="criteria_bsc_pillar[]" class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                    <option value="financial" ${bsc === 'financial' ? 'selected' : ''}>Tài Chính</option>
                    <option value="customer" ${bsc === 'customer' ? 'selected' : ''}>Khách Hàng</option>
                    <option value="internal_process" ${bsc === 'internal_process' ? 'selected' : ''}>Quy Trình</option>
                    <option value="learning_growth" ${bsc === 'learning_growth' ? 'selected' : ''}>Đào Tạo</option>
                </select>
            </div>

            <div class="sm:col-span-6">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Tên Tiêu Chí Đánh Giá <span class="text-rose-500">*</span></label>
                <input type="text" name="criteria_name[]" value="${escapeHtml(name)}" required placeholder="VD: Doanh thu bán hàng chuỗi theo ca"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Trọng Số (%) <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <input type="number" step="1" min="1" max="100" name="criteria_weight[]" value="${weight}" required oninput="calculateTotalWeight()"
                           class="criteria-weight-input w-full px-3 py-2 pr-7 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-mono text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500">
                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">%</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Đơn Vị Tính</label>
                <input type="text" name="criteria_unit[]" value="${escapeHtml(unit)}" placeholder="VD: %, VNĐ, Bug"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Chiều Đo Lường</label>
                <select name="criteria_calc_direction[]" class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                    <option value="higher_better" ${calc_dir === 'higher_better' ? 'selected' : ''}>Thuận (Càng cao càng tốt)</option>
                    <option value="lower_better" ${calc_dir === 'lower_better' ? 'selected' : ''}>Nghịch (Càng thấp càng tốt)</option>
                    <option value="boolean" ${calc_dir === 'boolean' ? 'selected' : ''}>Nhị phân (Đạt / Không đạt)</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Chỉ Tiêu Kế Hoạch (Target)</label>
                <input type="text" name="criteria_target_value[]" value="${escapeHtml(target)}" placeholder="VD: ≥ 95%"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono text-slate-800 dark:text-white">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Nguồn Dữ Liệu Kiểm Chứng</label>
                <input type="text" name="criteria_data_source[]" value="${escapeHtml(data_src)}" placeholder="VD: ERP / POS / GitLab"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
            </div>

            <div class="sm:col-span-1 text-right pt-4 sm:pt-0">
                <button type="button" onclick="removeCriteriaRow('${rowId}')" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs transition" title="Xóa tiêu chí này">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>

        <div>
            <input type="text" name="criteria_desc[]" value="${escapeHtml(desc)}" placeholder="Mô tả công thức tính, phương pháp thu thập hoặc hướng dẫn chấm..."
                   class="w-full px-3 py-1.5 bg-white/70 dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700/80 rounded-xl text-[11px] text-slate-600 dark:text-slate-300">
        </div>
    `;

    container.appendChild(div);
    calculateTotalWeight();
}

function removeCriteriaRow(rowId) {
    const el = document.getElementById(rowId);
    if (el) el.remove();
    calculateTotalWeight();
}

function calculateTotalWeight() {
    const inputs = document.querySelectorAll('.criteria-weight-input');
    let total = 0;
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });

    const weightBadge = document.getElementById('weightSummary');
    const weightVal = document.getElementById('currentWeightVal');
    const submitBtn = document.getElementById('btnSubmitTemplate');

    weightVal.textContent = Math.round(total * 10) / 10 + '%';

    if (Math.abs(total - 100.0) < 0.1) {
        weightBadge.className = 'px-3 py-1.5 rounded-xl font-bold font-mono text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5';
        weightVal.innerHTML = '100% <i class="fa-solid fa-check ml-1"></i>';
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        weightBadge.className = 'px-3 py-1.5 rounded-xl font-bold font-mono text-xs bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center gap-1.5';
        weightVal.innerHTML = Math.round(total * 10) / 10 + '% <i class="fa-solid fa-triangle-exclamation ml-1"></i>';
    }
}

function editTemplate(template, items) {
    document.getElementById('form_template_id').value = template.id;
    document.getElementById('modalTitle').textContent = 'Chỉnh Sửa Bộ Tiêu Chí: ' + template.code;
    document.getElementById('form_code').value = template.code;
    document.getElementById('form_name').value = template.name;
    document.getElementById('form_hierarchy_level').value = template.hierarchy_level || 'specialist';
    document.getElementById('form_position_id').value = template.position_id || '';
    document.getElementById('form_department_id').value = template.department_id || '';
    document.getElementById('form_status').value = template.status;
    document.getElementById('form_description').value = template.description || '';

    const container = document.getElementById('criteriaRowsContainer');
    container.innerHTML = '';
    criteriaIndex = 0;

    if (items && items.length > 0) {
        items.forEach(it => {
            addCriteriaRow(
                it.criteria_name,
                it.priority_group || 'B',
                it.bsc_pillar || 'internal_process',
                it.description || '',
                parseFloat(it.weight),
                it.target_type || 'percentage',
                it.target_value || '',
                it.unit || '%',
                it.calc_direction || 'higher_better',
                it.data_source || ''
            );
        });
    } else {
        addCriteriaRow();
    }

    calculateTotalWeight();
    document.getElementById('templateModal').classList.remove('hidden');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
