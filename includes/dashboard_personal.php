<?php
// includes/dashboard_personal.php - Bảng điều khiển Tự phục vụ Nhân viên (Employee Self-Service Dashboard)

require_once __DIR__ . '/../core/LeaveService.php';

$emp = current_employee();
$emp_id = $emp ? (int)$emp['id'] : 0;
$today = date('Y-m-d');
$current_month = (int)date('m');
$current_year = (int)date('Y');

// 1. Chấm công hôm nay của tôi
$my_today_att = null;
if ($emp_id) {
    $attStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
    $attStmt->execute([$emp_id, $today]);
    $my_today_att = $attStmt->fetch();
}

// 2. Thống kê ngày công tháng này
$my_month_stats = [
    'present_days' => 0,
    'late_days'    => 0,
    'leave_days'   => 0,
    'ot_hours'     => 0.0
];
if ($emp_id) {
    $mStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status IN ('late', 'early_leave') THEN 1 END) as late_days,
            COUNT(CASE WHEN status = 'leave_with_permit' THEN 1 END) as leave_days,
            SUM(COALESCE(overtime_hours, 0)) as ot_hours
        FROM attendance 
        WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
    ");
    $mStmt->execute([$emp_id, $current_month, $current_year]);
    $row = $mStmt->fetch();
    if ($row) {
        $my_month_stats = $row;
    }
}

// 3. Quỹ phép năm của tôi
$my_leave = $emp_id ? LeaveService::getLeaveSummary($pdo, $emp_id) : [
    'entitled' => 12.0, 'used' => 0.0, 'pending' => 0.0, 'remaining' => 12.0, 'seniority_years' => 0
];

// 4. Phiếu lương gần nhất (đã khóa sổ hoặc đã chi trả)
$my_latest_payslip = null;
if ($emp_id) {
    $psStmt = $pdo->prepare("
        SELECT * FROM payrolls 
        WHERE employee_id = ? 
        ORDER BY year DESC, month DESC 
        LIMIT 1
    ");
    $psStmt->execute([$emp_id]);
    $my_latest_payslip = $psStmt->fetch();
}

// 5. Điểm đánh giá KPI gần nhất
$my_latest_kpi = null;
if ($emp_id) {
    $kpiStmt = $pdo->prepare("
        SELECT e.*, ep.name AS period_name 
        FROM evaluations e
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.id
        WHERE e.employee_id = ? 
        ORDER BY e.id DESC 
        LIMIT 1
    ");
    $kpiStmt->execute([$emp_id]);
    $my_latest_kpi = $kpiStmt->fetch();
}

// 6. Đề xuất gần đây của tôi
$my_proposals = [];
if ($emp_id) {
    $pStmt = $pdo->prepare("
        SELECT * FROM proposals 
        WHERE employee_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $pStmt->execute([$emp_id]);
    $my_proposals = $pStmt->fetchAll();
}

// 7. Kỳ nghỉ lễ sắp tới
$upcoming_holidays = [];
try {
    $hStmt = $pdo->query("SELECT * FROM public_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 2");
    $upcoming_holidays = $hStmt->fetchAll();
} catch (Exception $e) {}

$type_map = [
    'leave'         => ['Nghỉ Phép', 'fa-calendar-minus', 'sky'],
    'overtime'      => ['Tăng Ca',    'fa-clock',          'violet'],
    'business_trip' => ['Công Tác',   'fa-plane',          'teal'],
    'salary_raise'  => ['Tăng Lương', 'fa-coins',          'amber'],
    'equipment'     => ['Thiết Bị',   'fa-laptop',         'indigo'],
    'other'         => ['Khác',       'fa-clipboard-list', 'slate'],
];
?>

<div class="space-y-6">

    <!-- Banner Chào Mừng Nhân Viên (Flat Metro UI) -->
    <div class="metro-gradient text-white p-6 sm:p-8 rounded-sm shadow-xs relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-sm text-xs font-extrabold bg-white/20 text-white mb-3 backdrop-blur-xs">
                    <i class="fa-solid fa-store"></i> <?= e(brand('company_short')) ?> &bull; Cổng Tự Phục Vụ Nhân Viên (ESS)
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Xin chào, <?= e($emp['fullname'] ?? $user['fullname']) ?>!</h2>
                <p class="text-indigo-100 text-xs sm:text-sm mt-1 max-w-xl font-medium">
                    Mã NV: <b class="font-mono text-white"><?= e($emp['employee_code'] ?? 'N/A') ?></b> &bull; <?= e($emp['position_name'] ?? 'Nhân viên') ?> &bull; <?= e($emp['department_name'] ?? 'Bộ phận') ?> &bull; <?= e($emp['branch_name'] ?? 'Chi nhánh') ?>
                </p>
            </div>

            <!-- Trạng thái Check-in Hôm Nay -->
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur-md p-3.5 rounded-sm border border-white/20 flex-shrink-0">
                <div class="w-12 h-12 rounded-sm <?= $my_today_att ? 'bg-emerald-500' : 'bg-amber-400 text-slate-900' ?> flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid <?= $my_today_att ? 'fa-circle-check text-white' : 'fa-clock' ?>"></i>
                </div>
                <div>
                    <div class="text-[11px] uppercase font-bold text-indigo-100">Điểm Danh Hôm Nay</div>
                    <div class="text-sm font-extrabold text-white mt-0.5">
                        <?php if ($my_today_att): ?>
                            Đã Check-in: <span class="font-mono"><?= substr($my_today_att['check_in'] ?? '08:00', 0, 5) ?></span>
                        <?php else: ?>
                            Chưa ghi nhận Check-in
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('modules/attendance/index.php') ?>" class="text-[11px] text-amber-200 hover:text-white underline font-semibold flex items-center gap-1 mt-0.5">
                        <span>Vào chấm công cá nhân</span> <i class="fa-solid fa-arrow-right text-[9px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số Cá Nhân (Personal KPIs) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        
        <!-- KPI 1: Ngày Công Tháng Này -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-sm border border-slate-300 dark:border-slate-700 flex items-center justify-between card-hover">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Công Đi Làm Tháng <?= $current_month ?></span>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1 font-mono">
                    <?= (int)$my_month_stats['present_days'] ?> <span class="text-xs font-normal text-slate-400">ngày công</span>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-1 flex items-center gap-2">
                    <?php if ((float)$my_month_stats['ot_hours'] > 0): ?>
                        <span class="text-violet-600 font-bold">+<?= (float)$my_month_stats['ot_hours'] ?>h OT</span> &bull;
                    <?php endif; ?>
                    <span>Trễ: <?= (int)$my_month_stats['late_days'] ?> lần</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-sm bg-indigo-600 text-white flex items-center justify-center text-lg">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>

        <!-- KPI 2: Quỹ Phép Năm Còn Lại -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-sm border border-slate-300 dark:border-slate-700 flex items-center justify-between card-hover">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Quỹ Phép Năm <?= date('Y') ?></span>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                    <?= $my_leave['remaining'] ?> <span class="text-xs font-normal text-slate-400">ngày khả dụng</span>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-1">
                    Đã nghỉ: <b><?= $my_leave['used'] ?></b> / Hưởng: <b><?= $my_leave['entitled'] ?></b> ngày
                </div>
            </div>
            <div class="w-12 h-12 rounded-sm bg-emerald-600 text-white flex items-center justify-center text-lg">
                <i class="fa-solid fa-umbrella-beach"></i>
            </div>
        </div>

        <!-- KPI 3: Phiếu Lương Gần Nhất -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-sm border border-slate-300 dark:border-slate-700 flex items-center justify-between card-hover">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">
                    <?= $my_latest_payslip ? ('Lương T' . $my_latest_payslip['month'] . ' (Thực Lĩnh)') : 'Phiếu Lương Gần Nhất' ?>
                </span>
                <div class="text-xl font-black text-slate-900 dark:text-white mt-1 font-mono">
                    <?= $my_latest_payslip ? format_money($my_latest_payslip['final_salary']) : '---' ?>
                </div>
                <div class="text-xs text-indigo-600 dark:text-indigo-400 font-bold mt-1">
                    <a href="<?= base_url('modules/payroll/my_payslip.php') ?>" class="hover:underline flex items-center gap-1">
                        <span>Xem chi tiết phiếu lương</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
            <div class="w-12 h-12 rounded-sm bg-amber-500 text-white flex items-center justify-center text-lg">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
        </div>

        <!-- KPI 4: Đánh Giá Hiệu Suất / KPI -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-sm border border-slate-300 dark:border-slate-700 flex items-center justify-between card-hover">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Điểm KPI Đợt Gần Nhất</span>
                <div class="text-2xl font-black text-violet-600 dark:text-violet-400 mt-1 font-mono">
                    <?= ($my_latest_kpi && $my_latest_kpi['final_score']) ? round($my_latest_kpi['final_score'], 1) : '---' ?>
                    <span class="text-xs font-normal text-slate-400">/100</span>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-1">
                    <?= $my_latest_kpi ? ('Xếp loại: ' . e($my_latest_kpi['grade'] ?: 'Đang chấm')) : 'Chưa có kỳ đánh giá' ?>
                </div>
            </div>
            <div class="w-12 h-12 rounded-sm bg-violet-600 text-white flex items-center justify-center text-lg">
                <i class="fa-solid fa-bullseye"></i>
            </div>
        </div>

    </div>

    <!-- Cụm Hành Động Nhanh (1-Click Actions) -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-sm border border-slate-300 dark:border-slate-700 shadow-2xs">
        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-bolt text-indigo-600 dark:text-indigo-400"></i>
            Tác Vụ Tự Phục Vụ Nhanh (Quick Actions)
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="<?= base_url('modules/attendance/index.php') ?>" 
               class="p-4 rounded-sm border border-slate-200 dark:border-slate-700 hover:border-indigo-500 hover:bg-indigo-50/50 dark:hover:bg-slate-700/60 transition flex flex-col items-center text-center group">
                <div class="w-10 h-10 rounded-sm bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-fingerprint"></i>
                </div>
                <span class="font-extrabold text-xs text-slate-800 dark:text-white">Điểm Danh Chấm Công</span>
                <span class="text-[11px] text-slate-500 mt-0.5">Vào / ra ca làm</span>
            </a>

            <a href="<?= base_url('modules/proposals/create.php') ?>" 
               class="p-4 rounded-sm border border-slate-200 dark:border-slate-700 hover:border-sky-500 hover:bg-sky-50/50 dark:hover:bg-slate-700/60 transition flex flex-col items-center text-center group">
                <div class="w-10 h-10 rounded-sm bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <span class="font-extrabold text-xs text-slate-800 dark:text-white">Gửi Đơn Xin Nghỉ / OT</span>
                <span class="text-[11px] text-slate-500 mt-0.5">Phép năm, tăng ca</span>
            </a>

            <a href="<?= base_url('modules/payroll/my_payslip.php') ?>" 
               class="p-4 rounded-sm border border-slate-200 dark:border-slate-700 hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-slate-700/60 transition flex flex-col items-center text-center group">
                <div class="w-10 h-10 rounded-sm bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <span class="font-extrabold text-xs text-slate-800 dark:text-white">Tra Cứu Phiếu Lương</span>
                <span class="text-[11px] text-slate-500 mt-0.5">Xem & in bảng lương</span>
            </a>

            <a href="<?= base_url('modules/evaluations/index.php') ?>" 
               class="p-4 rounded-sm border border-slate-200 dark:border-slate-700 hover:border-purple-500 hover:bg-purple-50/50 dark:hover:bg-slate-700/60 transition flex flex-col items-center text-center group">
                <div class="w-10 h-10 rounded-sm bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg mb-2 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-award"></i>
                </div>
                <span class="font-extrabold text-xs text-slate-800 dark:text-white">Tự Đánh Giá KPI</span>
                <span class="text-[11px] text-slate-500 mt-0.5">Hồ sơ hiệu suất</span>
            </a>
        </div>
    </div>

    <!-- 2 Cột: Đề Xuất Gần Đây & Thông Tin Chế Độ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Cột 1 & 2: Danh Sách Đề Xuất Gần Đây Của Tôi -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 p-6 rounded-sm border border-slate-300 dark:border-slate-700 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Đề Xuất & Đơn Từ Của Tôi</h3>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Trạng thái phê duyệt các đơn xin nghỉ, tăng ca, công tác</p>
                </div>
                <a href="<?= base_url('modules/proposals/index.php') ?>" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                    <span>Xem tất cả</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <?php if (empty($my_proposals)): ?>
                <div class="p-8 text-center text-xs text-slate-400">
                    <i class="fa-regular fa-clipboard text-2xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Bạn chưa gửi đề xuất nào trong hệ thống.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($my_proposals as $p): 
                        $t_info = $type_map[$p['type']] ?? ['Khác', 'fa-file', 'slate'];
                    ?>
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-sm bg-<?= $t_info[2] ?>-100 dark:bg-<?= $t_info[2] ?>-950/60 text-<?= $t_info[2] ?>-600 dark:text-<?= $t_info[2] ?>-400 flex items-center justify-center text-sm flex-shrink-0">
                                    <i class="fa-solid <?= $t_info[1] ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-extrabold text-xs text-slate-800 dark:text-white truncate">
                                        <?= e($p['title']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                                        <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold"><?= e($p['proposal_code']) ?></span> &bull;
                                        <span>Gửi ngày: <?= format_date($p['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <?php if ($p['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200">
                                        <i class="fa-solid fa-circle-check mr-1"></i> Đã Duyệt
                                    </span>
                                <?php elseif ($p['status'] === 'pending'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[10px] font-extrabold bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200">
                                        <i class="fa-solid fa-clock mr-1"></i> Chờ Duyệt
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[10px] font-extrabold bg-rose-100 dark:bg-rose-900/60 text-rose-800 dark:text-rose-200">
                                        <i class="fa-solid fa-circle-xmark mr-1"></i> Từ Chối
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cột 3: Lịch Nghỉ Lễ Sắp Tới & Văn Hóa Doanh Nghiệp -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-sm border border-slate-300 dark:border-slate-700 space-y-4">
            <div class="pb-3 border-b-2 border-slate-200 dark:border-slate-700">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Thông Báo & Nghỉ Lễ</h3>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Lịch nghỉ lễ toàn quốc theo quy định</p>
            </div>

            <div class="space-y-3">
                <?php if (empty($upcoming_holidays)): ?>
                    <div class="p-3 rounded-sm bg-slate-50 dark:bg-slate-700/50 text-xs text-slate-500">
                        Không có lịch nghỉ lễ sắp tới trong thời gian gần.
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_holidays as $h): ?>
                        <div class="p-3.5 rounded-sm bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800">
                            <div class="flex items-center justify-between">
                                <span class="font-extrabold text-xs text-slate-900 dark:text-white"><?= e($h['name']) ?></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-200 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200"><?= (float)$h['days_off'] ?> ngày nghỉ</span>
                            </div>
                            <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-1 flex items-center gap-1.5 font-medium">
                                <i class="fa-regular fa-calendar text-indigo-500"></i>
                                <span>Ngày: <?= date('d/m/Y', strtotime($h['holiday_date'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Cam Kết Văn Hóa WinCommerce -->
                <div class="p-3.5 rounded-sm bg-gradient-to-r from-amber-50 to-orange-50 dark:from-slate-750 dark:to-slate-700 border border-amber-200 dark:border-slate-600 text-xs text-slate-700 dark:text-slate-200">
                    <div class="font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1.5 mb-1">
                        <i class="fa-solid fa-heart text-rose-500"></i> Giá Trị Cốt Lõi WiN
                    </div>
                    <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-300">
                        "Tận tâm phục vụ khách hàng &bull; Chính trực & kỷ luật &bull; Tinh thần phụng sự hàng triệu gia đình Việt mỗi ngày."
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>

