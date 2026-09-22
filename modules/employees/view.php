<?php
// modules/employees/view.php - Chi tiết Hồ sơ Nhân sự (Dossier View)
$page_title = 'Chi Tiết Hồ Sơ Cán Bộ';

require_once __DIR__ . '/../../core/auth.php';
require_permission('employees', 'view');

$id = (int)($_GET['id'] ?? 0);

// Truy vấn toàn bộ thông tin nhân viên kèm chi nhánh, phòng ban & chức vụ
$stmt = $pdo->prepare("
    SELECT e.*, 
           b.name AS branch_name, b.code AS branch_code,
           d.name AS department_name, d.code AS department_code, d.description AS department_desc,
           p.name AS position_name, p.base_salary AS position_salary,
           u.username AS linked_username
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    set_flash('danger', 'Hồ sơ nhân viên không tồn tại trong hệ thống.');
    redirect('modules/employees/index.php');
}

// Truy vấn toàn bộ lịch sử thuyên chuyển & luân chuyển công tác của cán bộ này
$transfers_stmt = $pdo->prepare("
    SELECT 
        t.*,
        fb.name AS from_branch_name, fb.code AS from_branch_code,
        tb.name AS to_branch_name, tb.code AS to_branch_code,
        fd.name AS from_dept_name, td.name AS to_dept_name,
        fp.name AS from_pos_name, tp.name AS to_pos_name,
        u_app.fullname AS approver_name
    FROM transfers t
    JOIN branches fb ON t.from_branch_id = fb.id
    JOIN branches tb ON t.to_branch_id = tb.id
    LEFT JOIN departments fd ON t.from_department_id = fd.id
    JOIN departments td ON t.to_department_id = td.id
    LEFT JOIN positions fp ON t.from_position_id = fp.id
    JOIN positions tp ON t.to_position_id = tp.id
    LEFT JOIN users u_app ON t.approved_by = u_app.id
    WHERE t.employee_id = ?
    ORDER BY t.effective_date DESC, t.id DESC
");
$transfers_stmt->execute([$id]);
$transfer_history = $transfers_stmt->fetchAll();

// Tính thâm niên công tác
$hire_date = new DateTime($emp['hire_date']);
$now = new DateTime();
$seniority = $hire_date->diff($now);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Banner Hồ Sơ Cá Nhân -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-center gap-5">
                <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $emp['avatar'])): ?>
                    <img src="<?= base_url('assets/uploads/' . e($emp['avatar'])) ?>" 
                         class="w-20 h-20 rounded-2xl object-cover border-2 border-slate-100 shadow-md" 
                         alt="<?= e($emp['fullname']) ?>">
                <?php else: ?>
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-2xl shadow-md">
                        <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-slate-800"><?= e($emp['fullname']) ?></h2>
                        <?php if ($emp['employment_status'] === 'official'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Chính Thức</span>
                        <?php elseif ($emp['employment_status'] === 'probation'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Đang Thử Việc</span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Đã Nghỉ Việc</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-slate-500 text-xs mt-1">
                        Mã NV: <span class="font-mono font-bold text-indigo-600"><?= e($emp['employee_code']) ?></span> 
                        • Chi nhánh: <strong class="text-indigo-700"><?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?></strong>
                        • Phòng: <strong class="text-slate-700"><?= e($emp['department_name'] ?? 'Chưa phân bổ') ?></strong>
                        • Chức vụ: <strong class="text-slate-700"><?= e($emp['position_name'] ?? 'Chưa bổ nhiệm') ?></strong>
                    </p>
                </div>
            </div>

            <!-- Nút Thao Tác Nhanh -->
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Quay Lại
                </a>
                <?php if (has_permission('transfers', 'create')): ?>
                    <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $emp['id']) ?>" 
                       class="px-4 py-2 rounded-xl bg-violet-50 hover:bg-violet-100 text-violet-700 text-xs font-semibold border border-violet-200 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-people-arrows"></i>
                        <span>Điều Động / Thuyên Chuyển</span>
                    </a>
                <?php endif; ?>
                <?php if (has_permission('employees', 'edit')): ?>
                    <a href="form.php?id=<?= $emp['id'] ?>" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Chỉnh Sửa Hồ Sơ</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3 Chỉ Số Quan Trọng -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6 text-center sm:text-left">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Thâm Niên Công Tác</span>
                <div class="text-base font-bold text-slate-800 mt-1">
                    <?= $seniority->y > 0 ? "{$seniority->y} năm " : "" ?><?= $seniority->m ?> tháng <?= $seniority->d ?> ngày
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Kể từ: <?= format_date($emp['hire_date']) ?></div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Mức Lương Định Ngạch</span>
                <div class="text-base font-bold text-emerald-600 font-mono mt-1">
                    <?= format_money($emp['position_salary'] ?: 0) ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Theo chức danh công tác</div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tài Khoản Đăng Nhập</span>
                <div class="text-base font-bold text-slate-800 mt-1">
                    <?= $emp['linked_username'] ? '@' . e($emp['linked_username']) : 'Chưa kích hoạt tài khoản' ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Cổng dịch vụ nhân viên</div>
            </div>
        </div>
    </div>

    <!-- Khối Chi Tiết 2 Cột -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Khối 1: Thông tin cá nhân & Nhân khẩu học -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
                <i class="fa-regular fa-address-card text-indigo-600"></i>
                <span>Lý Lịch & Nhân Khẩu Học</span>
            </h3>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Giới Tính:</span>
                    <span class="font-semibold text-slate-700"><?= e($emp['gender']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Ngày Sinh:</span>
                    <span class="font-semibold text-slate-700"><?= format_date($emp['birth_date']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Số Căn Cước / CMND:</span>
                    <span class="font-mono font-semibold text-slate-700"><?= e($emp['identity_card'] ?: 'Chưa cập nhật') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Số Điện Thoại:</span>
                    <span class="font-semibold text-slate-700"><?= e($emp['phone'] ?: 'Chưa cập nhật') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Email:</span>
                    <span class="font-semibold text-indigo-600"><?= e($emp['email'] ?: 'Chưa cập nhật') ?></span>
                </div>
                <div class="py-1.5">
                    <span class="text-slate-400 block mb-1">Địa Chỉ Thường Trú / Nơi Ở:</span>
                    <span class="text-slate-700 leading-relaxed"><?= e($emp['address'] ?: 'Chưa có thông tin địa chỉ') ?></span>
                </div>
            </div>
        </div>

        <!-- Khối 2: Thông tin tổ chức & Chế độ làm việc -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
                <i class="fa-solid fa-briefcase text-sky-600"></i>
                <span>Tổ Chức & Chế Độ Làm Việc</span>
            </h3>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Chi Nhánh Công Tác:</span>
                    <span class="font-bold text-indigo-700 flex items-center gap-1">
                        <i class="fa-solid fa-building-flag text-[10px]"></i>
                        <span><?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?></span>
                    </span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Phòng Ban Quản Lý:</span>
                    <span class="font-semibold text-slate-700"><?= e($emp['department_name'] ?? '---') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Mã Khối Phòng Ban:</span>
                    <span class="font-mono font-semibold text-slate-700"><?= e($emp['department_code'] ?? '---') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Chức Danh Hiện Tại:</span>
                    <span class="font-semibold text-slate-700"><?= e($emp['position_name'] ?? '---') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Ngày Ký Hợp Đồng / Vào Làm:</span>
                    <span class="font-semibold text-slate-700"><?= format_date($emp['hire_date']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50">
                    <span class="text-slate-400">Trạng Thái Hợp Đồng:</span>
                    <span class="font-semibold text-slate-700">
                        <?= $emp['employment_status'] === 'official' ? 'Hợp đồng lao động chính thức' : ($emp['employment_status'] === 'probation' ? 'Hợp đồng thử việc' : 'Đã chấm dứt hợp đồng') ?>
                    </span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-400">Thời Điểm Tạo Hồ Sơ:</span>
                    <span class="text-slate-500"><?= format_date($emp['created_at']) ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- KHỐI 3: LỊCH SỬ THUYÊN CHUYỂN & LUÂN CHUYỂN CÔNG TÁC (CAREER TIMELINE) -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-timeline text-indigo-600"></i>
                    <span>Lịch Sử Thuyên Chuyển & Luân Chuyển Công Tác</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Toàn bộ quá trình điều động qua các chi nhánh, thăng chức và luân chuyển công tác của cán bộ</p>
            </div>

            <?php if (has_permission('transfers', 'create')): ?>
                <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $emp['id']) ?>" 
                   class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-indigo-100">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Lập Quyết Định Mới</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($transfer_history)): ?>
            <div class="text-center py-8 text-slate-400 text-xs">
                <i class="fa-solid fa-route text-3xl mb-2 text-slate-300 block"></i>
                Cán bộ này hiện chưa có lịch sử điều động / thuyên chuyển công tác liên chi nhánh.
            </div>
        <?php else: ?>
            <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-indigo-200">
                <?php foreach ($transfer_history as $th): ?>
                    <div class="relative group">
                        <!-- Chấm tròn Timeline -->
                        <div class="absolute -left-[27px] top-1 w-4 h-4 rounded-full border-2 border-white bg-indigo-600 shadow-xs"></div>

                        <div class="bg-slate-50 hover:bg-white rounded-2xl border border-slate-200/80 p-4 transition space-y-2.5">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-indigo-700"><?= e($th['transfer_code']) ?></span>
                                    <span class="text-slate-400">•</span>
                                    <span class="text-xs font-semibold text-slate-700">QĐ: <?= e($th['decision_number'] ?: '---') ?></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $th['status'] === 'executed' ? 'bg-emerald-100 text-emerald-800' : ($th['status'] === 'approved' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800') ?>">
                                        <?= $th['status'] === 'executed' ? 'Đã Thực Thi' : ($th['status'] === 'approved' ? 'Đã Phê Duyệt' : 'Chờ Duyệt') ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Hiệu lực: <strong class="text-slate-800"><?= format_date($th['effective_date']) ?></strong>
                                </div>
                            </div>

                            <!-- Chi tiết lộ trình thuyên chuyển -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-white rounded-xl border border-slate-100 text-xs">
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Đơn Vị Chuyển Đi (Cũ)</span>
                                    <div class="font-bold text-slate-800 mt-0.5"><?= e($th['from_branch_name']) ?></div>
                                    <div class="text-slate-500 text-[11px]"><?= e($th['from_dept_name']) ?> • <?= e($th['from_pos_name']) ?></div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wider block">Đơn Vị Tiếp Nhận (Mới)</span>
                                    <div class="font-bold text-indigo-700 mt-0.5"><?= e($th['to_branch_name']) ?></div>
                                    <div class="text-slate-700 text-[11px] font-medium"><?= e($th['to_dept_name']) ?> • <strong class="text-slate-900"><?= e($th['to_pos_name']) ?></strong></div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-600 gap-2 pt-1">
                                <div class="italic">
                                    <strong>Lý do:</strong> <?= e($th['reason']) ?>
                                    <?php if ($th['allowance_support'] > 0): ?>
                                        • Phụ cấp chuyển vùng: <strong class="text-emerald-600"><?= format_money($th['allowance_support']) ?></strong>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= base_url('modules/transfers/print_decision.php?id=' . $th['id']) ?>" 
                                   target="_blank"
                                   class="text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1 flex-shrink-0">
                                    <i class="fa-solid fa-print"></i> In Quyết Định &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>