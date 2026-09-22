<?php
// modules/rewards/form.php - Lập mới và Chỉnh sửa Quyết định Khen thưởng
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('rewards', 'create'); // Cần quyền quản lý khen thưởng
    $page_title = 'Chỉnh Sửa Quyết Định Khen Thưởng';
} else {
    require_permission('rewards', 'create');
    $page_title = 'Lập Quyết Định Khen Thưởng Mới';
}

// Lấy danh sách nhân viên đang làm việc kèm thông tin phòng ban & chi nhánh
$employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.avatar,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

$reward = [
    'reward_code'     => '',
    'employee_id'     => isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : '',
    'reward_type'     => 'bonus',
    'title'           => '',
    'description'     => '',
    'amount'          => '0',
    'decision_number' => '',
    'reward_date'     => date('Y-m-d'),
    'status'          => 'pending',
    'attachment'      => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy quyết định khen thưởng này.');
        redirect('modules/rewards/index.php');
    }
    $reward = $existing;
} else {
    // Tự sinh mã quyết định khen thưởng tiếp theo (VD: KT-2026-005)
    $year = date('Y');
    $last_code = $pdo->query("SELECT reward_code FROM rewards WHERE reward_code LIKE 'KT-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_num = 1;
    if ($last_code && preg_match('/KT-\d{4}-(\d+)/', $last_code, $matches)) {
        $next_num = (int)$matches[1] + 1;
    }
    $reward['reward_code'] = sprintf('KT-%s-%03d', $year, $next_num);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $employee_id     = (int)($_POST['employee_id'] ?? 0);
    $reward_type     = $_POST['reward_type'] ?? 'bonus';
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $amount          = (float)str_replace(['.', ',', ' '], '', $_POST['amount'] ?? '0');
    $decision_number = trim($_POST['decision_number'] ?? '');
    $reward_date     = !empty($_POST['reward_date']) ? $_POST['reward_date'] : date('Y-m-d');
    $status          = $_POST['status'] ?? 'pending';

    // Cập nhật lại mảng dữ liệu giữ state form
    $reward = array_merge($reward, [
        'employee_id'     => $employee_id,
        'reward_type'     => $reward_type,
        'title'           => $title,
        'description'     => $description,
        'amount'          => $amount,
        'decision_number' => $decision_number,
        'reward_date'     => $reward_date,
        'status'          => $status
    ]);

    if ($employee_id <= 0) {
        $errors[] = 'Vui lòng chọn cán bộ / nhân viên được khen thưởng.';
    }

    if (empty($title)) {
        $errors[] = 'Tiêu đề khen thưởng không được để trống.';
    }

    // Xử lý tệp đính kèm (Drag & Drop File Upload)
    $attachment_name = $reward['attachment'];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_name = $_FILES['attachment']['name'];
        $file_size = $_FILES['attachment']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_exts)) {
            $errors[] = 'Định dạng tệp đính kèm không hợp lệ. Chỉ chấp nhận PDF, Word (doc, docx) hoặc Hình ảnh.';
        } elseif ($file_size > 10 * 1024 * 1024) {
            $errors[] = 'Dung lượng tệp đính kèm không được vượt quá 10MB.';
        } else {
            $upload_dir = __DIR__ . '/../../assets/uploads/attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = 'reward_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                // Xóa file cũ nếu có
                if (!empty($attachment_name) && file_exists($upload_dir . $attachment_name)) {
                    @unlink($upload_dir . $attachment_name);
                }
                $attachment_name = $new_file_name;
            } else {
                $errors[] = 'Không thể lưu tệp đính kèm tải lên máy chủ.';
            }
        }
    }

    if (empty($errors)) {
        $user = current_user();
        $userId = $user['id'] ?? null;
        $now = date('Y-m-d H:i:s');

        try {
            if ($is_edit) {
                $upSql = "UPDATE rewards SET 
                            employee_id = :employee_id,
                            reward_type = :reward_type,
                            title = :title,
                            description = :description,
                            amount = :amount,
                            decision_number = :decision_number,
                            reward_date = :reward_date,
                            status = :status,
                            attachment = :attachment";
                
                $params = [
                    ':employee_id'     => $employee_id,
                    ':reward_type'     => $reward_type,
                    ':title'           => $title,
                    ':description'     => $description,
                    ':amount'          => $amount,
                    ':decision_number' => $decision_number,
                    ':reward_date'     => $reward_date,
                    ':status'          => $status,
                    ':attachment'      => $attachment_name,
                    ':id'              => $id
                ];

                if ($status === 'approved' && empty($reward['approved_by'])) {
                    $upSql .= ", approved_by = :approved_by, approved_at = :approved_at";
                    $params[':approved_by'] = $userId;
                    $params[':approved_at'] = $now;
                } elseif ($status === 'executed') {
                    $upSql .= ", executed_at = :executed_at";
                    $params[':executed_at'] = $now;
                    if (empty($reward['approved_by'])) {
                        $upSql .= ", approved_by = :approved_by, approved_at = :approved_at";
                        $params[':approved_by'] = $userId;
                        $params[':approved_at'] = $now;
                    }
                }

                $upSql .= " WHERE id = :id";
                $stmt = $pdo->prepare($upSql);
                $stmt->execute($params);

                set_flash('success', "Đã cập nhật quyết định khen thưởng '{$reward['reward_code']}' thành công.");
            } else {
                $inSql = "INSERT INTO rewards 
                            (reward_code, employee_id, reward_type, title, description, amount, decision_number, reward_date, status, attachment, created_by, approved_by, approved_at, executed_at)
                          VALUES 
                            (:reward_code, :employee_id, :reward_type, :title, :description, :amount, :decision_number, :reward_date, :status, :attachment, :created_by, :approved_by, :approved_at, :executed_at)";
                
                $approved_by = null;
                $approved_at = null;
                $executed_at = null;
                if ($status === 'approved') {
                    $approved_by = $userId;
                    $approved_at = $now;
                } elseif ($status === 'executed') {
                    $approved_by = $userId;
                    $approved_at = $now;
                    $executed_at = $now;
                }

                $stmt = $pdo->prepare($inSql);
                $stmt->execute([
                    ':reward_code'     => $reward['reward_code'],
                    ':employee_id'     => $employee_id,
                    ':reward_type'     => $reward_type,
                    ':title'           => $title,
                    ':description'     => $description,
                    ':amount'          => $amount,
                    ':decision_number' => $decision_number,
                    ':reward_date'     => $reward_date,
                    ':status'          => $status,
                    ':attachment'      => $attachment_name,
                    ':created_by'      => $userId,
                    ':approved_by'     => $approved_by,
                    ':approved_at'     => $approved_at,
                    ':executed_at'     => $executed_at
                ]);

                set_flash('success', "Đã tạo mới quyết định khen thưởng '{$reward['reward_code']}' thành công.");
            }

            redirect('modules/rewards/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Điều Hướng Form -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition shadow-xs">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-award text-emerald-600"></i>
                    <span><?= $is_edit ? 'Chỉnh Sửa Quyết Định Khen Thưởng' : 'Lập Quyết Định Khen Thưởng Mới' ?></span>
                </h2>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 ml-12">
                Mã định danh: <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= e($reward['reward_code']) ?></span>
            </p>
        </div>

        <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition">
            Hủy Bỏ
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                <span>Vui lòng kiểm tra lại thông tin:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Khối Form Chính -->
    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <?= csrf_field() ?>

        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 sm:p-8 space-y-6">
            
            <!-- Phần 1: Đối Tượng & Hình Thức Khen Thưởng -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">1</span>
                    <span>Cán Bộ & Hình Thức Khen Thưởng</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Chọn nhân viên -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Cán Bộ / Nhân Viên Được Khen Thưởng <span class="text-rose-500">*</span>
                        </label>
                        <select name="employee_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            <option value="">-- Chọn cán bộ nhận khen thưởng --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($reward['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                    <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) - <?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?> / <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Hình thức khen thưởng -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Hình Thức Khen Thưởng <span class="text-rose-500">*</span>
                        </label>
                        <select name="reward_type" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            <option value="bonus" <?= $reward['reward_type'] === 'bonus' ? 'selected' : '' ?>>💰 Thưởng Tiền Mặt / Chuyển Khoản</option>
                            <option value="certificate" <?= $reward['reward_type'] === 'certificate' ? 'selected' : '' ?>>📜 Giấy Khen / Bằng Khen</option>
                            <option value="promotion_bonus" <?= $reward['reward_type'] === 'promotion_bonus' ? 'selected' : '' ?>>🚀 Thưởng Bổ Nhiệm / Thăng Cấp</option>
                            <option value="achievement" <?= $reward['reward_type'] === 'achievement' ? 'selected' : '' ?>>⭐ Thành Tích Xuất Sắc / Sáng Kiến Đột Phá</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Phần 2: Nội Dung & Giá Trị Khen Thưởng -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">2</span>
                    <span>Nội Dung & Quyết Định Khen Thưởng</span>
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Tiêu Đề Khen Thưởng <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="title" required value="<?= e($reward['title']) ?>" 
                               placeholder="Ví dụ: Khen thưởng thành tích xuất sắc triển khai dự án công nghệ Q1/2026"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Số tiền thưởng -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Tiền Thưởng (VNĐ)
                            </label>
                            <div class="relative">
                                <input type="number" name="amount" id="rewardAmountInput" step="100000" min="0" 
                                       value="<?= (float)$reward['amount'] ?>" 
                                       placeholder="0"
                                       class="w-full pl-4 pr-12 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-mono font-bold text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <span class="absolute right-3.5 top-2.5 text-xs text-slate-400 font-semibold">₫</span>
                            </div>
                            <p id="amountFormattedPreview" class="text-[11px] text-emerald-600 font-semibold mt-1">
                                <?= format_money($reward['amount']) ?>
                            </p>
                        </div>

                        <!-- Số Quyết Định -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Số Quyết Định / Văn Bản
                            </label>
                            <input type="text" name="decision_number" value="<?= e($reward['decision_number']) ?>" 
                                   placeholder="VD: QĐ-15/QĐ-KT"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>

                        <!-- Ngày Quyết Định -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Ngày Khen Thưởng <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" name="reward_date" required value="<?= e($reward['reward_date']) ?>" 
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>
                    </div>

                    <!-- Trạng thái quy trình -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Trạng Thái Phê Duyệt
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="pending" <?= $reward['status'] === 'pending' ? 'checked' : '' ?> class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <div class="text-xs font-bold text-amber-700 dark:text-amber-400">Chờ Phê Duyệt</div>
                                    <div class="text-[10px] text-slate-400">Đề xuất chờ lãnh đạo ký</div>
                                </div>
                            </label>
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="approved" <?= $reward['status'] === 'approved' ? 'checked' : '' ?> class="text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="text-xs font-bold text-sky-700 dark:text-sky-400">Đã Phê Duyệt</div>
                                    <div class="text-[10px] text-slate-400">Quyết định đã ký duyệt</div>
                                </div>
                            </label>
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="executed" <?= $reward['status'] === 'executed' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-bold text-emerald-700 dark:text-emerald-400">Đã Thực Thi</div>
                                    <div class="text-[10px] text-slate-400">Đã chi trả tiền thưởng / trao tặng</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Mô tả lý do chi tiết -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Căn Cứ & Thành Tích Chi Tiết
                        </label>
                        <textarea name="description" rows="3" placeholder="Ghi chú chi tiết các đóng góp cụ thể, biên bản bình xét thi đua của phòng ban..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"><?= e($reward['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Phần 3: Kéo - Thả Tải Tệp Scan Đính Kèm (Drag & Drop File Upload) -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">3</span>
                    <span>Hồ Sơ & Quyết Định Scan Đính Kèm</span>
                </h3>

                <!-- Dropzone Kéo Thả -->
                <div class="drag-drop-zone relative border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-emerald-500 dark:hover:border-emerald-500 rounded-3xl p-6 text-center cursor-pointer transition-all duration-200 bg-slate-50/60 dark:bg-slate-900/40">
                    <input type="file" name="attachment" class="drag-drop-input absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                    
                    <!-- Trạng thái chờ kéo thả -->
                    <div class="drag-drop-idle space-y-2 py-4">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Kéo và thả tệp quyết định vào đây, hoặc <span class="text-emerald-600 dark:text-emerald-400 underline">chọn từ máy tính</span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Hỗ trợ: PDF, Word (.doc, .docx), PNG, JPG (Tối đa 10MB)
                        </p>
                    </div>

                    <!-- Khu vực hiển thị tệp đã chọn -->
                    <div class="drag-drop-preview hidden text-left relative z-20"></div>
                </div>

                <!-- Tệp hiện có nếu là Edit -->
                <?php if (!empty($reward['attachment']) && file_exists(__DIR__ . '/../../assets/uploads/attachments/' . $reward['attachment'])): ?>
                    <div class="mt-3 p-3.5 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-paperclip text-emerald-600 text-lg"></i>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Tệp hiện tại: <?= e($reward['attachment']) ?></span>
                                <span class="text-[11px] text-slate-400">Tải tệp mới ở trên nếu bạn muốn thay thế tệp này</span>
                            </div>
                        </div>
                        <a href="<?= base_url('assets/uploads/attachments/' . e($reward['attachment'])) ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-emerald-200 text-emerald-700 dark:text-emerald-300 text-xs font-bold hover:bg-emerald-50 transition flex items-center gap-1.5 shadow-2xs">
                            <i class="fa-solid fa-arrow-down"></i> Tải Về / Xem
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Nút Thao Tác Chân Trang -->
        <div class="flex items-center justify-end gap-3">
            <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                Hủy Bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-semibold shadow-md shadow-emerald-200 dark:shadow-none transition flex items-center gap-2">
                <i class="fa-solid fa-check"></i>
                <span><?= $is_edit ? 'Cập Nhật Quyết Định' : 'Lưu & Ban Hành Quyết Định' ?></span>
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo kéo thả upload file
    HRMSDragDrop.initDropzone({
        dropzoneSelector: '.drag-drop-zone',
        inputSelector: '.drag-drop-input',
        previewSelector: '.drag-drop-preview',
        allowedExts: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],
        maxSizeMB: 10
    });

    // Cập nhật preview số tiền theo thời gian thực
    const amountInput = document.getElementById('rewardAmountInput');
    const amountPreview = document.getElementById('amountFormattedPreview');
    if (amountInput && amountPreview) {
        amountInput.addEventListener('input', function() {
            const val = parseFloat(this.value) || 0;
            amountPreview.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
