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
    redirect('modules/planning/index.php');
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
            set_flash('success', 'Đã thêm đề xuất điều động nhân sự vào kế hoạch thành công.');
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
            $delItem = $pdo->prepare("DELETE FROM transfer_plan_items WHERE id = ? AND plan_id = ?");
            $delItem->execute([$item_id, $id]);
            set_flash('success', 'Đã xóa đề xuất khỏi kế hoạch.');
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
            set_flash('success', "Đã thực thi quy hoạch thành công! Đã điều chuyển {$executed_count} cán bộ sang các chi nhánh mới.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi thực thi hàng loạt: ' . $e->getMessage());
        }
        redirect('modules/planning/view.php?id=' . $id);
    }
}

// Lấy danh sách các đề xuất trong kế hoạch
$items_stmt = $pdo->prepare("
    SELECT 
        tpi.*,
        e.fullname AS emp_fullname,
        e.employee_code AS emp_code,
        fb.name AS from_branch_name,
        tb.name AS to_branch_name,
        fd.name AS from_dept_name,
        td.name AS to_dept_name,
        fp.name AS from_pos_name,
        tp.name AS to_pos_name,
        tr.transfer_code,
        tr.status AS transfer_status
    FROM transfer_plan_items tpi
    JOIN employees e ON tpi.employee_id = e.id
    JOIN branches fb ON tpi.from_branch_id = fb.id
    JOIN branches tb ON tpi.to_branch_id = tb.id
    LEFT JOIN departments fd ON tpi.from_department_id = fd.id
    JOIN departments td ON tpi.to_department_id = td.id
    LEFT JOIN positions fp ON tpi.from_position_id = fp.id
    JOIN positions tp ON tpi.to_position_id = tp.id
    LEFT JOIN transfers tr ON tpi.transfer_id = tr.id
    WHERE tpi.plan_id = ?
    ORDER BY tpi.priority DESC, tpi.id ASC
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

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

$all_branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$all_departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();
$all_positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Thông Tin Kế Hoạch -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">
                    <?= e($plan['plan_code']) ?>
                </span>
                <?php if ($plan['status'] === 'active'): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Đang Triển Khai</span>
                <?php elseif ($plan['status'] === 'completed'): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800">Đã Hoàn Thành Thực Thi</span>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Bản Thảo</span>
                <?php endif; ?>
            </div>
            <h2 class="text-xl font-bold text-slate-800"><?= e($plan['title']) ?></h2>
            <p class="text-xs text-slate-500 mt-1">
                Thời gian: <strong class="text-slate-700"><?= format_date($plan['start_date']) ?> &rarr; <?= format_date($plan['end_date']) ?></strong>
                • Người lập: <strong class="text-slate-700"><?= e($plan['creator_name'] ?? 'Admin') ?></strong>
                <?php if (!empty($plan['target_branch_name'])): ?>
                    • Chi nhánh trọng điểm: <strong class="text-indigo-600"><?= e($plan['target_branch_name']) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-shrink-0">
            <a href="index.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                &larr; Kế Hoạch
            </a>
            <a href="form.php?id=<?= $plan['id'] ?>" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                <i class="fa-solid fa-pen-to-square mr-1"></i> Sửa
            </a>
            <?php if ($plan['status'] !== 'completed' && !empty($items) && has_permission('transfers', 'approve')): ?>
                <button onclick="confirmBatchExecute()" 
                        class="px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Thực Thi Kế Hoạch Hàng Loạt</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mô Tả & Thuyết Minh Kế Hoạch -->
    <?php if (!empty($plan['description'])): ?>
        <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-100 text-xs text-slate-700 leading-relaxed">
            <strong class="text-indigo-900 block font-bold mb-1">Mục Tiêu & Định Hướng Chiến Lược:</strong>
            <?= nl2br(e($plan['description'])) ?>
        </div>
    <?php endif; ?>

    <!-- Danh Sách Cán Bộ Đề Xuất Điều Động -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-users-gear text-indigo-600"></i>
                    <span>Danh Sách Cán Bộ Dự Kiến Điều Động (<?= count($items) ?> Cán Bộ)</span>
                </h3>
                <p class="text-slate-400 text-xs mt-0.5">Các nhân sự được chọn để điều chuyển theo phương án tối ưu trong chiến dịch này</p>
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
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 uppercase font-semibold">
                        <th class="py-3 px-4">Cán Bộ / Nhân Viên</th>
                        <th class="py-3 px-4">Chi Nhánh & Vị Trí Hiện Tại</th>
                        <th class="py-3 px-4">Đơn Vị & Vị Trí Mới Dự Kiến</th>
                        <th class="py-3 px-4 text-center">Mức Ưu Tiên</th>
                        <th class="py-3 px-4 text-right">Dự Toán Phụ Cấp</th>
                        <th class="py-3 px-4 text-center">Trạng Thái</th>
                        <th class="py-3 px-4 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-400">
                                Kế hoạch này chưa có cán bộ nào được thêm. Bấm "Thêm Cán Bộ Vào Kế Hoạch" để chọn nhân sự.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $it): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-800">
                                <div><?= e($it['emp_fullname']) ?></div>
                                <span class="font-mono text-[10px] text-slate-400"><?= e($it['emp_code']) ?></span>
                            </td>

                            <td class="py-3.5 px-4 text-slate-600">
                                <div class="font-semibold text-slate-700"><?= e($it['from_branch_name']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= e($it['from_dept_name']) ?> • <?= e($it['from_pos_name']) ?></div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-bold text-indigo-700 flex items-center gap-1">
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    <span><?= e($it['to_branch_name']) ?></span>
                                </div>
                                <div class="text-[10px] text-slate-600"><?= e($it['to_dept_name']) ?> • <strong class="text-slate-800"><?= e($it['to_pos_name']) ?></strong></div>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($it['priority'] === 'high'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Ưu tiên cao</span>
                                <?php elseif ($it['priority'] === 'medium'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Trung bình</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">Bình thường</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600">
                                <?= format_money($it['estimated_allowance']) ?>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($it['transfer_id']): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check mr-0.5"></i> Đã Thực Thi
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
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
                                                data-confirm="Bạn có chắc chắn muốn xóa cán bộ này khỏi kế hoạch điều động?" 
                                                data-confirm-title="Xóa Đề Xuất Khỏi Kế Hoạch"
                                                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400 inline-flex items-center justify-center transition">
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
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base">Thêm Cán Bộ Vào Kế Hoạch Điều Động</h3>
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chọn Cán Bộ Nhân Viên *</label>
                <select name="employee_id" id="modalEmpSelect" required onchange="onModalSelectEmp(this.value)"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn cán bộ cần điều động --</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) — [<?= e($emp['branch_name']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="modalCurInfo" class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1 hidden">
                <div class="text-slate-500">Đơn vị hiện tại: <strong id="modalCurBranch" class="text-slate-800"></strong></div>
                <div class="text-slate-500">Phòng & chức vụ: <strong id="modalCurDeptPos" class="text-slate-800"></strong></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chi Nhánh Đến *</label>
                    <select name="to_branch_id" id="modalToBranch" required onchange="filterModalDepts(this.value)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chi Nhánh Tiếp Nhận --</option>
                        <?php foreach ($all_branches as $br): ?>
                            <option value="<?= $br['id'] ?>">
                                <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Phòng Ban Mới *</label>
                    <select name="to_department_id" id="modalToDept" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chức Vụ Mới *</label>
                    <select name="to_position_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chức Vụ --</option>
                        <?php foreach ($all_positions as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mức Độ Ưu Tiên</label>
                    <select name="priority"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="high">🔥 Ưu tiên cao (Bổ sung gấp)</option>
                        <option value="medium" selected>⚡ Trung bình</option>
                        <option value="low">Định kỳ / Thấp</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Dự Toán Phụ Cấp Chuyển Vùng (VNĐ)</label>
                <input type="number" name="estimated_allowance" value="3000000" step="500000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Lý Do Đề Xuất & Thuyết Minh</label>
                <textarea name="rationale" rows="2" placeholder="Nêu lý do bố trí cán bộ này vào vị trí mới..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeAddItemModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
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
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xác Nhận Kích Hoạt Kế Hoạch?</h3>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
            Hệ thống sẽ tự động tạo các quyết định thuyên chuyển chính thức và cập nhật toàn bộ hồ sơ cán bộ sang các chi nhánh mới.
        </p>
        
        <form method="POST" action="view.php?id=<?= $plan['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="batch_execute_plan">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('batchModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
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
