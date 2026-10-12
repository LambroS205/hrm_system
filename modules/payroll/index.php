<?php
// modules/payroll/index.php - Bảng tính lương tự động, phiếu lương chi tiết & quản lý thanh toán theo Luật Việt Nam
$page_title = 'Bảng Tính Lương Cán Bộ & Nhân Viên';

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/PayrollService.php';
require_permission('payroll', 'view');

$selected_month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_dept  = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

$current_user = current_user();

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
            $res = PayrollService::calculateMonth($pdo, $calc_month, $calc_year, null, $current_user['id']);
            $pdo->commit();

            $msg = "Đã tính toán thành công bảng lương cho {$res['calculated_count']} nhân sự Tháng {$calc_month}/{$calc_year} (Công chuẩn: {$res['standard_days']} ngày, Lễ: {$res['holiday_count']} ngày).";
            if ($res['skipped_locked_count'] > 0) {
                $msg .= " Đã bỏ qua {$res['skipped_locked_count']} bản ghi do đã chốt hoặc thanh toán.";
            }
            set_flash('success', $msg);
            redirect("modules/payroll/index.php?month={$calc_month}&year={$calc_year}");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('danger', 'Lỗi tính toán bảng lương: ' . $e->getMessage());
        }
    }

    // 2. Chốt & Khóa bảng lương
    if ($action_type === 'lock_monthly_payroll') {
        require_permission('payroll', 'edit');
        $l_month = (int)$_POST['month'];
        $l_year  = (int)$_POST['year'];

        try {
            PayrollService::lockMonth($pdo, $l_month, $l_year, $current_user['id']);
            set_flash('success', "Đã chốt và khóa thành công bảng tính lương Tháng {$l_month}/{$l_year}. Các số liệu chấm công và ngày công của tháng này đã được bảo vệ.");
            redirect("modules/payroll/index.php?month={$l_month}&year={$l_year}");
        } catch (Exception $e) {
            set_flash('danger', 'Lỗi khi khóa bảng lương: ' . $e->getMessage());
        }
    }

    // 3. Mở khóa bảng lương
    if ($action_type === 'unlock_monthly_payroll') {
        require_permission('payroll', 'edit');
        $u_month = (int)$_POST['month'];
        $u_year  = (int)$_POST['year'];

        try {
            PayrollService::unlockMonth($pdo, $u_month, $u_year);
            set_flash('success', "Đã mở khóa bảng tính lương Tháng {$u_month}/{$u_year} để cho phép cập nhật lại số liệu.");
            redirect("modules/payroll/index.php?month={$u_month}&year={$u_year}");
        } catch (Exception $e) {
            set_flash('danger', 'Không thể mở khóa: ' . $e->getMessage());
        }
    }

    // 4. Cập nhật số liệu điều chỉnh của 1 cá nhân
    if ($action_type === 'update_single_payroll') {
        require_permission('payroll', 'edit');
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $work_days  = (float)($_POST['work_days'] ?? 0);
        
        // SỬA BUG A3: Parse chuẩn xác định dạng số tiền, không làm gấp 100 lần số tiền
        $rawAllowance = $_POST['allowance'] ?? '0';
        $rawDeduction = $_POST['deduction'] ?? '0';
        $allowance  = (float)preg_replace('/[^\d.]/', '', str_replace(',', '', $rawAllowance));
        $deduction  = (float)preg_replace('/[^\d.]/', '', str_replace(',', '', $rawDeduction));

        if ($payroll_id > 0) {
            try {
                $pdo->beginTransaction();
                PayrollService::recalculateOne($pdo, $payroll_id, [
                    'work_days' => $work_days,
                    'allowance' => $allowance,
                    'deduction' => $deduction
                ]);
                $pdo->commit();
                set_flash('success', 'Đã điều chỉnh và tính toán lại toàn bộ thông số lương, bảo hiểm & thuế của nhân sự.');
                redirect("modules/payroll/index.php?month={$selected_month}&year={$selected_year}");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Lỗi cập nhật: ' . $e->getMessage());
            }
        }
    }

    // 5. Đổi trạng thái thanh toán (pending <-> paid)
    if ($action_type === 'toggle_payment_status') {
        require_permission('payroll', 'edit');
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $status = $_POST['status'] === 'paid' ? 'paid' : 'pending';

        if ($payroll_id > 0) {
            $upStmt = $pdo->prepare("
                UPDATE payrolls 
                SET payment_status = ?,
                    locked_at = CASE WHEN ? = 'paid' AND locked_at IS NULL THEN NOW() ELSE locked_at END
                WHERE id = ? AND month = ? AND year = ?
            ");
            $upStmt->execute([$status, $status, $payroll_id, $selected_month, $selected_year]);
            set_flash('success', 'Đã cập nhật trạng thái chi trả lương.');
            redirect("modules/payroll/index.php?month={$selected_month}&year={$selected_year}");
        }
    }

    // 6. Xóa bảng lương nháp của tháng
    if ($action_type === 'delete_monthly_payroll') {
        require_permission('payroll', 'delete');
        $del_m = (int)$_POST['month'];
        $del_y = (int)$_POST['year'];

        // Kiểm tra xem có bản ghi đã khóa hoặc đã thanh toán hay không
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM payrolls 
            WHERE month = ? AND year = ? AND (locked_at IS NOT NULL OR payment_status = 'paid')
        ");
        $checkStmt->execute([$del_m, $del_y]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            set_flash('danger', 'Không thể xóa bảng lương đã chốt số liệu hoặc đã chi trả.');
            redirect("modules/payroll/index.php?month={$del_m}&year={$del_y}");
        }

        $delStmt = $pdo->prepare("DELETE FROM payrolls WHERE month = ? AND year = ?");
        $delStmt->execute([$del_m, $del_y]);
        set_flash('success', "Đã xóa toàn bộ bảng tính lương nháp Tháng {$del_m}/{$del_y}.");
        redirect("modules/payroll/index.php?month={$del_m}&year={$del_y}");
    }
}

// Lấy danh sách phòng ban phục vụ bộ lọc
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
           e.fullname, e.employee_code, e.avatar, e.bank_account, e.employment_status,
           d.name AS department_name, d.code AS department_code,
           p.name AS position_name,
           b.name AS branch_name, b.wage_region
    FROM payrolls pr
    JOIN employees e ON pr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE {$where_sql}
    ORDER BY pr.id ASC
";
$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$payroll_items = $stmt->fetchAll();

// Thống kê tổng hợp quỹ lương & Thuế/Bảo hiểm
$total_gross = 0;
$total_net = 0;
$total_paid = 0;
$total_pending = 0;
$count_paid = 0;
$total_insurance_emp = 0;
$total_insurance_empr = 0;
$total_pit = 0;
$is_month_locked = false;

foreach ($payroll_items as $item) {
    $gross = (float)($item['gross_salary'] ?: $item['final_salary']);
    $net = (float)$item['final_salary'];

    $total_gross += $gross;
    $total_net   += $net;
    $total_insurance_emp += (float)($item['bhxh_employee'] + $item['bhyt_employee'] + $item['bhtn_employee']);
    $total_insurance_empr += (float)$item['employer_insurance'];
    $total_pit += (float)$item['pit_amount'];

    if ($item['payment_status'] === 'paid') {
        $total_paid += $net;
        $count_paid++;
    } else {
        $total_pending += $net;
    }

    if (!empty($item['locked_at'])) {
        $is_month_locked = true;
    }
}

// Lấy thông số quy tắc pháp lý của kỳ
$current_rules = payroll_rules_for($selected_year, $selected_month);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Hành Động -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                    <i class="fa-solid fa-wallet"></i> Tiền Lương & Thuế Việt Nam
                </span>
                <?php if ($is_month_locked): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        <i class="fa-solid fa-lock"></i> Đã Chốt Sổ Lương
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                        <i class="fa-solid fa-pen-ruler"></i> Bảng Lương Dự Thảo
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white">Bảng Tính Lương Tháng <?= $selected_month ?>/<?= $selected_year ?></h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Tuân thủ: Thuế TNCN lũy tiến 5 bậc, Mức giảm trừ 15.5tr / 6.2tr (NQ 110/2025), BHXH 10.5% (Luật BHXH 2024), Lương tối thiểu NĐ 293/2025/NĐ-CP.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="<?= base_url('modules/payroll/holidays.php?year=' . $selected_year) ?>" 
               class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition flex items-center gap-2"
               title="Xem và quản lý các ngày lễ trong năm">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span>Ngày Lễ Trong Năm</span>
            </a>

            <?php if (has_permission('payroll', 'create') && !$is_month_locked): ?>
                <form action="index.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="calculate_payroll">
                    <input type="hidden" name="calc_month" value="<?= $selected_month ?>">
                    <input type="hidden" name="calc_year" value="<?= $selected_year ?>">
                    <button type="submit" 
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-amber-100 dark:shadow-none transition flex items-center gap-2">
                        <i class="fa-solid fa-calculator"></i>
                        <span>Tính Lương Toàn Bộ</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($payroll_items) && has_permission('payroll', 'edit')): ?>
                <?php if (!$is_month_locked): ?>
                    <form action="index.php?month=<?= $selected_month ?>&year=<?= $selected_year ?>" method="POST" onsubmit="return confirm('Bạn có chắc muốn chốt và khóa bảng lương Tháng <?= $selected_month ?>/<?= $selected_year ?>? Thao tác này sẽ khóa chấm công và ngăn tính lại.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action_type" value="lock_monthly_payroll">
                        <input type="hidden" name="month" value="<?= $selected_month ?>">
                        <input type="hidden" name="year" value="<?= $selected_year ?>">
                        <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                            <i class="fa-solid fa-lock"></i>
                            <span>Chốt Lương</span>
                        </button>
                    </form>
                <?php elseif ($count_paid === 0): ?>
                    <form action="index.php?month=<?= $selected_month ?>&year=<?= $selected_year ?>" method="POST" onsubmit="return confirm('Mở khóa bảng lương để điều chỉnh lại?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action_type" value="unlock_monthly_payroll">
                        <input type="hidden" name="month" value="<?= $selected_month ?>">
                        <input type="hidden" name="year" value="<?= $selected_year ?>">
                        <button type="submit" class="px-4 py-2.5 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                            <i class="fa-solid fa-lock-open"></i>
                            <span>Mở Khóa</span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($payroll_items) && has_permission('payroll', 'delete') && !$is_month_locked): ?>
                <button onclick="confirmDeletePayroll(<?= $selected_month ?>, <?= $selected_year ?>)" 
                        class="px-3.5 py-2.5 bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-100 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-semibold border border-rose-200 dark:border-rose-800 transition" 
                        title="Xóa bảng lương nháp tháng này">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hướng Dẫn Quy Trình Chốt Lương Từng Bước (Interactive Workflow Guide) -->
    <div class="workflow-guide-banner p-4 text-xs space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5 font-bold text-indigo-900 dark:text-indigo-200">
                <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <span class="text-sm">Quy Trình 4 Bước Tính & Chốt Bảng Lương Chuẩn Doanh Nghiệp</span>
            </div>
            <button type="button" onclick="toggleWorkflowBanner('payrollWorkflowGuide')" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                <span id="payrollWorkflowGuide-toggleText">Thu gọn</span>
                <i id="payrollWorkflowGuide-toggleIcon" class="fa-solid fa-chevron-up text-[10px]"></i>
            </button>
        </div>

        <div id="payrollWorkflowGuide" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
            <div class="workflow-step-item">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
                    <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-[10px] font-black">1</span>
                    <span>Chốt Bảng Công</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Đảm bảo công nhân viên đã đủ dữ liệu chấm công và ngày công chuẩn T2-T6.</p>
            </div>

            <div class="workflow-step-item">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
                    <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-[10px] font-black">2</span>
                    <span>Duyệt Đề Xuất & Kỷ Luật</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Duyệt nghỉ phép năm/ốm đau, tăng ca OT và các biên bản bồi thường thiệt hại.</p>
            </div>

            <div class="workflow-step-item">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
                    <span class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center text-[10px] font-black">3</span>
                    <span>Bấm "Tính Lương Toàn Bộ"</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Hệ thống áp dụng biểu thuế 5 bậc 2026, trần BHXH 20x và miễn thuế cho phần OT.</p>
            </div>

            <div class="workflow-step-item">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
                    <span class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-[10px] font-black">4</span>
                    <span>Kiểm Tra & "Chốt Lương"</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">In phiếu lương 5 mục chi tiết cho nhân viên và khóa vĩnh viễn kỳ lương.</p>
            </div>
        </div>
    </div>

    <!-- Banner Thông Số Pháp Lý Áp Dụng -->
    <div class="bg-indigo-50/70 dark:bg-indigo-950/40 p-4 rounded-2xl border border-indigo-200/60 dark:border-indigo-800/60 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs text-indigo-900 dark:text-indigo-200">
        <div class="flex items-center gap-2 font-semibold">
            <i class="fa-solid fa-circle-info text-indigo-600 dark:text-indigo-400 text-sm"></i>
            <span>Thông số pháp lý kỳ tính lương:</span>
        </div>
        <div class="flex items-center gap-4 flex-wrap text-[11px] font-mono">
            <span>Mức tham chiếu / LCS: <strong class="text-indigo-700 dark:text-indigo-300"><?= format_money($current_rules['base_reference_salary']) ?></strong></span>
            <span>•</span>
            <span>Giảm trừ bản thân: <strong class="text-indigo-700 dark:text-indigo-300"><?= format_money($current_rules['personal_deduction']) ?></strong></span>
            <span>•</span>
            <span>Giảm trừ NPT: <strong class="text-indigo-700 dark:text-indigo-300"><?= format_money($current_rules['dependent_deduction']) ?></strong>/người</span>
            <span>•</span>
            <span>BHXH NLĐ: <strong class="text-indigo-700 dark:text-indigo-300">10.5%</strong> (Trần BH: <?= format_money($current_rules['base_reference_salary'] * 20) ?>)</span>
        </div>
    </div>

    <!-- 4 Khối Thống Kê Quỹ Lương & Thuế/Bảo hiểm -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Tổng GROSS -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tổng Quỹ Lương GROSS</span>
                <div class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-1"><?= format_money($total_gross) ?></div>
                <div class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-0.5"><?= count($payroll_items) ?> nhân sự trong danh sách</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl font-bold border border-amber-200 dark:border-amber-800/60">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
        </div>

        <!-- Tổng Thực Lĩnh NET -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Thực Lĩnh Của NLĐ (NET)</span>
                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1"><?= format_money($total_net) ?></div>
                <div class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300 mt-0.5"><?= format_money($total_paid) ?> đã thanh toán</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl font-bold border border-emerald-200 dark:border-emerald-800/60">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
        </div>

        <!-- Bảo Hiểm & Thuế Phải Nộp -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Bảo Hiểm & Thuế TNCN</span>
                <div class="text-lg font-bold font-mono text-indigo-600 dark:text-indigo-400 mt-1"><?= format_money($total_insurance_emp + $total_pit) ?></div>
                <div class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">Thuế: <?= format_money($total_pit) ?> • BH NLĐ: <?= format_money($total_insurance_emp) ?></div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl font-bold border border-indigo-200 dark:border-indigo-800/60">
                <i class="fa-solid fa-building-columns"></i>
            </div>
        </div>

        <!-- Chi Phí Doanh Nghiệp -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="text-xs font-semibold text-rose-600 dark:text-rose-400">Tổng Chi Phí Nhân Sự (DN)</span>
                <div class="text-lg font-bold font-mono text-rose-600 dark:text-rose-400 mt-1"><?= format_money($total_gross + $total_insurance_empr) ?></div>
                <div class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">Gồm <?= format_money($total_insurance_empr) ?> DN đóng BH/KPCĐ</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl font-bold border border-rose-200 dark:border-rose-800/60">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
    </div>

    <!-- Thanh Lọc Kỳ Lương & Đơn Vị -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tháng:</span>
                <select name="month" onchange="this.form.submit()" 
                        class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 outline-none">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Năm:</span>
                <select name="year" onchange="this.form.submit()" 
                        class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 outline-none">
                    <?php for ($y = 2024; $y <= 2028; $y++): ?>
                        <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Phòng Ban:</span>
                <select name="department_id" onchange="this.form.submit()"
                        class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 outline-none">
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
            Quy tắc: <span class="font-mono text-slate-600 dark:text-slate-300">GROSS − BHXH (10.5%) − Thuế TNCN (5 bậc) − Bồi thường (≤30%) = THỰC LĨNH</span>
        </div>
    </div>

    <!-- Bảng Danh Sách Lương Chi Tiết -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto table-sticky-container">
            <table class="w-full min-w-[1280px] text-left text-sm table-enterprise">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-6 sticky-col-code bg-slate-50 dark:bg-slate-900/90 shadow-xs">Cán Bộ / Nhân Viên</th>
                        <th class="py-3.5 px-4 text-right">Lương Cơ Bản</th>
                        <th class="py-3.5 px-4 text-center">Ngày Công</th>
                        <th class="py-3.5 px-4 text-right">GROSS</th>
                        <th class="py-3.5 px-4 text-right text-indigo-700 dark:text-indigo-400">BH NLĐ (10.5%)</th>
                        <th class="py-3.5 px-4 text-right text-rose-600 dark:text-rose-400">Thuế TNCN</th>
                        <th class="py-3.5 px-4 text-right">Khấu Trừ Khác</th>
                        <th class="py-3.5 px-6 text-right bg-emerald-50/50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300">Thực Lĩnh NET</th>
                        <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                        <th class="py-3.5 px-6 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (empty($payroll_items)): ?>
                        <tr>
                            <td colspan="10" class="py-12">
                                <div class="empty-state-box max-w-md mx-auto">
                                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mb-3 shadow-xs border border-amber-200 dark:border-amber-800">
                                        <i class="fa-solid fa-calculator"></i>
                                    </div>
                                    <h4 class="text-sm font-extrabold text-slate-800 dark:text-white">Bảng Lương Tháng <?= $selected_month ?>/<?= $selected_year ?> Chưa Được Tạo</h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">
                                        Hệ thống sẽ tổng hợp tự động công chuẩn, OT, BHXH và Thuế TNCN khi bạn bấm tính lương.
                                    </p>
                                    <?php if (has_permission('payroll', 'create')): ?>
                                        <form action="index.php" method="POST" class="mt-4">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="calculate_payroll">
                                            <input type="hidden" name="calc_month" value="<?= $selected_month ?>">
                                            <input type="hidden" name="calc_year" value="<?= $selected_year ?>">
                                            <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-md transition inline-flex items-center gap-2">
                                                <i class="fa-solid fa-play text-xs"></i>
                                                <span>Tính Lương Ngay</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payroll_items as $item): 
                        $warnings = !empty($item['calc_warnings']) ? json_decode($item['calc_warnings'], true) : [];
                        $insEmp = (float)($item['bhxh_employee'] + $item['bhyt_employee'] + $item['bhtn_employee']);
                        $otherDeduct = (float)($item['deduction'] + $item['compensation_deduction']);
                        $grossVal = (float)($item['gross_salary'] ?: $item['final_salary']);
                    ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition">
                            <!-- Nhân viên -->
                            <td class="py-4 px-6 sticky-col-code bg-white dark:bg-slate-800">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($item['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $item['avatar'])): ?>
                                        <img src="<?= base_url('assets/uploads/' . e($item['avatar'])) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200" alt="">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 flex items-center justify-center font-bold text-xs">
                                            <?= strtoupper(mb_substr($item['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-800 dark:text-slate-100 text-sm"><?= e($item['fullname']) ?></span>
                                            <?php if (!empty($warnings)): ?>
                                                <span class="text-amber-500 cursor-pointer" title="<?= e(implode("\n• ", $warnings)) ?>">
                                                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono">
                                            <?= e($item['employee_code']) ?> • <?= e($item['position_name'] ?? 'Chức danh') ?>
                                            <?php if ($item['contract_type'] === 'probation'): ?>
                                                <span class="text-amber-600 font-semibold">[Thử việc]</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Lương cơ bản -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-slate-600 dark:text-slate-300">
                                <?= format_money($item['basic_salary']) ?>
                                <?php if ((float)$item['contract_allowance'] > 0): ?>
                                    <div class="text-[10px] text-emerald-600 font-mono">+<?= format_money($item['contract_allowance']) ?> PC</div>
                                <?php endif; ?>
                            </td>

                            <!-- Ngày công -->
                            <td class="py-4 px-4 text-center font-mono text-xs">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    <?= number_format($item['work_days'], 1) ?>
                                </span>
                                <span class="text-slate-400">/ <?= number_format($item['standard_days'] ?: 22, 0) ?></span>
                                <?php if ((float)$item['ot_weekday_hours'] + (float)$item['ot_weekend_hours'] > 0): ?>
                                    <div class="text-[10px] text-violet-600 font-mono">+<?= number_format((float)$item['ot_weekday_hours'] + (float)$item['ot_weekend_hours'], 1) ?>h OT</div>
                                <?php endif; ?>
                            </td>

                            <!-- Lương GROSS -->
                            <td class="py-4 px-4 text-right font-mono font-semibold text-xs text-slate-800 dark:text-slate-200">
                                <?= format_money($grossVal) ?>
                            </td>

                            <!-- Bảo hiểm NLĐ -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                <?= $insEmp > 0 ? ('-' . format_money($insEmp)) : '0 ₫' ?>
                            </td>

                            <!-- Thuế TNCN -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-rose-500">
                                <?= (float)$item['pit_amount'] > 0 ? ('-' . format_money($item['pit_amount'])) : '0 ₫' ?>
                            </td>

                            <!-- Khấu trừ khác / bồi thường -->
                            <td class="py-4 px-4 text-right font-mono text-xs text-slate-500">
                                <?= $otherDeduct > 0 ? ('-' . format_money($otherDeduct)) : '0 ₫' ?>
                            </td>

                            <!-- Thực lĩnh NET -->
                            <td class="py-4 px-6 text-right font-mono font-bold text-sm text-emerald-700 dark:text-emerald-300 bg-emerald-50/40 dark:bg-emerald-950/20">
                                <?= format_money($item['final_salary']) ?>
                            </td>

                            <!-- Trạng thái chi trả -->
                            <td class="py-4 px-4 text-center">
                                <?php if ($item['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                        <i class="fa-solid fa-check text-[9px] mr-1"></i> Đã Trả
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">
                                        <i class="fa-solid fa-clock text-[9px] mr-1"></i> Chờ Trả
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Nút Thao tác -->
                            <td class="py-4 px-6 text-right space-x-1 whitespace-nowrap">
                                <!-- Nút xem phiếu lương (Payslip) -->
                                <button onclick='viewPayslip(<?= json_encode($item) ?>)' 
                                        class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-indigo-50 hover:text-indigo-600 text-slate-500 dark:text-slate-300 inline-flex items-center justify-center transition"
                                        title="Xem & In Phiếu Lương Chi Tiết">
                                    <i class="fa-solid fa-receipt text-xs"></i>
                                </button>

                                <?php if (has_permission('payroll', 'edit')): ?>
                                    <!-- Nút Sửa phụ cấp / giảm trừ (chỉ khi chưa khóa) -->
                                    <?php if (empty($item['locked_at']) && $item['payment_status'] !== 'paid'): ?>
                                        <button onclick='openEditPayrollModal(<?= json_encode($item) ?>)' 
                                                class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-amber-50 hover:text-amber-600 text-slate-500 dark:text-slate-300 inline-flex items-center justify-center transition"
                                                title="Điều chỉnh số liệu">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Nút Đổi trạng thái thanh toán -->
                                    <form action="index.php?month=<?= $selected_month ?>&year=<?= $selected_year ?>" method="POST" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="toggle_payment_status">
                                        <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $item['payment_status'] === 'paid' ? 'pending' : 'paid' ?>">
                                        <button type="submit" 
                                                class="w-8 h-8 rounded-lg <?= $item['payment_status'] === 'paid' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 hover:bg-rose-50 hover:text-rose-600' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-600' ?> inline-flex items-center justify-center transition"
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
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-slate-800 dark:text-white text-base">Điều Chỉnh Lương Cá Nhân</h3>
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
                <div id="editPayrollEmpName" class="font-bold text-slate-800 dark:text-slate-100 text-sm">---</div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Số Ngày Công Thực Tế *</label>
                <input type="number" name="work_days" id="editWorkDays" step="0.5" min="0" max="31" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Phụ Cấp Bổ Sung Khác (VNĐ)</label>
                <input type="text" name="allowance" id="editAllowance" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Giảm Trừ / Tạm Ứng Khác (VNĐ)</label>
                <input type="text" name="deduction" id="editDeduction" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 outline-none">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="document.getElementById('editPayrollModal').classList.add('hidden')"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu & Tính Lại
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xem Phiếu Lương Chi Tiết & Hỗ Trợ In (Print Payslip - Chuẩn Pháp Luật Việt Nam) -->
<div id="payslipModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 dark:border-slate-700 relative max-h-[92vh] overflow-y-auto">
        <button onclick="document.getElementById('payslipModal').classList.add('hidden')" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 text-xl print:hidden">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="printablePayslip" class="space-y-5">
            <!-- Header phiếu lương -->
            <div class="text-center pb-3 border-b border-slate-200 dark:border-slate-700">
                <div class="text-[11px] uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">DOANH NGHIỆP CỔ PHẦN TẬP ĐOÀN AURA</div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mt-1">PHIẾU THANH TOÁN TIỀN LƯƠNG & BẢO HIỂM</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Kỳ chi trả: Tháng <span id="slipMonth"></span>/<span id="slipYear"></span></p>
            </div>

            <!-- Thông tin nhân sự -->
            <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-900/60 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-700">
                <div><span class="text-slate-400">Họ và tên:</span> <strong id="slipName" class="text-slate-800 dark:text-slate-100 block text-sm"></strong></div>
                <div><span class="text-slate-400">Mã nhân viên:</span> <span id="slipCode" class="font-mono font-bold text-indigo-600 dark:text-indigo-400 block text-sm"></span></div>
                <div><span class="text-slate-400">Đơn vị:</span> <span id="slipDept" class="text-slate-700 dark:text-slate-300 block"></span></div>
                <div><span class="text-slate-400">Chức vụ:</span> <span id="slipPos" class="text-slate-700 dark:text-slate-300 block"></span></div>
                <div><span class="text-slate-400">Loại hợp đồng:</span> <span id="slipContract" class="text-slate-700 dark:text-slate-300 block font-semibold"></span></div>
                <div><span class="text-slate-400">Người phụ thuộc:</span> <span id="slipDependents" class="text-slate-700 dark:text-slate-300 block"></span></div>
            </div>

            <!-- Bảng kê các mục theo Luật -->
            <div class="space-y-2 text-xs">
                <!-- I. THU NHẬP -->
                <div class="font-bold text-slate-700 dark:text-slate-200 uppercase text-[11px] pt-1">I. Các Khoản Thu Nhập (GROSS)</div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">1. Lương cơ bản theo hợp đồng:</span>
                    <span id="slipBaseSalary" class="font-mono font-semibold text-slate-800 dark:text-slate-200"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">2. Số ngày công thực tế / chuẩn:</span>
                    <span id="slipWorkDays" class="font-mono font-semibold text-indigo-600 dark:text-indigo-400"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">3. Tiền lương thời gian:</span>
                    <span id="slipTimeSalary" class="font-mono font-semibold text-slate-800 dark:text-slate-200"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">4. Phụ cấp lương & bổ sung (+):</span>
                    <span id="slipAllowance" class="font-mono font-semibold text-emerald-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">5. Tiền làm thêm giờ OT (Điều 98 BLLĐ):</span>
                    <span id="slipOtPay" class="font-mono font-semibold text-violet-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">6. Tiền thưởng khen thưởng:</span>
                    <span id="slipBonus" class="font-mono font-semibold text-emerald-600"></span>
                </div>
                <div class="flex justify-between py-1.5 bg-slate-50 dark:bg-slate-900 px-3 rounded-lg font-bold">
                    <span>TỔNG THU NHẬP GROSS:</span>
                    <span id="slipGross" class="font-mono text-slate-900 dark:text-white"></span>
                </div>

                <!-- II. BẢO HIỂM BẮT BUỘC (NLĐ) -->
                <div class="font-bold text-slate-700 dark:text-slate-200 uppercase text-[11px] pt-2">II. Trích Nộp Bảo Hiểm Bắt Buộc (10.5%)</div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- BHXH (8.0%):</span>
                    <span id="slipBhxh" class="font-mono text-rose-500"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- BHYT (1.5%):</span>
                    <span id="slipBhyt" class="font-mono text-rose-500"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- BHTN (1.0%):</span>
                    <span id="slipBhtn" class="font-mono text-rose-500"></span>
                </div>

                <!-- III. THUẾ TNCN -->
                <div class="font-bold text-slate-700 dark:text-slate-200 uppercase text-[11px] pt-2">III. Thuế Thu Nhập Cá Nhân (PIT)</div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- Tổng giảm trừ gia cảnh (bản thân + NPT):</span>
                    <span id="slipFamilyDeduct" class="font-mono text-slate-700 dark:text-slate-300"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- Thu nhập tính thuế:</span>
                    <span id="slipAssessable" class="font-mono text-slate-700 dark:text-slate-300"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- Thuế TNCN phải nộp:</span>
                    <span id="slipPit" class="font-mono font-bold text-rose-600"></span>
                </div>

                <!-- IV. KHẤU TRỪ KHÁC -->
                <div class="font-bold text-slate-700 dark:text-slate-200 uppercase text-[11px] pt-2">IV. Khấu Trừ Khác</div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- Bồi thường thiệt hại (Điều 102 BLLĐ ≤ 30% lương):</span>
                    <span id="slipCompensation" class="font-mono text-rose-500"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 text-slate-500">
                    <span>- Tạm ứng & giảm trừ khác:</span>
                    <span id="slipOtherDeduct" class="font-mono text-rose-500"></span>
                </div>

                <!-- V. THỰC LĨNH -->
                <div class="flex justify-between py-3 bg-emerald-50 dark:bg-emerald-950/60 px-4 rounded-xl text-sm font-bold text-emerald-900 dark:text-emerald-100 mt-2">
                    <span>V. SỐ TIỀN THỰC LĨNH (NET):</span>
                    <span id="slipFinalSalary" class="font-mono text-base text-emerald-700 dark:text-emerald-400"></span>
                </div>

                <!-- Tham khảo: Doanh nghiệp đóng -->
                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-[11px] text-slate-500 dark:text-slate-400 space-y-1 mt-2">
                    <div class="font-semibold text-slate-600 dark:text-slate-300">Ghi chú trích nộp từ Người sử dụng lao động (DN):</div>
                    <div class="flex justify-between">
                        <span>Doanh nghiệp đóng BHXH, BHYT, BHTN & KPCĐ (23.5%):</span>
                        <span id="slipEmprIns" class="font-mono font-semibold text-slate-700 dark:text-slate-300"></span>
                    </div>
                </div>
            </div>

            <!-- Ký nhận -->
            <div class="grid grid-cols-2 text-center text-xs pt-4 text-slate-500 border-t border-slate-200 dark:border-slate-700">
                <div>
                    <div class="font-semibold text-slate-700 dark:text-slate-200 mb-12">Người Nhận Tiền</div>
                    <div>(Ký và ghi rõ họ tên)</div>
                </div>
                <div>
                    <div class="font-semibold text-slate-700 dark:text-slate-200 mb-12">Thủ Trưởng Phê Duyệt</div>
                    <div>(Đã duyệt chi lương)</div>
                </div>
            </div>
        </div>

        <!-- Nút In -->
        <div class="flex items-center justify-end gap-2 pt-5 border-t border-slate-100 dark:border-slate-700 mt-5 print:hidden">
            <button type="button" onclick="document.getElementById('payslipModal').classList.add('hidden')" 
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
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
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-white text-base mb-1">Xóa Bảng Lương Nháp?</h3>
        <p id="delPayrollMsg" class="text-xs text-slate-500 mb-6"></p>
        
        <form action="index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_monthly_payroll">
            <input type="hidden" name="month" id="delPayrollMonth" value="">
            <input type="hidden" name="year" id="delPayrollYear" value="">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('deletePayrollModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-600 text-xs font-semibold rounded-xl transition">
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
function formatVND(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
}

function openEditPayrollModal(item) {
    document.getElementById('editPayrollId').value = item.id;
    document.getElementById('editPayrollEmpName').innerText = `${item.fullname} (${item.employee_code})`;
    document.getElementById('editWorkDays').value = item.work_days;
    document.getElementById('editAllowance').value = item.allowance;
    document.getElementById('editDeduction').value = item.deduction;
    document.getElementById('editPayrollModal').classList.remove('hidden');
}

function viewPayslip(item) {
    document.getElementById('slipMonth').innerText = item.month;
    document.getElementById('slipYear').innerText = item.year;
    document.getElementById('slipName').innerText = item.fullname;
    document.getElementById('slipCode').innerText = item.employee_code;
    document.getElementById('slipDept').innerText = item.department_name || 'Chưa gán';
    document.getElementById('slipPos').innerText = item.position_name || 'Chưa gán';
    
    const contractLabels = {
        'probation': 'Thử Việc',
        'fixed_term': 'Xác Định Thời Hạn',
        'indefinite': 'Không Xác Định Thời Hạn',
        'seasonal': 'Theo Mùa Vụ'
    };
    document.getElementById('slipContract').innerText = contractLabels[item.contract_type] || 'Chính Thức';
    document.getElementById('slipDependents').innerText = (item.dependents_count || 0) + ' người';

    // Breakdown
    document.getElementById('slipBaseSalary').innerText = formatVND(item.basic_salary);
    document.getElementById('slipWorkDays').innerText = `${item.work_days} / ${item.standard_days || 22} ngày`;
    document.getElementById('slipTimeSalary').innerText = formatVND(item.time_salary || item.basic_salary);
    
    const totalAllowance = parseFloat(item.allowance || 0) + parseFloat(item.contract_allowance || 0);
    document.getElementById('slipAllowance').innerText = '+ ' + formatVND(totalAllowance);
    document.getElementById('slipOtPay').innerText = '+ ' + formatVND(item.ot_pay || 0);
    document.getElementById('slipBonus').innerText = '+ ' + formatVND(item.bonus || 0);
    document.getElementById('slipGross').innerText = formatVND(item.gross_salary || item.final_salary);

    // Bảo hiểm
    document.getElementById('slipBhxh').innerText = '- ' + formatVND(item.bhxh_employee || 0);
    document.getElementById('slipBhyt').innerText = '- ' + formatVND(item.bhyt_employee || 0);
    document.getElementById('slipBhtn').innerText = '- ' + formatVND(item.bhtn_employee || 0);

    // Thuế
    document.getElementById('slipFamilyDeduct').innerText = formatVND(item.family_deduction || 0);
    document.getElementById('slipAssessable').innerText = formatVND(item.assessable_income || 0);
    document.getElementById('slipPit').innerText = '- ' + formatVND(item.pit_amount || 0);

    // Khấu trừ khác
    document.getElementById('slipCompensation').innerText = '- ' + formatVND(item.compensation_deduction || 0);
    document.getElementById('slipOtherDeduct').innerText = '- ' + formatVND(item.deduction || 0);

    // Thực lĩnh
    document.getElementById('slipFinalSalary').innerText = formatVND(item.final_salary);
    document.getElementById('slipEmprIns').innerText = formatVND(item.employer_insurance || 0);

    document.getElementById('payslipModal').classList.remove('hidden');
}

function confirmDeletePayroll(month, year) {
    document.getElementById('delPayrollMonth').value = month;
    document.getElementById('delPayrollYear').value = year;
    document.getElementById('delPayrollMsg').innerText = `Bạn có chắc muốn xóa bảng tính lương Tháng ${month}/${year}? Thao tác này chỉ xóa các bản ghi chưa khóa.`;
    document.getElementById('deletePayrollModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>