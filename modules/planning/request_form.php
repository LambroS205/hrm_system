<?php
// modules/planning/request_form.php - Lập / Chỉnh Sửa Đề Xuất Nhu Cầu Nhân Lực Từ Chi Nhánh
$page_title = 'Lập Đề Xuất Nhu Cầu Nhân Lực';

require_once __DIR__ . '/../../core/auth.php';
require_permission('planning', 'view');

$user = current_user();
$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$request_data = [
    'request_code' => '',
    'branch_id' => $user['branch_id'] ?? 1,
    'department_id' => '',
    'position_id' => '',
    'request_type' => 'request_headcount',
    'title' => '',
    'urgency' => 'normal',
    'quantity' => 1,
    'candidate_id' => '',
    'rationale' => '',
    'estimated_cost' => 0,
    'status' => 'pending'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM branch_workforce_requests WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $request_data = $existing;
    } else {
        set_flash('danger', 'Không tìm thấy đề xuất này.');
        redirect('modules/planning/index.php?tab=requests');
    }
} else {
    $branch_code = 'HQ';
    if (!empty($user['branch_id'])) {
        $bstmt = $pdo->prepare("SELECT code FROM branches WHERE id = ?");
        $bstmt->execute([$user['branch_id']]);
        $branch_code = $bstmt->fetchColumn() ?: 'HQ';
    }
    $last_id = (int)($pdo->query("SELECT MAX(id) FROM branch_workforce_requests")->fetchColumn() ?: 0);
    $request_data['request_code'] = 'DX-' . preg_replace('/[^A-Za-z0-9]/', '', $branch_code) . '-' . date('Y') . '-' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);
}

// Xử lý Lưu Đề Xuất
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $request_code = strtoupper(trim($_POST['request_code'] ?? ''));
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $request_type = $_POST['request_type'] ?? 'request_headcount';
    $title = trim($_POST['title'] ?? '');
    $urgency = $_POST['urgency'] ?? 'normal';
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $candidate_id = !empty($_POST['candidate_id']) ? (int)$_POST['candidate_id'] : null;
    $rationale = trim($_POST['rationale'] ?? '');
    $estimated_cost = (float)str_replace(['.', ','], '', $_POST['estimated_cost'] ?? '0');

    if (!empty($request_code) && !empty($title) && !empty($rationale) && $branch_id > 0) {
        try {
            if ($is_edit) {
                $upStmt = $pdo->prepare("
                    UPDATE branch_workforce_requests 
                    SET request_code = ?, branch_id = ?, department_id = ?, position_id = ?,
                        request_type = ?, title = ?, urgency = ?, quantity = ?, candidate_id = ?,
                        rationale = ?, estimated_cost = ?
                    WHERE id = ?
                ");
                $upStmt->execute([
                    $request_code, $branch_id, $department_id, $position_id,
                    $request_type, $title, $urgency, $quantity, $candidate_id,
                    $rationale, $estimated_cost, $id
                ]);
                set_flash('success', "Đã cập nhật thành công đề xuất: {$title}");
            } else {
                $insStmt = $pdo->prepare("
                    INSERT INTO branch_workforce_requests (
                        request_code, branch_id, department_id, position_id, request_type,
                        title, urgency, quantity, candidate_id, rationale, estimated_cost, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $insStmt->execute([
                    $request_code, $branch_id, $department_id, $position_id, $request_type,
                    $title, $urgency, $quantity, $candidate_id, $rationale, $estimated_cost, $user['id']
                ]);
                set_flash('success', "Đã gửi thành công đề xuất nhân lực lên Tập đoàn: {$title}");
            }
            redirect('modules/planning/index.php?tab=requests');
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi lưu đề xuất: ' . $e->getMessage());
        }
    } else {
        set_flash('warning', 'Vui lòng điền đầy đủ Tiêu đề, Chi nhánh và Lý do thuyết minh.');
    }
}

// Danh mục dữ liệu
$branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches WHERE status = 'active' ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();
$employees = $pdo->query("SELECT id, fullname, employee_code, branch_id FROM employees WHERE employment_status != 'resigned' ORDER BY fullname ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Header Tiêu Đề -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 mb-2">
                <i class="fa-solid fa-paper-plane text-xs"></i> Đề Xuất Nhân Lực Từ Chi Nhánh
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white"><?= $is_edit ? 'Chỉnh Sửa Đề Xuất Nhu Cầu Nhân Lực' : 'Khởi Tạo Đề Xuất Nhu Cầu Nhân Lực Mới' ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Trình Ban Giám Đốc & Ban Nhân Sự Tập đoàn phê duyệt bổ sung, thuyên chuyển hoặc tái cơ cấu bộ máy.</p>
        </div>
        <a href="index.php?tab=requests" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 flex-shrink-0">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Quay Lại</span>
        </a>
    </div>

    <!-- Biểu Mẫu Nhập Liệu -->
    <form action="request_form.php<?= $is_edit ? '?id=' . $id : '' ?>" method="POST" class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-5">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Mã Đề Xuất (Request Code) *</label>
                <input type="text" name="request_code" value="<?= e($request_data['request_code']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Loại Hình Đề Xuất *</label>
                <select name="request_type" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="request_headcount" <?= $request_data['request_type'] === 'request_headcount' ? 'selected' : '' ?>>Xin Bổ Sung Thêm Nhân Lực (Thiếu định biên)</option>
                    <option value="surplus_relocation" <?= $request_data['request_type'] === 'surplus_relocation' ? 'selected' : '' ?>>Đề Xuất Điều Chuyển Nhân Sự Dôi Dư Ra</option>
                    <option value="management_appointment" <?= $request_data['request_type'] === 'management_appointment' ? 'selected' : '' ?>>Đề Xuất Bổ Nhiệm / Tiếp Nhận Quản Lý</option>
                    <option value="internal_restructure" <?= $request_data['request_type'] === 'internal_restructure' ? 'selected' : '' ?>>Đề Xuất Tái Cơ Cấu & Sáp Nhập Bộ Phận</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Tiêu Đề Đề Xuất *</label>
            <input type="text" name="title" value="<?= e($request_data['title']) ?>" required placeholder="VD: Đề xuất bổ sung khẩn cấp 02 Chuyên viên Vận hành Kho Vận..."
                   class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Đơn Vị / Chi Nhánh Đề Xuất *</label>
                <select name="branch_id" id="reqBranchSelect" required onchange="filterBranchDepartments(this.value)"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $request_data['branch_id'] == $br['id'] ? 'selected' : '' ?>>
                            <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?> (<?= e($br['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Phòng Ban Trực Thuộc</label>
                <select name="department_id" id="reqDeptSelect"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Toàn Đơn Vị / Chưa Cố Định --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" data-branch="<?= $dept['branch_id'] ?>" <?= $request_data['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Vị Trí Chức Danh Liên Quan</label>
                <select name="position_id"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chọn Chức Danh --</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?= $pos['id'] ?>" <?= $request_data['position_id'] == $pos['id'] ? 'selected' : '' ?>>
                            <?= e($pos['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Số Lượng Nhân Sự *</label>
                <input type="number" name="quantity" min="1" max="50" value="<?= e($request_data['quantity']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Mức Độ Cấp Bách *</label>
                <select name="urgency" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="normal" <?= $request_data['urgency'] === 'normal' ? 'selected' : '' ?>>Bình Thường (Kế hoạch định kỳ)</option>
                    <option value="high" <?= $request_data['urgency'] === 'high' ? 'selected' : '' ?>>Cao (Cần bổ sung trong 30 ngày)</option>
                    <option value="urgent" <?= $request_data['urgency'] === 'urgent' ? 'selected' : '' ?>>Khẩn Cấp (Thiếu hụt vận hành nghiêm trọng)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Cán Bộ Tiến Cử (Nếu có)</label>
                <select name="candidate_id"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Chưa chỉ định nhân sự cụ thể --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $request_data['candidate_id'] == $emp['id'] ? 'selected' : '' ?>>
                            <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Dự Toán Chi Phí / Phụ Cấp (VNĐ)</label>
                <input type="text" name="estimated_cost" value="<?= number_format($request_data['estimated_cost'], 0, ',', '.') ?>"
                       placeholder="VD: 5.000.000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Căn Cứ & Giải Trình Nhu Cầu Thực Tế *</label>
            <textarea name="rationale" rows="4" required placeholder="Nêu rõ tình hình vận hành, khối lượng công việc tăng thêm hoặc lý do dôi dư nhân sự cần luân chuyển..."
                      class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= e($request_data['rationale']) ?></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
            <a href="index.php?tab=requests" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                Hủy Bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span><?= $is_edit ? 'Lưu Thay Đổi' : 'Gửi Đề Xuất Lên Tập Đoàn' ?></span>
            </button>
        </div>

    </form>

</div>

<script>
function filterBranchDepartments(branchId) {
    const deptSelect = document.getElementById('reqDeptSelect');
    const options = deptSelect.querySelectorAll('option[data-branch]');
    options.forEach(opt => {
        if (!branchId || opt.getAttribute('data-branch') === branchId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}
// Init filter
document.addEventListener('DOMContentLoaded', () => {
    const curBranch = document.getElementById('reqBranchSelect').value;
    filterBranchDepartments(curBranch);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
