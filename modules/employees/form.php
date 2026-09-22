<?php
// modules/employees/form.php - Màn hình Thêm mới và Chỉnh sửa Hồ sơ nhân viên
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('employees', 'edit');
    $page_title = 'Cập Nhật Hồ Sơ Nhân Viên';
} else {
    require_permission('employees', 'create');
    $page_title = 'Thêm Mới Hồ Sơ Nhân Viên';
}

// Lấy danh sách chi nhánh, phòng ban & chức vụ cho thẻ select
$branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

$employee = [
    'employee_code' => '',
    'fullname' => '',
    'gender' => 'Nam',
    'birth_date' => '',
    'identity_card' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'avatar' => '',
    'branch_id' => 1,
    'department_id' => '',
    'position_id' => '',
    'hire_date' => date('Y-m-d'),
    'employment_status' => 'probation'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy hồ sơ nhân viên này.');
        redirect('modules/employees/index.php');
    }
    $employee = $existing;
} else {
    // Tự sinh mã nhân viên kế tiếp (VD: NV003, NV004...)
    $last_id = $pdo->query("SELECT MAX(id) FROM employees")->fetchColumn() ?: 0;
    $employee['employee_code'] = 'NV' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $employee_code     = strtoupper(trim($_POST['employee_code'] ?? ''));
    $fullname          = trim($_POST['fullname'] ?? '');
    $gender            = $_POST['gender'] ?? 'Nam';
    $birth_date        = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $identity_card     = trim($_POST['identity_card'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $branch_id         = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : 1;
    $department_id     = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $position_id       = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $hire_date         = !empty($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d');
    $employment_status = $_POST['employment_status'] ?? 'probation';

    // Cập nhật lại mảng dữ liệu hiển thị trên form nếu có lỗi
    $employee = array_merge($employee, [
        'employee_code' => $employee_code,
        'fullname' => $fullname,
        'gender' => $gender,
        'birth_date' => $birth_date,
        'identity_card' => $identity_card,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'branch_id' => $branch_id,
        'department_id' => $department_id,
        'position_id' => $position_id,
        'hire_date' => $hire_date,
        'employment_status' => $employment_status
    ]);

    if (empty($employee_code)) {
        $errors[] = 'Mã nhân viên không được để trống.';
    } else {
        // Kiểm tra trùng mã nhân viên
        $checkSql = "SELECT COUNT(*) FROM employees WHERE employee_code = ?";
        $checkParams = [$employee_code];
        if ($is_edit) {
            $checkSql .= " AND id != ?";
            $checkParams[] = $id;
        }
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($checkParams);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Mã nhân viên '{$employee_code}' đã được sử dụng.";
        }
    }

    if (empty($fullname)) {
        $errors[] = 'Họ và tên nhân viên là bắt buộc.';
    }

    $avatar_name = $employee['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_exts)) {
            $errors[] = 'Ảnh đại diện chỉ chấp nhận định dạng JPG, PNG hoặc WEBP.';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $errors[] = 'Kích thước ảnh đại diện không được vượt quá 2MB.';
        } else {
            $upload_dir = __DIR__ . '/../../assets/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Đặt tên file ngẫu nhiên an toàn
            $new_avatar_name = 'avatar_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_avatar_name)) {
                // Xóa avatar cũ nếu có
                if (!empty($avatar_name) && file_exists($upload_dir . $avatar_name)) {
                    unlink($upload_dir . $avatar_name);
                }
                $avatar_name = $new_avatar_name;
            } else {
                $errors[] = 'Không thể lưu file ảnh tải lên máy chủ.';
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $sql = "UPDATE employees SET 
                    employee_code = ?, fullname = ?, gender = ?, birth_date = ?, identity_card = ?, 
                    phone = ?, email = ?, address = ?, avatar = ?, branch_id = ?, department_id = ?, position_id = ?, 
                    hire_date = ?, employment_status = ? 
                    WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $employee_code, $fullname, $gender, $birth_date, $identity_card,
                    $phone, $email, $address, $avatar_name, $branch_id, $department_id, $position_id,
                    $hire_date, $employment_status, $id
                ]);
                set_flash('success', "Đã cập nhật thành công hồ sơ nhân viên: {$fullname}");
            } else {
                $sql = "INSERT INTO employees (
                    employee_code, fullname, gender, birth_date, identity_card, 
                    phone, email, address, avatar, branch_id, department_id, position_id, 
                    hire_date, employment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $employee_code, $fullname, $gender, $birth_date, $identity_card,
                    $phone, $email, $address, $avatar_name, $branch_id, $department_id, $position_id,
                    $hire_date, $employment_status
                ]);
                set_flash('success', "Đã tạo mới thành công hồ sơ nhân viên: {$fullname}");
            }
            redirect('modules/employees/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header & Nút Quay Lại -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="index.php" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center transition">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-slate-800"><?= $page_title ?></h2>
                <p class="text-xs text-slate-500 mt-0.5">Điền các trường thông tin bên dưới để lưu hồ sơ vào hệ thống.</p>
            </div>
        </div>
        <button type="submit" form="employeeForm" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition flex items-center gap-2">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Lưu Hồ Sơ Nhân Viên</span>
        </button>
    </div>

    <!-- Thông báo lỗi nhập liệu nếu có -->
    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <i class="fa-solid fa-triangle-exclamation"></i> Vui lòng sửa các lỗi sau:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-2">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="employeeForm" action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <?= csrf_field() ?>

        <!-- CỘT TRÁI: Ảnh đại diện & Trạng thái làm việc -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm text-center">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">
                    Ảnh Chân Dung Đại Diện
                </label>
                
                <div class="relative w-32 h-32 mx-auto mb-4 group">
                    <div id="avatarPreviewContainer" class="w-full h-full rounded-2xl overflow-hidden border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center">
                        <?php if (!empty($employee['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $employee['avatar'])): ?>
                            <img id="avatarPreview" src="<?= base_url('assets/uploads/' . e($employee['avatar'])) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img id="avatarPreview" src="" class="w-full h-full object-cover hidden">
                            <i id="avatarPlaceholderIcon" class="fa-regular fa-user text-3xl text-slate-300"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer transition">
                    <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                    <span>Tải Lên File Ảnh</span>
                    <input type="file" name="avatar" id="avatarInput" accept="image/png, image/jpeg, image/webp" class="hidden" onchange="previewFile(event)">
                </label>
                <p class="text-[11px] text-slate-400 mt-2">Hỗ trợ JPG, PNG, WEBP (Tối đa 2MB)</p>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Trạng Thái Làm Việc</h3>
                
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tình Trạng Hợp Đồng *</label>
                    <select name="employment_status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                        <option value="probation" <?= ($employee['employment_status'] === 'probation') ? 'selected' : '' ?>>Đang Thử Việc (Probation)</option>
                        <option value="official" <?= ($employee['employment_status'] === 'official') ? 'selected' : '' ?>>Nhân Viên Chính Thức (Official)</option>
                        <option value="resigned" <?= ($employee['employment_status'] === 'resigned') ? 'selected' : '' ?>>Đã Nghỉ Việc (Resigned)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Ngày Vào Làm (Gia Nhập) *</label>
                    <input type="date" name="hire_date" value="<?= e($employee['hire_date']) ?>" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: Thông tin cá nhân & Cơ cấu tổ chức -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Card 1: Thông tin định danh cơ bản -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-2 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-id-card text-indigo-500"></i> Thông Tin Lý Lịch & Cá Nhân
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Mã Nhân Viên *</label>
                        <input type="text" name="employee_code" value="<?= e($employee['employee_code']) ?>" required
                               placeholder="VD: NV001"
                               class="w-full uppercase font-mono px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Họ Và Tên Khai Sinh *</label>
                        <input type="text" name="fullname" value="<?= e($employee['fullname']) ?>" required
                               placeholder="VD: Nguyễn Văn An"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Giới Tính *</label>
                        <select name="gender" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                            <option value="Nam" <?= ($employee['gender'] === 'Nam') ? 'selected' : '' ?>>Nam</option>
                            <option value="Nu" <?= ($employee['gender'] === 'Nu') ? 'selected' : '' ?>>Nữ</option>
                            <option value="Khac" <?= ($employee['gender'] === 'Khac') ? 'selected' : '' ?>>Khác</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Ngày Sinh</label>
                        <input type="date" name="birth_date" value="<?= e($employee['birth_date']) ?>"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Số Căn Cước / CMND</label>
                        <input type="text" name="identity_card" value="<?= e($employee['identity_card']) ?>" placeholder="12 chữ số CCCD"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Số Điện Thoại Di Động</label>
                        <input type="text" name="phone" value="<?= e($employee['phone']) ?>" placeholder="09xxxxxxxxx"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Hòm Thư Email Doanh Nghiệp / Cá Nhân</label>
                        <input type="email" name="email" value="<?= e($employee['email']) ?>" placeholder="nhanvien@company.vn"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Địa Chỉ Thường Trú / Nơi Ở Hiện Nay</label>
                        <textarea name="address" rows="2" placeholder="Số nhà, tên đường, phường/xã, quận/huyện..."
                                  class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"><?= e($employee['address']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Card 2: Bố trí chi nhánh, phòng ban & Vị trí công tác -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                        <i class="fa-solid fa-sitemap text-sky-500"></i> Bố Trí Chi Nhánh, Phòng Ban & Chức Vụ
                    </h3>
                    <?php if ($is_edit && has_permission('transfers', 'create')): ?>
                        <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $employee['id']) ?>" 
                           class="text-xs text-indigo-600 font-semibold hover:underline flex items-center gap-1">
                            <i class="fa-solid fa-people-arrows"></i> Thuyên chuyển công tác &rarr;
                        </a>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Chi Nhánh Công Tác *</label>
                        <select name="branch_id" id="empBranchSelect" required onchange="filterDeptOptions(this.value)"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none font-semibold text-slate-800">
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['id'] ?>" <?= ($employee['branch_id'] == $br['id']) ? 'selected' : '' ?>>
                                    <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?> (<?= e($br['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Thuộc Phòng Ban *</label>
                        <select name="department_id" id="empDeptSelect" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                            <option value="">-- Chưa gán phòng ban --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" data-branch-id="<?= $d['branch_id'] ?>" <?= ($employee['department_id'] == $d['id']) ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Đảm Nhiệm Chức Vụ *</label>
                        <select name="position_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                            <option value="">-- Chưa gán chức vụ --</option>
                            <?php foreach ($positions as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($employee['position_id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (Lương chuẩn: <?= format_money($p['base_salary']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

        </div>

    </form>

</div>

<script>
function previewFile(event) {
    const input = event.target;
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            const icon = document.getElementById('avatarPlaceholderIcon');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (icon) icon.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function filterDeptOptions(branchId) {
    branchId = parseInt(branchId);
    const deptSelect = document.getElementById('empDeptSelect');
    const options = deptSelect.querySelectorAll('option');

    options.forEach(opt => {
        if (!opt.value) return;
        const bId = parseInt(opt.getAttribute('data-branch-id'));
        if (!branchId || bId === branchId) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const curBranch = document.getElementById('empBranchSelect').value;
    if (curBranch) {
        filterDeptOptions(curBranch);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>