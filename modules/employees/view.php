<?php
// modules/employees/view.php - Chi tiết Hồ sơ Toàn diện Cán bộ Nhân sự (7 Tabs Dossier)
$page_title = 'Hồ Sơ Cán Bộ Chi Tiết';

require_once __DIR__ . '/../../core/auth.php';
require_permission('employees', 'view');

$id = (int)($_GET['id'] ?? 0);

// =========================================================================
// XỬ LÝ CÁC HÀNH ĐỘNG FORM POST (THÊM / XÓA CÁC HẠNG MỤC HỒ SƠ)
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_permission('employees', 'edit');
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $upload_dir = __DIR__ . '/../../assets/uploads/documents/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    // Helper xử lý upload file đính kèm
    $handle_upload = function($file_key) use ($upload_dir) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$file_key];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed) || $file['size'] > 10 * 1024 * 1024) {
            return null;
        }
        $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            return $filename;
        }
        return null;
    };

    // 1. Thêm Trình độ Học vấn
    if ($action === 'add_education') {
        $degree = trim($_POST['degree'] ?? '');
        $institution = trim($_POST['institution'] ?? '');
        $major = trim($_POST['major'] ?? '');
        $grad_year = (int)($_POST['graduation_year'] ?? 0);
        $gpa = trim($_POST['gpa'] ?? '');
        $file_name = $handle_upload('certificate_file');

        if ($degree && $institution && $major && $grad_year > 1950) {
            $stmt = $pdo->prepare("INSERT INTO employee_education (employee_id, degree, institution, major, graduation_year, gpa, certificate_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $degree, $institution, $major, $grad_year, $gpa ?: null, $file_name]);
            set_flash('success', 'Đã lưu thông tin trình độ học vấn thành công.');
        } else {
            set_flash('danger', 'Vui lòng điền đầy đủ bằng cấp, trường, chuyên ngành và năm tốt nghiệp.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=education");
    }

    // 2. Xóa Học vấn
    if ($action === 'delete_education') {
        $edu_id = (int)($_POST['edu_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT certificate_file FROM employee_education WHERE id = ? AND employee_id = ?");
        $stmt->execute([$edu_id, $id]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['certificate_file'] && file_exists($upload_dir . $row['certificate_file'])) {
                @unlink($upload_dir . $row['certificate_file']);
            }
            $pdo->prepare("DELETE FROM employee_education WHERE id = ? AND employee_id = ?")->execute([$edu_id, $id]);
            set_flash('success', 'Đã xóa bản ghi học vấn.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=education");
    }

    // 3. Thêm Chứng chỉ
    if ($action === 'add_certificate') {
        $name = trim($_POST['name'] ?? '');
        $issuer = trim($_POST['issuer'] ?? '');
        $issue_date = trim($_POST['issue_date'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '') ?: null;
        $file_name = $handle_upload('certificate_file');

        if ($name && $issuer && $issue_date) {
            $stmt = $pdo->prepare("INSERT INTO employee_certificates (employee_id, name, issuer, issue_date, expiry_date, certificate_file) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $issuer, $issue_date, $expiry_date, $file_name]);
            set_flash('success', 'Đã thêm chứng chỉ chuyên môn thành công.');
        } else {
            set_flash('danger', 'Vui lòng nhập tên chứng chỉ, cơ quan cấp và ngày cấp.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=education");
    }

    // 4. Xóa Chứng chỉ
    if ($action === 'delete_certificate') {
        $cert_id = (int)($_POST['cert_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT certificate_file FROM employee_certificates WHERE id = ? AND employee_id = ?");
        $stmt->execute([$cert_id, $id]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['certificate_file'] && file_exists($upload_dir . $row['certificate_file'])) {
                @unlink($upload_dir . $row['certificate_file']);
            }
            $pdo->prepare("DELETE FROM employee_certificates WHERE id = ? AND employee_id = ?")->execute([$cert_id, $id]);
            set_flash('success', 'Đã xóa chứng chỉ chuyên môn.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=education");
    }

    // 5. Thêm Hợp đồng Lao động
    if ($action === 'add_contract') {
        $contract_number = trim($_POST['contract_number'] ?? '');
        $contract_type = trim($_POST['contract_type'] ?? 'fixed_term');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '') ?: null;
        $base_salary = (float)($_POST['base_salary'] ?? 0);
        $allowance = (float)($_POST['allowance'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        $notes = trim($_POST['notes'] ?? '');
        $file_name = $handle_upload('contract_file');

        if ($contract_number && $start_date) {
            $stmt = $pdo->prepare("INSERT INTO employee_contracts (employee_id, contract_number, contract_type, start_date, end_date, base_salary, allowance, contract_file, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $contract_number, $contract_type, $start_date, $end_date, $base_salary, $allowance, $file_name, $status, $notes]);
            set_flash('success', 'Đã lưu hợp đồng lao động mới thành công.');
        } else {
            set_flash('danger', 'Vui lòng điền số hợp đồng và ngày bắt đầu hiệu lực.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=contracts");
    }

    // 6. Xóa Hợp đồng
    if ($action === 'delete_contract') {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT contract_file FROM employee_contracts WHERE id = ? AND employee_id = ?");
        $stmt->execute([$contract_id, $id]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['contract_file'] && file_exists($upload_dir . $row['contract_file'])) {
                @unlink($upload_dir . $row['contract_file']);
            }
            $pdo->prepare("DELETE FROM employee_contracts WHERE id = ? AND employee_id = ?")->execute([$contract_id, $id]);
            set_flash('success', 'Đã xóa bản ghi hợp đồng lao động.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=contracts");
    }

    // 7. Thêm Bảo hiểm
    if ($action === 'add_insurance') {
        $insurance_type = trim($_POST['insurance_type'] ?? 'social');
        $insurance_number = trim($_POST['insurance_number'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $hospital_name = trim($_POST['hospital_name'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $notes = trim($_POST['notes'] ?? '');

        if ($insurance_number && $start_date) {
            $stmt = $pdo->prepare("INSERT INTO employee_insurance (employee_id, insurance_type, insurance_number, start_date, hospital_name, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $insurance_type, $insurance_number, $start_date, $hospital_name ?: null, $status, $notes]);
            set_flash('success', 'Đã lưu thông tin bảo hiểm thành công.');
        } else {
            set_flash('danger', 'Vui lòng nhập mã số bảo hiểm và ngày bắt đầu tham gia.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=insurance");
    }

    // 8. Xóa Bảo hiểm
    if ($action === 'delete_insurance') {
        $ins_id = (int)($_POST['insurance_id'] ?? 0);
        $pdo->prepare("DELETE FROM employee_insurance WHERE id = ? AND employee_id = ?")->execute([$ins_id, $id]);
        set_flash('success', 'Đã xóa hồ sơ bảo hiểm.');
        redirect("modules/employees/view.php?id={$id}&tab=insurance");
    }

    // 9. Thêm Người thân / Phụ thuộc
    if ($action === 'add_dependent') {
        $fullname = trim($_POST['fullname'] ?? '');
        $relationship = trim($_POST['relationship'] ?? 'child');
        $birth_date = trim($_POST['birth_date'] ?? '') ?: null;
        $phone = trim($_POST['phone'] ?? '');
        $tax_deductible = isset($_POST['tax_deductible']) ? 1 : 0;
        $is_emergency = isset($_POST['is_emergency_contact']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if ($fullname) {
            $stmt = $pdo->prepare("INSERT INTO employee_dependents (employee_id, fullname, relationship, birth_date, phone, tax_deductible, is_emergency_contact, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $fullname, $relationship, $birth_date, $phone ?: null, $tax_deductible, $is_emergency, $notes]);
            set_flash('success', 'Đã thêm thông tin thân nhân / người phụ thuộc.');
        } else {
            set_flash('danger', 'Vui lòng nhập họ và tên người thân.');
        }
        redirect("modules/employees/view.php?id={$id}&tab=dependents");
    }

    // 10. Xóa Người thân
    if ($action === 'delete_dependent') {
        $dep_id = (int)($_POST['dependent_id'] ?? 0);
        $pdo->prepare("DELETE FROM employee_dependents WHERE id = ? AND employee_id = ?")->execute([$dep_id, $id]);
        set_flash('success', 'Đã xóa người thân / người phụ thuộc.');
        redirect("modules/employees/view.php?id={$id}&tab=dependents");
    }
}

// =========================================================================
// TRUY VẤN DỮ LIỆU CÁN BỘ VÀ TẤT CẢ PHÂN HỆ LIÊN QUAN
// =========================================================================

// 1. Thông tin cơ bản
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

// 2. Học vấn & Chứng chỉ
$edu_stmt = $pdo->prepare("SELECT * FROM employee_education WHERE employee_id = ? ORDER BY graduation_year DESC, id DESC");
$edu_stmt->execute([$id]);
$employee_education = $edu_stmt->fetchAll();

$cert_stmt = $pdo->prepare("SELECT * FROM employee_certificates WHERE employee_id = ? ORDER BY issue_date DESC, id DESC");
$cert_stmt->execute([$id]);
$employee_certificates = $cert_stmt->fetchAll();

// 3. Hợp đồng lao động
$contract_stmt = $pdo->prepare("SELECT * FROM employee_contracts WHERE employee_id = ? ORDER BY start_date DESC, id DESC");
$contract_stmt->execute([$id]);
$employee_contracts = $contract_stmt->fetchAll();

// 4. Bảo hiểm
$ins_stmt = $pdo->prepare("SELECT * FROM employee_insurance WHERE employee_id = ? ORDER BY start_date DESC, id DESC");
$ins_stmt->execute([$id]);
$employee_insurance = $ins_stmt->fetchAll();

// 5. Người phụ thuộc / Thân nhân
$dep_stmt = $pdo->prepare("SELECT * FROM employee_dependents WHERE employee_id = ? ORDER BY is_emergency_contact DESC, tax_deductible DESC, id ASC");
$dep_stmt->execute([$id]);
$employee_dependents = $dep_stmt->fetchAll();

// Đếm số người giảm trừ gia cảnh & người khẩn cấp
$tax_deductible_count = 0;
$emergency_contacts = [];
foreach ($employee_dependents as $d) {
    if ($d['tax_deductible']) $tax_deductible_count++;
    if ($d['is_emergency_contact']) $emergency_contacts[] = $d;
}

// 6. Khen thưởng & Kỷ luật
$rewards_stmt = $pdo->prepare("
    SELECT r.*, u_app.fullname AS approver_name
    FROM rewards r
    LEFT JOIN users u_app ON r.approved_by = u_app.id
    WHERE r.employee_id = ?
    ORDER BY r.reward_date DESC, r.id DESC
");
$rewards_stmt->execute([$id]);
$employee_rewards = $rewards_stmt->fetchAll();

$disciplines_stmt = $pdo->prepare("
    SELECT d.*, u_app.fullname AS approver_name
    FROM disciplines d
    LEFT JOIN users u_app ON d.approved_by = u_app.id
    WHERE d.employee_id = ?
    ORDER BY d.discipline_date DESC, d.id DESC
");
$disciplines_stmt->execute([$id]);
$employee_disciplines = $disciplines_stmt->fetchAll();

$total_reward_amount = 0;
foreach ($employee_rewards as $er) {
    if (in_array($er['status'], ['approved', 'executed'])) {
        $total_reward_amount += (float)$er['amount'];
    }
}
$total_penalty_amount = 0;
foreach ($employee_disciplines as $ed) {
    if (in_array($ed['status'], ['approved', 'executed'])) {
        $total_penalty_amount += (float)$ed['penalty_amount'];
    }
}

// 7. Lịch sử thuyên chuyển
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

// Tab đang được chọn
$active_tab = $_GET['tab'] ?? 'general';
$valid_tabs = ['general', 'education', 'contracts', 'insurance', 'dependents', 'rewards', 'transfers'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'general';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6 pb-12">

    <!-- Top Banner Hồ Sơ Cán Bộ -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs p-6 sm:p-8 transition-colors">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-5">
                <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $emp['avatar'])): ?>
                    <img src="<?= base_url('assets/uploads/' . e($emp['avatar'])) ?>" 
                         class="w-20 h-20 rounded-2xl object-cover border-2 border-white dark:border-slate-700 shadow-md ring-4 ring-indigo-50 dark:ring-indigo-950/40" 
                         alt="<?= e($emp['fullname']) ?>">
                <?php else: ?>
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-3xl shadow-md ring-4 ring-indigo-50 dark:ring-indigo-950/40">
                        <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight"><?= e($emp['fullname']) ?></h2>
                        <?php if ($emp['employment_status'] === 'official'): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">Chính Thức</span>
                        <?php elseif ($emp['employment_status'] === 'probation'): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">Đang Thử Việc</span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">Đã Nghỉ Việc</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1.5 flex items-center gap-2 flex-wrap">
                        <span>Mã NV: <strong class="font-mono text-indigo-600 dark:text-indigo-400"><?= e($emp['employee_code']) ?></strong></span>
                        <span>•</span>
                        <span>Chi nhánh: <strong class="text-slate-800 dark:text-slate-200"><?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?></strong></span>
                        <span>•</span>
                        <span>Phòng: <strong class="text-slate-800 dark:text-slate-200"><?= e($emp['department_name'] ?? 'Chưa phân bổ') ?></strong></span>
                        <span>•</span>
                        <span>Chức danh: <strong class="text-indigo-700 dark:text-indigo-300"><?= e($emp['position_name'] ?? 'Chưa bổ nhiệm') ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Nút Thao Tác Nhanh -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="index.php" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Danh Sách
                </a>
                <?php if (has_permission('rewards', 'create')): ?>
                    <a href="<?= base_url('modules/rewards/form.php?employee_id=' . $emp['id']) ?>" 
                       class="px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold border border-emerald-200 dark:border-emerald-800 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-award"></i> Khen Thưởng
                    </a>
                <?php endif; ?>
                <?php if (has_permission('disciplines', 'create')): ?>
                    <a href="<?= base_url('modules/disciplines/form.php?employee_id=' . $emp['id']) ?>" 
                       class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 text-xs font-semibold border border-rose-200 dark:border-rose-800 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-scale-unbalanced"></i> Kỷ Luật
                    </a>
                <?php endif; ?>
                <?php if (has_permission('transfers', 'create')): ?>
                    <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $emp['id']) ?>" 
                       class="px-3.5 py-2 rounded-xl bg-violet-50 hover:bg-violet-100 dark:bg-violet-950/40 dark:hover:bg-violet-900/60 text-violet-700 dark:text-violet-300 text-xs font-semibold border border-violet-200 dark:border-violet-800 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-people-arrows"></i> Thuyên Chuyển
                    </a>
                <?php endif; ?>
                <?php if (has_permission('employees', 'edit')): ?>
                    <a href="form.php?id=<?= $emp['id'] ?>" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <i class="fa-solid fa-pen-to-square"></i> Sửa Cơ Bản
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4 Chỉ Số Tóm Tắt Nhanh -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-6">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider block">Thâm Niên Công Tác</span>
                <div class="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-100 mt-1">
                    <?= $seniority->y > 0 ? "{$seniority->y} năm " : "" ?><?= $seniority->m ?> tháng <?= $seniority->d ?> ngày
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Từ: <?= format_date($emp['hire_date']) ?></div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider block">Lương Định Ngạch</span>
                <div class="text-sm sm:text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-1">
                    <?= format_money($emp['position_salary'] ?: 0) ?>
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Theo ngạch chức danh</div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider block">Giảm Trừ Gia Cảnh</span>
                <div class="text-sm sm:text-base font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                    <?= $tax_deductible_count ?> người phụ thuộc
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">-<?= format_money($tax_deductible_count * 4400000) ?> / tháng</div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider block">Tài Khoản Hệ Thống</span>
                <div class="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-100 mt-1 truncate">
                    <?= $emp['linked_username'] ? '@' . e($emp['linked_username']) : '<span class="text-slate-400 text-xs italic">Chưa kích hoạt</span>' ?>
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Quyền truy cập cổng NV</div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         THANH ĐIỀU HƯỚNG 7 TABS HỒ SƠ CHUYÊN SÂU
         ========================================================================= -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-1.5 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-center gap-1 overflow-x-auto transition-colors">
        <button type="button" onclick="switchProfileTab('general')" id="tabBtn_general" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'general' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-id-card"></i>
            <span>Lý Lịch & Tổ Chức</span>
        </button>

        <button type="button" onclick="switchProfileTab('education')" id="tabBtn_education" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'education' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Học Vấn & Chứng Chỉ</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'education' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($employee_education) + count($employee_certificates) ?>
            </span>
        </button>

        <button type="button" onclick="switchProfileTab('contracts')" id="tabBtn_contracts" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'contracts' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-file-contract"></i>
            <span>Hợp Đồng Lao Động</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'contracts' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($employee_contracts) ?>
            </span>
        </button>

        <button type="button" onclick="switchProfileTab('insurance')" id="tabBtn_insurance" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'insurance' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-shield-heart"></i>
            <span>Bảo Hiểm & Y Tế</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'insurance' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($employee_insurance) ?>
            </span>
        </button>

        <button type="button" onclick="switchProfileTab('dependents')" id="tabBtn_dependents" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'dependents' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-people-roof"></i>
            <span>Thân Nhân & Phụ Thuộc</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'dependents' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($employee_dependents) ?>
            </span>
        </button>

        <button type="button" onclick="switchProfileTab('rewards')" id="tabBtn_rewards" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'rewards' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-award"></i>
            <span>Khen Thưởng & Kỷ Luật</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'rewards' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($employee_rewards) + count($employee_disciplines) ?>
            </span>
        </button>

        <button type="button" onclick="switchProfileTab('transfers')" id="tabBtn_transfers" 
                class="tab-nav-btn flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'transfers' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' ?>">
            <i class="fa-solid fa-people-arrows"></i>
            <span>Quá Trình Thuyên Chuyển</span>
            <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'transfers' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' ?>">
                <?= count($transfer_history) ?>
            </span>
        </button>
    </div>

    <!-- =========================================================================
         NỘI DUNG CHI TIẾT TỪNG TAB
         ========================================================================= -->

    <!-- TAB 1: THÔNG TIN LÝ LỊCH & TỔ CHỨC -->
    <div id="tabContent_general" class="tab-panel-section <?= $active_tab === 'general' ? '' : 'hidden' ?> space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Cột 1: Thông tin nhân khẩu & lý lịch -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-4">
                <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <i class="fa-regular fa-address-card text-indigo-600 dark:text-indigo-400"></i>
                    <span>Lý Lịch & Nhân Khẩu Học</span>
                </h3>

                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Giới Tính:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['gender']) ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Ngày Sinh:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= format_date($emp['birth_date']) ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Số Căn Cước / CMND:</span>
                        <span class="font-mono font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['identity_card'] ?: 'Chưa cập nhật') ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Số Điện Thoại:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['phone'] ?: 'Chưa cập nhật') ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Email Cá Nhân/Công Việc:</span>
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400"><?= e($emp['email'] ?: 'Chưa cập nhật') ?></span>
                    </div>
                    <div class="py-1.5">
                        <span class="text-slate-400 block mb-1">Địa Chỉ Thường Trú / Nơi Ở Hiện Tại:</span>
                        <span class="text-slate-700 dark:text-slate-300 leading-relaxed"><?= e($emp['address'] ?: 'Chưa có thông tin địa chỉ') ?></span>
                    </div>
                </div>
            </div>

            <!-- Cột 2: Tổ chức & Chế độ làm việc -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-4">
                <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <i class="fa-solid fa-briefcase text-sky-600 dark:text-sky-400"></i>
                    <span>Tổ Chức & Chế Độ Làm Việc</span>
                </h3>

                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Chi Nhánh Trực Thuộc:</span>
                        <span class="font-bold text-indigo-700 dark:text-indigo-300 flex items-center gap-1">
                            <i class="fa-solid fa-building-flag text-[10px]"></i>
                            <span><?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?></span>
                        </span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Phòng Ban Quản Lý:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['department_name'] ?? '---') ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Mã Khối Phòng:</span>
                        <span class="font-mono font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['department_code'] ?? '---') ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Chức Danh Công Tác:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e($emp['position_name'] ?? '---') ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Ngày Ký HĐ Đầu Tiên / Vào Làm:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= format_date($emp['hire_date']) ?></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-700/50">
                        <span class="text-slate-400">Trạng Thái Hợp Đồng:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200">
                            <?= $emp['employment_status'] === 'official' ? 'Hợp đồng lao động chính thức' : ($emp['employment_status'] === 'probation' ? 'Hợp đồng thử việc' : 'Đã chấm dứt hợp đồng') ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-400">Thời Điểm Khởi Tạo:</span>
                        <span class="text-slate-500 dark:text-slate-400"><?= format_date($emp['created_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: TRÌNH ĐỘ HỌC VẤN & CHỨNG CHỈ NGHỀ NGHIỆP -->
    <div id="tabContent_education" class="tab-panel-section <?= $active_tab === 'education' ? '' : 'hidden' ?> space-y-6">
        
        <!-- PHẦN 1: HỌC VẤN & BẰNG CẤP CHÍNH QUY -->
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-indigo-600 dark:text-indigo-400"></i>
                        <span>Trình Độ Học Vấn & Bằng Cấp Đào Tạo</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Danh sách các văn bằng đại học, cao đẳng, thạc sĩ, tiến sĩ đã được thẩm định</p>
                </div>

                <?php if (has_permission('employees', 'edit')): ?>
                    <button type="button" onclick="openModal('modalAddEducation')" 
                            class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-indigo-100 dark:border-indigo-800">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ Thêm Bằng Cấp</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($employee_education)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-graduation-cap text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Chưa có thông tin trình độ học vấn nào được ghi nhận cho cán bộ này.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($employee_education as $edu): ?>
                        <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 inline-block">
                                        <?= e($edu['degree']) ?>
                                    </span>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1.5"><?= e($edu['institution']) ?></h4>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">Chuyên ngành: <strong class="text-slate-800 dark:text-slate-100"><?= e($edu['major']) ?></strong></p>
                                </div>
                                <?php if (has_permission('employees', 'edit')): ?>
                                    <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bằng cấp này?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_education">
                                        <input type="hidden" name="edu_id" value="<?= $edu['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs transition" title="Xóa bằng cấp">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200/60 dark:border-slate-600/60">
                                <span>Tốt nghiệp năm: <strong class="text-slate-800 dark:text-slate-200 font-mono"><?= $edu['graduation_year'] ?></strong></span>
                                <?php if ($edu['gpa']): ?>
                                    <span>Xếp loại: <strong class="text-emerald-600 dark:text-emerald-400"><?= e($edu['gpa']) ?></strong></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($edu['certificate_file'])): ?>
                                <div class="pt-1">
                                    <a href="<?= base_url('assets/uploads/documents/' . e($edu['certificate_file'])) ?>" target="_blank" 
                                       class="inline-flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span>Xem tệp scan văn bằng</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- PHẦN 2: CHỨNG CHỈ CHUYÊN MÔN & NGOẠI NGỮ -->
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-certificate text-amber-500"></i>
                        <span>Chứng Chỉ Chuyên Môn & Ngoại Ngữ</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Các chứng chỉ chuyên môn quốc tế, nghiệp vụ và năng lực ngoại ngữ</p>
                </div>

                <?php if (has_permission('employees', 'edit')): ?>
                    <button type="button" onclick="openModal('modalAddCertificate')" 
                            class="px-3.5 py-1.5 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/60 text-amber-800 dark:text-amber-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-amber-200 dark:border-amber-800">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ Thêm Chứng Chỉ</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($employee_certificates)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-certificate text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Chưa có chứng chỉ nghề nghiệp nào được cập nhật.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($employee_certificates as $cert): 
                        $is_expired = $cert['expiry_date'] && (strtotime($cert['expiry_date']) < time());
                    ?>
                        <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?= e($cert['name']) ?></h4>
                                        <?php if ($cert['expiry_date']): ?>
                                            <?php if ($is_expired): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Đã Hết Hạn</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Còn Hiệu Lực</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">Vĩnh Viễn</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Cơ quan cấp: <strong class="text-slate-700 dark:text-slate-200"><?= e($cert['issuer']) ?></strong></p>
                                </div>
                                <?php if (has_permission('employees', 'edit')): ?>
                                    <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa chứng chỉ này?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_certificate">
                                        <input type="hidden" name="cert_id" value="<?= $cert['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs transition" title="Xóa chứng chỉ">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200/60 dark:border-slate-600/60">
                                <span>Ngày cấp: <strong class="text-slate-700 dark:text-slate-200 font-mono"><?= format_date($cert['issue_date']) ?></strong></span>
                                <span>Hết hạn: <strong class="font-mono <?= $is_expired ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200' ?>"><?= $cert['expiry_date'] ? format_date($cert['expiry_date']) : 'Vô thời hạn' ?></strong></span>
                            </div>

                            <?php if (!empty($cert['certificate_file'])): ?>
                                <div class="pt-1">
                                    <a href="<?= base_url('assets/uploads/documents/' . e($cert['certificate_file'])) ?>" target="_blank" 
                                       class="inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span>Xem chứng chỉ scan</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- TAB 3: HỢP ĐỒNG LAO ĐỘNG (CONTRACTS) -->
    <div id="tabContent_contracts" class="tab-panel-section <?= $active_tab === 'contracts' ? '' : 'hidden' ?> space-y-6">
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-file-signature text-indigo-600 dark:text-indigo-400"></i>
                        <span>Quá Trình Ký Kết Hợp Đồng Lao Động</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Theo dõi chi tiết hợp đồng thử việc, xác định thời hạn và hợp đồng vô thời hạn</p>
                </div>

                <?php if (has_permission('employees', 'edit')): ?>
                    <button type="button" onclick="openModal('modalAddContract')" 
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-xs">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ Ký Hợp Đồng Mới</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($employee_contracts)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-file-circle-xmark text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Cán bộ chưa có hồ sơ hợp đồng lao động nào được lưu trữ.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($employee_contracts as $ctr): 
                        $type_label = [
                            'probation' => 'Hợp Đồng Thử Việc',
                            'fixed_term' => 'HĐ Xác Định Thời Hạn',
                            'indefinite' => 'HĐ Không Xác Định Thời Hạn',
                            'seasonal' => 'HĐ Theo Mùa Vụ / Khoán'
                        ][$ctr['contract_type']] ?? $ctr['contract_type'];

                        $status_badge = [
                            'active' => '<span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Đang Hiệu Lực</span>',
                            'expired' => '<span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">Đã Hết Hạn</span>',
                            'terminated' => '<span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">Đã Chấm Dứt</span>'
                        ][$ctr['status']] ?? $ctr['status'];
                    ?>
                        <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="font-mono text-xs font-extrabold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 px-2.5 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                        <?= e($ctr['contract_number']) ?>
                                    </span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-white"><?= $type_label ?></span>
                                    <?= $status_badge ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($ctr['contract_file'])): ?>
                                        <a href="<?= base_url('assets/uploads/documents/' . e($ctr['contract_file'])) ?>" target="_blank" 
                                           class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-indigo-600 dark:text-indigo-400 border border-slate-200 dark:border-slate-600 text-xs font-semibold flex items-center gap-1 transition">
                                            <i class="fa-solid fa-file-pdf"></i> Tệp Scan HĐ
                                        </a>
                                    <?php endif; ?>
                                    <?php if (has_permission('employees', 'edit')): ?>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bản hợp đồng này?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_contract">
                                            <input type="hidden" name="contract_id" value="<?= $ctr['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs transition" title="Xóa hợp đồng">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lưới thông tin chi tiết hợp đồng -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-white dark:bg-slate-800/80 rounded-xl border border-slate-100 dark:border-slate-700 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Ngày Hiệu Lực</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono mt-0.5 block"><?= format_date($ctr['start_date']) ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Ngày Kết Thúc</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono mt-0.5 block"><?= $ctr['end_date'] ? format_date($ctr['end_date']) : 'Vô thời hạn' ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Lương Cơ Bản</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-0.5 block"><?= format_money($ctr['base_salary']) ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Phụ Cấp Thỏa Thuận</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono mt-0.5 block"><?= format_money($ctr['allowance']) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($ctr['notes'])): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400 italic">
                                    <strong class="not-italic text-slate-700 dark:text-slate-300">Ghi chú điều khoản:</strong> <?= e($ctr['notes']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 4: BẢO HIỂM XÃ HỘI & Y TẾ (INSURANCE) -->
    <div id="tabContent_insurance" class="tab-panel-section <?= $active_tab === 'insurance' ? '' : 'hidden' ?> space-y-6">
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-shield-heart text-rose-500"></i>
                        <span>Chế Độ Bảo Hiểm Bắt Buộc & Tự Nguyện</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Quản lý sổ BHXH, thẻ BHYT, bảo hiểm thất nghiệp và các gói sức khỏe VIP doanh nghiệp</p>
                </div>

                <?php if (has_permission('employees', 'edit')): ?>
                    <button type="button" onclick="openModal('modalAddInsurance')" 
                            class="px-3.5 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-rose-200 dark:border-rose-800">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ Đăng Ký Bảo Hiểm</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($employee_insurance)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-heart-pulse text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Cán bộ chưa đăng ký thông tin bảo hiểm trên hệ thống.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($employee_insurance as $ins): 
                        $type_meta = [
                            'social' => ['label' => 'Bảo Hiểm Xã Hội (BHXH)', 'icon' => 'fa-id-badge', 'color' => 'indigo'],
                            'health' => ['label' => 'Bảo Hiểm Y Tế (BHYT)', 'icon' => 'fa-hospital-user', 'color' => 'emerald'],
                            'unemployment' => ['label' => 'Bảo Hiểm Thất Nghiệp (BHTN)', 'icon' => 'fa-user-shield', 'color' => 'amber'],
                            'commercial' => ['label' => 'Bảo Hiểm Sức Khỏe Cao Cấp', 'icon' => 'fa-hand-holding-heart', 'color' => 'purple'],
                            'accident' => ['label' => 'Bảo Hiểm Tai Nạn Lao Động', 'icon' => 'fa-shield-halved', 'color' => 'sky'],
                        ][$ins['insurance_type']] ?? ['label' => $ins['insurance_type'], 'icon' => 'fa-shield', 'color' => 'slate'];
                    ?>
                        <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-<?= $type_meta['color'] ?>-100 dark:bg-<?= $type_meta['color'] ?>-950/60 text-<?= $type_meta['color'] ?>-600 dark:text-<?= $type_meta['color'] ?>-300 flex items-center justify-center text-base">
                                        <i class="fa-solid <?= $type_meta['icon'] ?>"></i>
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block"><?= $type_meta['label'] ?></span>
                                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 block"><?= e($ins['insurance_number']) ?></span>
                                    </div>
                                </div>
                                <?php if (has_permission('employees', 'edit')): ?>
                                    <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bảo hiểm này?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_insurance">
                                        <input type="hidden" name="insurance_id" value="<?= $ins['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs transition" title="Xóa bảo hiểm">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 pt-2 border-t border-slate-200/60 dark:border-slate-600/60">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Ngày Bắt Đầu:</span>
                                    <span class="font-mono font-semibold"><?= format_date($ins['start_date']) ?></span>
                                </div>
                                <?php if ($ins['hospital_name']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">KCB Ban Đầu:</span>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200 text-right max-w-[200px] truncate" title="<?= e($ins['hospital_name']) ?>"><?= e($ins['hospital_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Trạng Thái:</span>
                                    <span class="font-semibold <?= $ins['status'] === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' ?>">
                                        <?= $ins['status'] === 'active' ? 'Đang Đóng / Hoạt Động' : ($ins['status'] === 'suspended' ? 'Tạm Hoãn' : 'Đã Đóng Sổ') ?>
                                    </span>
                                </div>
                                <?php if ($ins['notes']): ?>
                                    <div class="pt-1 text-[11px] text-slate-500 dark:text-slate-400 italic">
                                        * <?= e($ins['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 5: THÂN NHÂN & NGƯỜI PHỤ THUỘC (DEPENDENTS) -->
    <div id="tabContent_dependents" class="tab-panel-section <?= $active_tab === 'dependents' ? '' : 'hidden' ?> space-y-6">
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-people-roof text-teal-600 dark:text-teal-400"></i>
                        <span>Quan Hệ Thân Nhân & Giảm Trừ Gia Cảnh</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Theo dõi hồ sơ người phụ thuộc tính thuế TNCN và danh sách liên hệ khẩn cấp</p>
                </div>

                <?php if (has_permission('employees', 'edit')): ?>
                    <button type="button" onclick="openModal('modalAddDependent')" 
                            class="px-3.5 py-1.5 bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/40 dark:hover:bg-teal-900/60 text-teal-800 dark:text-teal-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-teal-200 dark:border-teal-800">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ Thêm Thân Nhân</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Tóm tắt giảm trừ gia cảnh -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-teal-50/60 dark:bg-teal-950/30 border border-teal-100 dark:border-teal-800/60 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-teal-700 dark:text-teal-300 uppercase tracking-wider block">Người Phụ Thuộc Giảm Trừ Thuế</span>
                        <div class="text-2xl font-extrabold text-teal-900 dark:text-teal-100 mt-1"><?= $tax_deductible_count ?> người</div>
                        <div class="text-xs text-teal-700 dark:text-teal-300 font-mono mt-0.5 font-bold">
                            Mức giảm trừ: <?= format_money($tax_deductible_count * 4400000) ?> / tháng
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-teal-100 dark:bg-teal-900/60 text-teal-600 dark:text-teal-300 flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-800/60 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider block">Người Liên Hệ Khẩn Cấp</span>
                        <div class="text-2xl font-extrabold text-indigo-900 dark:text-indigo-100 mt-1"><?= count($emergency_contacts) ?> người</div>
                        <div class="text-xs text-indigo-600 dark:text-indigo-300 mt-0.5">
                            <?= !empty($emergency_contacts) ? e($emergency_contacts[0]['fullname']) . ' (' . e($emergency_contacts[0]['phone'] ?: '---') . ')' : 'Chưa có thông tin' ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300 flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-phone-volume"></i>
                    </div>
                </div>
            </div>

            <?php if (empty($employee_dependents)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-people-arrows text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Chưa có thông tin thân nhân / người phụ thuộc nào.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($employee_dependents as $dep): 
                        $rel_map = [
                            'spouse' => 'Vợ / Chồng',
                            'child' => 'Con Ruột / Con Nuôi',
                            'parent' => 'Bố / Mẹ',
                            'sibling' => 'Anh / Chị / Em',
                            'other' => 'Khác'
                        ];
                    ?>
                        <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?= e($dep['fullname']) ?></h4>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                            <?= $rel_map[$dep['relationship']] ?? $dep['relationship'] ?>
                                        </span>
                                        <?php if ($dep['is_emergency_contact']): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 flex items-center gap-1">
                                                <i class="fa-solid fa-phone text-[8px]"></i> Khẩn Cấp
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($dep['tax_deductible']): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">
                                                Giảm Trừ Thuế
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        <?php if ($dep['birth_date']): ?>
                                            <span>Sinh ngày: <strong class="text-slate-700 dark:text-slate-200"><?= format_date($dep['birth_date']) ?></strong></span>
                                        <?php endif; ?>
                                        <?php if ($dep['phone']): ?>
                                            <span class="ml-2">SĐT: <strong class="text-indigo-600 dark:text-indigo-400 font-mono"><?= e($dep['phone']) ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (has_permission('employees', 'edit')): ?>
                                    <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa bản ghi thân nhân này?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_dependent">
                                        <input type="hidden" name="dependent_id" value="<?= $dep['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs transition" title="Xóa thân nhân">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($dep['notes'])): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200/60 dark:border-slate-600/60 italic">
                                    <?= e($dep['notes']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 6: KHEN THƯỞNG & KỶ LUẬT (REWARDS & DISCIPLINES) -->
    <div id="tabContent_rewards" class="tab-panel-section <?= $active_tab === 'rewards' ? '' : 'hidden' ?> space-y-6">
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-medal text-emerald-600 dark:text-emerald-400"></i>
                        <span>Lịch Sử Khen Thưởng & Kỷ Luật Cán Bộ</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Toàn bộ quyết định vinh danh thành tích, sáng kiến và các biên bản xử lý vi phạm</p>
                </div>

                <div class="flex items-center gap-2">
                    <?php if (has_permission('rewards', 'create')): ?>
                        <a href="<?= base_url('modules/rewards/form.php?employee_id=' . $emp['id']) ?>" 
                           class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-emerald-100 dark:border-emerald-800">
                            <i class="fa-solid fa-award text-[10px]"></i>
                            <span>+ Khen Thưởng</span>
                        </a>
                    <?php endif; ?>
                    <?php if (has_permission('disciplines', 'create')): ?>
                        <a href="<?= base_url('modules/disciplines/form.php?employee_id=' . $emp['id']) ?>" 
                           class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-rose-100 dark:border-rose-800">
                            <i class="fa-solid fa-scale-unbalanced text-[10px]"></i>
                            <span>+ Lập Kỷ Luật</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2 Thẻ Tóm Tắt Khen Thưởng / Kỷ Luật -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/30 border border-emerald-100/80 dark:border-emerald-800/60 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Khen Thưởng Đã Nhận</span>
                        <div class="text-xl font-extrabold text-emerald-800 dark:text-emerald-200 mt-1">
                            <?= count($employee_rewards) ?> lần vinh danh
                        </div>
                        <div class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5 font-mono font-bold">
                            Tổng tiền thưởng: <?= format_money($total_reward_amount) ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-rose-50/50 dark:bg-rose-950/30 border border-rose-100/80 dark:border-rose-800/60 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider block">Kỷ Luật & Vi Phạm</span>
                        <div class="text-xl font-extrabold text-rose-800 dark:text-rose-200 mt-1">
                            <?= count($employee_disciplines) ?> biên bản xử lý
                        </div>
                        <div class="text-xs text-rose-700 dark:text-rose-300 mt-0.5 font-mono font-bold">
                            Tổng khấu trừ: <?= format_money($total_penalty_amount) ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
            </div>

            <!-- Subtabs Khen Thưởng vs Kỷ Luật -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                    <button type="button" id="tabRewardsBtn" onclick="switchDossierSubTab('rewards')" 
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-emerald-600 text-white shadow-xs">
                        <i class="fa-solid fa-award"></i>
                        <span>Danh Sách Khen Thưởng (<?= count($employee_rewards) ?>)</span>
                    </button>
                    <button type="button" id="tabDisciplinesBtn" onclick="switchDossierSubTab('disciplines')" 
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60">
                        <i class="fa-solid fa-scale-unbalanced"></i>
                        <span>Biên Bản Kỷ Luật (<?= count($employee_disciplines) ?>)</span>
                    </button>
                </div>

                <!-- Panel 1: Lịch Sử Khen Thưởng -->
                <div id="panelRewards" class="space-y-3">
                    <?php if (empty($employee_rewards)): ?>
                        <div class="text-center py-8 text-slate-400 text-xs">
                            <i class="fa-solid fa-award text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                            Chưa có quyết định khen thưởng nào được ghi nhận cho cán bộ này.
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($employee_rewards as $rew): ?>
                                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300"><?= e($rew['reward_code']) ?></span>
                                            <span class="text-slate-300 dark:text-slate-600">•</span>
                                            <span class="text-xs font-bold text-slate-800 dark:text-white"><?= e($rew['title']) ?></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $rew['status'] === 'executed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($rew['status'] === 'approved' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') ?>">
                                                <?= $rew['status'] === 'executed' ? 'Đã Thực Thi' : ($rew['status'] === 'approved' ? 'Đã Phê Duyệt' : 'Chờ Duyệt') ?>
                                            </span>
                                        </div>
                                        <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            +<?= format_money($rew['amount']) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed italic">
                                        <?= e($rew['description'] ?: 'Khen thưởng theo thành tích công tác định kỳ.') ?>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-700/60">
                                        <span>QĐ: <strong><?= e($rew['decision_number'] ?: '---') ?></strong> • Ngày: <?= format_date($rew['reward_date']) ?></span>
                                        <?php if (!empty($rew['attachment'])): ?>
                                            <a href="<?= base_url('assets/uploads/attachments/' . e($rew['attachment'])) ?>" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-1">
                                                <i class="fa-solid fa-paperclip"></i> Xem Tệp Scan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Panel 2: Lịch Sử Kỷ Luật -->
                <div id="panelDisciplines" class="hidden space-y-3">
                    <?php if (empty($employee_disciplines)): ?>
                        <div class="text-center py-8 text-slate-400 text-xs">
                            <i class="fa-solid fa-shield-halved text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                            Cán bộ chấp hành tốt mọi nội quy, không có biên bản vi phạm kỷ luật nào.
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($employee_disciplines as $disc): ?>
                                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 transition space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-xs font-bold text-rose-700 dark:text-rose-300"><?= e($disc['discipline_code']) ?></span>
                                            <span class="text-slate-300 dark:text-slate-600">•</span>
                                            <span class="text-xs font-bold text-slate-800 dark:text-white"><?= e($disc['title']) ?></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $disc['status'] === 'executed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($disc['status'] === 'approved' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') ?>">
                                                <?= $disc['status'] === 'executed' ? 'Đã Thi Hành' : ($disc['status'] === 'approved' ? 'Đã Phê Duyệt' : 'Chờ Xử Lý') ?>
                                            </span>
                                        </div>
                                        <?php if ($disc['penalty_amount'] > 0): ?>
                                            <span class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400">
                                                -<?= format_money($disc['penalty_amount']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">Không phạt tiền</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed italic">
                                        <?= e($disc['description'] ?: 'Biên bản xử lý vi phạm nội bộ.') ?>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-700/60">
                                        <span>Biên bản số: <strong><?= e($disc['decision_number'] ?: '---') ?></strong> • Ngày: <?= format_date($disc['discipline_date']) ?></span>
                                        <?php if (!empty($disc['attachment'])): ?>
                                            <a href="<?= base_url('assets/uploads/attachments/' . e($disc['attachment'])) ?>" target="_blank" class="text-rose-600 dark:text-rose-400 hover:underline font-semibold flex items-center gap-1">
                                                <i class="fa-solid fa-paperclip"></i> Xem Tệp Scan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 7: LỊCH SỬ THUYÊN CHUYỂN & LUÂN CHUYỂN CÔNG TÁC -->
    <div id="tabContent_transfers" class="tab-panel-section <?= $active_tab === 'transfers' ? '' : 'hidden' ?> space-y-6">
        <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-timeline text-indigo-600 dark:text-indigo-400"></i>
                        <span>Quá Trình Thuyên Chuyển & Luân Chuyển Công Tác</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Toàn bộ quá trình điều động qua các chi nhánh, thăng chức và luân chuyển công tác của cán bộ</p>
                </div>

                <?php if (has_permission('transfers', 'create')): ?>
                    <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $emp['id']) ?>" 
                       class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 border border-indigo-100 dark:border-indigo-800">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>Lập Quyết Định Mới</span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($transfer_history)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-route text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Cán bộ này hiện chưa có lịch sử điều động / thuyên chuyển công tác liên chi nhánh.
                </div>
            <?php else: ?>
                <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-indigo-200 dark:before:bg-indigo-900">
                    <?php foreach ($transfer_history as $th): ?>
                        <div class="relative group">
                            <!-- Chấm tròn Timeline -->
                            <div class="absolute -left-[27px] top-1 w-4 h-4 rounded-full border-2 border-white dark:border-slate-800 bg-indigo-600 shadow-xs"></div>

                            <div class="bg-slate-50/70 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/60 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 p-4 transition space-y-2.5">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-indigo-700 dark:text-indigo-300"><?= e($th['transfer_code']) ?></span>
                                        <span class="text-slate-400">•</span>
                                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">QĐ: <?= e($th['decision_number'] ?: '---') ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $th['status'] === 'executed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($th['status'] === 'approved' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') ?>">
                                            <?= $th['status'] === 'executed' ? 'Đã Thực Thi' : ($th['status'] === 'approved' ? 'Đã Phê Duyệt' : 'Chờ Duyệt') ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                                        Hiệu lực: <strong class="text-slate-800 dark:text-slate-200"><?= format_date($th['effective_date']) ?></strong>
                                    </div>
                                </div>

                                <!-- Chi tiết lộ trình thuyên chuyển -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 text-xs">
                                    <div>
                                        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Đơn Vị Chuyển Đi (Cũ)</span>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5"><?= e($th['from_branch_name']) ?></div>
                                        <div class="text-slate-500 dark:text-slate-400 text-[11px]"><?= e($th['from_dept_name']) ?> • <?= e($th['from_pos_name']) ?></div>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-semibold text-indigo-500 dark:text-indigo-400 uppercase tracking-wider block">Đơn Vị Tiếp Nhận (Mới)</span>
                                        <div class="font-bold text-indigo-700 dark:text-indigo-300 mt-0.5"><?= e($th['to_branch_name']) ?></div>
                                        <div class="text-slate-700 dark:text-slate-300 text-[11px] font-medium"><?= e($th['to_dept_name']) ?> • <strong class="text-slate-900 dark:text-white"><?= e($th['to_pos_name']) ?></strong></div>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-600 dark:text-slate-300 gap-2 pt-1">
                                    <div class="italic">
                                        <strong>Lý do:</strong> <?= e($th['reason']) ?>
                                        <?php if ($th['allowance_support'] > 0): ?>
                                            • Phụ cấp chuyển vùng: <strong class="text-emerald-600 dark:text-emerald-400"><?= format_money($th['allowance_support']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= base_url('modules/transfers/print_decision.php?id=' . $th['id']) ?>" 
                                       target="_blank"
                                       class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-1 flex-shrink-0">
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

</div>

<!-- =========================================================================
     CÁC MODAL THÊM MỚI HẠNG MỤC HỒ SƠ (KÈM KÉO THẢ TỆP TIN DROPZONE)
     ========================================================================= -->

<!-- MODAL 1: THÊM HỌC VẤN -->
<div id="modalAddEducation" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-indigo-600"></i>
                <span>Thêm Bằng Cấp / Học Vấn</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddEducation')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_education">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Trình Độ Bằng Cấp <span class="text-rose-500">*</span></label>
                <input type="text" name="degree" required placeholder="Ví dụ: Cử nhân, Kỹ sư, Thạc sĩ, Tiến sĩ, Cao đẳng..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cơ Sở Đào Tạo / Tên Trường <span class="text-rose-500">*</span></label>
                <input type="text" name="institution" required placeholder="Ví dụ: Đại học Bách Khoa Hà Nội, ĐHQG..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Chuyên Ngành <span class="text-rose-500">*</span></label>
                    <input type="text" name="major" required placeholder="Công nghệ thông tin..."
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Năm Tốt Nghiệp <span class="text-rose-500">*</span></label>
                    <input type="number" name="graduation_year" required min="1960" max="2035" value="<?= date('Y') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Điểm Tốt Nghiệp / Xếp Loại</label>
                <input type="text" name="gpa" placeholder="Ví dụ: 3.65/4.0 (Xuất sắc), Giỏi..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <!-- Drag & Drop Tệp scan văn bằng -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tệp Scan Bằng Cấp / Bảng Điểm (PDF / Ảnh)</label>
                <div class="drag-drop-zone p-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-600 hover:border-indigo-400 dark:hover:border-indigo-500 bg-slate-50 dark:bg-slate-700/40 text-center transition cursor-pointer relative" onclick="this.querySelector('input[type=file]').click()">
                    <input type="file" name="certificate_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="drag-drop-input hidden">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-indigo-500 mb-1 block"></i>
                    <span class="text-xs text-slate-600 dark:text-slate-300 font-medium block">Kéo thả tệp vào đây hoặc nhấn để chọn file</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Hỗ trợ PDF, DOCX, JPG, PNG (Tối đa 10MB)</span>
                    <div class="drag-drop-preview mt-2 hidden text-xs font-bold text-indigo-600 dark:text-indigo-400"></div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('modalAddEducation')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200">Hủy</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs">Lưu Bằng Cấp</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: THÊM CHỨNG CHỈ NGHỀ NGHIỆP -->
<div id="modalAddCertificate" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-certificate text-amber-500"></i>
                <span>Thêm Chứng Chỉ Chuyên Môn / Ngoại Ngữ</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddCertificate')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_certificate">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tên Chứng Chỉ <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required placeholder="Ví dụ: AWS Solutions Architect, PMP, IELTS 7.5, CPA..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cơ Quan / Tổ Chức Cấp <span class="text-rose-500">*</span></label>
                <input type="text" name="issuer" required placeholder="Ví dụ: Amazon Web Services, PMI, British Council..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Cấp <span class="text-rose-500">*</span></label>
                    <input type="date" name="issue_date" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Hết Hạn</label>
                    <input type="date" name="expiry_date" placeholder="Để trống nếu vĩnh viễn"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <span class="text-[10px] text-slate-400">Để trống nếu chứng chỉ có hiệu lực vĩnh viễn</span>
                </div>
            </div>

            <!-- Drag & Drop Tệp scan chứng chỉ -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tệp Scan Chứng Chỉ (PDF / Ảnh)</label>
                <div class="drag-drop-zone p-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-600 hover:border-amber-400 dark:hover:border-amber-500 bg-slate-50 dark:bg-slate-700/40 text-center transition cursor-pointer relative" onclick="this.querySelector('input[type=file]').click()">
                    <input type="file" name="certificate_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="drag-drop-input hidden">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-amber-500 mb-1 block"></i>
                    <span class="text-xs text-slate-600 dark:text-slate-300 font-medium block">Kéo thả tệp vào đây hoặc nhấn để chọn file</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Hỗ trợ PDF, DOCX, JPG, PNG (Tối đa 10MB)</span>
                    <div class="drag-drop-preview mt-2 hidden text-xs font-bold text-amber-600 dark:text-amber-400"></div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('modalAddCertificate')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200">Hủy</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs">Lưu Chứng Chỉ</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: THÊM HỢP ĐỒNG LAO ĐỘNG -->
<div id="modalAddContract" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-file-signature text-indigo-600"></i>
                <span>Thiết Lập Hợp Đồng Lao Động Mới</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddContract')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_contract">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Số Hợp Đồng <span class="text-rose-500">*</span></label>
                    <input type="text" name="contract_number" required value="HĐLĐ-<?= date('Y') ?>/<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Loại Hợp Đồng <span class="text-rose-500">*</span></label>
                    <select name="contract_type" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="fixed_term">Xác định thời hạn (1 - 3 năm)</option>
                        <option value="probation">Hợp đồng thử việc (30 - 60 ngày)</option>
                        <option value="indefinite">Không xác định thời hạn (Dài hạn)</option>
                        <option value="seasonal">Hợp đồng theo mùa vụ / công việc ngắn hạn</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Bắt Đầu Hiệu Lực <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Hết Hạn</label>
                    <input type="date" name="end_date"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <span class="text-[10px] text-slate-400">Để trống nếu là HĐ không thời hạn</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Lương Cơ Bản (VNĐ)</label>
                    <input type="number" name="base_salary" step="100000" value="<?= $emp['position_salary'] ?: 15000000 ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Phụ Cấp Thỏa Thuận (VNĐ)</label>
                    <input type="number" name="allowance" step="100000" value="1000000"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Trạng Thái HĐ</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="active" selected>Đang hiệu lực (Active)</option>
                        <option value="expired">Đã hết hạn (Expired)</option>
                        <option value="terminated">Đã chấm dứt (Terminated)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ghi Chú Điều Khoản</label>
                    <input type="text" name="notes" placeholder="Ghi chú điều khoản bổ sung..."
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <!-- Drag & Drop Tệp scan hợp đồng -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tệp Scan Hợp Đồng Đã Ký (PDF / Ảnh)</label>
                <div class="drag-drop-zone p-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-600 hover:border-indigo-400 dark:hover:border-indigo-500 bg-slate-50 dark:bg-slate-700/40 text-center transition cursor-pointer relative" onclick="this.querySelector('input[type=file]').click()">
                    <input type="file" name="contract_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="drag-drop-input hidden">
                    <i class="fa-solid fa-file-contract text-2xl text-indigo-500 mb-1 block"></i>
                    <span class="text-xs text-slate-600 dark:text-slate-300 font-medium block">Kéo thả tệp hợp đồng hoặc nhấn để chọn</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Hỗ trợ PDF, DOCX, JPG, PNG (Tối đa 10MB)</span>
                    <div class="drag-drop-preview mt-2 hidden text-xs font-bold text-indigo-600 dark:text-indigo-400"></div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('modalAddContract')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200">Hủy</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs">Lưu Hợp Đồng</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: THÊM BẢO HIỂM -->
<div id="modalAddInsurance" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-shield-heart text-rose-500"></i>
                <span>Đăng Ký Hồ Sơ Bảo Hiểm</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddInsurance')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_insurance">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Loại Bảo Hiểm <span class="text-rose-500">*</span></label>
                <select name="insurance_type" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
                    <option value="social">Bảo Hiểm Xã Hội (BHXH)</option>
                    <option value="health">Bảo Hiểm Y Tế (BHYT)</option>
                    <option value="unemployment">Bảo Hiểm Thất Nghiệp (BHTN)</option>
                    <option value="commercial">Bảo Hiểm Sức Khỏe Cao Cấp (Doanh nghiệp cấp)</option>
                    <option value="accident">Bảo Hiểm Tai Nạn Lao Động</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mã Số Sổ / Thẻ Bảo Hiểm <span class="text-rose-500">*</span></label>
                <input type="text" name="insurance_number" required placeholder="Ví dụ: BHXH-0123456789 hoặc mã thẻ BHYT..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Bắt Đầu Đóng <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Trạng Thái</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
                        <option value="active" selected>Đang đóng (Active)</option>
                        <option value="suspended">Tạm hoãn (Suspended)</option>
                        <option value="closed">Đã đóng sổ (Closed)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cơ Sở Khám Chữa Bệnh Ban Đầu (BHYT / Phúc Lợi)</label>
                <input type="text" name="hospital_name" placeholder="Ví dụ: Bệnh viện Bạch Mai, Bệnh viện Nhân dân Gia Định..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ghi Chú</label>
                <input type="text" name="notes" placeholder="Ghi chú thêm về gói bảo hiểm..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 outline-none">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('modalAddInsurance')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200">Hủy</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs">Lưu Bảo Hiểm</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: THÊM NGƯỜI THÂN / PHỤ THUỘC -->
<div id="modalAddDependent" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-people-roof text-teal-600"></i>
                <span>Thêm Thân Nhân & Người Phụ Thuộc</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddDependent')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_dependent">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Họ Và Tên Thân Nhân <span class="text-rose-500">*</span></label>
                <input type="text" name="fullname" required placeholder="Nguyễn Văn A..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mối Quan Hệ <span class="text-rose-500">*</span></label>
                    <select name="relationship" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="child">Con ruột / Con nuôi</option>
                        <option value="spouse">Vợ / Chồng</option>
                        <option value="parent">Bố / Mẹ</option>
                        <option value="sibling">Anh / Chị / Em ruột</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Ngày Sinh</label>
                    <input type="date" name="birth_date"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Số Điện Thoại</label>
                <input type="text" name="phone" placeholder="0901234567"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500 outline-none">
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-700/40 rounded-xl space-y-2 border border-slate-100 dark:border-slate-600">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="tax_deductible" value="1" checked class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500">
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">Đăng ký giảm trừ gia cảnh (Tính trừ vào thuế TNCN)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_emergency_contact" value="1" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500">
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">Là người liên hệ khẩn cấp khi có sự cố</span>
                </label>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Nghề Nghiệp / Ghi Chú</label>
                <input type="text" name="notes" placeholder="Học sinh, đã nghỉ hưu, v.v..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500 outline-none">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('modalAddDependent')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200">Hủy</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold shadow-xs">Lưu Thân Nhân</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     JAVASCRIPT: CHUYỂN TAB TỨC THỜI & QUẢN LÝ MODAL, DRAG & DROP
     ========================================================================= -->
<script src="<?= asset('js/drag_drop.js') ?>"></script>
<script>
// Hàm chuyển đổi giữa 7 Tabs lớn
function switchProfileTab(tabName) {
    // Ẩn tất cả panels
    document.querySelectorAll('.tab-panel-section').forEach(el => el.classList.add('hidden'));
    
    // Bỏ highlight tất cả buttons
    document.querySelectorAll('.tab-nav-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-xs');
        btn.classList.add('text-slate-600', 'dark:text-slate-300');
        const badge = btn.querySelector('.tab-badge');
        if (badge) {
            badge.classList.remove('bg-indigo-500', 'text-white');
            badge.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-600', 'dark:text-slate-300');
        }
    });

    // Mở panel tương ứng
    const targetPanel = document.getElementById('tabContent_' + tabName);
    if (targetPanel) {
        targetPanel.classList.remove('hidden');
    }

    // Highlight button tương ứng
    const targetBtn = document.getElementById('tabBtn_' + tabName);
    if (targetBtn) {
        targetBtn.classList.remove('text-slate-600', 'dark:text-slate-300');
        targetBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-xs');
        const badge = targetBtn.querySelector('.tab-badge');
        if (badge) {
            badge.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-600', 'dark:text-slate-300');
            badge.classList.add('bg-indigo-500', 'text-white');
        }
    }

    // Cập nhật URL query parameter mà không cần reload trang
    const newUrl = new URL(window.location.href);
    newUrl.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', newUrl.toString());
}

// Hàm chuyển đổi Khen thưởng vs Kỷ luật bên trong Tab 6
function switchDossierSubTab(subTab) {
    const btnRewards = document.getElementById('tabRewardsBtn');
    const btnDisciplines = document.getElementById('tabDisciplinesBtn');
    const panelRewards = document.getElementById('panelRewards');
    const panelDisciplines = document.getElementById('panelDisciplines');

    if (subTab === 'rewards') {
        btnRewards.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-emerald-600 text-white shadow-xs';
        btnDisciplines.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60';
        panelRewards.classList.remove('hidden');
        panelDisciplines.classList.add('hidden');
    } else {
        btnDisciplines.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-rose-600 text-white shadow-xs';
        btnRewards.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60';
        panelDisciplines.classList.remove('hidden');
        panelRewards.classList.add('hidden');
    }
}

// Quản lý Modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Đóng modal khi nhấn phím Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['modalAddEducation', 'modalAddCertificate', 'modalAddContract', 'modalAddInsurance', 'modalAddDependent'].forEach(id => {
            closeModal(id);
        });
    }
});

// Khởi tạo tính năng Drag & Drop cho các khu vực Upload file đính kèm
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.drag-drop-zone').forEach(zone => {
        const input = zone.querySelector('.drag-drop-input');
        const preview = zone.querySelector('.drag-drop-preview');
        if (!input) return;

        ['dragenter', 'dragover'].forEach(ev => {
            zone.addEventListener(ev, function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('border-indigo-500', 'bg-indigo-50/50', 'scale-[1.01]');
            });
        });

        ['dragleave', 'drop'].forEach(ev => {
            zone.addEventListener(ev, function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('border-indigo-500', 'bg-indigo-50/50', 'scale-[1.01]');
            });
        });

        zone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                input.files = files;
                showPreview(files[0]);
            }
        });

        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                showPreview(this.files[0]);
            }
        });

        function showPreview(file) {
            if (preview) {
                preview.classList.remove('hidden');
                preview.innerHTML = `<i class="fa-solid fa-file-circle-check text-emerald-500 mr-1"></i> Đã đính kèm: <strong>${file.name}</strong> (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>