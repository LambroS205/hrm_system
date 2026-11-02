<?php
// modules/payroll/my_payslip.php - Cổng tự phục vụ tra cứu Phiếu Lương Cá Nhân (ESS)

$page_title = 'Phiếu Lương Của Tôi';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/scope.php';
require_once __DIR__ . '/../../config/payroll_rules.php';

require_login();

$emp = current_employee();
if (!$emp) {
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="max-w-xl mx-auto mt-12 bg-white dark:bg-slate-800 p-8 rounded-3xl border border-slate-200 dark:border-slate-700 text-center shadow-sm">
        <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fa-solid fa-user-xmark"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Chưa Tìm Thấy Hồ Sơ Nhân Sự</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            Tài khoản đăng nhập của bạn chưa được liên kết với mã nhân viên trong hệ thống ' . e(brand('company_short')) . '. Vui lòng liên hệ Phòng Nhân sự (C&B) để được đồng bộ dữ liệu.
        </p>
    </div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$emp_id = (int)$emp['id'];
$selected_year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Lấy danh sách phiếu lương của nhân viên theo năm (chỉ lấy các kỳ đã khóa sổ hoặc đã chi trả)
$stmt = $pdo->prepare("
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
    WHERE pr.employee_id = ? AND pr.year = ?
    ORDER BY pr.month DESC
");
$stmt->execute([$emp_id, $selected_year]);
$payslips = $stmt->fetchAll();

// Tính tổng tích lũy YTD trong năm
$ytd_net = 0;
$ytd_gross = 0;
$ytd_insurance = 0;
$ytd_pit = 0;
$ytd_work_days = 0;

foreach ($payslips as $ps) {
    $ytd_net += (float)$ps['final_salary'];
    $ytd_gross += (float)($ps['gross_salary'] ?: $ps['final_salary']);
    $ytd_insurance += (float)($ps['bhxh_employee'] + $ps['bhyt_employee'] + $ps['bhtn_employee']);
    $ytd_pit += (float)$ps['pit_amount'];
    $ytd_work_days += (float)($ps['work_days'] ?? 0);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Topbar Tiêu đề & Chọn Năm -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-900 dark:text-emerald-200 mb-2 border border-emerald-300 dark:border-emerald-700">
                <i class="fa-solid fa-file-invoice-dollar text-emerald-600 dark:text-emerald-400"></i> Cổng Tự Phục Vụ Nhân Viên (ESS)
            </div>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">Phiếu Lương & Thu Nhập Cá Nhân</h2>
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-0.5">
                Cán bộ: <span class="text-indigo-600 dark:text-indigo-400 font-bold"><?= e($emp['fullname']) ?></span> (<?= e($emp['employee_code']) ?>) &bull; <?= e($emp['position_name']) ?> &bull; <?= e($emp['branch_name']) ?>
            </p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Năm quyết toán:</span>
            <select name="year" onchange="this.form.submit()"
                    class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-extrabold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $selected_year === $y ? 'selected' : '' ?>>Năm <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- 4 Khối Chỉ Số Tổng Thu Nhập Lũy Kế Trong Năm (YTD Summary) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Thực Lĩnh Lũy Kế (Net YTD)</span>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 font-mono"><?= format_money($ytd_net) ?></div>
                <div class="text-[11px] text-slate-500 mt-1 font-medium"><?= count($payslips) ?> kỳ lương đã quyết toán</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Tổng Thu Nhập Gross YTD</span>
                <div class="text-xl font-extrabold text-slate-800 dark:text-white mt-1 font-mono"><?= format_money($ytd_gross) ?></div>
                <div class="text-[11px] text-slate-500 mt-1 font-medium">Trước khi trích bảo hiểm & thuế</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">BHXH/BHYT/BHTN Đã Đóng</span>
                <div class="text-xl font-extrabold text-sky-600 dark:text-sky-400 mt-1 font-mono"><?= format_money($ytd_insurance) ?></div>
                <div class="text-[11px] text-slate-500 mt-1 font-medium">10.5% khấu trừ từ lương NLĐ</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-shield-heart"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Thuế TNCN Đã Khấu Trừ</span>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 font-mono"><?= format_money($ytd_pit) ?></div>
                <div class="text-[11px] text-slate-500 mt-1 font-medium">Tổng ngày công: <b><?= $ytd_work_days ?></b> công</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-receipt"></i>
            </div>
        </div>
    </div>

    <!-- Danh sách các Phiếu Lương theo Tháng -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-emerald-600"></i>
                Bảng Kê Chi Tiết Từng Kỳ Lương Năm <?= $selected_year ?>
            </h3>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                Hiển thị <?= count($payslips) ?> kỳ chi trả
            </span>
        </div>

        <?php if (empty($payslips)): ?>
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700/60 text-slate-400 flex items-center justify-center mx-auto text-2xl">
                    <i class="fa-regular fa-folder-open"></i>
                </div>
                <h4 class="font-bold text-slate-700 dark:text-slate-200 text-sm">Chưa Có Phiếu Lương Nào Trong Năm <?= $selected_year ?></h4>
                <p class="text-xs text-slate-400 max-w-md mx-auto">
                    Kỳ lương của bạn có thể đang trong giai đoạn tính toán hoặc chưa được Ban Quản trị C&B chốt khóa sổ.
                </p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                <?php foreach ($payslips as $item): ?>
                    <div class="p-6 hover:bg-slate-50/80 dark:hover:bg-slate-750 transition flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex flex-col items-center justify-center font-bold flex-shrink-0 border border-indigo-200/80 dark:border-indigo-800">
                                <span class="text-[10px] uppercase tracking-wider font-extrabold text-indigo-400">Tháng</span>
                                <span class="text-lg font-mono font-black leading-none"><?= str_pad($item['month'], 2, '0', STR_PAD_LEFT) ?></span>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-slate-900 dark:text-white text-base">
                                        Kỳ Lương Tháng <?= $item['month'] ?>/<?= $item['year'] ?>
                                    </h4>
                                    <?php if ($item['payment_status'] === 'paid'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200">
                                            <i class="fa-solid fa-check-double mr-1"></i> Đã Chuyển Khoản
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200">
                                            <i class="fa-solid fa-lock mr-1"></i> Đã Chốt Sổ
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-4 gap-y-1 font-medium">
                                    <span>Công chuẩn: <b><?= (float)($item['standard_days'] ?: 22) ?></b></span>
                                    <span>Công thực tế: <b class="text-indigo-600 dark:text-indigo-400"><?= (float)($item['work_days'] ?? 0) ?></b></span>
                                    <?php $item_ot = (float)($item['ot_weekday_hours'] ?? 0) + (float)($item['ot_weekend_hours'] ?? 0) + (float)($item['ot_holiday_hours'] ?? 0); ?>
                                    <?php if ($item_ot > 0): ?>
                                        <span>Tăng ca: <b class="text-violet-600 font-mono"><?= $item_ot ?>h</b></span>
                                    <?php endif; ?>
                                    <span>Tài khoản nhận: <b class="font-mono text-slate-700 dark:text-slate-200"><?= e($item['bank_account'] ?: 'Chưa cập nhật') ?></b></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between lg:justify-end gap-6 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100 dark:border-slate-700">
                            <div class="text-left lg:text-right">
                                <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Thực Lĩnh (Net)</div>
                                <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                                    <?= format_money($item['final_salary']) ?>
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Gross: <?= format_money($item['gross_salary'] ?: $item['final_salary']) ?>
                                </div>
                            </div>

                            <button type="button" onclick="viewPayslip(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm shadow-indigo-200 dark:shadow-none flex items-center gap-2 transition cursor-pointer flex-shrink-0">
                                <i class="fa-solid fa-receipt"></i>
                                <span>Xem Chi Tiết & In</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Xem Phiếu Lương Chi Tiết & Hỗ Trợ In (Chuẩn BLLĐ 2019 Điều 102) -->
<div id="payslipModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 dark:border-slate-700 relative max-h-[92vh] overflow-y-auto">
        <button onclick="document.getElementById('payslipModal').classList.add('hidden')" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 text-xl print:hidden cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="printablePayslip" class="space-y-5">
            <!-- Header phiếu lương -->
            <div class="text-center pb-3 border-b border-slate-200 dark:border-slate-700">
                <div class="text-[11px] uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider"><?= e(brand('company_legal')) ?></div>
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
                    <span id="slipGrossSalary" class="font-mono text-slate-800 dark:text-white"></span>
                </div>

                <!-- II. CÁC KHOẢN KHẤU TRỪ -->
                <div class="font-bold text-slate-700 dark:text-slate-200 uppercase text-[11px] pt-3">II. Các Khoản Giảm Trừ & Khấu Trừ</div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">1. BHXH (8.0%):</span>
                    <span id="slipBhxh" class="font-mono font-semibold text-rose-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">2. BHYT (1.5%):</span>
                    <span id="slipBhyt" class="font-mono font-semibold text-rose-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">3. BHTN (1.0%):</span>
                    <span id="slipBhtn" class="font-mono font-semibold text-rose-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">4. Giảm trừ kỷ luật / bồi thường (-):</span>
                    <span id="slipDiscipline" class="font-mono font-semibold text-rose-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-500">5. Thuế Thu Nhập Cá Nhân (TNCN):</span>
                    <span id="slipPit" class="font-mono font-semibold text-amber-600"></span>
                </div>
                <div class="flex justify-between py-1.5 bg-rose-50 dark:bg-rose-950/30 px-3 rounded-lg font-bold text-rose-800 dark:text-rose-300">
                    <span>TỔNG KHẤU TRỪ:</span>
                    <span id="slipTotalDeduction" class="font-mono"></span>
                </div>

                <!-- III. THỰC LĨNH -->
                <div class="flex justify-between items-center py-3 bg-emerald-50 dark:bg-emerald-950/40 px-4 rounded-xl border border-emerald-200 dark:border-emerald-800 mt-3">
                    <div>
                        <div class="font-black text-sm uppercase text-emerald-900 dark:text-emerald-200">LƯƠNG THỰC LĨNH (NET)</div>
                        <div class="text-[10px] text-emerald-700 dark:text-emerald-300">Đã bao gồm đầy đủ nghĩa vụ thuế & bảo hiểm</div>
                    </div>
                    <span id="slipFinalSalary" class="font-mono font-black text-2xl text-emerald-600 dark:text-emerald-400"></span>
                </div>
            </div>

            <!-- Chữ ký số & Pháp lý -->
            <div class="grid grid-cols-2 gap-4 pt-4 text-center text-xs border-t border-slate-200 dark:border-slate-700 print:pt-8">
                <div>
                    <div class="font-bold text-slate-700 dark:text-slate-300">NGƯỜI LAO ĐỘNG</div>
                    <div class="text-[11px] text-slate-400 italic mt-0.5">(Đã xác nhận điện tử)</div>
                    <div class="mt-8 font-semibold text-slate-800 dark:text-slate-200" id="slipSignName"></div>
                </div>
                <div>
                    <div class="font-bold text-slate-700 dark:text-slate-300">ĐẠI DIỆN DOANH NGHIỆP</div>
                    <div class="text-[11px] text-slate-400 italic mt-0.5"><?= e(brand('company_short')) ?> Payroll Center</div>
                    <div class="mt-8 font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-1">
                        <i class="fa-solid fa-certificate"></i> ĐÃ DUYỆT ĐIỆN TỬ
                    </div>
                </div>
            </div>
        </div>

        <!-- Nút In & Tải PDF -->
        <div class="mt-6 flex justify-end gap-3 print:hidden">
            <button type="button" onclick="window.print()"
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-sm transition cursor-pointer">
                <i class="fa-solid fa-print"></i>
                <span>In Phiếu Lương (A4)</span>
            </button>
            <button type="button" onclick="document.getElementById('payslipModal').classList.add('hidden')"
                    class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition cursor-pointer">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(amount || 0)) + ' đ';
}

function viewPayslip(item) {
    document.getElementById('slipMonth').innerText = item.month;
    document.getElementById('slipYear').innerText = item.year;
    document.getElementById('slipName').innerText = item.fullname;
    document.getElementById('slipSignName').innerText = item.fullname;
    document.getElementById('slipCode').innerText = item.employee_code;
    document.getElementById('slipDept').innerText = item.department_name || 'N/A';
    document.getElementById('slipPos').innerText = item.position_name || 'N/A';
    document.getElementById('slipContract').innerText = (item.employment_status === 'official' ? 'Hợp đồng lao động chính thức' : (item.employment_status === 'probation' ? 'Hợp đồng thử việc' : 'Đã nghỉ việc'));
    document.getElementById('slipDependents').innerText = (item.dependents_count || item.dependent_count || 0) + ' người';

    // Thu nhập
    const baseSal = parseFloat(item.basic_salary || item.base_salary || 0);
    const workDays = parseFloat(item.work_days ?? item.actual_work_days ?? 0);
    const stdDays = parseFloat(item.standard_days || item.standard_work_days || 22);
    const timeSal = parseFloat(item.time_salary || item.time_based_salary || baseSal);
    const allowances = parseFloat(item.allowance || 0) + parseFloat(item.contract_allowance || 0) + parseFloat(item.allowances || 0);
    const otPay = parseFloat(item.ot_pay || 0);
    const bonus = parseFloat(item.bonus || 0);
    const grossSal = parseFloat(item.gross_salary || (timeSal + allowances + otPay + bonus));

    document.getElementById('slipBaseSalary').innerText = formatMoney(baseSal);
    document.getElementById('slipWorkDays').innerText = workDays + ' / ' + stdDays + ' ngày';
    document.getElementById('slipTimeSalary').innerText = formatMoney(timeSal);
    document.getElementById('slipAllowance').innerText = '+ ' + formatMoney(allowances);
    document.getElementById('slipOtPay').innerText = '+ ' + formatMoney(otPay);
    document.getElementById('slipBonus').innerText = '+ ' + formatMoney(bonus);
    document.getElementById('slipGrossSalary').innerText = formatMoney(grossSal);

    // Khấu trừ
    const bhxh = parseFloat(item.bhxh_employee || 0);
    const bhyt = parseFloat(item.bhyt_employee || 0);
    const bhtn = parseFloat(item.bhtn_employee || 0);
    const pit = parseFloat(item.pit_amount || 0);
    const otherDeduct = parseFloat(item.deduction || 0) + parseFloat(item.compensation_deduction || 0) + parseFloat(item.discipline_deduction || 0);

    document.getElementById('slipBhxh').innerText = '- ' + formatMoney(bhxh);
    document.getElementById('slipBhyt').innerText = '- ' + formatMoney(bhyt);
    document.getElementById('slipBhtn').innerText = '- ' + formatMoney(bhtn);
    document.getElementById('slipDiscipline').innerText = '- ' + formatMoney(otherDeduct);
    document.getElementById('slipPit').innerText = '- ' + formatMoney(pit);

    const totalDeduct = bhxh + bhyt + bhtn + pit + otherDeduct;
    document.getElementById('slipTotalDeduction').innerText = '- ' + formatMoney(totalDeduct);

    // Thực lĩnh
    document.getElementById('slipFinalSalary').innerText = formatMoney(item.final_salary || (grossSal - totalDeduct));

    document.getElementById('payslipModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

