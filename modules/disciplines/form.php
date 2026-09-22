<?php
// modules/disciplines/form.php - Lập mới và Chỉnh sửa Biên bản Kỷ luật
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('disciplines', 'create');
    $page_title = 'Chỉnh Sửa Quyết Định Kỷ Luật';
} else {
    require_permission('disciplines', 'create');
    $page_title = 'Lập Quyết Định / Biên Bản Kỷ Luật Mới';
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

$discipline = [
    'discipline_code' => '',
    'employee_id'     => isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : '',
    'discipline_type' => 'warning',
    'title'           => '',
    'description'     => '',
    'penalty_amount'  => '0',
    'decision_number' => '',
    'discipline_date' => date('Y-m-d'),
    'status'          => 'pending',
    'attachment'      => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM disciplines WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy biên bản kỷ luật này.');
        redirect('modules/disciplines/index.php');
    }
    $discipline = $existing;
} else {
    // Tự sinh mã biên bản tiếp theo (VD: KL-2026-004)
    $year = date('Y');
    $last_code = $pdo->query("SELECT discipline_code FROM disciplines WHERE discipline_code LIKE 'KL-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_num = 1;
    if ($last_code && preg_match('/KL-\d{4}-(\d+)/', $last_code, $matches)) {
        $next_num = (int)$matches[1] + 1;
    }
    $discipline['discipline_code'] = sprintf('KL-%s-%03d', $year, $next_num);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $employee_id     = (int)($_POST['employee_id'] ?? 0);
    $discipline_type = $_POST['discipline_type'] ?? 'warning';
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $penalty_amount  = (float)str_replace(['.', ',', ' '], '', $_POST['penalty_amount'] ?? '0');
    $decision_number = trim($_POST['decision_number'] ?? '');
    $discipline_date = !empty($_POST['discipline_date']) ? $_POST['discipline_date'] : date('Y-m-d');
    $status          = $_POST['status'] ?? 'pending';

    $discipline = array_merge($discipline, [
        'employee_id'     => $employee_id,
        'discipline_type' => $discipline_type,
        'title'           => $title,
        'description'     => $description,
        'penalty_amount'  => $penalty_amount,
        'decision_number' => $decision_number,
        'discipline_date' => $discipline_date,
        'status'          => $status
    ]);

    if ($employee_id <= 0) {
        $errors[] = 'Vui lòng chọn cán bộ / nhân viên bị xử lý kỷ luật.';
    }

    if (empty($title)) {
        $errors[] = 'Tiêu đề / Hành vi vi phạm không được để trống.';
    }

    // Xử lý kéo thả upload tệp đính kèm
    $attachment_name = $discipline['attachment'];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_name = $_FILES['attachment']['name'];
        $file_size = $_FILES['attachment']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_exts)) {
            $errors[] = 'Định dạng tệp đính kèm không hợp lệ. Chỉ chấp nhận PDF, Word hoặc Hình ảnh.';
        } elseif ($file_size > 10 * 1024 * 1024) {
            $errors[] = 'Dung lượng tệp đính kèm không được vượt quá 10MB.';
        } else {
            $upload_dir = __DIR__ . '/../../assets/uploads/attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = 'discipline_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
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
                $upSql = "UPDATE disciplines SET 
                            employee_id = :employee_id,
                            discipline_type = :discipline_type,
                            title = :title,
                            description = :description,
                            penalty_amount = :penalty_amount,
                            decision_number = :decision_number,
                            discipline_date = :discipline_date,
                            status = :status,
                            attachment = :attachment";
                
                $params = [
                    ':employee_id'     => $employee_id,
                    ':discipline_type' => $discipline_type,
                    ':title'           => $title,
                    ':description'     => $description,
                    ':penalty_amount'  => $penalty_amount,
                    ':decision_number' => $decision_number,
                    ':discipline_date' => $discipline_date,
                    ':status'          => $status,
                    ':attachment'      => $attachment_name,
                    ':id'              => $id
                ];

                if ($status === 'approved' && empty($discipline['approved_by'])) {
                    $upSql .= ", approved_by = :approved_by, approved_at = :approved_at";
                    $params[':approved_by'] = $userId;
                    $params[':approved_at'] = $now;
                } elseif ($status === 'executed') {
                    $upSql .= ", executed_at = :executed_at";
                    $params[':executed_at'] = $now;
                    if (empty($discipline['approved_by'])) {
                        $upSql .= ", approved_by = :approved_by, approved_at = :approved_at";
                        $params[':approved_by'] = $userId;
                        $params[':approved_at'] = $now;
                    }
                }

                $upSql .= " WHERE id = :id";
                $stmt = $pdo->prepare($upSql);
                $stmt->execute($params);

                set_flash('success', "Đã cập nhật biên bản kỷ luật '{$discipline['discipline_code']}' thành công.");
            } else {
                $inSql = "INSERT INTO disciplines 
                            (discipline_code, employee_id, discipline_type, title, description, penalty_amount, decision_number, discipline_date, status, attachment, created_by, approved_by, approved_at, executed_at)
                          VALUES 
                            (:discipline_code, :employee_id, :discipline_type, :title, :description, :penalty_amount, :decision_number, :discipline_date, :status, :attachment, :created_by, :approved_by, :approved_at, :executed_at)";
                
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
                    ':discipline_code' => $discipline['discipline_code'],
                    ':employee_id'     => $employee_id,
                    ':discipline_type' => $discipline_type,
                    ':title'           => $title,
                    ':description'     => $description,
                    ':penalty_amount'  => $penalty_amount,
                    ':decision_number' => $decision_number,
                    ':discipline_date' => $discipline_date,
                    ':status'          => $status,
                    ':attachment'      => $attachment_name,
                    ':created_by'      => $userId,
                    ':approved_by'     => $approved_by,
                    ':approved_at'     => $approved_at,
                    ':executed_at'     => $executed_at
                ]);

                set_flash('success', "Đã lập quyết định / biên bản kỷ luật '{$discipline['discipline_code']}' thành công.");
            }

            redirect('modules/disciplines/index.php');
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
                    <i class="fa-solid fa-scale-unbalanced text-rose-600"></i>
                    <span><?= $is_edit ? 'Chỉnh Sửa Quyết Định Kỷ Luật' : 'Lập Quyết Định / Biên Bản Kỷ Luật Mới' ?></span>
                </h2>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 ml-12">
                Mã định danh: <span class="font-mono font-bold text-rose-600 dark:text-rose-400"><?= e($discipline['discipline_code']) ?></span>
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
            
            <!-- Phần 1: Đối Tượng & Mức Độ Kỷ Luật -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs">1</span>
                    <span>Cán Bộ & Hình Thức Xử Lý</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Chọn nhân viên -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Cán Bộ / Nhân Viên Bị Xử Lý <span class="text-rose-500">*</span>
                        </label>
                        <select name="employee_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                            <option value="">-- Chọn cán bộ bị xử lý --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($discipline['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                    <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) - <?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?> / <?= e($emp['department_name'] ?? 'Phòng ban') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Hình thức kỷ luật -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Hình Thức Kỷ Luật <span class="text-rose-500">*</span>
                        </label>
                        <select name="discipline_type" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                            <option value="warning" <?= $discipline['discipline_type'] === 'warning' ? 'selected' : '' ?>>⚠️ Nhắc Nhở / Phê Bình Nội Bộ</option>
                            <option value="reprimand" <?= $discipline['discipline_type'] === 'reprimand' ? 'selected' : '' ?>>📜 Khiển Trách Toàn Cơ Quan</option>
                            <option value="salary_cut" <?= $discipline['discipline_type'] === 'salary_cut' ? 'selected' : '' ?>>📉 Trừ Lương / Kéo Dài Nâng Bậc Lương</option>
                            <option value="demotion" <?= $discipline['discipline_type'] === 'demotion' ? 'selected' : '' ?>>🔻 Giáng Chức / Cách Chức</option>
                            <option value="termination" <?= $discipline['discipline_type'] === 'termination' ? 'selected' : '' ?>>⛔ Buộc Thôi Việc / Sa Thải</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Phần 2: Nội Dung Vi Phạm & Chế Tài -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs">2</span>
                    <span>Nội Dung Vi Phạm & Quyết Định Xử Lý</span>
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Hành Vi Vi Phạm / Tiêu Đề Biên Bản <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="title" required value="<?= e($discipline['title']) ?>" 
                               placeholder="Ví dụ: Vi phạm quy định bảo mật thông tin nội bộ / Đi muộn không phép quá số lần"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Mức phạt / Khấu trừ lương -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Mức Phạt Tiền / Khấu Trừ (VNĐ)
                            </label>
                            <div class="relative">
                                <input type="number" name="penalty_amount" id="penaltyAmountInput" step="50000" min="0" 
                                       value="<?= (float)$discipline['penalty_amount'] ?>" 
                                       placeholder="0"
                                       class="w-full pl-4 pr-12 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-mono font-bold text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                                <span class="absolute right-3.5 top-2.5 text-xs text-slate-400 font-semibold">₫</span>
                            </div>
                            <p id="penaltyFormattedPreview" class="text-[11px] text-rose-600 font-semibold mt-1">
                                <?= format_money($discipline['penalty_amount']) ?>
                            </p>
                        </div>

                        <!-- Số Quyết Định / Biên Bản -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Số Biên Bản / Quyết Định
                            </label>
                            <input type="text" name="decision_number" value="<?= e($discipline['decision_number']) ?>" 
                                   placeholder="VD: BB-08/BB-KL"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                        </div>

                        <!-- Ngày Lập / Áp Dụng -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                                Ngày Xử Lý Kỷ Luật <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" name="discipline_date" required value="<?= e($discipline['discipline_date']) ?>" 
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
                        </div>
                    </div>

                    <!-- Trạng thái quy trình -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Trạng Thái Quy Trình
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="pending" <?= $discipline['status'] === 'pending' ? 'checked' : '' ?> class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <div class="text-xs font-bold text-amber-700 dark:text-amber-400">Chờ Xử Lý</div>
                                    <div class="text-[10px] text-slate-400">Đang xem xét biên bản</div>
                                </div>
                            </label>
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="approved" <?= $discipline['status'] === 'approved' ? 'checked' : '' ?> class="text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="text-xs font-bold text-sky-700 dark:text-sky-400">Đã Phê Duyệt</div>
                                    <div class="text-[10px] text-slate-400">Quyết định đã ký duyệt</div>
                                </div>
                            </label>
                            <label class="cursor-pointer border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <input type="radio" name="status" value="executed" <?= $discipline['status'] === 'executed' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-bold text-emerald-700 dark:text-emerald-400">Đã Thi Hành</div>
                                    <div class="text-[10px] text-slate-400">Đã trừ lương / thi hành kỷ luật</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Mô tả chi tiết vi phạm -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Chi Tiết Vi Phạm & Kết Luận Của Hội Đồng
                        </label>
                        <textarea name="description" rows="3" placeholder="Ghi nhận diễn biến vi phạm, giải trình của cán bộ và ý kiến kết luận của hội đồng kỷ luật..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition"><?= e($discipline['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Phần 3: Kéo - Thả Tải Tệp Biên Bản Scan Đính Kèm (Drag & Drop Zone) -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs">3</span>
                    <span>Tệp Biên Bản Scan Đính Kèm</span>
                </h3>

                <!-- Dropzone Kéo Thả -->
                <div class="drag-drop-zone relative border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-rose-500 dark:hover:border-rose-500 rounded-3xl p-6 text-center cursor-pointer transition-all duration-200 bg-slate-50/60 dark:bg-slate-900/40">
                    <input type="file" name="attachment" class="drag-drop-input absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                    
                    <div class="drag-drop-idle space-y-2 py-4">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Kéo và thả tệp biên bản vào đây, hoặc <span class="text-rose-600 dark:text-rose-400 underline">chọn từ máy tính</span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Hỗ trợ: PDF, Word (.doc, .docx), PNG, JPG (Tối đa 10MB)
                        </p>
                    </div>

                    <div class="drag-drop-preview hidden text-left relative z-20"></div>
                </div>

                <!-- Tệp hiện tại nếu có -->
                <?php if (!empty($discipline['attachment']) && file_exists(__DIR__ . '/../../assets/uploads/attachments/' . $discipline['attachment'])): ?>
                    <div class="mt-3 p-3.5 bg-rose-50/50 dark:bg-rose-950/20 rounded-2xl border border-rose-200/60 dark:border-rose-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-paperclip text-rose-600 text-lg"></i>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Tệp hiện tại: <?= e($discipline['attachment']) ?></span>
                                <span class="text-[11px] text-slate-400">Tải tệp mới ở trên nếu bạn muốn thay thế tệp này</span>
                            </div>
                        </div>
                        <a href="<?= base_url('assets/uploads/attachments/' . e($discipline['attachment'])) ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-rose-200 text-rose-700 dark:text-rose-300 text-xs font-bold hover:bg-rose-50 transition flex items-center gap-1.5 shadow-2xs">
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
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-sm font-semibold shadow-md shadow-rose-200 dark:shadow-none transition flex items-center gap-2">
                <i class="fa-solid fa-check"></i>
                <span><?= $is_edit ? 'Cập Nhật Quyết Định' : 'Ban Hành Quyết Định Kỷ Luật' ?></span>
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    HRMSDragDrop.initDropzone({
        dropzoneSelector: '.drag-drop-zone',
        inputSelector: '.drag-drop-input',
        previewSelector: '.drag-drop-preview',
        allowedExts: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],
        maxSizeMB: 10
    });

    const penaltyInput = document.getElementById('penaltyAmountInput');
    const penaltyPreview = document.getElementById('penaltyFormattedPreview');
    if (penaltyInput && penaltyPreview) {
        penaltyInput.addEventListener('input', function() {
            const val = parseFloat(this.value) || 0;
            penaltyPreview.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
