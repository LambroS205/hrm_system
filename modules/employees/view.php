<?php
// modules/employees/view.php - Chi tiết Hồ sơ Nhân sự (Dossier View)
$page_title = 'Chi Tiết Hồ Sơ Cán Bộ';

require_once __DIR__ . '/../../core/auth.php';
require_permission('employees', 'view');

$id = (int)($_GET['id'] ?? 0);

// Truy vấn toàn bộ thông tin nhân viên kèm phòng ban & chức vụ
$stmt = $pdo->prepare("
    SELECT e.*, 
           d.name AS department_name, d.code AS department_code, d.description AS department_desc,
           p.name AS position_name, p.base_salary AS position_salary,
           u.username AS linked_username
    FROM employees e
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
                        • Chức vụ: <strong class="text-slate-700"><?= e($emp['position_name'] ?? 'Chưa bổ nhiệm') ?></strong>
                        • Phòng: <strong class="text-slate-700"><?= e($emp['department_name'] ?? 'Chưa phân bổ') ?></strong>
                    </p>
                </div>
            </div>

            <!-- Nút Thao Tác Nhanh -->
            <div class="flex items-center gap-2">
                <a href="index.php" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Quay Lại
                </a>
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

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>