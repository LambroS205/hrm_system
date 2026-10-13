<?php
// modules/planning/view.php - Chi Tiết Kế Hoạch & Quản Lý Danh Sách Đề Xuất Điều Động Hàng Loạt
require_once __DIR__ . '/../../core/auth.php';
require_permission('planning', 'view');

$user = current_user();
$id = (int)($_GET['id'] ?? 0);

// Lấy thông tin kế hoạch
$stmt = $pdo->prepare("
    SELECT p.*, tb.name AS target_branch_name, tb.code AS target_branch_code, u.fullname AS creator_name
    FROM transfer_plans p
    LEFT JOIN branches tb ON p.target_branch_id = tb.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$plan = $stmt->fetch();

if (!$plan) {
    set_flash('danger', 'Không tìm thấy kế hoạch này.');
    redirect('modules/planning/index.php?tab=plans');
}

$page_title = 'Kế Hoạch: ' . $plan['title'];

// Xử lý các thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. Thêm đề xuất cán bộ vào kế hoạch
    if ($action_type === 'add_plan_item') {
        require_permission('planning', 'create');
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $from_branch_id = (int)($_POST['from_branch_id'] ?? 0);
        $from_department_id = !empty($_POST['from_department_id']) ? (int)$_POST['from_department_id'] : null;
        $from_position_id = !empty($_POST['from_position_id']) ? (int)$_POST['from_position_id'] : null;

        $to_branch_id = (int)($_POST['to_branch_id'] ?? 0);
        $to_department_id = (int)($_POST['to_department_id'] ?? 0);
        $to_position_id = (int)($_POST['to_position_id'] ?? 0);
        $priority = $_POST['priority'] ?? 'medium';
        $rationale = trim($_POST['rationale'] ?? '');
        $estimated_allowance = (float)str_replace(['.', ','], '', $_POST['estimated_allowance'] ?? '0');

        if ($employee_id > 0 && $to_branch_id > 0 && $to_department_id > 0 && $to_position_id > 0) {
            $insItem = $pdo->prepare("
                INSERT INTO transfer_plan_items (plan_id, employee_id, from_branch_id, to_branch_id, from_department_id, to_department_id, from_position_id, to_position_id, priority, rationale, estimated_allowance)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insItem->execute([$id, $employee_id, $from_branch_id, $to_branch_id, $from_department_id, $to_department_id, $from_position_id, $to_position_id, $priority, $rationale, $estimated_allowance]);
            set_flash('success', 'Đã thêm cán bộ vào kế hoạch điều động thành công.');
            redirect('modules/planning/view.php?id=' . $id);
        } else {
            set_flash('warning', 'Vui lòng chọn đầy đủ cán bộ, chi nhánh đến, phòng ban và chức vụ mới.');
        }
    }

    // 2. Xóa đề xuất khỏi kế hoạch
    if ($action_type === 'delete_plan_item') {
        require_permission('planning', 'create');
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            $delItem = $pdo->prepare("DELETE FROM transfer_plan_items WHERE id = ? AND plan_id = ? AND transfer_id IS NULL");
            $delItem->execute([$item_id, $id]);
            set_flash('success', 'Đã xóa cán bộ khỏi kế hoạch.');
            redirect('modules/planning/view.php?id=' . $id);
        }
    }

    // 3. Thực thi kế hoạch hàng loạt (Batch Execution)
    if ($action_type === 'batch_execute_plan') {
        require_permission('transfers', 'approve');
        try {
            $pdo->beginTransaction();

            // Lấy tất cả items chưa được tạo transfer
            $items_stmt = $pdo->prepare("
                SELECT * FROM transfer_plan_items 
                WHERE plan_id = ? AND transfer_id IS NULL
            ");
            $items_stmt->execute([$id]);
            $pending_items = $items_stmt->fetchAll();

            $executed_count = 0;
            foreach ($pending_items as $item) {
                // Sinh mã phiếu thuyên chuyển
                $last_t_id = (int)($pdo->query("SELECT MAX(id) FROM transfers")->fetchColumn() ?: 0);
                $trans_code = 'TC-' . date('Y') . '-' . str_pad($last_t_id + 1, 3, '0', STR_PAD_LEFT);
                $dec_no = 'QĐ-KH' . $id . '-' . str_pad($item['id'], 2, '0', STR_PAD_LEFT) . '/QĐ-ĐĐ';

                // Tạo phiếu transfer executed
                $insTrans = $pdo->prepare("
                    INSERT INTO transfers (
                        transfer_code, employee_id, from_branch_id, to_branch_id,
                        from_department_id, to_department_id, from_position_id, to_position_id,
                        transfer_type, reason, effective_date, decision_number,
                        allowance_support, status, requested_by, approved_by,
                        approved_at, executed_at, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'branch_transfer', ?, CURDATE(), ?, ?, 'executed', ?, ?, NOW(), NOW(), ?)
                ");
                $reason_text = "Thực thi theo Kế hoạch quy hoạch: " . $plan['title'] . ". " . $item['rationale'];
                $insTrans->execute([
                    $trans_code, $item['employee_id'], $item['from_branch_id'], $item['to_branch_id'],
                    $item['from_department_id'], $item['to_department_id'], $item['from_position_id'], $item['to_position_id'],
                    $reason_text, $dec_no, $item['estimated_allowance'], $user['id'], $user['id'],
                    "Quy hoạch đợt: " . $plan['plan_code']
                ]);
                $transfer_id = $pdo->lastInsertId();

                // Cập nhật ngược lại vào transfer_plan_items
                $upItem = $pdo->prepare("UPDATE transfer_plan_items SET transfer_id = ? WHERE id = ?");
                $upItem->execute([$transfer_id, $item['id']]);

                // Cập nhật trực tiếp nhân viên sang chi nhánh và phòng ban mới
                $upEmp = $pdo->prepare("
                    UPDATE employees 
                    SET branch_id = ?, department_id = ?, position_id = ? 
                    WHERE id = ?
                ");
                $upEmp->execute([
                    $item['to_branch_id'],
                    $item['to_department_id'],
                    $item['to_position_id'],
                    $item['employee_id']
                ]);

                $executed_count++;
            }

            // Cập nhật trạng thái kế hoạch sang 'completed'
            $pdo->prepare("UPDATE transfer_plans SET status = 'completed' WHERE id = ?")->execute([$id]);

            $pdo->commit();
            set_flash('success', "Đã thực thi quy hoạch thành công! Đã điều chuyển {$executed_count} cán bộ sang các đơn vị mới.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi thực thi hàng loạt: ' . $e->getMessage());
        }
        redirect('modules/planning/view.php?id=' . $id);
    }
}

// Lấy danh sách các đề xuất trong kế hoạch kèm điểm KPI gần nhất
$items_stmt = $pdo->prepare("
    SELECT 
        tpi.*,
        e.fullname AS emp_fullname,
        e.employee_code AS emp_code,
        fb.name AS from_branch_name, fb.code AS from_branch_code,
        tb.name AS to_branch_name, tb.code AS to_branch_code,
        fd.name AS from_dept_name,
        td.name AS to_dept_name,
        fp.name AS from_pos_name,
        tp.name AS to_pos_name,
        tr.transfer_code,
        tr.status AS transfer_status,
        ev.final_score AS kpi_score,
        ev.rating_grade AS kpi_grade
    FROM transfer_plan_items tpi
    JOIN employees e ON tpi.employee_id = e.id
    JOIN branches fb ON tpi.from_branch_id = fb.id
    JOIN branches tb ON tpi.to_branch_id = tb.id
    LEFT JOIN departments fd ON tpi.from_department_id = fd.id
    JOIN departments td ON tpi.to_department_id = td.id
    LEFT JOIN positions fp ON tpi.from_position_id = fp.id
    JOIN positions tp ON tpi.to_position_id = tp.id
    LEFT JOIN transfers tr ON tpi.transfer_id = tr.id
    LEFT JOIN (
        SELECT employee_id, final_score, rating_grade
        FROM evaluations
        WHERE status = 'approved'
        ORDER BY created_at DESC
    ) ev ON e.id = ev.employee_id
    WHERE tpi.plan_id = ?
    ORDER BY FIELD(tpi.priority, 'high', 'medium', 'low'), tpi.id ASC
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

// Tổng hợp thống kê kế hoạch
$total_candidates = count($items);
$executed_candidates = count(array_filter($items, fn($i) => !empty($i['transfer_id'])));
$total_estimated_allowance = array_sum(array_column($items, 'estimated_allowance'));

// Phân tích Before vs After impact cho các chi nhánh liên quan
$branches_impact = [];
foreach ($items as $it) {
    // Chi nhánh nguồn
    $fb_id = $it['from_branch_id'];
    if (!isset($branches_impact[$fb_id])) {
        $branches_impact[$fb_id] = [
            'id' => $fb_id,
            'name' => $it['from_branch_name'],
            'code' => $it['from_branch_code'],
            'out_count' => 0,
            'in_count' => 0
        ];
    }
    $branches_impact[$fb_id]['out_count']++;

    // Chi nhánh đích
    $tb_id = $it['to_branch_id'];
    if (!isset($branches_impact[$tb_id])) {
        $branches_impact[$tb_id] = [
            'id' => $tb_id,
            'name' => $it['to_branch_name'],
            'code' => $it['to_branch_code'],
            'out_count' => 0,
            'in_count' => 0
        ];
    }
    $branches_impact[$tb_id]['in_count']++;
}

// Bổ sung số liệu thực tế & định biên vào bảng impact
foreach ($branches_impact as $bId => &$bImp) {
    $bInfo = $pdo->query("SELECT target_headcount, (SELECT COUNT(*) FROM employees WHERE branch_id = {$bId} AND employment_status != 'resigned') AS cur_hc FROM branches WHERE id = {$bId}")->fetch();
    $bImp['target_headcount'] = (int)($bInfo['target_headcount'] ?? 50);
    $bImp['current_headcount'] = (int)($bInfo['cur_hc'] ?? 0);
    $bImp['projected_headcount'] = $bImp['current_headcount'] - $bImp['out_count'] + $bImp['in_count'];
    $bImp['current_rate'] = round(($bImp['current_headcount'] / max(1, $bImp['target_headcount'])) * 100);
    $bImp['projected_rate'] = round(($bImp['projected_headcount'] / max(1, $bImp['target_headcount'])) * 100);
}
unset($bImp);

// Dữ liệu cho modal thêm đề xuất
$all_employees = $pdo->query("
    SELECT e.id, e.fullname, e.employee_code, e.branch_id, e.department_id, e.position_id,
           b.name AS branch_name, d.name AS dept_name, p.name AS pos_name
    FROM employees e
    JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

$all_branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches WHERE status = 'active' ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$all_departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();
$all_positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Thông Tin Kế Hoạch -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    <?= e($plan['plan_code']) ?>
                </span>
                <?php if ($plan['status'] === 'active'): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">Đang Triển Khai</span>
                <?php elseif ($plan['status'] === 'completed'): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-300">Đã Hoàn Thành Thực Thi</span>
                <?php else: ?>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">Bản Thảo</span>
                <?php endif; ?>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white"><?= e($plan['title']) ?></h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Thời gian: <strong class="text-slate-700 dark:text-slate-300"><?= format_date($plan['start_date']) ?> &rarr; <?= format_date($plan['end_date']) ?></strong>
                • Người lập: <strong class="text-slate-700 dark:text-slate-300"><?= e($plan['creator_name'] ?? 'Admin') ?></strong>
                <?php if (!empty($plan['target_branch_name'])): ?>
                    • Chi nhánh trọng điểm: <strong class="text-indigo-600 dark:text-indigo-400"><?= e($plan['target_branch_name']) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-shrink-0 flex-wrap">
            <a href="index.php?tab=plans" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                &larr; Danh Sách Kế Hoạch
            </a>
            <a href="form.php?id=<?= $plan['id'] ?>" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                <i class="fa-solid fa-pen-to-square mr-1"></i> Sửa
            </a>
            <?php if ($plan['status'] !== 'completed' && !empty($items) && has_permission('transfers', 'approve')): ?>
                <button onclick="confirmBatchExecute()" 
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Thực Thi Kế Hoạch Hàng Loạt</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Thẻ Thống Kê Nhanh Kế Hoạch -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-semibold">Cán Bộ Điều Động</span>
                <div class="text-xl font-bold text-slate-800 dark:text-white mt-0.5 font-mono">
                    <?= $executed_candidates ?> / <?= $total_candidates ?> Cán Bộ
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-semibold">Dự Toán Phụ Cấp Chuyển Vùng</span>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 font-mono">
                    <?= format_money($total_estimated_allowance) ?>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider block font-semibold">Tiến Độ Thực Thi</span>
                <div class="text-xl font-bold text-slate-800 dark:text-white mt-0.5 font-mono">
                    <?= $total_candidates > 0 ? round(($executed_candidates / $total_candidates) * 100) : 0 ?>%
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-list-check"></i>
            </div>
        </div>
    </div>

    <!-- Mô Tả & Thuyết Minh Kế Hoạch -->
    <?php if (!empty($plan['description'])): ?>
        <div class="bg-indigo-50/50 dark:bg-indigo-950/20 p-5 rounded-2xl border border-indigo-100 dark:border-indigo-900/40 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
            <strong class="text-indigo-900 dark:text-indigo-300 block font-bold mb-1">Mục Tiêu & Định Hướng Chiến Lược:</strong>
            <?= nl2br(e($plan['description'])) ?>
        </div>
    <?php endif; ?>

    <!-- Khối Dự Báo Tác Động Trước & Sau Khi Thực Thi (Before vs After Impact) -->
    <?php if (!empty($branches_impact)): ?>
        <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-emerald-600"></i>
                <span>Dự Báo Tác Động Lên Định Biên Các Chi Nhánh Liên Quan (Before vs After)</span>
            </h3>
            <p class="text-xs text-slate-400">Mô phỏng sự thay đổi cán cân nhân sự trước và sau khi kích hoạt kế hoạch này</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-2">
                <?php foreach ($branches_impact as $bImp): ?>
                    <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <strong class="text-slate-800 dark:text-white"><?= e($bImp['name']) ?></strong>
                            <span class="font-mono text-[10px] text-indigo-600 dark:text-indigo-400 font-bold"><?= e($bImp['code']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Biến động nhân sự:</span>
                            <span class="font-mono font-bold">
                                <?php if ($bImp['in_count'] > 0): ?>
                                    <span class="text-emerald-600">+<?= $bImp['in_count'] ?></span>
                                <?php endif; ?>
                                <?php if ($bImp['out_count'] > 0): ?>
                                    <span class="text-rose-600 ml-1">-<?= $bImp['out_count'] ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span>Lấp đầy định biên:</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300">
                                <?= $bImp['current_rate'] ?>% &rarr; <span class="text-emerald-500"><?= $bImp['projected_rate'] ?>%</span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Danh Sách Cán Bộ Đề Xuất Điều Động -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                    <i class="fa-solid fa-users-gear text-indigo-600"></i>
                    <span>Danh Sách Cán Bộ Điều Động Trong Kế Hoạch (<?= count($items) ?> Cán Bộ)</span>
                </h3>
                <p class="text-slate-400 text-xs mt-0.5">Bao gồm thông tin năng lực KPI, chức vụ xuất phát và chức vụ mới tiếp nhận</p>
            </div>

            <?php if ($plan['status'] !== 'completed' && has_permission('planning', 'create')): ?>
                <button onclick="openAddItemModal()" 
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Thêm Cán Bộ Vào Kế Hoạch</span>
                </button>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                        <th class="py-3 px-4">Cán Bộ & Năng Lực KPI</th>
                        <th class="py-3 px-4">Đơn Vị & Chức Vụ Hiện Tại</th>
                        <th class="py-3 px-4">Đơn Vị & Chức Vụ Mới</th>
                        <th class="py-3 px-4 text-center">Ưu Tiên</th>
                        <th class="py-3 px-4 text-right">Phụ Cấp</th>
                        <th class="py-3 px-4 text-center">Trạng Thái</th>
                        <th class="py-3 px-4 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-400">
                                Kế hoạch này chưa có cán bộ nào. Bấm "Thêm Cán Bộ Vào Kế Hoạch" để bổ sung nhân sự.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $it): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-white">
                                <div><?= e($it['emp_fullname']) ?></div>
                                <div class="flex items-center gap-2 mt-0.5 font-normal">
                                    <span class="font-mono text-[10px] text-slate-400"><?= e($it['emp_code']) ?></span>
                                    <?php if (!empty($it['kpi_score'])): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                            KPI: <?= $it['kpi_score'] ?> (<?= $it['kpi_grade'] ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                <div class="font-semibold text-slate-700 dark:text-slate-200"><?= e($it['from_branch_name']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= e($it['from_dept_name']) ?> • <?= e($it['from_pos_name']) ?></div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    <span><?= e($it['to_branch_name']) ?></span>
                                </div>
                                <div class="text-[10px] text-slate-600 dark:text-slate-300"><?= e($it['to_dept_name']) ?> • <strong class="text-slate-800 dark:text-white"><?= e($it['to_pos_name']) ?></strong></div>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($it['priority'] === 'high'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">Ưu tiên cao</span>
                                <?php elseif ($it['priority'] === 'medium'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">Trung bình</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">Bình thường</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                <?= format_money($it['estimated_allowance']) ?>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($it['transfer_id']): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300">
                                        <i class="fa-solid fa-check mr-0.5"></i> Đã Thực Thi
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        Dự Kiến
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 text-right space-x-1">
                                <?php if (!$it['transfer_id'] && has_permission('planning', 'create')): ?>
                                    <form method="POST" action="view.php?id=<?= $plan['id'] ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="delete_plan_item">
                                        <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                        <button type="submit" 
                                                data-confirm="Bạn có chắc chắn muốn xóa cán bộ '<?= e($it['fullname']) ?>' khỏi kế hoạch?"
                                                data-confirm-title="Xác Nhận Xóa Khỏi Kế Hoạch"
                                                data-confirm-danger="true"
                                                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-900/40 text-slate-400 inline-flex items-center justify-center transition">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Thêm Cán Bộ Vào Kế Hoạch -->
<div id="addItemModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-slate-800 dark:text-white text-base">Thêm Cán Bộ Vào Kế Hoạch Điều Động</h3>
            <button onclick="closeAddItemModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="view.php?id=<?= $plan['id'] ?>" method="POST" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="add_plan_item">
            <input type="hidden" name="from_branch_id" id="modalFromBranchId" value="">
            <input type="hidden" name="from_department_id" id="modalFromDeptId" value="">
            <input type="hidden" name="from_position_id" id="modalFromPosId" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Chọn Cán Bộ Nhân Viên *</label>
                <select name="employee_id" id="modalEmpSelect" required onchange="onModalSelectEmp(this.value)"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn cán bộ cần điều động --</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) — [<?= e($emp['branch_name']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="modalCurInfo" class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-xs space-y-1 hidden">
                <div class="text-slate-500">Đơn vị hiện tại: <strong id="modalCurBranch" class="text-slate-800 dark:text-white"></strong></div>
                <div class="text-slate-500">Phòng & chức vụ: <strong id="modalCurDeptPos" class="text-slate-800 dark:text-white"></strong></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Chi Nhánh Đến *</label>
                    <select name="to_branch_id" id="modalToBranch" required onchange="filterModalDepts(this.value)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chi Nhánh Tiếp Nhận --</option>
                        <?php foreach ($all_branches as $br): ?>
                            <option value="<?= $br['id'] ?>">
                                <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Phòng Ban Mới *</label>
                    <select name="to_department_id" id="modalToDept" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Phòng Ban --</option>
                        <?php foreach ($all_departments as $d): ?>
                            <option value="<?= $d['id'] ?>" data-branch-id="<?= $d['branch_id'] ?>">
                                <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Chức Vụ Mới *</label>
                    <select name="to_position_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chức Vụ --</option>
                        <?php foreach ($all_positions as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Mức Độ Ưu Tiên</label>
                    <select name="priority"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="high">🔥 Ưu tiên cao (Bổ sung gấp)</option>
                        <option value="medium" selected>⚡ Trung bình</option>
                        <option value="low">Định kỳ / Thấp</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Dự Toán Phụ Cấp Chuyển Vùng (VNĐ)</label>
                <input type="number" name="estimated_allowance" value="3500000" step="500000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Lý Do Đề Xuất & Thuyết Minh</label>
                <textarea name="rationale" rows="2" placeholder="Nêu lý do bố trí cán bộ này vào vị trí mới..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeAddItemModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-xs transition">
                    Thêm Vào Kế Hoạch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xác Nhận Thực Thi Hàng Loạt -->
<div id="batchModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-white text-base mb-1">Xác Nhận Kích Hoạt Kế Hoạch?</h3>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
            Hệ thống sẽ tự động tạo các quyết định thuyên chuyển chính thức và cập nhật toàn bộ hồ sơ cán bộ sang các đơn vị mới.
        </p>
        
        <form method="POST" action="view.php?id=<?= $plan['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="batch_execute_plan">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('batchModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md transition">
                    Xác Nhận Thực Thi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const allEmployeesData = <?= json_encode($all_employees) ?>;

function openAddItemModal() {
    document.getElementById('addItemModal').classList.remove('hidden');
}

function closeAddItemModal() {
    document.getElementById('addItemModal').classList.add('hidden');
}

function onModalSelectEmp(empId) {
    empId = parseInt(empId);
    const box = document.getElementById('modalCurInfo');
    if (!empId) {
        box.classList.add('hidden');
        return;
    }
    const emp = allEmployeesData.find(e => parseInt(e.id) === empId);
    if (emp) {
        document.getElementById('modalCurBranch').innerText = emp.branch_name;
        document.getElementById('modalCurDeptPos').innerText = (emp.dept_name || 'Chưa gán') + ' • ' + (emp.pos_name || 'Cán bộ');
        document.getElementById('modalFromBranchId').value = emp.branch_id;
        document.getElementById('modalFromDeptId').value = emp.department_id || '';
        document.getElementById('modalFromPosId').value = emp.position_id || '';
        box.classList.remove('hidden');
    }
}

function filterModalDepts(branchId) {
    branchId = parseInt(branchId);
    const select = document.getElementById('modalToDept');
    const options = select.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        const bId = parseInt(opt.getAttribute('data-branch-id'));
        if (!branchId || bId === branchId) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });
}

function confirmBatchExecute() {
    document.getElementById('batchModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
