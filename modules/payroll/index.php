<?php
// modules/payroll/index.php - Bảng tính lương tự động, phiếu lương chi tiết & quản lý thanh toán
$page_title = 'Bảng Tính Lương Cán Bộ & Nhân Viên';

require_once __DIR__ . '/../../core/auth.php';
require_permission('payroll', 'view');

$selected_month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_dept  = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. Tính toán hoặc Cập nhật Bảng lương Tự động cho tháng
    if ($action_type === 'calculate_payroll') {
        require_permission('payroll', 'create');
        $calc_month = (int)$_POST['calc_month'];
        $calc_year  = (int)$_POST['calc_year'];

        try {
            $pdo->beginTransaction();

            // Lấy tất cả nhân viên đang làm việc kèm lương chức vụ
            $empStmt = $pdo->query("
                SELECT e.id, p.base_salary 
                FROM employees e
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.employment_status != 'resigned'
            ");
            $employees = $empStmt->fetchAll();

            // Xác định khoảng ngày trong tháng để đếm công
            $start_date = sprintf('%04d-%02d-01', $calc_year, $calc_month);
            $end_date   = date('Y-m-t', strtotime($start_date));

            // Đếm ngày công từ bảng attendance
            $attStmt = $pdo->prepare("
                SELECT 
                    employee_id,
                    (COUNT(CASE WHEN status IN ('present', 'leave_with_permit') THEN 1 END) + 
                     COUNT(CASE WHEN status IN ('late', 'early_leave') THEN 1 END) * 0.5) AS total_work_days
                FROM attendance
                WHERE date BETWEEN ? AND ?
                GROUP BY employee_id
            ");
            $attStmt->execute([$start_date, $end_date]);
            $attendance_map = $attStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Kiểm tra các bản ghi lương hiện có để giữ lại phụ cấp/khấu trừ nếu đã sửa tay
            $existStmt = $pdo->prepare("SELECT employee_id, allowance, deduction, payment_status FROM payrolls WHERE month = ? AND year = ?");
            $existStmt->execute([$calc_month, $calc_year]);
            $existing_payrolls = [];
            foreach ($existStmt->fetchAll() as $row) {
                $existing_payrolls[$row['employee_id']] = $row;
            }

            $upsertStmt = $pdo->prepare("
                INSERT INTO payrolls (employee_id, month, year, basic_salary, work_days, allowance, deduction, final_salary, payment_status)
                VALUES (:emp_id, :month, :year, :basic_salary, :work_days, :allowance, :deduction, :final_salary, :payment_status)
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    work_days = VALUES(work_days),
                    allowance = VALUES(allowance),
                    deduction = VALUES(deduction),
                    final_salary = VALUES(final_salary),
                    payment_status = VALUES(payment_status)
            ");

            foreach ($employees as $emp) {
                $emp_id = (int)$emp['id'];
                $base_salary = (float)($emp['base_salary'] ?: 0);

                // Nếu có ghi nhận chấm công trong tháng thì lấy theo thực tế, nếu chưa có ngày nào được chấm thì mặc định 26 ngày công chuẩn
                $work_days = isset($attendance_map[$emp_id]) ? (float)$attendance_map[$emp_id] : 26.0;

                // Giữ lại phụ cấp / khấu trừ đã sửa trước đó
                $allowance = isset($existing_payrolls[$emp_id]) ? (float)$existing_payrolls[$emp_id]['allowance'] : 0.00;
                $deduction = isset($existing_payrolls[$emp_id]) ? (float)$existing_payrolls[$emp_id]['deduction'] : 0.00;
                $payment_status = isset($existing_payrolls[$emp_id]) ? $existing_payrolls[$emp_id]['payment_status'] : 'pending';

                // Công thức tính lương chuẩn: (Lương cơ bản / 26 ngày công chuẩn) * Ngày công + Phụ cấp - Giảm trừ
                $final_salary = max(0, round(($base_salary / 26) * $work_days + $allowance - $deduction));

                $upsertStmt->execute([
                    ':emp_id'         => $emp_id,
                    ':month'          => $calc_month,
                    ':year'           => $calc_year,
                    ':basic_salary'   => $base_salary,
                    ':work_days'      => $work_days,
                    ':allowance'      => $allowance,
                    ':deduction'      => $deduction,
                    ':final_salary'   => $final_salary,
                    ':payment_status' => $payment_status
                ]);
            }

            $pdo->commit();
            set_flash('success', "Đã tính toán và cập nhật thành công bảng lương cho Tháng {$calc_month}/{$calc_year}!");
            redirect("modules/payroll/index.php?month={$calc_month}&year={$calc_year}");
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi tính toán bảng lương: ' . $e->getMessage());
        }
    }

    if ($action_type === 'update_single_payroll') {
        require_permission('payroll', 'edit');
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $work_days  = (float)($_POST['work_days'] ?? 0);
        $allowance  = (float)str_replace(['.', ','], '', $_POST['allowance'] ?? '0');
        $deduction  = (float)str_replace(['.', ','], '', $_POST['deduction'] ?? '0');

        if ($payroll_id > 0) {
            // Lấy lương cơ bản của bản ghi
            $pStmt = $pdo->prepare("SELECT basic_salary FROM payrolls WHERE id = ?");
            $pStmt->execute([$payroll_id]);
            $base_salary = (float)$pStmt->fetchColumn();

            $final_salary = max(0, round(($base_salary / 26) * $work_days + $allowance - $deduction));

            $upStmt = $pdo->prepare("
                UPDATE payrolls 
                SET work_days = ?, allowance = ?, deduction = ?, final_salary = ? 
                WHERE id = ?
            ");
            $upStmt->execute([$work_days, $allowance, $deduction, $final_salary, $payroll_id]);
            set_flash('success', 'Đã điều chỉnh thành công thông số lương của nhân sự.');
            redirect("modules/payroll/index.php?month={$selected_month}&year={$selected_year}");
        }
    }

    if ($action_type === 'toggle_payment_status') {
        require_permission('payroll', 'edit');
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $status = $_POST['status'] === 'paid' ? 'paid' : 'pending';

        if ($payroll_id > 0) {
            $upStmt = $pdo->prepare("UPDATE payrolls SET payment_status = ? WHERE id = ?");
            $upStmt->execute([$status, $payroll_id]);
            set_flash('success', 'Đã cập nhật trạng thái thanh toán lương.');
            redirect("modules/payroll/index.php?month={$selected_month}&year={$selected_year}");
        }
    }

    if ($action_type === 'delete_monthly_payroll') {
        require_permission('payroll', 'delete');
        $del_m = (int)$_POST['month'];
        $del_y = (int)$_POST['year'];

        $delStmt = $pdo->prepare("DELETE FROM payrolls WHERE month = ? AND year = ?");
        $delStmt->execute([$del_m, $del_y]);
        set_flash('success', "Đã xóa toàn bộ bảng tính lương Tháng {$del_m}/{$del_y}.");
        redirect("modules/payroll/index.php?month={$del_m}&year={$del_y}");
    }
}

// Danh sách phòng ban phục vụ bộ lọc
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Lấy danh sách bảng lương của tháng
$where_clauses = ["pr.month = ?", "pr.year = ?"];
$params = [$selected_month, $selected_year];

if ($selected_dept > 0) {
    $where_clauses[] = "e.department_id = ?";
    $params[] = $selected_dept;
}

$where_sql = implode(' AND ', $where_clauses);

$query_sql = "
    SELECT pr.*, 
           e.fullname, e.employee_code, e.avatar, e.bank_account,
           d.name AS department_name, d.code AS department_code,
           p.name AS position_name
    FROM payrolls pr
    JOIN employees e ON pr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE {$where_sql}
    ORDER BY pr.id ASC
";
$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$payroll_items = $stmt->fetchAll();

// Thống kê tổng hợp quỹ lương
$total_fund = 0;
$total_paid = 0;
$total_pending = 0;
$count_paid = 0;

foreach ($payroll_items as $item) {
    $total_fund += $item['final_salary'];
    if ($item['payment_status'] === 'paid') {
        $total_paid += $item['final_salary'];
        $count_paid++;
    } else {
        $total_pending += $item['final_salary'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Nút Tính Lương -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 mb-2">
                <i class="fa-solid fa-wallet"></i> Phân Hệ Tiền Lương & Quyết Toán
            </div>
            <h2 class="text-xl font-bold text-slate-800">Bảng Tính Lương Tháng <?= $selected_month ?>/<?= $selected_year ?></h2>
            <p class="text-sm text-slate-500 mt-0.5">Tự động tổng hợp dữ liệu ngày công, mức lương định ngạch, phụ cấp và các khoản trích nộp theo quy chế.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (has_permission('payroll', 'create')): ?>
                <form action="index.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="calculate_payroll">
                    <input type="hidden" name="calc_month" value="<?= $selected_month ?>">
                    <input type="hidden" name="calc_year" value="<?= $selected_year ?>">
                    <button type="submit" 
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-amber-100 transition flex items-center gap-2">
                        <i class="fa-solid fa-calculator"></i>
                        <span>Tính Lương Tháng Này</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($payroll_items) && has_permission('payroll', 'delete')): ?>
                <button onclick="confirmDeletePayroll(<?= $selected_month ?>, <?= $selected_year ?>)" 
                        class="px-3.5 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-sm font-semibold border border-rose-200 transition" 
                        title="Xóa bảng lương tháng này">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Thống Kê Quỹ Lương -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tổng Quỹ Lương Tháng</span>
                <div class="text-xl font-bold text-slate-800 font-mono mt-1"><?= format_money($total_fund) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5"><?= count($payroll_items) ?> nhân sự trong danh sách</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Đã Thanh Toán</span>
                <div class="text-xl font-bold text-emerald-600 font-mono mt-1"><?= format_money($total_paid) ?></div>
                <div class="text-[11px] text-emerald-600 mt-0.5"><?= $count_paid ?> / <?= count($payroll_items) ?> đã nhận lương</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-500">Chưa Chi Trả</span>
                <div class="text-xl font-bold text-rose-600 font-mono mt-1"><?= format_money($total_pending) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5"><?= (count($payroll_items) - $count_paid) ?> nhân sự đang chờ</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Tỷ Lệ Hoàn Tất</span>
                <div class="text-xl font-bold text-slate-800 font-mono mt-1">
                    <?= count($payroll_items) > 0 ? round(($count_paid / count($payroll_items)) * 100) : 0 ?>%
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Tiến độ chi lương</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
        </div>
    </div>

    <!-- Thanh Lọc Kỳ Lương & Đơn Vị -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600">Tháng:</span>
                <select name="month" onchange="this.form.submit()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 outline-none">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600">Năm:</span>
                <select name="year" onchange="this.form.submit()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 outline-none">
                    <?php for ($y = 2024; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600">Phòng Ban:</span>
                <select name="department_id" onchange="this.form.submit()"
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 outline-none">
                    <option value="0">-- Toàn bộ công ty --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($selected_dept == $d['id']) ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="text-xs text-slate-400">
            Công thức: <span class="font-mono text-slate-600">(Lương cứng / 26) × Ngày công + Phụ cấp - Khấu trừ</span>
        </div>
    </div>

    <!-- Bảng Danh Sách Lương Chi Tiết -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-6">Cán Bộ / Nhân Viên</th>
                        <th class="py-3.5 px-4 text-right">Lương Cơ Bản</th>
                        <th class="py-3.5 px-4 text-center">Ngày Công</th>
                        <th class="py-3.5 px-4 text-right">Phụ Cấp</th>
                        <th class="py-3.5 px-4 text-right">Khấu Trừ</th>
                        <th class="py-3.5 px-6 text-right bg-emerald-50/50 text-emerald-800">Thực Lĩnh (VNĐ)</th>
                        <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                        <th class="py-3.5 px-6 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($payroll_items)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400 text-sm">
                                <i class="fa-solid fa-calculator text-3xl mb-2 block text-slate-300"></i>
                                Bảng lương Tháng <?= $selected_month ?>/<?= $selected_year ?> chưa được khởi tạo. 
                                <?php if (has_permission('payroll', 'create')): ?>
                                    <br>Hãy nhấn nút <strong class="text-amber-600">"Tính Lương Tháng Này"</strong> ở trên để tạo bảng tính tự động.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payroll_items as $item): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Nhân viên -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($item['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $item['avatar'])): ?>
                                        <img src="<?= base_url('assets/uploads/' . e($item['avatar'])) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200" alt="">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xs">
                                            <?= strtoupper(mb_substr($item['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-bold text-slate-800 text-sm"><?= e($item['fullname']) ?></div>
                                        <div class="text-[11px] text-slate-400 font-mono">
                                            <?= e($item['employee_code']) ?> • <?= e($item['position_name'] ?? 'Chức danh') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Lương cơ bản -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-slate-600">
                                <?= format_money($item['basic_salary']) ?>
                            </td>

                            <!-- Ngày công -->
                            <td class="py-4 px-4 text-center font-mono font-semibold text-xs text-indigo-600">
                                <?= number_format($item['work_days'], 1) ?>
                            </td>

                            <!-- Phụ cấp -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-emerald-600">
                                <?= format_money($item['allowance']) ?>
                            </td>

                            <!-- Giảm trừ -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-rose-500">
                                <?= format_money($item['deduction']) ?>
                            </td>

                            <!-- Thực lĩnh -->
                            <td class="py-4 px-6 text-right font-mono font-bold text-sm text-emerald-700 bg-emerald-50/40">
                                <?= format_money($item['final_salary']) ?>
                            </td>

                            <!-- Trạng thái chi trả -->
                            <td class="py-4 px-4 text-center">
                                <?php if ($item['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check text-[9px] mr-1"></i> Đã Trả
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock text-[9px] mr-1"></i> Chờ Trả
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Nút Thao tác -->
                            <td class="py-4 px-6 text-right space-x-1">
                                <!-- Nút xem phiếu lương (Payslip) -->
                                <button onclick='viewPayslip(<?= json_encode($item) ?>)' 
                                        class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-500 inline-flex items-center justify-center transition"
                                        title="Xem & In Phiếu Lương">
                                    <i class="fa-solid fa-receipt text-xs"></i>
                                </button>

                                <?php if (has_permission('payroll', 'edit')): ?>
                                    <!-- Nút Sửa phụ cấp / giảm trừ -->
                                    <button onclick='openEditPayrollModal(<?= json_encode($item) ?>)' 
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-500 inline-flex items-center justify-center transition"
                                            title="Điều chỉnh số liệu">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>

                                    <!-- Nút Đổi trạng thái thanh toán -->
                                    <form action="index.php?month=<?= $selected_month ?>&year=<?= $selected_year ?>" method="POST" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="toggle_payment_status">
                                        <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $item['payment_status'] === 'paid' ? 'pending' : 'paid' ?>">
                                        <button type="submit" 
                                                class="w-8 h-8 rounded-lg <?= $item['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-600 hover:bg-rose-50 hover:text-rose-600' : 'bg-slate-100 text-slate-500 hover:bg-emerald-50 hover:text-emerald-600' ?> inline-flex items-center justify-center transition"
                                                title="<?= $item['payment_status'] === 'paid' ? 'Đổi về Chưa thanh toán' : 'Đánh dấu Đã thanh toán' ?>">
                                            <i class="fa-solid <?= $item['payment_status'] === 'paid' ? 'fa-money-bill-check' : 'fa-hand-holding-dollar' ?> text-xs"></i>
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

<!-- Modal Chỉnh Sửa Phụ Cấp & Khấu Trừ -->
<div id="editPayrollModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base">Điều Chỉnh Lương Cá Nhân</h3>
            <button onclick="document.getElementById('editPayrollModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="index.php?month=<?= $selected_month ?>&year=<?= $selected_year ?>" method="POST" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="update_single_payroll">
            <input type="hidden" name="payroll_id" id="editPayrollId" value="">

            <div>
                <span class="text-xs text-slate-400 block mb-1">Cán Bộ / Nhân Viên:</span>
                <div id="editPayrollEmpName" class="font-bold text-slate-800 text-sm">---</div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Số Ngày Công Thực Tế *</label>
                <input type="number" name="work_days" id="editWorkDays" step="0.5" min="0" max="31" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Khoản Phụ Cấp (Ăn trưa, trách nhiệm, thưởng...) VNĐ</label>
                <input type="number" name="allowance" id="editAllowance" step="10000" min="0" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Khoản Giảm Trừ (Tạm ứng, phạt, BHXH...) VNĐ</label>
                <input type="number" name="deduction" id="editDeduction" step="10000" min="0" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editPayrollModal').classList.add('hidden')"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu Điều Chỉnh
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xem Phiếu Lương Chi Tiết & Hỗ Trợ In (Print Payslip) -->
<div id="payslipModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative">
        <button onclick="document.getElementById('payslipModal').classList.add('hidden')" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 text-xl print:hidden">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="printablePayslip" class="space-y-6">
            <!-- Header phiếu lương -->
            <div class="text-center pb-4 border-b border-slate-200">
                <div class="text-xs uppercase font-bold text-indigo-600 tracking-wider">HỆ THỐNG DOANH NGHIỆP NỘI BỘ HRMS</div>
                <h3 class="text-xl font-bold text-slate-800 mt-1">PHIẾU LƯƠNG CÁ NHÂN</h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Kỳ chi trả: Tháng <span id="slipMonth"></span>/<span id="slipYear"></span></p>
            </div>

            <!-- Thông tin nhân sự -->
            <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div><span class="text-slate-400">Họ và tên:</span> <strong id="slipName" class="text-slate-800 block text-sm"></strong></div>
                <div><span class="text-slate-400">Mã nhân viên:</span> <span id="slipCode" class="font-mono font-bold text-indigo-600 block text-sm"></span></div>
                <div><span class="text-slate-400">Phòng ban:</span> <span id="slipDept" class="text-slate-700 block"></span></div>
                <div><span class="text-slate-400">Chức vụ:</span> <span id="slipPos" class="text-slate-700 block"></span></div>
            </div>

            <!-- Bảng kê các khoản -->
            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">1. Mức lương cơ bản ngạch bậc:</span>
                    <span id="slipBaseSalary" class="font-mono font-semibold text-slate-800"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">2. Số ngày công ghi nhận thực tế:</span>
                    <span id="slipWorkDays" class="font-mono font-semibold text-indigo-600"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">3. Phụ cấp trách nhiệm & ăn trưa (+):</span>
                    <span id="slipAllowance" class="font-mono font-semibold text-emerald-600"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">4. Các khoản giảm trừ & tạm ứng (-):</span>
                    <span id="slipDeduction" class="font-mono font-semibold text-rose-500"></span>
                </div>
                <div class="flex justify-between py-3 bg-emerald-50 px-4 rounded-xl text-sm font-bold text-emerald-900">
                    <span>5. SỐ TIỀN THỰC LĨNH:</span>
                    <span id="slipFinalSalary" class="font-mono text-base text-emerald-700"></span>
                </div>
            </div>

            <!-- Ký nhận -->
            <div class="grid grid-cols-2 text-center text-xs pt-4 text-slate-500">
                <div>
                    <div class="font-semibold text-slate-700 mb-12">Người Nhận Tiền</div>
                    <div>(Ký và ghi rõ họ tên)</div>
                </div>
                <div>
                    <div class="font-semibold text-slate-700 mb-12">Thủ Trưởng Phê Duyệt</div>
                    <div>(Đã duyệt chi lương)</div>
                </div>
            </div>
        </div>

        <!-- Nút In -->
        <div class="flex items-center justify-end gap-2 pt-6 border-t border-slate-100 mt-6 print:hidden">
            <button type="button" onclick="document.getElementById('payslipModal').classList.add('hidden')" 
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                Đóng
            </button>
            <button type="button" onclick="window.print()" 
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i>
                <span>In Phiếu Lương</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Xác Nhận Xóa Bảng Lương Tháng -->
<div id="deletePayrollModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xóa Toàn Bộ Bảng Lương?</h3>
        <p id="delPayrollMsg" class="text-xs text-slate-500 mb-6"></p>
        
        <form action="index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_monthly_payroll">
            <input type="hidden" name="month" id="delPayrollMonth" value="">
            <input type="hidden" name="year" id="delPayrollYear" value="">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('deletePayrollModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Không, Hủy
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Xác Nhận Xóa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Định dạng tiền tệ JS
function formatVND(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Mở Modal Điều Chỉnh Lương
function openEditPayrollModal(item) {
    document.getElementById('editPayrollId').value = item.id;
    document.getElementById('editPayrollEmpName').innerText = `${item.fullname} (${item.employee_code})`;
    document.getElementById('editWorkDays').value = item.work_days;
    document.getElementById('editAllowance').value = item.allowance;
    document.getElementById('editDeduction').value = item.deduction;
    document.getElementById('editPayrollModal').classList.remove('hidden');
}

// Mở Modal Xem & In Phiếu Lương
function viewPayslip(item) {
    document.getElementById('slipMonth').innerText = item.month;
    document.getElementById('slipYear').innerText = item.year;
    document.getElementById('slipName').innerText = item.fullname;
    document.getElementById('slipCode').innerText = item.employee_code;
    document.getElementById('slipDept').innerText = item.department_name || 'Chưa gán';
    document.getElementById('slipPos').innerText = item.position_name || 'Chưa gán';
    document.getElementById('slipBaseSalary').innerText = formatVND(item.basic_salary);
    document.getElementById('slipWorkDays').innerText = item.work_days + ' ngày';
    document.getElementById('slipAllowance').innerText = '+ ' + formatVND(item.allowance);
    document.getElementById('slipDeduction').innerText = '- ' + formatVND(item.deduction);
    document.getElementById('slipFinalSalary').innerText = formatVND(item.final_salary);
    document.getElementById('payslipModal').classList.remove('hidden');
}

// Modal Xác nhận xóa bảng lương tháng
function confirmDeletePayroll(month, year) {
    document.getElementById('delPayrollMonth').value = month;
    document.getElementById('delPayrollYear').value = year;
    document.getElementById('delPayrollMsg').innerText = `Bạn có chắc chắn muốn xóa bảng tính lương Tháng ${month}/${year}? Thao tác này sẽ xóa toàn bộ số liệu của tháng này.`;
    document.getElementById('deletePayrollModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>