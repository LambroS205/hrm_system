<?php
// modules/recruitment/hire.php - Tuyển dụng & Tự động tạo Hồ sơ Nhân viên chính thức
require_once __DIR__ . '/../../core/auth.php';
require_permission('recruitment', 'hire');

$candidate_id = (int)($_POST['candidate_id'] ?? ($_GET['id'] ?? 0));

if ($candidate_id <= 0) {
    set_flash('danger', 'Ứng viên không hợp lệ.');
    redirect('modules/recruitment/index.php');
}

// Lấy thông tin ứng viên kèm vị trí tuyển dụng
$stmt = $pdo->prepare("
    SELECT c.*, 
           j.title AS job_title, j.department_id, j.branch_id, j.position_id, j.salary_range_min
    FROM candidates c
    JOIN job_positions j ON c.job_position_id = j.id
    WHERE c.id = ?
");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    set_flash('danger', 'Không tìm thấy hồ sơ ứng viên này.');
    redirect('modules/recruitment/index.php');
}

// Nếu đã được chuyển thành nhân viên trước đó
if (!empty($candidate['hired_employee_id'])) {
    set_flash('info', "Ứng viên này đã được tuyển dụng và có mã hồ sơ nhân viên trong hệ thống.");
    redirect('modules/employees/view.php?id=' . $candidate['hired_employee_id']);
}

// Xử lý POST chuyển đổi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $pdo->beginTransaction();

        // 1. Tự sinh mã nhân viên kế tiếp (VD: NV007, NV008...)
        $last_id = $pdo->query("SELECT MAX(id) FROM employees")->fetchColumn() ?: 0;
        $next_emp_code = 'NV' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);

        // Đảm bảo không trùng mã
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_code = ?");
        $checkStmt->execute([$next_emp_code]);
        if ($checkStmt->fetchColumn() > 0) {
            $next_emp_code = 'NV' . str_pad($last_id + 2, 3, '0', STR_PAD_LEFT);
        }

        $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d');
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : ($candidate['branch_id'] ?: 1);
        $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : $candidate['department_id'];
        $pos_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : $candidate['position_id'];
        $employment_status = $_POST['employment_status'] ?? 'probation'; // Mặc định thử việc

        // 2. Chèn vào bảng employees
        $empSql = "
            INSERT INTO employees 
                (employee_code, fullname, gender, birth_date, phone, email, branch_id, department_id, position_id, hire_date, employment_status)
            VALUES 
                (:code, :fullname, :gender, :birth_date, :phone, :email, :branch_id, :department_id, :position_id, :hire_date, :status)
        ";
        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute([
            ':code'          => $next_emp_code,
            ':fullname'      => $candidate['fullname'],
            ':gender'        => $candidate['gender'] ?? 'Nam',
            ':birth_date'    => !empty($candidate['birth_date']) ? $candidate['birth_date'] : null,
            ':phone'         => $candidate['phone'],
            ':email'         => $candidate['email'],
            ':branch_id'     => $branch_id,
            ':department_id' => $dept_id,
            ':position_id'   => $pos_id,
            ':hire_date'     => $hire_date,
            ':status'        => $employment_status
        ]);

        $newEmployeeId = (int)$pdo->lastInsertId();

        // 3. Cập nhật bảng candidates: gán stage = 'hired' và hired_employee_id
        $candUpStmt = $pdo->prepare("UPDATE candidates SET stage = 'hired', hired_employee_id = ? WHERE id = ?");
        $candUpStmt->execute([$newEmployeeId, $candidate_id]);

        $pdo->commit();

        set_flash('success', "🎉 Chúc mừng! Đã chuyển đổi thành công ứng viên '{$candidate['fullname']}' thành Nhân sự mới với mã [{$next_emp_code}].");
        redirect('modules/employees/view.php?id=' . $newEmployeeId);
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Lỗi khi tạo hồ sơ nhân viên: ' . $e->getMessage());
        redirect('modules/recruitment/candidate_view.php?id=' . $candidate_id);
    }
}

// Nếu truy cập bằng GET: Hiển thị form xác nhận tiếp nhận nhân sự
$page_title = 'Xác Nhận Tuyển Dụng Ứng Viên';
$branches = $pdo->query("SELECT id, name, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="candidate_view.php?id=<?= $candidate['id'] ?>" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition shadow-xs">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-check text-emerald-600"></i>
                    <span>Tiếp Nhận Nhân Sự & Chuyển Thành Nhân Viên</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Hồ sơ ứng viên: <strong><?= e($candidate['fullname']) ?></strong> (<?= e($candidate['candidate_code']) ?>)</p>
            </div>
        </div>
    </div>

    <!-- Thông Tin Tóm Tắt Ứng Viên -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 space-y-6">
        
        <div class="flex items-center gap-4 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-sm flex-shrink-0">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-200">
                    Ứng viên đã trúng tuyển vị trí: <?= e($candidate['job_title']) ?>
                </h3>
                <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                    Hệ thống sẽ tự động chuyển dữ liệu sang phân hệ Nhân sự (HR Core) và cấp mã số định danh cán bộ mới.
                </p>
            </div>
        </div>

        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Họ & Tên Nhân Viên</label>
                    <input type="text" disabled value="<?= e($candidate['fullname']) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-semibold cursor-not-allowed">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email Làm Việc</label>
                    <input type="text" disabled value="<?= e($candidate['email']) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-semibold cursor-not-allowed">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Số Điện Thoại</label>
                    <input type="text" disabled value="<?= e($candidate['phone']) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-semibold cursor-not-allowed">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Ngày Bắt Đầu Làm Việc (Hire Date)</label>
                    <input type="date" name="hire_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Chi Nhánh Phân Bổ</label>
                    <select name="branch_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= ($candidate['branch_id'] == $br['id']) ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Phòng Ban Tiếp Nhận</label>
                    <select name="department_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <?php foreach ($departments as $dp): ?>
                            <option value="<?= $dp['id'] ?>" <?= ($candidate['department_id'] == $dp['id']) ? 'selected' : '' ?>><?= e($dp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Chức Danh Công Tác</label>
                    <select name="position_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <?php foreach ($positions as $ps): ?>
                            <option value="<?= $ps['id'] ?>" <?= ($candidate['position_id'] == $ps['id']) ? 'selected' : '' ?>><?= e($ps['name']) ?> (<?= format_money($ps['base_salary']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Chế Độ Hợp Đồng Ban Đầu</label>
                    <select name="employment_status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <option value="probation" selected>Thử việc (2 tháng)</option>
                        <option value="official">Hợp đồng chính thức</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <a href="candidate_view.php?id=<?= $candidate['id'] ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    Hủy Bỏ
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold transition flex items-center gap-2 shadow-md shadow-emerald-200 dark:shadow-none">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Tạo Hồ Sơ Nhân Viên Ngay</span>
                </button>
            </div>
        </form>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
