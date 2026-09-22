<?php
// modules/attendance/index.php - Phân hệ Chấm công hàng ngày và Bảng tổng hợp công tháng
$page_title = 'Quản Lý & Chấm Công Nhân Sự';

require_once __DIR__ . '/../../core/auth.php';
require_permission('attendance', 'view');

// Lấy tham số tab hiển thị (daily = Chấm công ngày, monthly = Tổng hợp tháng)
$active_tab = $_GET['tab'] ?? 'daily';
$selected_date = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Lấy tham số tháng/năm cho bảng tổng hợp
$selected_month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_dept = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    // 1. Lưu bảng điểm danh hàng loạt theo ngày
    if ($action_type === 'save_daily_attendance') {
        require_permission('attendance', 'create');
        $att_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $records = $_POST['att'] ?? []; // Mảng [emp_id => ['status', 'check_in', 'check_out', 'note']]

        try {
            $pdo->beginTransaction();

            $upsertStmt = $pdo->prepare("
                INSERT INTO attendance (employee_id, date, check_in, check_out, status, note)
                VALUES (:emp_id, :att_date, :check_in, :check_out, :status, :note)
                ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in),
                    check_out = VALUES(check_out),
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

                $upsertStmt->execute([
                    ':emp_id'    => (int)$emp_id,
                    ':att_date'  => $att_date,
                    ':check_in'  => $check_in,
                    ':check_out' => $check_out,
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

    foreach ($active_employees as $emp) {
        $st = $daily_attendance[$emp['id']]['status'] ?? 'not_checked';
        if ($st === 'present') $stat_present++;
        elseif ($st === 'late' || $st === 'early_leave') $stat_late++;
        elseif ($st === 'leave_with_permit') $stat_leave++;
        elseif ($st === 'absent') $stat_absent++;
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
            COUNT(CASE WHEN status = 'absent' THEN 1 END) AS count_absent
        FROM attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY employee_id
    ";
    $sumStmt = $pdo->prepare($summary_sql);
    $sumStmt->execute([$start_date, $end_date]);
    $sumRows = $sumStmt->fetchAll();

    foreach ($sumRows as $row) {
        // Công thức tính công tháng: Đi làm đủ (1) + Nghỉ phép (1) + Đi muộn/Về sớm (0.5)
        $total_work_days = $row['count_present'] + $row['count_leave'] + (($row['count_late'] + $row['count_early']) * 0.5);
        $row['total_work_days'] = $total_work_days;
        $monthly_summary[$row['employee_id']] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu đề & Giới thiệu -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 mb-2">
                <i class="fa-solid fa-calendar-check"></i> Quản Lý Công Tác & Điểm Danh
            </div>
            <h2 class="text-xl font-bold text-slate-800">Chấm Công & Bảng Tổng Hợp Ngày Công</h2>
            <p class="text-sm text-slate-500 mt-0.5">Theo dõi lịch trình có mặt, đi muộn, nghỉ phép và xuất dữ liệu công tổng hợp làm căn cứ tính lương.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($active_tab === 'daily' && has_permission('attendance', 'create')): ?>
                <button type="submit" form="dailyAttendanceForm" 
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-emerald-100 transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu Bảng Điểm Danh</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thanh Tabs Chuyển đổi chế độ xem -->
    <div class="flex border-b border-slate-200">
        <a href="?tab=daily&date=<?= urlencode($selected_date) ?>&department_id=<?= $selected_dept ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'daily' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-clipboard-user"></i>
            <span>1. Điểm Danh Hàng Ngày (Theo Ngày)</span>
        </a>
        <a href="?tab=monthly&month=<?= $selected_month ?>&year=<?= $selected_year ?>&department_id=<?= $selected_dept ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'monthly' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>2. Bảng Tổng Hợp Công Tháng</span>
        </a>
    </div>

    <?php if ($active_tab === 'daily'): ?>
        <!-- ================= TAB 1: ĐIỂM DANH HÀNG NGÀY ================= -->

        <!-- 4 Khối Thống kê Trực quan Trong Ngày -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Có Mặt Đầy Đủ</span>
                    <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $stat_present ?> <span class="text-xs font-normal text-slate-400">/ <?= count($active_employees) ?></span></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Đi Muộn / Về Sớm</span>
                    <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $stat_late ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-sky-600">Nghỉ Có Phép</span>
                    <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $stat_leave ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-500">Vắng Không Phép</span>
                    <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $stat_absent ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
        </div>

        <!-- Bộ lọc ngày và phòng ban -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="hidden" name="tab" value="daily">
                
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 whitespace-nowrap">Chọn Ngày:</span>
                    <input type="date" name="date" value="<?= e($selected_date) ?>" onchange="this.form.submit()"
                           class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 whitespace-nowrap">Phòng Ban:</span>
                    <select name="department_id" onchange="this.form.submit()"
                            class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white">
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
                            class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-check-double text-[10px]"></i>
                        <span>Tất Cả Đi Làm</span>
                    </button>
                    <button type="button" onclick="setAllAttendance('absent')" 
                            class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                        <span>Đặt Nghỉ Hết</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bảng Nhập Liệu Điểm Danh Ngày -->
        <form id="dailyAttendanceForm" action="index.php" method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <input type="hidden" name="action_type" value="save_daily_attendance">
            <input type="hidden" name="attendance_date" value="<?= e($selected_date) ?>">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Nhân Viên</th>
                            <th class="py-3.5 px-6">Trạng Thái Điểm Danh</th>
                            <th class="py-3.5 px-4 text-center">Giờ Vào (Check-in)</th>
                            <th class="py-3.5 px-4 text-center">Giờ Ra (Check-out)</th>
                            <th class="py-3.5 px-6">Ghi Chú Công Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($active_employees)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-12 text-slate-400 text-sm">
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
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <!-- Nhân viên -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $emp['avatar'])): ?>
                                            <img src="<?= base_url('assets/uploads/' . e($emp['avatar'])) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200" alt="">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                                                <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-bold text-slate-800 text-xs sm:text-sm"><?= e($emp['fullname']) ?></div>
                                            <div class="text-[11px] text-slate-400 font-mono">
                                                <?= e($emp['employee_code']) ?> • <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Lựa chọn trạng thái -->
                                <td class="py-4 px-6">
                                    <select name="att[<?= $emp['id'] ?>][status]" 
                                            class="att-status-select text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none w-48 transition">
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
                                </td>

                                <!-- Giờ vào -->
                                <td class="py-4 px-4 text-center">
                                    <input type="time" name="att[<?= $emp['id'] ?>][check_in]" value="<?= e($cur_in) ?>"
                                           class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>

                                <!-- Giờ ra -->
                                <td class="py-4 px-4 text-center">
                                    <input type="time" name="att[<?= $emp['id'] ?>][check_out]" value="<?= e($cur_out) ?>"
                                           class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>

                                <!-- Ghi chú -->
                                <td class="py-4 px-6">
                                    <input type="text" name="att[<?= $emp['id'] ?>][note]" value="<?= e($cur_note) ?>" placeholder="Lý do đi muộn, công tác ngoài..."
                                           class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Nút Lưu Footer -->
            <?php if (has_permission('attendance', 'create')): ?>
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Nhớ nhấn <strong class="text-slate-700">"Lưu Bảng Điểm Danh"</strong> để ghi nhận số liệu ngày công vào hệ thống.
                    </span>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Lưu Bảng Điểm Danh Ngày <?= format_date($selected_date) ?></span>
                    </button>
                </div>
            <?php endif; ?>
        </form>

    <?php else: ?>
        <!-- ================= TAB 2: BẢNG TỔNG HỢP CÔNG THÁNG ================= -->

        <!-- Khung Lọc Tháng & Năm -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="hidden" name="tab" value="monthly">

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600">Tháng:</span>
                    <select name="month" onchange="this.form.submit()" 
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600">Năm:</span>
                    <select name="year" onchange="this.form.submit()" 
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php for ($y = 2024; $y <= 2027; $y++): ?>
                            <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600">Phòng Ban:</span>
                    <select name="department_id" onchange="this.form.submit()"
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none">
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
                <?php if (has_permission('payroll', 'create')): ?>
                    <a href="<?= base_url('modules/payroll/index.php?month=' . $selected_month . '&year=' . $selected_year) ?>" 
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-calculator text-xs"></i>
                        <span>Chuyển Sang Tính Lương Tháng <?= $selected_month ?>/<?= $selected_year ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bảng Tổng Hợp Công Chi Tiết -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Nhân Viên & Đơn Vị</th>
                            <th class="py-3.5 px-4 text-center">Đủ Công (Ngày)</th>
                            <th class="py-3.5 px-4 text-center">Nghỉ Có Phép</th>
                            <th class="py-3.5 px-4 text-center">Đi Muộn / Sớm</th>
                            <th class="py-3.5 px-4 text-center">Không Phép</th>
                            <th class="py-3.5 px-6 text-center bg-emerald-50/50 text-emerald-800">Tổng Ngày Công Quy Đổi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($active_employees as $emp): ?>
                            <?php 
                            $m = $monthly_summary[$emp['id']] ?? [
                                'count_present' => 0,
                                'count_leave' => 0,
                                'count_late' => 0,
                                'count_early' => 0,
                                'count_absent' => 0,
                                'total_work_days' => 0
                            ];
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-slate-800 text-sm"><?= e($emp['fullname']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        Mã: <?= e($emp['employee_code']) ?> • <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                    </div>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-emerald-600">
                                    <?= $m['count_present'] ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-sky-600">
                                    <?= $m['count_leave'] ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-amber-600">
                                    <?= ($m['count_late'] + $m['count_early']) ?>
                                </td>

                                <td class="py-4 px-4 text-center font-semibold text-rose-500">
                                    <?= $m['count_absent'] ?>
                                </td>

                                <td class="py-4 px-6 text-center bg-emerald-50/40">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 font-mono">
                                        <?= number_format($m['total_work_days'], 1) ?> công
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500">
                * Quy chuẩn tính công: 1 ngày Có mặt = 1.0 công; 1 ngày Nghỉ có phép = 1.0 công; 1 lần Đi muộn / Về sớm = 0.5 công; Vắng không phép = 0 công.
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
// Hàm thao tác nhanh toàn bộ danh sách điểm danh
function setAllAttendance(status) {
    document.querySelectorAll('.att-status-select').forEach(select => {
        select.value = status;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>