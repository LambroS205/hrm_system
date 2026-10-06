<?php
// modules/evaluations/kpi_templates.php - Quản lý Bộ Tiêu Chí KPI & Trọng Số
$page_title = 'Quản Lý Bộ Mẫu Tiêu Chí KPI';

require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

// Lấy danh sách phòng ban cho dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

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
        $description = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        
        $item_names = $_POST['criteria_name'] ?? [];
        $item_descs = $_POST['criteria_desc'] ?? [];
        $item_weights = $_POST['criteria_weight'] ?? [];
        $item_types = $_POST['criteria_target_type'] ?? [];
        $item_targets = $_POST['criteria_target_value'] ?? [];

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
                'criteria_name' => $c_name,
                'description' => trim($item_descs[$i] ?? ''),
                'weight' => $c_weight,
                'target_type' => in_array($item_types[$i] ?? '', ['percentage', 'numeric', 'rating', 'boolean']) ? $item_types[$i] : 'percentage',
                'target_value' => trim($item_targets[$i] ?? ''),
                'sort_order' => $i + 1
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
                    SET code = ?, name = ?, department_id = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $department_id, $description, $status, $template_id]);

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
                    INSERT INTO kpi_templates (code, name, department_id, description, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $department_id, $description, $status]);
                $template_id = (int)$pdo->lastInsertId();
            }

            // Lưu các tiêu chí con
            $item_stmt = $pdo->prepare("
                INSERT INTO kpi_template_items (template_id, criteria_name, description, weight, target_type, target_value, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items_to_save as $item) {
                $item_stmt->execute([
                    $template_id,
                    $item['criteria_name'],
                    $item['description'],
                    $item['weight'],
                    $item['target_type'],
                    $item['target_value'],
                    $item['sort_order']
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Đã lưu bộ mẫu tiêu chí KPI [' . e($code) . '] thành công!');
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
            $new_code = $src['code'] . '-COPY-' . strtoupper(substr(md5(uniqid()), 0, 4));
            $new_name = $src['name'] . ' (Bản Sao)';

            $pdo->beginTransaction();
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO kpi_templates (code, name, department_id, description, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $ins_stmt->execute([$new_code, $new_name, $src['department_id'], $src['description']]);
                $new_id = (int)$pdo->lastInsertId();

                // Sao chép toàn bộ tiêu chí
                $items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
                $items_stmt->execute([$source_id]);
                $src_items = $items_stmt->fetchAll();

                $copy_item = $pdo->prepare("
                    INSERT INTO kpi_template_items (template_id, criteria_name, description, weight, target_type, target_value, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($src_items as $si) {
                    $copy_item->execute([
                        $new_id,
                        $si['criteria_name'],
                        $si['description'],
                        $si['weight'],
                        $si['target_type'],
                        $si['target_value'],
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
            // Kiểm tra xem có phiếu evaluation nào đang dùng template này không
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
$filter_dept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT t.*, d.name AS department_name,
           (SELECT COUNT(*) FROM kpi_template_items WHERE template_id = t.id) AS items_count,
           (SELECT COALESCE(SUM(weight), 0) FROM kpi_template_items WHERE template_id = t.id) AS total_weight,
           (SELECT COUNT(*) FROM evaluations WHERE template_id = t.id) AS evaluations_count
    FROM kpi_templates t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE 1=1
";
$params = [];

if ($filter_dept > 0) {
    $sql .= " AND t.department_id = ?";
    $params[] = $filter_dept;
}

if (!empty($search)) {
    $sql .= " AND (t.name LIKE ? OR t.code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY t.status ASC, t.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Lấy tất cả items của từng template để hiển thị chi tiết
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
                <span class="text-slate-500 dark:text-slate-400">Bộ Mẫu Tiêu Chí</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <span>Quản Lý Bộ Mẫu Tiêu Chí KPI</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Thiết lập hệ thống tiêu chí, trọng số %, chỉ tiêu đo lường chuẩn hóa theo từng phòng ban và khối chức năng.
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
                <i class="fa-solid fa-list-check text-indigo-500"></i>
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

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="relative flex-1 md:w-72">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Tìm tên hoặc mã bộ KPI..." 
                       class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <select name="department_id" onchange="this.form.submit()"
                    class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">-- Tất cả khối phòng ban --</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                Lọc
            </button>
            <?php if (!empty($search) || $filter_dept > 0): ?>
                <a href="<?= base_url('modules/evaluations/kpi_templates.php') ?>" class="text-xs text-rose-500 hover:underline">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">
            Tổng cộng: <strong class="text-indigo-600 dark:text-indigo-400"><?= count($templates) ?></strong> bộ tiêu chí
        </div>
    </div>

    <!-- Grid Danh Sách Bộ Mẫu Tiêu Chí -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php if (empty($templates)): ?>
            <div class="col-span-full bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-slate-200 dark:border-slate-700">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-500 flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white">Chưa Có Bộ Tiêu Chí Nào</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    Bắt đầu bằng cách tạo bộ tiêu chí mẫu KPI đầu tiên cho khối phòng ban hoặc toàn tập đoàn.
                </p>
                <?php if (has_permission('evaluations', 'create')): ?>
                    <button type="button" onclick="openTemplateModal()" class="mt-4 px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-xl text-xs hover:bg-indigo-700 transition">
                        <i class="fa-solid fa-plus mr-1.5"></i> Tạo Ngay
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($templates as $t): ?>
            <?php 
                $items = $template_items_map[$t['id']] ?? [];
                $is_weight_valid = (abs((float)$t['total_weight'] - 100.0) < 0.1);
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div class="p-5 sm:p-6 space-y-4">
                    <!-- Top row: Code + Badges -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-bold text-xs border border-indigo-200 dark:border-indigo-800">
                                    <?= e($t['code']) ?>
                                </span>
                                <?php if ($t['status'] === 'active'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                        Đang Áp Dụng
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                        Tạm Dừng
                                    </span>
                                <?php endif; ?>

                                <?php if ($is_weight_valid): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300" title="Tổng trọng số = 100%">
                                        <i class="fa-solid fa-circle-check text-sky-500 mr-1"></i>100% Trọng Số
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300" title="Cần điều chỉnh về 100%">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= round($t['total_weight'], 1) ?>% Trọng Số
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mt-2 leading-snug">
                                <?= e($t['name']) ?>
                            </h3>

                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                                <?= e($t['description'] ?: 'Không có mô tả chi tiết.') ?>
                            </p>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                                <i class="fa-solid fa-building text-indigo-500"></i>
                                <?= e($t['department_name'] ?: 'Toàn Tập Đoàn') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Danh sách tiêu chí con Preview -->
                    <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-3.5 space-y-2 border border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">
                            <span>Chi Tiết Tiêu Chí (<?= count($items) ?> mục)</span>
                            <span class="text-[11px] text-slate-400 font-normal">Trọng số %</span>
                        </div>
                        <?php foreach ($items as $idx => $it): ?>
                            <div class="flex items-center justify-between text-xs py-1 border-b border-slate-200/50 dark:border-slate-800/80 last:border-none">
                                <div class="flex items-center gap-2 truncate pr-2">
                                    <span class="w-5 h-5 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-500 flex-shrink-0">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <span class="text-slate-800 dark:text-slate-200 font-medium truncate" title="<?= e($it['criteria_name']) ?>">
                                        <?= e($it['criteria_name']) ?>
                                    </span>
                                    <?php if (!empty($it['target_value'])): ?>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-mono hidden sm:inline">
                                            <?= e($it['target_value']) ?>
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
                            Tạo ngày: <?= format_date($t['created_at']) ?>
                        </div>
                    </div>
                </div>

                <!-- Footer Card Actions -->
                <div class="px-5 py-3 bg-slate-50/80 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-end gap-2">
                    <?php if (has_permission('evaluations', 'create')): ?>
                        <!-- Nút Clone -->
                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn nhân bản bộ tiêu chí [<?= e($t['code']) ?>]?')">
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
                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa bộ tiêu chí [<?= e($t['code']) ?>]?')">
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
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-4xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-slate-200 dark:border-slate-700">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Tạo Mới Bộ Tiêu Chí KPI</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Thiết lập bộ mẫu tiêu chí & cấu trúc trọng số phần trăm</p>
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mã Bộ KPI <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="code" id="form_code" required placeholder="VD: KPI-TECH-2026"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white uppercase focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Tên Bộ Tiêu Chí <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="form_name" required placeholder="VD: Bộ Tiêu Chí Hiệu Suất Kỹ Sư Phần Mềm"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Khối Phòng Ban Áp Dụng
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
                        Mô Tả Mục Tiêu
                    </label>
                    <input type="text" name="description" id="form_description" placeholder="Mục đích và đối tượng..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Bảng danh sách tiêu chí con & Trọng số -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Danh Sách Tiêu Chí Đánh Giá Thành Phần</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Tổng trọng số các mục phải đạt chính xác 100%</p>
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
                    <span>Lưu Bộ Tiêu Chí</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let criteriaIndex = 0;

function openTemplateModal() {
    document.getElementById('form_template_id').value = '0';
    document.getElementById('modalTitle').textContent = 'Tạo Mới Bộ Tiêu Chí KPI';
    document.getElementById('form_code').value = '';
    document.getElementById('form_name').value = '';
    document.getElementById('form_department_id').value = '';
    document.getElementById('form_status').value = 'active';
    document.getElementById('form_description').value = '';
    
    // Clear and add 3 default rows
    const container = document.getElementById('criteriaRowsContainer');
    container.innerHTML = '';
    criteriaIndex = 0;
    
    addCriteriaRow('Tiến độ & Khối lượng công việc theo cam kết', 'Hoàn thành các đầu việc đúng deadline', 40, 'percentage', '≥ 95%');
    addCriteriaRow('Chất lượng chuyên môn & Tính chính xác', 'Sản phẩm hoàn thành đạt tiêu chuẩn chất lượng', 35, 'percentage', '≥ 90%');
    addCriteriaRow('Kỷ luật, Tác phong & Phối hợp đội ngũ', 'Tuân thủ nội quy và chủ động hỗ trợ đồng nghiệp', 25, 'rating', 'Đạt chuẩn');
    
    calculateTotalWeight();
    document.getElementById('templateModal').classList.remove('hidden');
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.add('hidden');
}

function addCriteriaRow(name = '', desc = '', weight = 20, type = 'percentage', target = '') {
    criteriaIndex++;
    const container = document.getElementById('criteriaRowsContainer');
    const rowId = `criteria_row_${criteriaIndex}`;

    const div = document.createElement('div');
    div.id = rowId;
    div.className = 'p-3.5 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-700/80 space-y-3';
    
    div.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-5">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Tên Tiêu Chí <span class="text-rose-500">*</span></label>
                <input type="text" name="criteria_name[]" value="${escapeHtml(name)}" required placeholder="VD: Tỷ lệ hoàn thành công việc"
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

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Đơn Vị Đo</label>
                <select name="criteria_target_type[]" class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-300">
                    <option value="percentage" ${type === 'percentage' ? 'selected' : ''}>Tỷ lệ %</option>
                    <option value="numeric" ${type === 'numeric' ? 'selected' : ''}>Số lượng</option>
                    <option value="rating" ${type === 'rating' ? 'selected' : ''}>Xếp loại</option>
                    <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Đạt / Không</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Mục Tiêu Kỳ Vọng</label>
                <input type="text" name="criteria_target_value[]" value="${escapeHtml(target)}" placeholder="VD: ≥ 95%"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
            </div>

            <div class="sm:col-span-1 text-right pt-4 sm:pt-0">
                <button type="button" onclick="removeCriteriaRow('${rowId}')" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs transition" title="Xóa dòng này">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>

        <div>
            <input type="text" name="criteria_desc[]" value="${escapeHtml(desc)}" placeholder="Chi tiết cách thức đo lường hoặc công thức tính điểm..."
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
    document.getElementById('form_department_id').value = template.department_id || '';
    document.getElementById('form_status').value = template.status;
    document.getElementById('form_description').value = template.description || '';

    const container = document.getElementById('criteriaRowsContainer');
    container.innerHTML = '';
    criteriaIndex = 0;

    if (items && items.length > 0) {
        items.forEach(it => {
            addCriteriaRow(it.criteria_name, it.description, parseFloat(it.weight), it.target_type, it.target_value);
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
