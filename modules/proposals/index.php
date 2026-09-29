<?php
// modules/proposals/index.php - Trung Tâm Đề Xuất & Phê Duyệt
$page_title = 'Trung Tâm Đề Xuất & Phê Duyệt';

require_once __DIR__ . '/../../core/auth.php';

// Cho phép xem nếu có quyền view hoặc create
if (!has_permission('proposals', 'view') && !has_permission('proposals', 'create')) {
    require_permission('proposals', 'view');
}

// Tham số lọc
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_dept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_from = $_GET['from_date'] ?? '';
$filter_to = $_GET['to_date'] ?? '';

// Xử lý POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. PHÊ DUYỆT ĐỀ XUẤT
    if ($action_type === 'approve_proposal') {
        require_permission('proposals', 'approve');
        $proposal_id = (int)($_POST['proposal_id'] ?? 0);

        if ($proposal_id > 0) {
            $user = current_user();
            $pdo->beginTransaction();
            try {
                // Cập nhật trạng thái
                $stmt = $pdo->prepare("UPDATE proposals SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$user['id'], $proposal_id]);

                // Nếu là đề xuất nghỉ phép → tự động tạo bản ghi attendance
                $propStmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
                $propStmt->execute([$proposal_id]);
                $proposal = $propStmt->fetch();

                if ($proposal && $proposal['type'] === 'leave' && $proposal['start_date'] && $proposal['end_date']) {
                    $start = new DateTime($proposal['start_date']);
                    $end = new DateTime($proposal['end_date']);
                    $end->modify('+1 day'); // Inclusive

                    $attInsert = $pdo->prepare("
                        INSERT INTO attendance (employee_id, date, check_in, check_out, status, note, proposal_id)
                        VALUES (?, ?, NULL, NULL, 'leave_with_permit', ?, ?)
                        ON DUPLICATE KEY UPDATE status = 'leave_with_permit', note = VALUES(note), proposal_id = VALUES(proposal_id)
                    ");

                    $interval = new DateInterval('P1D');
                    $period = new DatePeriod($start, $interval, $end);

                    foreach ($period as $dt) {
                        $dayOfWeek = (int)$dt->format('N');
                        // Bỏ qua T7 (6) và CN (7)
                        if ($dayOfWeek >= 6) continue;

                        $attInsert->execute([
                            $proposal['employee_id'],
                            $dt->format('Y-m-d'),
                            'Nghỉ phép theo đề xuất #' . $proposal['proposal_code'],
                            $proposal_id
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Đã phê duyệt đề xuất thành công!' . ($proposal['type'] === 'leave' ? ' Bản ghi chấm công nghỉ phép đã được tạo tự động.' : ''));
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Lỗi khi phê duyệt: ' . $e->getMessage());
            }
            redirect('modules/proposals/index.php');
        }
    }

    // 2. TỪ CHỐI ĐỀ XUẤT
    if ($action_type === 'reject_proposal') {
        require_permission('proposals', 'approve');
        $proposal_id = (int)($_POST['proposal_id'] ?? 0);
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');

        if ($proposal_id > 0 && !empty($rejection_reason)) {
            $user = current_user();
            $stmt = $pdo->prepare("UPDATE proposals SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ? AND status = 'pending'");
            $stmt->execute([$user['id'], $rejection_reason, $proposal_id]);
            set_flash('success', 'Đã từ chối đề xuất. Lý do đã được ghi nhận.');
        } else {
            set_flash('danger', 'Vui lòng nhập lý do từ chối.');
        }
        redirect('modules/proposals/index.php');
    }

    // 3. XÓA ĐỀ XUẤT
    if ($action_type === 'delete_proposal') {
        require_permission('proposals', 'delete');
        $proposal_id = (int)($_POST['proposal_id'] ?? 0);

        if ($proposal_id > 0) {
            // Xóa attendance records liên kết (nếu có)
            $pdo->prepare("DELETE FROM attendance WHERE proposal_id = ?")->execute([$proposal_id]);
            $pdo->prepare("DELETE FROM proposals WHERE id = ?")->execute([$proposal_id]);
            set_flash('success', 'Đã xóa đề xuất thành công.');
        }
        redirect('modules/proposals/index.php');
    }
}

// Lấy danh sách phòng ban
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Thống kê KPI
$kpi_sql = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
    FROM proposals
";
$kpi = $pdo->query($kpi_sql)->fetch();

// Query danh sách đề xuất
$sql = "
    SELECT p.*, 
           e.fullname, e.employee_code, e.avatar,
           d.name AS department_name,
           pos.name AS position_name,
           u.fullname AS approver_name
    FROM proposals p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN users u ON p.approved_by = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_type)) {
    $sql .= " AND p.type = ?";
    $params[] = $filter_type;
}
if (!empty($filter_status)) {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
}
if ($filter_dept > 0) {
    $sql .= " AND e.department_id = ?";
    $params[] = $filter_dept;
}
if (!empty($filter_from)) {
    $sql .= " AND p.created_at >= ?";
    $params[] = $filter_from . ' 00:00:00';
}
if (!empty($filter_to)) {
    $sql .= " AND p.created_at <= ?";
    $params[] = $filter_to . ' 23:59:59';
}

$sql .= " ORDER BY 
    CASE p.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 END ASC,
    CASE p.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 END ASC,
    p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proposals = $stmt->fetchAll();

// Map label & style cho loại đề xuất
$type_labels = [
    'leave'         => ['Nghỉ Phép',    'fa-calendar-minus', 'sky'],
    'overtime'      => ['Tăng Ca',       'fa-clock',          'violet'],
    'business_trip' => ['Công Tác',      'fa-plane',          'teal'],
    'salary_raise'  => ['Tăng Lương',    'fa-coins',          'amber'],
    'equipment'     => ['Thiết Bị',      'fa-laptop',         'indigo'],
    'other'         => ['Khác',          'fa-clipboard-list', 'slate'],
];

$priority_labels = [
    'low'    => ['Thấp',         'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'],
    'normal' => ['Bình Thường',  'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800'],
    'high'   => ['Cao',          'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
    'urgent' => ['Khẩn Cấp',    'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800'],
];

$status_labels = [
    'pending'  => ['Chờ Duyệt',  'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700'],
    'approved' => ['Đã Duyệt',   'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700'],
    'rejected' => ['Từ Chối',    'bg-rose-100 dark:bg-rose-900/60 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700'],
];

$leave_type_labels = [
    'annual'    => 'Phép Năm',
    'sick'      => 'Nghỉ Ốm',
    'maternity' => 'Thai Sản',
    'personal'  => 'Việc Riêng',
    'unpaid'    => 'Không Lương',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 mb-2">
                <i class="fa-solid fa-paper-plane"></i> Trung Tâm Đề Xuất & Phê Duyệt
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white">Quản Lý Đề Xuất Nhân Sự</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Gửi, theo dõi và phê duyệt các đề xuất nghỉ phép, tăng ca, công tác, tăng lương và thiết bị.</p>
        </div>
        <?php if (has_permission('proposals', 'create')): ?>
            <a href="<?= base_url('modules/proposals/create.php') ?>"
               class="px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-violet-100 dark:shadow-none transition flex items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-plus"></i>
                <span>Tạo Đề Xuất Mới</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- 4 KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-amber transition-all hover:shadow-md">
            <div>
                <span class="kpi-label text-amber-600 dark:text-amber-400">Chờ Duyệt</span>
                <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $kpi['pending_count'] ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-base border border-amber-200 dark:border-amber-800/60">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-emerald transition-all hover:shadow-md">
            <div>
                <span class="kpi-label text-emerald-600 dark:text-emerald-400">Đã Duyệt</span>
                <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $kpi['approved_count'] ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-base border border-emerald-200 dark:border-emerald-800/60">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-rose transition-all hover:shadow-md">
            <div>
                <span class="kpi-label text-rose-600 dark:text-rose-400">Từ Chối</span>
                <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $kpi['rejected_count'] ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-base border border-rose-200 dark:border-rose-800/60">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-indigo transition-all hover:shadow-md">
            <div>
                <span class="kpi-label text-indigo-600 dark:text-indigo-400">Tổng Đề Xuất</span>
                <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $kpi['total'] ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-base border border-indigo-200 dark:border-indigo-800/60">
                <i class="fa-solid fa-layer-group"></i>
            </div>
        </div>
    </div>

    <!-- Bộ Lọc -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <form action="index.php" method="GET" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Loại Đề Xuất</label>
                <select name="type" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="">-- Tất cả --</option>
                    <?php foreach ($type_labels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filter_type === $key) ? 'selected' : '' ?>><?= $info[0] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-[120px]">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Trạng Thái</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="">-- Tất cả --</option>
                    <?php foreach ($status_labels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filter_status === $key) ? 'selected' : '' ?>><?= $info[0] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Phòng Ban</label>
                <select name="department_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="0">-- Tất cả --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($filter_dept == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="min-w-[120px]">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Từ Ngày</label>
                <input type="date" name="from_date" value="<?= e($filter_from) ?>"
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
            </div>

            <div class="min-w-[120px]">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Đến Ngày</label>
                <input type="date" name="to_date" value="<?= e($filter_to) ?>"
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
            </div>

            <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-filter text-[10px]"></i>
                <span>Lọc</span>
            </button>

            <?php if (!empty($filter_type) || !empty($filter_status) || $filter_dept > 0 || !empty($filter_from) || !empty($filter_to)): ?>
                <a href="<?= base_url('modules/proposals/index.php') ?>" class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition border border-slate-200 dark:border-slate-600">
                    <i class="fa-solid fa-xmark mr-1"></i>Xóa lọc
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bảng Danh Sách Đề Xuất -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/70 text-slate-600 dark:text-slate-300 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-5">Mã ĐX</th>
                        <th class="py-3.5 px-5">Người Đề Xuất</th>
                        <th class="py-3.5 px-4">Loại</th>
                        <th class="py-3.5 px-5">Tiêu Đề & Thời Gian</th>
                        <th class="py-3.5 px-4 text-center">Ưu Tiên</th>
                        <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                        <th class="py-3.5 px-4 text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php if (empty($proposals)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-16 text-slate-400 dark:text-slate-500">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                        <i class="fa-solid fa-inbox text-2xl text-slate-300 dark:text-slate-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-500 dark:text-slate-400 text-sm">Chưa có đề xuất nào</div>
                                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Nhấn "Tạo Đề Xuất Mới" để bắt đầu</div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($proposals as $p): ?>
                        <?php
                        $t = $type_labels[$p['type']] ?? ['Khác', 'fa-clipboard-list', 'slate'];
                        $pr = $priority_labels[$p['priority']] ?? $priority_labels['normal'];
                        $st = $status_labels[$p['status']] ?? $status_labels['pending'];
                        ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/50 transition <?= $p['status'] === 'pending' ? 'bg-amber-50/30 dark:bg-amber-950/10' : '' ?>">
                            <!-- Mã ĐX -->
                            <td class="py-4 px-5">
                                <a href="<?= base_url('modules/proposals/view.php?id=' . $p['id']) ?>" class="font-mono font-bold text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-300 text-xs transition">
                                    <?= e($p['proposal_code']) ?>
                                </a>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5"><?= format_datetime($p['created_at']) ?></div>
                            </td>

                            <!-- Người Đề Xuất -->
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($p['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $p['avatar'])): ?>
                                        <img src="<?= base_url('assets/uploads/' . e($p['avatar'])) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700" alt="">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xs shadow-sm flex-shrink-0">
                                            <?= strtoupper(mb_substr($p['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-white text-xs sm:text-sm"><?= e($p['fullname']) ?></div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-400 font-mono">
                                            <?= e($p['employee_code']) ?> • <?= e($p['department_name'] ?? 'Phòng ban') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Loại -->
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-<?= $t[2] ?>-50 dark:bg-<?= $t[2] ?>-950/60 text-<?= $t[2] ?>-700 dark:text-<?= $t[2] ?>-300 border border-<?= $t[2] ?>-200 dark:border-<?= $t[2] ?>-800">
                                    <i class="fa-solid <?= $t[1] ?> text-[10px]"></i>
                                    <?= $t[0] ?>
                                </span>
                                <?php if ($p['type'] === 'leave' && !empty($p['leave_type'])): ?>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 ml-1"><?= $leave_type_labels[$p['leave_type']] ?? '' ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Tiêu Đề & Thời Gian -->
                            <td class="py-4 px-5">
                                <a href="<?= base_url('modules/proposals/view.php?id=' . $p['id']) ?>" class="font-semibold text-slate-800 dark:text-white text-xs hover:text-violet-600 dark:hover:text-violet-400 transition line-clamp-1">
                                    <?= e($p['title']) ?>
                                </a>
                                <?php if (!empty($p['start_date'])): ?>
                                    <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        <i class="fa-regular fa-calendar text-[9px]"></i>
                                        <span><?= format_date($p['start_date']) ?></span>
                                        <?php if (!empty($p['end_date']) && $p['end_date'] !== $p['start_date']): ?>
                                            <span>→ <?= format_date($p['end_date']) ?></span>
                                            <span class="font-bold text-slate-500 dark:text-slate-400">(<?= number_format((float)$p['total_days'], 1) ?> ngày)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ((float)$p['amount'] > 0): ?>
                                    <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400 mt-0.5">
                                        <i class="fa-solid fa-coins text-[9px] mr-0.5"></i> <?= format_money($p['amount']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Ưu Tiên -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $pr[1] ?>">
                                    <?= $pr[0] ?>
                                </span>
                            </td>

                            <!-- Trạng Thái -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $st[1] ?>">
                                    <?php if ($p['status'] === 'pending'): ?><i class="fa-solid fa-hourglass-half text-[9px]"></i>
                                    <?php elseif ($p['status'] === 'approved'): ?><i class="fa-solid fa-check text-[9px]"></i>
                                    <?php else: ?><i class="fa-solid fa-xmark text-[9px]"></i>
                                    <?php endif; ?>
                                    <?= $st[0] ?>
                                </span>
                                <?php if ($p['status'] !== 'pending' && !empty($p['approver_name'])): ?>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">bởi <?= e($p['approver_name']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Thao Tác -->
                            <td class="py-4 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="<?= base_url('modules/proposals/view.php?id=' . $p['id']) ?>"
                                       class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center justify-center transition"
                                       title="Xem chi tiết">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>

                                    <?php if ($p['status'] === 'pending' && has_permission('proposals', 'approve')): ?>
                                        <form action="index.php" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="approve_proposal">
                                            <input type="hidden" name="proposal_id" value="<?= $p['id'] ?>">
                                            <button type="submit" onclick="return confirm('Bạn xác nhận PHÊ DUYỆT đề xuất này?')"
                                                    class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center transition"
                                                    title="Phê duyệt">
                                                <i class="fa-solid fa-check text-xs"></i>
                                            </button>
                                        </form>

                                        <button type="button" onclick="openRejectModal(<?= $p['id'] ?>, '<?= e($p['proposal_code']) ?>')"
                                                class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center transition"
                                                title="Từ chối">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (has_permission('proposals', 'delete')): ?>
                                        <form action="index.php" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="delete_proposal">
                                            <input type="hidden" name="proposal_id" value="<?= $p['id'] ?>">
                                            <button type="submit" onclick="return confirm('Xóa vĩnh viễn đề xuất <?= e($p['proposal_code']) ?>?')"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-slate-400 dark:text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 flex items-center justify-center transition"
                                                    title="Xóa đề xuất">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
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

        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 flex items-center justify-between">
            <span>Tổng cộng <strong class="text-slate-700 dark:text-slate-200"><?= count($proposals) ?></strong> đề xuất <?= !empty($filter_status) ? '(' . ($status_labels[$filter_status][0] ?? '') . ')' : '' ?></span>
            <?php if ($kpi['pending_count'] > 0 && has_permission('proposals', 'approve')): ?>
                <span class="text-amber-600 dark:text-amber-400 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-bell text-xs animate-pulse"></i>
                    <?= $kpi['pending_count'] ?> đề xuất đang chờ phê duyệt
                </span>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal Từ Chối Đề Xuất -->
<div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden animate-fade-in">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full p-6 border-2 border-slate-200 dark:border-slate-700 animate-scale-up">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 text-xl shadow-xs">
                <i class="fa-solid fa-ban"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 dark:text-white text-base mb-0.5">Từ Chối Đề Xuất</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Mã: <strong id="rejectProposalCode" class="text-rose-600 dark:text-rose-400"></strong></p>
            </div>
        </div>
        <form action="index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="reject_proposal">
            <input type="hidden" name="proposal_id" id="rejectProposalId" value="">

            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">Lý Do Từ Chối <span class="text-rose-500">*</span></label>
            <textarea name="rejection_reason" rows="4" required placeholder="Nhập lý do từ chối để nhân viên biết cần điều chỉnh gì..."
                      class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-rose-500 outline-none resize-none"></textarea>

            <div class="mt-5 flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-bold transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold transition shadow-sm">
                    <i class="fa-solid fa-ban mr-1.5"></i>Xác Nhận Từ Chối
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id, code) {
    document.getElementById('rejectProposalId').value = id;
    document.getElementById('rejectProposalCode').textContent = code;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
// Close on backdrop click
document.getElementById('rejectModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
