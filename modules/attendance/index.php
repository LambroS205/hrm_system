<?php
// modules/attendance/index.php - Phân hệ Chấm công hàng ngày, Bảng tổng hợp công tháng & Lịch sử cá nhân
$page_title = 'Quản Lý & Chấm Công Nhân Sự';

require_once __DIR__ . '/../../core/auth.php';
require_permission('attendance', 'view');

// Lấy tham số tab hiển thị (daily = Chấm công ngày, monthly = Tổng hợp tháng, personal = Cá nhân)
$active_tab = $_GET['tab'] ?? 'daily';
$selected_date = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Lấy tham số tháng/năm cho bảng tổng hợp
$selected_month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_dept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$selected_emp = !empty($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. Lưu bảng điểm danh hàng loạt theo ngày
    if ($action_type === 'save_daily_attendance') {
        require_permission('attendance', 'create');
        $att_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $records = $_POST['att'] ?? []; // Mảng [emp_id => ['status', 'check_in', 'check_out', 'note']]

        try {
            $pdo->beginTransaction();

            $upsertStmt = $pdo->prepare("
                INSERT INTO attendance (employee_id, date, check_in, check_out, overtime_hours, late_minutes, early_minutes, status, note)
                VALUES (:emp_id, :att_date, :check_in, :check_out, :ot, :late, :early, :status, :note)
                ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in),
                    check_out = VALUES(check_out),
                    overtime_hours = VALUES(overtime_hours),
                    late_minutes = VALUES(late_minutes),
                    early_minutes = VALUES(early_minutes),
                    status = VALUES(status),
                    note = VALUES(note)
            ");

            foreach ($records as $emp_id => $data) {
                $status = $data['status'] ?? 'present';
                $check_in = !empty($data['check_in']) ? $data['check_in'] : null;
                $check_out = !empty($data['check_out']) ? $data['check_out'] : null;
                $note = trim($data['note'] ?? '');

                // Tự động điền giờ chuẩn nếu nhân viên có mặt mà chưa nhập giờ
                if ($status === 'present' && empty($check_in)) {
                    $check_in = '08:00:00';
                    $check_out = '17:30:00';
                }

                // Tính số phút đi muộn (so với 08:00)
                $late_minutes = 0;
                if (!empty($check_in) && $check_in > '08:00:00' && in_array($status, ['present', 'late'])) {
                    $late_minutes = max(0, (int)((strtotime($check_in) - strtotime('08:00:00')) / 60));
                }

                // Tính số phút về sớm (so với 17:30)
                $early_minutes = 0;
                if (!empty($check_out) && $check_out < '17:30:00' && in_array($status, ['present', 'early_leave'])) {
                    $early_minutes = max(0, (int)((strtotime('17:30:00') - strtotime($check_out)) / 60));
                }

                // Tính giờ OT (nếu check-out sau 18:00 — OT tính sau 30 phút break)
                $overtime_hours = 0;
                if (!empty($check_out) && $check_out > '18:00:00' && $status === 'present') {
                    $overtime_hours = max(0, round((strtotime($check_out) - strtotime('18:00:00')) / 3600, 2));
                }

                $upsertStmt->execute([
                    ':emp_id'    => (int)$emp_id,
                    ':att_date'  => $att_date,
                    ':check_in'  => $check_in,
                    ':check_out' => $check_out,
                    ':ot'        => $overtime_hours,
                    ':late'      => $late_minutes,
                    ':early'     => $early_minutes,
                    ':status'    => $status,
                    ':note'      => $note
                ]);
            }

            $pdo->commit();
            set_flash('success', "Đã lưu thành công dữ liệu chấm công ngày " . format_date($att_date));
            redirect("modules/attendance/index.php?tab=daily&date=" . urlencode($att_date) . ($selected_dept ? "&department_id={$selected_dept}" : ''));
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi khi lưu bảng chấm công: ' . $e->getMessage());
        }
    }

    if ($action_type === 'delete_attendance_record') {
        require_permission('attendance', 'delete');
        $att_id = (int)($_POST['attendance_id'] ?? 0);
        if ($att_id > 0) {
            $delStmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
            $delStmt->execute([$att_id]);
            set_flash('success', 'Đã xóa bản ghi chấm công thành công.');
            redirect("modules/attendance/index.php?tab=daily&date=" . urlencode($selected_date));
        }
    }
}

// Danh sách phòng ban cho bộ lọc
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Truy vấn danh sách nhân viên đang làm việc (loại bỏ nhân viên đã nghỉ)
$emp_sql = "
    SELECT e.id, e.employee_code, e.fullname, e.avatar, d.name AS department_name, p.name AS position_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
";
$emp_params = [];

if ($selected_dept > 0) {
    $emp_sql .= " AND e.department_id = ?";
    $emp_params[] = $selected_dept;
}
$emp_sql .= " ORDER BY e.id ASC";

$empStmt = $pdo->prepare($emp_sql);
$empStmt->execute($emp_params);
$active_employees = $empStmt->fetchAll();

// Nếu ở Tab Chấm công ngày: Lấy dữ liệu điểm danh của ngày đó
$daily_attendance = [];
if ($active_tab === 'daily') {
    $attStmt = $pdo->prepare("SELECT * FROM attendance WHERE date = ?");
    $attStmt->execute([$selected_date]);
    $rows = $attStmt->fetchAll();
    foreach ($rows as $r) {
        $daily_attendance[$r['employee_id']] = $r;
    }

    // Thống kê nhanh của ngày
    $stat_present = 0;
    $stat_late = 0;
    $stat_leave = 0;
    $stat_absent = 0;
    $stat_total_ot = 0;
    $stat_total_late_count = 0;

    foreach ($active_employees as $emp) {
        $rec = $daily_attendance[$emp['id']] ?? null;
        $st = $rec ? $rec['status'] : 'not_checked';
        if ($st === 'present') $stat_present++;
        elseif ($st === 'late' || $st === 'early_leave') $stat_late++;
        elseif ($st === 'leave_with_permit') $stat_leave++;
        elseif ($st === 'absent') $stat_absent++;
        
        if ($rec) {
            $stat_total_ot += (float)($rec['overtime_hours'] ?? 0);
            if ((int)($rec['late_minutes'] ?? 0) > 0) $stat_total_late_count++;
        }
    }
}

// Nếu ở Tab Tổng hợp tháng: Lấy tổng hợp công của tất cả nhân viên trong tháng
$monthly_summary = [];
if ($active_tab === 'monthly') {
    $start_date = sprintf('%04d-%02d-01', $selected_year, $selected_month);
    $end_date = date('Y-m-t', strtotime($start_date));

    $summary_sql = "
        SELECT 
            employee_id,
            COUNT(CASE WHEN status = 'present' THEN 1 END) AS count_present,
            COUNT(CASE WHEN status = 'late' THEN 1 END) AS count_late,
            COUNT(CASE WHEN status = 'early_leave' THEN 1 END) AS count_early,
            COUNT(CASE WHEN status = 'leave_with_permit' THEN 1 END) AS count_leave,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) AS count_absent,
            COALESCE(SUM(overtime_hours), 0) AS total_ot,
            COUNT(CASE WHEN late_minutes > 0 THEN 1 END) AS late_count
        FROM attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY employee_id
    ";
    $sumStmt = $pdo->prepare($summary_sql);
    $sumStmt->execute([$start_date, $end_date]);
    $sumRows = $sumStmt->fetchAll();

    foreach ($sumRows as $row) {
        $total_work_days = $row['count_present'] + $row['count_leave'] + (($row['count_late'] + $row['count_early']) * 0.5);
        $row['total_work_days'] = $total_work_days;
        $monthly_summary[$row['employee_id']] = $row;
    }
}

// Nếu ở Tab Lịch sử cá nhân: Lấy dữ liệu chấm công theo tháng của 1 nhân viên
$personal_data = [];
$personal_employee = null;
if ($active_tab === 'personal') {
    if ($selected_emp > 0) {
        // Lấy thông tin nhân viên
        $empDetailStmt = $pdo->prepare("
            SELECT e.*, d.name AS department_name, p.name AS position_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN positions p ON e.position_id = p.id 
            WHERE e.id = ?
        ");
        $empDetailStmt->execute([$selected_emp]);
        $personal_employee = $empDetailStmt->fetch();

        // Lấy dữ liệu chấm công tháng
        $start_date = sprintf('%04d-%02d-01', $selected_year, $selected_month);
        $end_date = date('Y-m-t', strtotime($start_date));
        $days_in_month = (int)date('t', strtotime($start_date));

        $personalStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
        $personalStmt->execute([$selected_emp, $start_date, $end_date]);
        $personalRows = $personalStmt->fetchAll();

        foreach ($personalRows as $pr) {
            $day = (int)date('j', strtotime($pr['date']));
            $personal_data[$day] = $pr;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu đề & Giới thiệu -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 mb-2">
                <i class="fa-solid fa-calendar-check"></i> Quản Lý Công Tác & Điểm Danh
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white">Chấm Công & Bảng Tổng Hợp Ngày Công</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Theo dõi lịch trình có mặt, đi muộn, nghỉ phép và xuất dữ liệu công tổng hợp làm căn cứ tính lương.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($active_tab === 'daily' && has_permission('attendance', 'create')): ?>
                <button type="submit" form="dailyAttendanceForm" 
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-emerald-100 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu Bảng Điểm Danh</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thanh Tabs Chuyển đổi chế độ xem -->
    <div class="flex border-b border-slate-200 dark:border-slate-700">
        <a href="?tab=daily&date=<?= urlencode($selected_date) ?>&department_id=<?= $selected_dept ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'daily' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 dark:border-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i class="fa-solid fa-clipboard-user"></i>
            <span>1. Điểm Danh Hàng Ngày</span>
        </a>
        <a href="?tab=monthly&month=<?= $selected_month ?>&year=<?= $selected_year ?>&department_id=<?= $selected_dept ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'monthly' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 dark:border-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>2. Tổng Hợp Công Tháng</span>
        </a>
        <a href="?tab=personal&month=<?= $selected_month ?>&year=<?= $selected_year ?>&employee_id=<?= $selected_emp ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'personal' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 dark:border-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i class="fa-solid fa-user-clock"></i>
            <span>3. Lịch Sử Cá Nhân</span>
        </a>
    </div>

    <?php if ($active_tab === 'daily'): ?>
        <!-- ================= TAB 1: ĐIỂM DANH HÀNG NGÀY ================= -->

        <!-- 6 Khối Thống kê Trực quan Trong Ngày -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-emerald transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-emerald-600 dark:text-emerald-400">Có Mặt</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $stat_present ?> <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">/ <?= count($active_employees) ?></span></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-base border border-emerald-200 dark:border-emerald-800/60">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-amber transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-amber-600 dark:text-amber-400">Muộn / Sớm</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $stat_late ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-base border border-amber-200 dark:border-amber-800/60">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-sky transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-sky-600 dark:text-sky-400">Nghỉ Phép</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $stat_leave ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold text-base border border-sky-200 dark:border-sky-800/60">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-rose transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-rose-600 dark:text-rose-400">Vắng K.Phép</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $stat_absent ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-base border border-rose-200 dark:border-rose-800/60">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-violet transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-violet-600 dark:text-violet-400">Tổng OT</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5 font-mono"><?= number_format($stat_total_ot, 1) ?>h</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold text-base border border-violet-200 dark:border-violet-800/60">
                    <i class="fa-solid fa-business-time"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between kpi-stripe-indigo transition-all hover:shadow-md">
                <div>
                    <span class="kpi-label text-indigo-600 dark:text-indigo-400">NV Đi Muộn</span>
                    <div class="kpi-value text-slate-900 dark:text-white mt-0.5"><?= $stat_total_late_count ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-base border border-indigo-200 dark:border-indigo-800/60">
                    <i class="fa-solid fa-person-walking-arrow-right"></i>
                </div>
            </div>
        </div>

        <!-- Bộ lọc ngày và phòng ban -->
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="hidden" name="tab" value="daily">
                
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Chọn Ngày:</span>
                    <input type="date" name="date" value="<?= e($selected_date) ?>" onchange="this.form.submit()"
                           class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white dark:focus:bg-slate-900">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Phòng Ban:</span>
                    <select name="department_id" onchange="this.form.submit()"
                            class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white dark:focus:bg-slate-900">
                        <option value="0">-- Tất cả phòng ban --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($selected_dept == $d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if (has_permission('attendance', 'create')): ?>
                <!-- Thao tác chọn nhanh -->
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" onclick="setAllAttendance('present')" 
                            class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-check-double text-[10px]"></i>
                        <span>Tất Cả Đi Làm</span>
                    </button>
                    <button type="button" onclick="setAllAttendance('absent')" 
                            class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900/80 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/80 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                        <span>Đặt Nghỉ Hết</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bảng Nhập Liệu Điểm Danh Ngày -->
        <form id="dailyAttendanceForm" action="index.php" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_daily_attendance">
            <input type="hidden" name="attendance_date" value="<?= e($selected_date) ?>">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/70 text-slate-600 dark:text-slate-300 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Nhân Viên</th>
                            <th class="py-3.5 px-6">Trạng Thái Điểm Danh</th>
                            <th class="py-3.5 px-4 text-center">Giờ Vào</th>
                            <th class="py-3.5 px-4 text-center">Giờ Ra</th>
                            <th class="py-3.5 px-3 text-center">Muộn</th>
                            <th class="py-3.5 px-3 text-center">OT</th>
                            <th class="py-3.5 px-6">Ghi Chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php if (empty($active_employees)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-12 text-slate-400 dark:text-slate-500 text-sm">
                                    Không có nhân viên nào phù hợp với điều kiện lọc.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($active_employees as $emp): ?>
                            <?php 
                            $rec = $daily_attendance[$emp['id']] ?? null;
                            $cur_status = $rec ? $rec['status'] : 'present';
                            $cur_in = $rec ? $rec['check_in'] : '08:00';
                            $cur_out = $rec ? $rec['check_out'] : '17:30';
                            $cur_note = $rec ? $rec['note'] : '';
                            $cur_late = $rec ? (int)($rec['late_minutes'] ?? 0) : 0;
                            $cur_ot = $rec ? (float)($rec['overtime_hours'] ?? 0) : 0;
                            $has_proposal = $rec && !empty($rec['proposal_id']);
                            ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/50 transition">
                                <!-- Nhân viên -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $emp['avatar'])): ?>
                                            <img src="<?= base_url('assets/uploads/' . e($emp['avatar'])) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700" alt="">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900 flex items-center justify-center font-bold text-xs">
                                                <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-white text-xs sm:text-sm"><?= e($emp['fullname']) ?></div>
                                            <div class="text-[11px] text-slate-400 dark:text-slate-400 font-mono">
                                                <?= e($emp['employee_code']) ?> • <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Lựa chọn trạng thái -->
                                <td class="py-4 px-6">
                                    <select name="att[<?= $emp['id'] ?>][status]" 
                                            class="att-status-select text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none w-48 transition">
                                        <option value="present" <?= ($cur_status === 'present') ? 'selected' : '' ?>>
                                            🟢 Có Mặt Đầy Đủ (1.0 Công)
                                        </option>
                                        <option value="late" <?= ($cur_status === 'late') ? 'selected' : '' ?>>
                                            🟡 Đi Muộn (0.5 Công)
                                        </option>
                                        <option value="early_leave" <?= ($cur_status === 'early_leave') ? 'selected' : '' ?>>
                                            🟠 Về Sớm (0.5 Công)
                                        </option>
                                        <option value="leave_with_permit" <?= ($cur_status === 'leave_with_permit') ? 'selected' : '' ?>>
                                            🔵 Nghỉ Có Phép (1.0 Công)
                                        </option>
                                        <option value="absent" <?= ($cur_status === 'absent') ? 'selected' : '' ?>>
                                            🔴 Vắng Không Phép (0 Công)
                                        </option>
                                    </select>
                                    <?php if ($has_proposal): ?>
                                        <a href="<?= base_url('modules/proposals/view.php?id=' . $rec['proposal_id']) ?>" class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline mt-1 block">
                                            <i class="fa-solid fa-link text-[8px]"></i> Xem đề xuất nghỉ phép
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <!-- Giờ vào -->
                                <td class="py-4 px-4 text-center">
                                    <input type="time" name="att[<?= $emp['id'] ?>][check_in]" value="<?= e($cur_in) ?>"
                                           class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-700 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>

                                <!-- Giờ ra -->
                                <td class="py-4 px-4 text-center">
                                    <input type="time" name="att[<?= $emp['id'] ?>][check_out]" value="<?= e($cur_out) ?>"
                                           class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-700 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>

                                <!-- Đi muộn (phút) -->
                                <td class="py-4 px-3 text-center">
                                    <?php if ($cur_late > 0): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            <?= $cur_late ?>'
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-300 dark:text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- OT (giờ) -->
                                <td class="py-4 px-3 text-center">
                                    <?php if ($cur_ot > 0): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 dark:bg-violet-900/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                                            +<?= number_format($cur_ot, 1) ?>h
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-300 dark:text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Ghi chú -->
                                <td class="py-4 px-6">
                                    <input type="text" name="att[<?= $emp['id'] ?>][note]" value="<?= e($cur_note) ?>" placeholder="Lý do đi muộn, công tác ngoài..."
                                           class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Nút Lưu Footer -->
            <?php if (has_permission('attendance', 'create')): ?>
                <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Nhớ nhấn <strong class="text-slate-700 dark:text-slate-200">"Lưu Bảng Điểm Danh"</strong> để ghi nhận số liệu ngày công vào hệ thống.
                    </span>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Lưu Bảng Điểm Danh Ngày <?= format_date($selected_date) ?></span>
                    </button>
                </div>
            <?php endif; ?>
        </form>

    <?php elseif ($active_tab === 'monthly'): ?>
        <!-- ================= TAB 2: BẢNG TỔNG HỢP CÔNG THÁNG ================= -->

        <!-- Khung Lọc Tháng & Năm -->
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="hidden" name="tab" value="monthly">

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tháng:</span>
                    <select name="month" onchange="this.form.submit()" 
                            class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Năm:</span>
                    <select name="year" onchange="this.form.submit()" 
                            class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($y = 2024; $y <= 2027; $y++): ?>
                            <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Phòng Ban:</span>
                    <select name="department_id" onchange="this.form.submit()" 
                            class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option value="0">-- Toàn bộ phòng ban --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($selected_dept == $d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="flex items-center gap-2">
                <!-- Nút xuất Excel -->
                <button type="button" onclick="exportMonthlyCSV()"
                        class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-file-csv text-xs"></i>
                    <span>Xuất Excel</span>
                </button>

                <?php if (has_permission('payroll', 'create')): ?>
                    <a href="<?= base_url('modules/payroll/index.php?month=' . $selected_month . '&year=' . $selected_year) ?>" 
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-calculator text-xs"></i>
                        <span>Tính Lương T<?= $selected_month ?>/<?= $selected_year ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bảng Tổng Hợp Công Chi Tiết -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table id="monthlySummaryTable" class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/70 text-slate-600 dark:text-slate-300 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Nhân Viên & Đơn Vị</th>
                            <th class="py-3.5 px-4 text-center">Đủ Công</th>
                            <th class="py-3.5 px-4 text-center">Nghỉ Phép</th>
                            <th class="py-3.5 px-4 text-center">Muộn/Sớm</th>
                            <th class="py-3.5 px-4 text-center">K.Phép</th>
                            <th class="py-3.5 px-4 text-center">Tổng OT (h)</th>
                            <th class="py-3.5 px-4 text-center">Số Lần Muộn</th>
                            <th class="py-3.5 px-6 text-center bg-emerald-50/50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300">Tổng Công</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($active_employees as $emp): ?>
                            <?php 
                            $m = $monthly_summary[$emp['id']] ?? [
                                'count_present' => 0,
                                'count_leave' => 0,
                                'count_late' => 0,
                                'count_early' => 0,
                                'count_absent' => 0,
                                'total_ot' => 0,
                                'late_count' => 0,
                                'total_work_days' => 0
                            ];
                            ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/50 transition">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-slate-800 dark:text-white text-sm"><?= e($emp['fullname']) ?></div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-400 font-mono">
                                        Mã: <?= e($emp['employee_code']) ?> • <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                    </div>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-emerald-600 dark:text-emerald-400">
                                    <?= $m['count_present'] ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-sky-600 dark:text-sky-400">
                                    <?= $m['count_leave'] ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-amber-600 dark:text-amber-400">
                                    <?= ($m['count_late'] + $m['count_early']) ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-rose-500 dark:text-rose-400">
                                    <?= $m['count_absent'] ?>
                                </td>

                                <td class="py-4 px-4 text-center">
                                    <?php if ((float)$m['total_ot'] > 0): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 dark:bg-violet-900/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 font-mono">
                                            <?= number_format((float)$m['total_ot'], 1) ?>h
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">0</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-4 text-center">
                                    <?php if ((int)$m['late_count'] >= 3): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 animate-pulse" title="⚠️ Vi phạm: Đi muộn ≥ 3 lần/tháng">
                                            ⚠️ <?= (int)$m['late_count'] ?>
                                        </span>
                                    <?php elseif ((int)$m['late_count'] > 0): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            <?= (int)$m['late_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">0</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-6 text-center bg-emerald-50/40 dark:bg-emerald-950/20">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-mono">
                                        <?= number_format($m['total_work_days'], 1) ?> công
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                * Quy chuẩn: 1 ngày Có mặt = 1.0 công; Nghỉ có phép = 1.0 công; Đi muộn/Về sớm = 0.5 công; Vắng không phép = 0 công. 
                <span class="text-rose-500 dark:text-rose-400 font-bold ml-2">⚠️ = Đi muộn ≥ 3 lần/tháng (vi phạm nội quy)</span>
            </div>
        </div>

    <?php else: ?>
        <!-- ================= TAB 3: LỊCH SỬ CHẤM CÔNG CÁ NHÂN ================= -->

        <!-- Bộ lọc chọn nhân viên -->
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="personal">

                <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Nhân Viên:</span>
                    <select name="employee_id" onchange="this.form.submit()"
                            class="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option value="0">-- Chọn nhân viên --</option>
                        <?php
                        $all_emps = $pdo->query("SELECT e.id, e.employee_code, e.fullname, d.name AS dept FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.employment_status != 'resigned' ORDER BY e.fullname ASC")->fetchAll();
                        foreach ($all_emps as $ae): ?>
                            <option value="<?= $ae['id'] ?>" <?= ($selected_emp == $ae['id']) ? 'selected' : '' ?>>
                                <?= e($ae['employee_code']) ?> - <?= e($ae['fullname']) ?> (<?= e($ae['dept'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tháng:</span>
                    <select name="month" onchange="this.form.submit()"
                            class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>T<?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Năm:</span>
                    <select name="year" onchange="this.form.submit()"
                            class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($y = 2024; $y <= 2027; $y++): ?>
                            <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($personal_employee): ?>
            <!-- Thông tin nhân viên -->
            <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <?= render_avatar($personal_employee['fullname'], $personal_employee['avatar'] ?? null, 12) ?>
                <div>
                    <div class="font-bold text-slate-800 dark:text-white text-lg"><?= e($personal_employee['fullname']) ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        <span class="font-mono font-bold"><?= e($personal_employee['employee_code']) ?></span>
                        • <?= e($personal_employee['department_name'] ?? '') ?>
                        • <?= e($personal_employee['position_name'] ?? '') ?>
                    </div>
                </div>
            </div>

            <!-- Calendar Grid -->
            <?php
            $start_date_str = sprintf('%04d-%02d-01', $selected_year, $selected_month);
            $days_in_month = (int)date('t', strtotime($start_date_str));
            $first_day_of_week = (int)date('N', strtotime($start_date_str)); // 1=Mon, 7=Sun

            // Tính thống kê tháng
            $p_present = 0; $p_late = 0; $p_early = 0; $p_leave = 0; $p_absent = 0; $p_ot = 0;
            foreach ($personal_data as $pd) {
                if ($pd['status'] === 'present') $p_present++;
                elseif ($pd['status'] === 'late') $p_late++;
                elseif ($pd['status'] === 'early_leave') $p_early++;
                elseif ($pd['status'] === 'leave_with_permit') $p_leave++;
                elseif ($pd['status'] === 'absent') $p_absent++;
                $p_ot += (float)($pd['overtime_hours'] ?? 0);
            }
            $p_total = $p_present + $p_leave + (($p_late + $p_early) * 0.5);

            $status_colors = [
                'present'            => 'bg-emerald-500 text-white',
                'late'               => 'bg-amber-500 text-white',
                'early_leave'        => 'bg-orange-500 text-white',
                'leave_with_permit'  => 'bg-sky-500 text-white',
                'absent'             => 'bg-rose-500 text-white',
            ];
            $status_short = [
                'present'            => 'P',
                'late'               => 'M',
                'early_leave'        => 'S',
                'leave_with_permit'  => 'NP',
                'absent'             => 'V',
            ];
            ?>

            <!-- Thống kê mini -->
            <div class="grid grid-cols-3 sm:grid-cols-7 gap-3">
                <div class="bg-emerald-50 dark:bg-emerald-950/40 p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 text-center">
                    <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300"><?= $p_present ?></div>
                    <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Có Mặt</div>
                </div>
                <div class="bg-amber-50 dark:bg-amber-950/40 p-3 rounded-xl border border-amber-200 dark:border-amber-800 text-center">
                    <div class="text-lg font-bold text-amber-700 dark:text-amber-300"><?= $p_late ?></div>
                    <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400">Đi Muộn</div>
                </div>
                <div class="bg-orange-50 dark:bg-orange-950/40 p-3 rounded-xl border border-orange-200 dark:border-orange-800 text-center">
                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300"><?= $p_early ?></div>
                    <div class="text-[10px] font-bold text-orange-600 dark:text-orange-400">Về Sớm</div>
                </div>
                <div class="bg-sky-50 dark:bg-sky-950/40 p-3 rounded-xl border border-sky-200 dark:border-sky-800 text-center">
                    <div class="text-lg font-bold text-sky-700 dark:text-sky-300"><?= $p_leave ?></div>
                    <div class="text-[10px] font-bold text-sky-600 dark:text-sky-400">Nghỉ Phép</div>
                </div>
                <div class="bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-800 text-center">
                    <div class="text-lg font-bold text-rose-700 dark:text-rose-300"><?= $p_absent ?></div>
                    <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400">Vắng</div>
                </div>
                <div class="bg-violet-50 dark:bg-violet-950/40 p-3 rounded-xl border border-violet-200 dark:border-violet-800 text-center">
                    <div class="text-lg font-bold text-violet-700 dark:text-violet-300 font-mono"><?= number_format($p_ot, 1) ?></div>
                    <div class="text-[10px] font-bold text-violet-600 dark:text-violet-400">OT (h)</div>
                </div>
                <div class="bg-indigo-50 dark:bg-indigo-950/40 p-3 rounded-xl border border-indigo-200 dark:border-indigo-800 text-center">
                    <div class="text-lg font-bold text-indigo-700 dark:text-indigo-300 font-mono"><?= number_format($p_total, 1) ?></div>
                    <div class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">Tổng Công</div>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-emerald-600 dark:text-emerald-400"></i>
                    Lịch Chấm Công Tháng <?= $selected_month ?>/<?= $selected_year ?>
                </h3>

                <!-- Header ngày trong tuần -->
                <div class="grid grid-cols-7 gap-1.5 mb-2">
                    <?php foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $dayName): ?>
                        <div class="text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 py-1"><?= $dayName ?></div>
                    <?php endforeach; ?>
                </div>

                <!-- Grid ngày -->
                <div class="grid grid-cols-7 gap-1.5">
                    <?php
                    // Ô trống đầu tháng
                    for ($i = 1; $i < $first_day_of_week; $i++) {
                        echo '<div></div>';
                    }

                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $date_str = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
                        $dow = (int)date('N', strtotime($date_str));
                        $is_weekend = ($dow >= 6);
                        $att = $personal_data[$day] ?? null;
                        $status = $att ? $att['status'] : null;
                        $color_class = $status ? ($status_colors[$status] ?? 'bg-slate-200 text-slate-600') : ($is_weekend ? 'bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500' : 'bg-slate-50 dark:bg-slate-900 text-slate-400 dark:text-slate-500 border border-dashed border-slate-200 dark:border-slate-700');
                        $short = $status ? ($status_short[$status] ?? '?') : ($is_weekend ? '' : '—');
                        $tooltip = '';
                        if ($att) {
                            $tooltip = $status_short[$status] ?? '';
                            if ((int)($att['late_minutes'] ?? 0) > 0) $tooltip .= ' | Muộn ' . $att['late_minutes'] . '\'';
                            if ((float)($att['overtime_hours'] ?? 0) > 0) $tooltip .= ' | OT ' . number_format((float)$att['overtime_hours'], 1) . 'h';
                        }
                    ?>
                        <div class="aspect-square rounded-xl <?= $color_class ?> flex flex-col items-center justify-center cursor-default transition hover:scale-105 hover:shadow-md relative group" title="<?= e($tooltip) ?>">
                            <span class="text-[10px] font-medium <?= $status ? 'opacity-70' : '' ?>"><?= $day ?></span>
                            <?php if ($short): ?>
                                <span class="text-[10px] font-extrabold leading-none"><?= $short ?></span>
                            <?php endif; ?>
                            <?php if ($att && (float)($att['overtime_hours'] ?? 0) > 0): ?>
                                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-violet-600 text-white text-[7px] font-bold flex items-center justify-center shadow-sm">+</span>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
                </div>

                <!-- Chú thích -->
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 flex flex-wrap gap-3 text-[10px]">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500"></span> <strong>P</strong> = Có mặt</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> <strong>M</strong> = Đi muộn</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-500"></span> <strong>S</strong> = Về sớm</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-sky-500"></span> <strong>NP</strong> = Nghỉ phép</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-rose-500"></span> <strong>V</strong> = Vắng</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-violet-600"></span> <strong>+</strong> = Có OT</span>
                </div>
            </div>
        <?php elseif ($selected_emp <= 0): ?>
            <div class="bg-white dark:bg-slate-800 p-12 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-user-clock text-2xl text-slate-300 dark:text-slate-500"></i>
                </div>
                <div class="font-bold text-slate-500 dark:text-slate-400 text-sm">Chọn nhân viên để xem lịch sử chấm công</div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Sử dụng bộ lọc phía trên để chọn nhân viên cụ thể.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
// Hàm thao tác nhanh toàn bộ danh sách điểm danh
function setAllAttendance(status) {
    document.querySelectorAll('.att-status-select').forEach(select => {
        select.value = status;
    });
}

// Xuất Excel (CSV) bảng tổng hợp công tháng
function exportMonthlyCSV() {
    const table = document.getElementById('monthlySummaryTable');
    if (!table) return;
    
    let csv = '\uFEFF'; // BOM for UTF-8
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            let text = col.innerText.replace(/"/g, '""').trim();
            rowData.push('"' + text + '"');
        });
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'bang_tong_hop_cong_T<?= $selected_month ?>_<?= $selected_year ?>.csv';
    link.click();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>