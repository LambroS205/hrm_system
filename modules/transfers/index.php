<?php
// modules/transfers/index.php - Quản lý Thuyên Chuyển & Điều Động Công Tác Nhân Sự
$page_title = 'Thuyên Chuyển Công Tác';

require_once __DIR__ . '/../../core/auth.php';
require_permission('transfers', 'view');

$user = current_user();

// Xử lý các thao tác Phê Duyệt / Thực Thi / Từ Chối / Xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];
    $transfer_id = (int)($_POST['transfer_id'] ?? 0);

    if ($transfer_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch();

        if ($transfer) {
            // 1. Phê Duyệt & Thực Thi Điều Động Ngay Lập Tức
            if ($action_type === 'execute_transfer') {
                require_permission('transfers', 'approve');
                try {
                    $pdo->beginTransaction();

                    // Cập nhật thông tin nhân viên sang chi nhánh, phòng ban, chức vụ mới
                    $upEmp = $pdo->prepare("
                        UPDATE employees 
                        SET branch_id = ?, department_id = ?, position_id = ? 
                        WHERE id = ?
                    ");
                    $upEmp->execute([
                        $transfer['to_branch_id'],
                        $transfer['to_department_id'],
                        $transfer['to_position_id'],
                        $transfer['employee_id']
                    ]);

                    // Cập nhật trạng thái phiếu thuyên chuyển sang 'executed'
                    $upTrans = $pdo->prepare("
                        UPDATE transfers 
                        SET status = 'executed', 
                            approved_by = ?, 
                            approved_at = NOW(), 
                            executed_at = NOW() 
                        WHERE id = ?
                    ");
                    $upTrans->execute([$user['id'], $transfer_id]);

                    $pdo->commit();
                    set_flash('success', "Đã phê duyệt và thực thi chuyển công tác thành công cho nhân sự sang chi nhánh mới!");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Lỗi khi thực thi điều chuyển: ' . $e->getMessage());
                }
                redirect('modules/transfers/index.php');
            }

            // 2. Chỉ Phê Duyệt (Chờ ngày hiệu lực)
            if ($action_type === 'approve_transfer') {
                require_permission('transfers', 'approve');
                $upTrans = $pdo->prepare("
                    UPDATE transfers 
                    SET status = 'approved', 
                        approved_by = ?, 
                        approved_at = NOW() 
                    WHERE id = ?
                ");
                $upTrans->execute([$user['id'], $transfer_id]);
                set_flash('success', "Đã phê duyệt lệnh thuyên chuyển (trạng thái: Chờ ngày hiệu lực).");
                redirect('modules/transfers/index.php');
            }

            // 3. Từ Chối Thuyên Chuyển
            if ($action_type === 'reject_transfer') {
                require_permission('transfers', 'approve');
                $reject_reason = trim($_POST['reject_reason'] ?? 'Không được phê duyệt');
                $upTrans = $pdo->prepare("
                    UPDATE transfers 
                    SET status = 'rejected', 
                        approved_by = ?, 
                        approved_at = NOW(), 
                        notes = CONCAT(COALESCE(notes, ''), ' [Lý do từ chối: ', ?, ']')
                    WHERE id = ?
                ");
                $upTrans->execute([$user['id'], $reject_reason, $transfer_id]);
                set_flash('warning', "Đã từ chối yêu cầu thuyên chuyển công tác.");
                redirect('modules/transfers/index.php');
            }

            // 4. Xóa Phiếu Thuyên Chuyển
            if ($action_type === 'delete_transfer') {
                require_permission('transfers', 'delete');
                if (in_array($transfer['status'], ['pending', 'rejected', 'cancelled'])) {
                    $delStmt = $pdo->prepare("DELETE FROM transfers WHERE id = ?");
                    $delStmt->execute([$transfer_id]);
                    set_flash('success', 'Đã xóa bản ghi đề xuất thuyên chuyển.');
                } else {
                    set_flash('danger', 'Không thể xóa quyết định đã được duyệt hoặc đã thực thi.');
                }
                redirect('modules/transfers/index.php');
            }
        }
    }
}

// Nhận tham số lọc & tìm kiếm
$status_filter = trim($_GET['status'] ?? '');
$from_branch_filter = (int)($_GET['from_branch_id'] ?? 0);
$to_branch_filter = (int)($_GET['to_branch_id'] ?? 0);
$keyword = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if (!empty($status_filter)) {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($from_branch_filter > 0) {
    $where[] = "t.from_branch_id = ?";
    $params[] = $from_branch_filter;
}

if ($to_branch_filter > 0) {
    $where[] = "t.to_branch_id = ?";
    $params[] = $to_branch_filter;
}

if (!empty($keyword)) {
    $where[] = "(e.fullname LIKE ? OR e.employee_code LIKE ? OR t.transfer_code LIKE ? OR t.decision_number LIKE ?)";
    $kw = "%{$keyword}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$where_sql = implode(' AND ', $where);

// Truy vấn danh sách thuyên chuyển kèm chi nhánh, phòng ban, chức vụ đi và đến
$transfers_stmt = $pdo->prepare("
    SELECT 
        t.*,
        e.fullname AS emp_fullname,
        e.employee_code AS emp_code,
        e.avatar AS emp_avatar,
        fb.name AS from_branch_name,
        fb.code AS from_branch_code,
        tb.name AS to_branch_name,
        tb.code AS to_branch_code,
        fd.name AS from_dept_name,
        td.name AS to_dept_name,
        fp.name AS from_pos_name,
        tp.name AS to_pos_name,
        u_req.fullname AS req_user_name,
        u_app.fullname AS app_user_name
    FROM transfers t
    JOIN employees e ON t.employee_id = e.id
    JOIN branches fb ON t.from_branch_id = fb.id
    JOIN branches tb ON t.to_branch_id = tb.id
    LEFT JOIN departments fd ON t.from_department_id = fd.id
    JOIN departments td ON t.to_department_id = td.id
    LEFT JOIN positions fp ON t.from_position_id = fp.id
    JOIN positions tp ON t.to_position_id = tp.id
    LEFT JOIN users u_req ON t.requested_by = u_req.id
    LEFT JOIN users u_app ON t.approved_by = u_app.id
    WHERE {$where_sql}
    ORDER BY t.id DESC
");
$transfers_stmt->execute($params);
$transfers = $transfers_stmt->fetchAll();

// Thống kê nhanh theo trạng thái
$count_all = $pdo->query("SELECT COUNT(*) FROM transfers")->fetchColumn() ?: 0;
$count_pending = $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'pending'")->fetchColumn() ?: 0;
$count_approved = $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'approved'")->fetchColumn() ?: 0;
$count_executed = $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'executed'")->fetchColumn() ?: 0;

// Danh sách chi nhánh cho bộ lọc
$branches_list = $pdo->query("SELECT id, name, code FROM branches ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Nút Thao Tác -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 mb-2">
                <i class="fa-solid fa-people-arrows"></i> Điều Động & Luân Chuyển Nhân Sự
            </div>
            <h2 class="text-xl font-bold text-slate-800">Quản Lý Thuyên Chuyển Công Tác</h2>
            <p class="text-sm text-slate-500 mt-0.5">Xử lý quy trình điều chuyển cán bộ giữa các chi nhánh, phê duyệt quyết định và cập nhật hồ sơ tự động.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="<?= base_url('modules/planning/index.php') ?>" 
               class="px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-xl transition flex items-center gap-2 border border-amber-200">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i>
                <span>Xem Phương Án Tối Ưu</span>
            </a>
            <?php if (has_permission('transfers', 'create')): ?>
                <a href="<?= base_url('modules/transfers/create.php') ?>" 
                   class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tạo Đề Xuất Thuyên Chuyển Mới</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số KPI Thuyên Chuyển -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="?status=" class="bg-white p-5 rounded-2xl border <?= empty($status_filter) ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-slate-200' ?> shadow-sm flex items-center justify-between hover:border-indigo-200 transition">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tổng Số Lệnh Điều Động</span>
                <div class="text-2xl font-bold text-slate-800 mt-1"><?= $count_all ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Toàn bộ lịch sử thuyên chuyển</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-file-contract"></i>
            </div>
        </a>

        <a href="?status=pending" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'pending' ? 'border-amber-400 ring-2 ring-amber-50' : 'border-slate-200' ?> shadow-sm flex items-center justify-between hover:border-amber-200 transition">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Chờ Lãnh Đạo Phê Duyệt</span>
                <div class="text-2xl font-bold text-amber-600 mt-1"><?= $count_pending ?></div>
                <div class="text-[11px] text-amber-700 font-medium mt-1">Cần xem xét và duyệt sớm</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </a>

        <a href="?status=approved" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'approved' ? 'border-sky-400 ring-2 ring-sky-50' : 'border-slate-200' ?> shadow-sm flex items-center justify-between hover:border-sky-200 transition">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Đã Duyệt (Chờ Bàn Giao)</span>
                <div class="text-2xl font-bold text-sky-600 mt-1"><?= $count_approved ?></div>
                <div class="text-[11px] text-sky-700 font-medium mt-1">Đã có quyết định chính thức</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-stamp"></i>
            </div>
        </a>

        <a href="?status=executed" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'executed' ? 'border-emerald-400 ring-2 ring-emerald-50' : 'border-slate-200' ?> shadow-sm flex items-center justify-between hover:border-emerald-200 transition">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Đã Thực Thi Hoàn Tất</span>
                <div class="text-2xl font-bold text-emerald-600 mt-1"><?= $count_executed ?></div>
                <div class="text-[11px] text-emerald-700 font-medium mt-1">Hồ sơ đã cập nhật chi nhánh mới</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </a>
    </div>

    <!-- Thanh Bộ Lọc Nâng Cao -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Tìm Kiếm</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= e($keyword) ?>" placeholder="Tên NV, mã NV, số QĐ..."
                           class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Trạng Thái Xử Lý</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Chờ Phê Duyệt</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>✓ Đã Duyệt (Chờ ngày)</option>
                    <option value="executed" <?= $status_filter === 'executed' ? 'selected' : '' ?>>★ Đã Thực Thi Cập Nhật</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>✕ Từ Chối</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Chi Nhánh Hiện Tại (Đi)</label>
                <select name="from_branch_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="0">-- Tất cả chi nhánh đi --</option>
                    <?php foreach ($branches_list as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $from_branch_filter == $br['id'] ? 'selected' : '' ?>>
                            <?= e($br['name']) ?> (<?= e($br['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Chi Nhánh Tiếp Nhận (Đến)</label>
                    <select name="to_branch_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">-- Tất cả chi nhánh đến --</option>
                        <?php foreach ($branches_list as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= $to_branch_filter == $br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?> (<?= e($br['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition flex-shrink-0">
                    Lọc
                </button>
            </div>
        </form>
    </div>

    <!-- Bảng Danh Sách Các Lệnh Thuyên Chuyển -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-slate-500 uppercase font-semibold">
                        <th class="py-3.5 px-4">Mã Phiếu & Quyết Định</th>
                        <th class="py-3.5 px-4">Cán Bộ Được Điều Động</th>
                        <th class="py-3.5 px-4">Lộ Trình Thuyên Chuyển (Đi &rarr; Đến)</th>
                        <th class="py-3.5 px-4">Hình Thức & Lý Do</th>
                        <th class="py-3.5 px-4 text-center">Ngày Hiệu Lực</th>
                        <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                        <th class="py-3.5 px-4 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($transfers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400">
                                <i class="fa-solid fa-people-arrows text-3xl mb-2 text-slate-300 block"></i>
                                Chưa có hồ sơ thuyên chuyển nào theo tiêu chí lọc này.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($transfers as $t): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            
                            <!-- Cột 1: Mã phiếu & Số quyết định -->
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-800 font-mono text-xs"><?= e($t['transfer_code']) ?></div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    QĐ: <strong class="text-indigo-600"><?= e($t['decision_number'] ?: 'Đang soạn thảo') ?></strong>
                                </div>
                                <?php if ($t['allowance_support'] > 0): ?>
                                    <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">
                                        + Phụ cấp: <?= format_money($t['allowance_support']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Cột 2: Cán bộ nhân viên -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                        <?= strtoupper(mb_substr($t['emp_fullname'], 0, 1, 'UTF-8')) ?>
                                    </div>
                                    <div>
                                        <a href="<?= base_url('modules/employees/view.php?id=' . $t['employee_id']) ?>" 
                                           class="font-bold text-slate-800 hover:text-indigo-600 transition">
                                            <?= e($t['emp_fullname']) ?>
                                        </a>
                                        <div class="text-[10px] text-slate-400 font-mono"><?= e($t['emp_code']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Cột 3: Lộ trình Chi nhánh cũ -> Chi nhánh mới -->
                            <td class="py-4 px-4 max-w-xs">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <div class="p-1.5 rounded-lg bg-slate-100 text-slate-700 font-semibold text-[11px] truncate max-w-[120px]">
                                        <i class="fa-solid fa-building text-[10px] mr-1 text-slate-400"></i><?= e($t['from_branch_code']) ?>
                                    </div>
                                    <i class="fa-solid fa-arrow-right text-indigo-500 text-xs"></i>
                                    <div class="p-1.5 rounded-lg bg-indigo-50 text-indigo-800 font-bold text-[11px] truncate max-w-[130px] border border-indigo-100">
                                        <i class="fa-solid fa-building-flag text-[10px] mr-1 text-indigo-600"></i><?= e($t['to_branch_code']) ?>
                                    </div>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1 truncate">
                                    <span><?= e($t['from_dept_name']) ?></span>
                                    <span>&rarr;</span>
                                    <strong class="text-slate-800"><?= e($t['to_dept_name']) ?></strong>
                                </div>
                                <div class="text-[10px] text-slate-400 italic">
                                    Vị trí: <?= e($t['to_pos_name']) ?>
                                </div>
                            </td>

                            <!-- Cột 4: Hình thức điều động & Lý do -->
                            <td class="py-4 px-4 max-w-xs">
                                <div>
                                    <?php 
                                        $type_badge = [
                                            'branch_transfer' => ['bg' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'label' => 'Điều động chi nhánh'],
                                            'promotion' => ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Thăng chức & điều chuyển'],
                                            'rotation' => ['bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'label' => 'Luân chuyển định kỳ'],
                                            'special_assignment' => ['bg' => 'bg-purple-50 text-purple-700 border-purple-200', 'label' => 'Biệt phái công tác']
                                        ][$t['transfer_type']] ?? ['bg' => 'bg-slate-50 text-slate-700 border-slate-200', 'label' => 'Thuyên chuyển'];
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $type_badge['bg'] ?>">
                                        <?= $type_badge['label'] ?>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-600 mt-1.5 line-clamp-2" title="<?= e($t['reason']) ?>">
                                    <?= e($t['reason']) ?>
                                </p>
                            </td>

                            <!-- Cột 5: Ngày hiệu lực -->
                            <td class="py-4 px-4 text-center font-mono text-slate-700">
                                <?= format_date($t['effective_date']) ?>
                            </td>

                            <!-- Cột 6: Trạng thái -->
                            <td class="py-4 px-4 text-center">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Chờ Duyệt
                                    </span>
                                <?php elseif ($t['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                        <i class="fa-solid fa-check text-[9px]"></i> Đã Duyệt
                                    </span>
                                <?php elseif ($t['status'] === 'executed'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-circle-check text-[9px]"></i> Đã Thực Thi
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <i class="fa-solid fa-xmark text-[9px]"></i> Từ Chối
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Cột 7: Thao tác -->
                            <td class="py-4 px-4 text-right space-x-1.5 whitespace-nowrap">
                                
                                <!-- In Quyết Định -->
                                <a href="<?= base_url('modules/transfers/print_decision.php?id=' . $t['id']) ?>" 
                                   target="_blank"
                                   class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 inline-flex items-center justify-center transition"
                                   title="In Quyết Định Điều Động">
                                    <i class="fa-solid fa-print text-xs"></i>
                                </a>

                                <?php if ($t['status'] === 'pending' && has_permission('transfers', 'approve')): ?>
                                    <!-- Nút Duyệt & Thực Thi Ngay -->
                                    <button onclick="confirmExecute(<?= $t['id'] ?>, '<?= e($t['emp_fullname']) ?>', '<?= e($t['to_branch_name']) ?>')" 
                                            class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-[10px] transition"
                                            title="Phê duyệt và cập nhật hồ sơ ngay">
                                        <i class="fa-solid fa-check mr-0.5"></i> Duyệt Ngay
                                    </button>
                                    
                                    <!-- Nút Từ Chối -->
                                    <button onclick="openRejectModal(<?= $t['id'] ?>, '<?= e($t['emp_fullname']) ?>')" 
                                            class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 inline-flex items-center justify-center transition"
                                            title="Từ chối đề xuất">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                <?php elseif ($t['status'] === 'approved' && has_permission('transfers', 'approve')): ?>
                                    <button onclick="confirmExecute(<?= $t['id'] ?>, '<?= e($t['emp_fullname']) ?>', '<?= e($t['to_branch_name']) ?>')" 
                                            class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-[10px] transition">
                                        Thực thi
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($t['status'], ['pending', 'rejected']) && has_permission('transfers', 'delete')): ?>
                                    <button onclick="confirmDeleteTransfer(<?= $t['id'] ?>)" 
                                            class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-400 inline-flex items-center justify-center transition"
                                            title="Xóa đề xuất">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                <?php endif; ?>

                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Duyệt & Thực Thi Điều Động -->
<div id="executeModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xác Nhận Phê Duyệt & Thực Thi?</h3>
        <p id="executeModalMsg" class="text-xs text-slate-500 mb-6 leading-relaxed"></p>
        
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="execute_transfer">
            <input type="hidden" name="transfer_id" id="executeTransferId" value="">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('executeModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Đồng Ý Thực Thi Ngay
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Từ Chối Đề Xuất -->
<div id="rejectModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <h3 class="font-bold text-slate-800 text-base mb-1">Từ Chối Đề Xuất Thuyên Chuyển</h3>
        <p id="rejectModalMsg" class="text-xs text-slate-500 mb-4"></p>
        
        <form method="POST" action="index.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="reject_transfer">
            <input type="hidden" name="transfer_id" id="rejectTransferId" value="">
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Lý do từ chối *</label>
                <textarea name="reject_reason" required rows="3" placeholder="Nhập lý do không phê duyệt..."
                          class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Đóng
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition">
                    Xác Nhận Từ Chối
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xóa -->
<div id="deleteModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xóa Đề Xuất Này?</h3>
        <p class="text-xs text-slate-500 mb-5">Hành động này sẽ xóa vĩnh viễn phiếu đề xuất thuyên chuyển.</p>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_transfer">
            <input type="hidden" name="transfer_id" id="delTransferId" value="">
            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition">
                    Xóa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmExecute(id, empName, branchName) {
    document.getElementById('executeTransferId').value = id;
    document.getElementById('executeModalMsg').innerHTML = `Hệ thống sẽ cập nhật ngay cán bộ <strong>${empName}</strong> sang công tác tại chi nhánh <strong>${branchName}</strong>.`;
    document.getElementById('executeModal').classList.remove('hidden');
}

function openRejectModal(id, empName) {
    document.getElementById('rejectTransferId').value = id;
    document.getElementById('rejectModalMsg').innerText = `Cán bộ: ${empName}`;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function confirmDeleteTransfer(id) {
    document.getElementById('delTransferId').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
