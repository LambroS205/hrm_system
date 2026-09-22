<?php
// modules/planning/form.php - Tạo / Chỉnh Sửa Kế Hoạch Quy Hoạch Điều Động Nhân Sự
$page_title = 'Lập Kế Hoạch Quy Hoạch Nhân Sự';

require_once __DIR__ . '/../../core/auth.php';
require_permission('planning', 'create');

$user = current_user();
$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$plan = [
    'plan_code' => '',
    'title' => '',
    'description' => '',
    'plan_type' => 'quarterly',
    'target_branch_id' => '',
    'status' => 'active',
    'start_date' => date('Y-m-01'),
    'end_date' => date('Y-m-t', strtotime('+3 months'))
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM transfer_plans WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $plan = $existing;
    }
} else {
    $last_id = (int)($pdo->query("SELECT MAX(id) FROM transfer_plans")->fetchColumn() ?: 0);
    $plan['plan_code'] = 'PLAN-' . date('Y') . '-Q' . ceil(date('n') / 3) . '-' . str_pad($last_id + 1, 2, '0', STR_PAD_LEFT);
}

// Xử lý Lưu Kế Hoạch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $plan_code = strtoupper(trim($_POST['plan_code'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $plan_type = $_POST['plan_type'] ?? 'quarterly';
    $target_branch_id = !empty($_POST['target_branch_id']) ? (int)$_POST['target_branch_id'] : null;
    $status = $_POST['status'] ?? 'active';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? date('Y-m-d');

    if (!empty($plan_code) && !empty($title)) {
        try {
            if ($is_edit) {
                $upStmt = $pdo->prepare("
                    UPDATE transfer_plans 
                    SET plan_code = ?, title = ?, description = ?, plan_type = ?, 
                        target_branch_id = ?, status = ?, start_date = ?, end_date = ?
                    WHERE id = ?
                ");
                $upStmt->execute([$plan_code, $title, $description, $plan_type, $target_branch_id, $status, $start_date, $end_date, $id]);
                set_flash('success', "Đã cập nhật kế hoạch quy hoạch: {$title}");
                redirect('modules/planning/view.php?id=' . $id);
            } else {
                $insStmt = $pdo->prepare("
                    INSERT INTO transfer_plans (plan_code, title, description, plan_type, target_branch_id, status, start_date, end_date, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insStmt->execute([$plan_code, $title, $description, $plan_type, $target_branch_id, $status, $start_date, $end_date, $user['id']]);
                $new_id = $pdo->lastInsertId();
                set_flash('success', "Đã tạo thành công kế hoạch: {$title}. Bây giờ bạn có thể thêm danh sách cán bộ điều động.");
                redirect('modules/planning/view.php?id=' . $new_id);
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi: Mã kế hoạch có thể đã tồn tại hoặc dữ liệu không hợp lệ.');
        }
    } else {
        set_flash('warning', 'Vui lòng điền đầy đủ Mã và Tên kế hoạch.');
    }
}

// Lấy danh sách chi nhánh
$branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 mb-2">
                <i class="fa-solid fa-calendar-check"></i> Chiến Lược Định Biên
            </div>
            <h2 class="text-xl font-bold text-slate-800"><?= $is_edit ? 'Chỉnh Sửa Kế Hoạch' : 'Lập Kế Hoạch Quy Hoạch Mới' ?></h2>
            <p class="text-sm text-slate-500 mt-0.5">Xác định khung thời gian, mục tiêu và chi nhánh trọng điểm cho chiến dịch điều động nhân sự.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 flex-shrink-0">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Quay Lại</span>
        </a>
    </div>

    <form action="form.php<?= $is_edit ? '?id=' . $id : '' ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
        <?= csrf_field() ?>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mã Kế Hoạch (Plan Code) *</label>
                <input type="text" name="plan_code" value="<?= e($plan['plan_code']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Loại Hình Chiến Dịch *</label>
                <select name="plan_type" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="quarterly" <?= $plan['plan_type'] === 'quarterly' ? 'selected' : '' ?>>Quy hoạch định kỳ theo Quý</option>
                    <option value="annual" <?= $plan['plan_type'] === 'annual' ? 'selected' : '' ?>>Kế hoạch định biên Hàng Năm</option>
                    <option value="branch_expansion" <?= $plan['plan_type'] === 'branch_expansion' ? 'selected' : '' ?>>Mở rộng & Khai trương Chi nhánh mới</option>
                    <option value="emergency_rebalance" <?= $plan['plan_type'] === 'emergency_rebalance' ? 'selected' : '' ?>>Điều động & Cân bằng Khẩn cấp</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Tiêu Đề Kế Hoạch *</label>
            <input type="text" name="title" value="<?= e($plan['title']) ?>" required placeholder="VD: Kế Hoạch Luân Chuyển Cán Bộ Quý 2/2026 Toàn Hệ Thống..."
                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chi Nhánh Trọng Điểm Cần Tăng Cường</label>
                <select name="target_branch_id"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Toàn Bộ Các Chi Nhánh (Không cố định) --</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $plan['target_branch_id'] == $br['id'] ? 'selected' : '' ?>>
                            <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?> (<?= e($br['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Trạng Thái Kế Hoạch</label>
                <select name="status"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="draft" <?= $plan['status'] === 'draft' ? 'selected' : '' ?>>Bản Thảo (Đang xây dựng nháp)</option>
                    <option value="active" <?= $plan['status'] === 'active' ? 'selected' : '' ?>>Đang Kích Hoạt Triển Khai</option>
                    <option value="completed" <?= $plan['status'] === 'completed' ? 'selected' : '' ?>>Đã Hoàn Thành</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Ngày Bắt Đầu Hiệu Lực</label>
                <input type="date" name="start_date" value="<?= e($plan['start_date']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Ngày Kết Thúc / Hoàn Tất</label>
                <input type="date" name="end_date" value="<?= e($plan['end_date']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mục Tiêu & Thuyết Minh Chiến Lược</label>
            <textarea name="description" rows="3" placeholder="Nêu rõ lý do và mục đích điều động nhân sự của kế hoạch..."
                      class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= e($plan['description']) ?></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="index.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Hủy
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition flex items-center gap-1.5">
                <i class="fa-solid fa-floppy-disk"></i>
                <span><?= $is_edit ? 'Lưu Cập Nhật' : 'Lưu & Thêm Cán Bộ Điều Động' ?></span>
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
