<?php
// modules/proposals/create.php - Tạo Đề Xuất Mới
$page_title = 'Tạo Đề Xuất Mới';

require_once __DIR__ . '/../../core/auth.php';
require_permission('proposals', 'create');

$user = current_user();

// Lấy danh sách nhân viên (để admin tạo hộ)
$employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.avatar,
           d.name AS department_name, p.name AS position_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

// Tìm employee_id của user hiện tại (nếu có liên kết)
$current_emp_id = 0;
$empCheck = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
$empCheck->execute([$user['id']]);
$current_emp = $empCheck->fetch();
if ($current_emp) {
    $current_emp_id = $current_emp['id'];
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $type        = $_POST['type'] ?? 'leave';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date    = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $leave_type  = !empty($_POST['leave_type']) ? $_POST['leave_type'] : null;
    $total_days  = !empty($_POST['total_days']) ? (float)$_POST['total_days'] : 0;
    $amount      = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $priority    = $_POST['priority'] ?? 'normal';

    // Validation
    $errors = [];
    if ($employee_id <= 0) $errors[] = 'Vui lòng chọn nhân viên.';
    if (empty($title)) $errors[] = 'Tiêu đề không được để trống.';
    if ($type === 'leave' && empty($leave_type)) $errors[] = 'Vui lòng chọn loại nghỉ phép.';
    if (in_array($type, ['leave', 'overtime', 'business_trip']) && (empty($start_date) || empty($end_date))) {
        $errors[] = 'Vui lòng nhập ngày bắt đầu và kết thúc.';
    }
    if (!empty($start_date) && !empty($end_date) && $end_date < $start_date) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    if (empty($errors)) {
        // Tự động tạo mã đề xuất
        $year = date('Y');
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE proposal_code LIKE ?");
        $countStmt->execute(["DX-{$year}-%"]);
        $next_num = (int)$countStmt->fetchColumn() + 1;
        $proposal_code = sprintf("DX-%s-%03d", $year, $next_num);

        // Tự động tính tổng ngày nếu có date range
        if (!empty($start_date) && !empty($end_date) && $total_days <= 0) {
            $d1 = new DateTime($start_date);
            $d2 = new DateTime($end_date);
            $diff = $d1->diff($d2);
            $total_days = $diff->days + 1;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO proposals (proposal_code, employee_id, type, title, description, start_date, end_date, leave_type, total_days, amount, priority, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $proposal_code, $employee_id, $type, $title, $description,
                $start_date, $end_date, $leave_type, $total_days, $amount, $priority
            ]);

            set_flash('success', "Đã tạo đề xuất {$proposal_code} thành công! Đang chờ phê duyệt.");
            redirect('modules/proposals/index.php');
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi khi tạo đề xuất: ' . $e->getMessage());
        }
    } else {
        set_flash('danger', implode('<br>', $errors));
    }
}

$type_options = [
    'leave'         => ['Nghỉ Phép',       'fa-calendar-minus', 'sky',    'Xin nghỉ phép năm, ốm, thai sản, việc riêng'],
    'overtime'      => ['Tăng Ca',          'fa-clock',          'violet', 'Đề xuất làm thêm giờ ngoài giờ hành chính'],
    'business_trip' => ['Công Tác',         'fa-plane',          'teal',   'Đi công tác ngoài văn phòng / chi nhánh'],
    'salary_raise'  => ['Tăng Lương',       'fa-coins',          'amber',  'Đề xuất xem xét tăng lương / phụ cấp'],
    'equipment'     => ['Thiết Bị / Vật Tư','fa-laptop',         'indigo', 'Yêu cầu cấp phát thiết bị, văn phòng phẩm'],
    'other'         => ['Đề Xuất Khác',     'fa-clipboard-list', 'slate',  'Các đề xuất không thuộc phân loại trên'],
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="flex items-center justify-between bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 mb-2">
                <i class="fa-solid fa-plus-circle"></i> Lập Phiếu Đề Xuất
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white">Tạo Đề Xuất Mới</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Điền thông tin bên dưới để gửi đề xuất chờ phê duyệt.</p>
        </div>
        <a href="<?= base_url('modules/proposals/index.php') ?>"
           class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition flex items-center gap-2 border border-slate-200 dark:border-slate-600">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Quay Lại</span>
        </a>
    </div>

    <!-- Form Tạo Đề Xuất -->
    <form action="create.php" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Nhân Viên Đề Xuất -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-lg bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs">1</span>
                Thông Tin Người Đề Xuất
            </h3>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Nhân Viên <span class="text-rose-500">*</span></label>
                <select name="employee_id" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($current_emp_id == $emp['id'] || (isset($_POST['employee_id']) && (int)$_POST['employee_id'] == $emp['id'])) ? 'selected' : '' ?>>
                            <?= e($emp['employee_code']) ?> - <?= e($emp['fullname']) ?> (<?= e($emp['department_name'] ?? 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Loại Đề Xuất -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-lg bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs">2</span>
                Loại Đề Xuất
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php foreach ($type_options as $key => $opt): ?>
                    <label class="type-card cursor-pointer">
                        <input type="radio" name="type" value="<?= $key ?>" class="sr-only peer" <?= (!isset($_POST['type']) && $key === 'leave') || (isset($_POST['type']) && $_POST['type'] === $key) ? 'checked' : '' ?> onchange="onTypeChange('<?= $key ?>')">
                        <div class="p-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 hover:border-<?= $opt[2] ?>-400 dark:hover:border-<?= $opt[2] ?>-600 peer-checked:border-<?= $opt[2] ?>-500 peer-checked:bg-<?= $opt[2] ?>-50 dark:peer-checked:bg-<?= $opt[2] ?>-950/40 peer-checked:shadow-md transition-all">
                            <div class="flex items-center gap-2.5 mb-1.5">
                                <div class="w-8 h-8 rounded-xl bg-<?= $opt[2] ?>-100 dark:bg-<?= $opt[2] ?>-900/60 text-<?= $opt[2] ?>-600 dark:text-<?= $opt[2] ?>-400 flex items-center justify-center text-sm">
                                    <i class="fa-solid <?= $opt[1] ?>"></i>
                                </div>
                                <span class="font-bold text-slate-800 dark:text-white text-sm"><?= $opt[0] ?></span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed"><?= $opt[3] ?></p>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Nội Dung Đề Xuất -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-lg bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs">3</span>
                Nội Dung Đề Xuất
            </h3>

            <div class="space-y-4">
                <!-- Tiêu đề -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Tiêu Đề Đề Xuất <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" required placeholder="VD: Xin nghỉ phép năm 3 ngày..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-violet-500 outline-none">
                </div>

                <!-- Mô tả chi tiết -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Mô Tả Chi Tiết</label>
                    <textarea name="description" rows="4" placeholder="Mô tả chi tiết lý do, nội dung đề xuất..."
                              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-violet-500 outline-none resize-none"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <!-- Loại nghỉ phép (chỉ hiện khi type = leave) -->
                <div id="leaveTypeField" class="transition-all">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Loại Nghỉ Phép <span class="text-rose-500">*</span></label>
                    <select name="leave_type"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                        <option value="">-- Chọn loại --</option>
                        <option value="annual" <?= (isset($_POST['leave_type']) && $_POST['leave_type'] === 'annual') ? 'selected' : '' ?>>🌴 Phép Năm (Annual Leave)</option>
                        <option value="sick" <?= (isset($_POST['leave_type']) && $_POST['leave_type'] === 'sick') ? 'selected' : '' ?>>🏥 Nghỉ Ốm (Sick Leave)</option>
                        <option value="maternity" <?= (isset($_POST['leave_type']) && $_POST['leave_type'] === 'maternity') ? 'selected' : '' ?>>👶 Thai Sản (Maternity)</option>
                        <option value="personal" <?= (isset($_POST['leave_type']) && $_POST['leave_type'] === 'personal') ? 'selected' : '' ?>>🏠 Việc Riêng (Personal)</option>
                        <option value="unpaid" <?= (isset($_POST['leave_type']) && $_POST['leave_type'] === 'unpaid') ? 'selected' : '' ?>>💤 Không Lương (Unpaid)</option>
                    </select>
                </div>

                <!-- Ngày bắt đầu / kết thúc (cho leave, overtime, business_trip) -->
                <div id="dateRangeField" class="grid grid-cols-1 sm:grid-cols-3 gap-4 transition-all">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Ngày Bắt Đầu</label>
                        <input type="date" name="start_date" value="<?= e($_POST['start_date'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Ngày Kết Thúc</label>
                        <input type="date" name="end_date" value="<?= e($_POST['end_date'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Tổng Số Ngày</label>
                        <input type="number" name="total_days" value="<?= e($_POST['total_days'] ?? '') ?>" step="0.5" min="0" placeholder="Tự động tính"
                               class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    </div>
                </div>

                <!-- Số tiền (cho salary_raise, equipment) -->
                <div id="amountField" class="hidden transition-all">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Số Tiền (VNĐ)</label>
                    <input type="number" name="amount" value="<?= e($_POST['amount'] ?? '0') ?>" min="0" step="100000" placeholder="0"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-violet-500 outline-none">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Nhập mức đề xuất tăng lương hoặc giá trị thiết bị cần cấp.</p>
                </div>
            </div>
        </div>

        <!-- Mức Độ Ưu Tiên -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-lg bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs">4</span>
                Mức Độ Ưu Tiên
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="priority" value="low" class="sr-only peer" <?= (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'checked' : '' ?>>
                    <div class="p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 peer-checked:border-slate-500 peer-checked:bg-slate-100 dark:peer-checked:bg-slate-700 transition-all text-center">
                        <div class="text-lg mb-1">🔵</div>
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-200">Thấp</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="priority" value="normal" class="sr-only peer" <?= (!isset($_POST['priority']) || $_POST['priority'] === 'normal') ? 'checked' : '' ?>>
                    <div class="p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 peer-checked:border-sky-500 peer-checked:bg-sky-50 dark:peer-checked:bg-sky-950/40 transition-all text-center">
                        <div class="text-lg mb-1">🟢</div>
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-200">Bình Thường</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="priority" value="high" class="sr-only peer" <?= (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'checked' : '' ?>>
                    <div class="p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-950/40 transition-all text-center">
                        <div class="text-lg mb-1">🟡</div>
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-200">Cao</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="priority" value="urgent" class="sr-only peer" <?= (isset($_POST['priority']) && $_POST['priority'] === 'urgent') ? 'checked' : '' ?>>
                    <div class="p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 peer-checked:border-rose-500 peer-checked:bg-rose-50 dark:peer-checked:bg-rose-950/40 transition-all text-center">
                        <div class="text-lg mb-1">🔴</div>
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-200">Khẩn Cấp</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Nút Submit -->
        <div class="flex items-center justify-between bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-xs text-slate-500 dark:text-slate-400">
                Đề xuất sau khi gửi sẽ ở trạng thái <strong class="text-amber-600 dark:text-amber-400">"Chờ Duyệt"</strong> cho đến khi quản lý phê duyệt.
            </span>
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold rounded-xl shadow-md shadow-violet-100 dark:shadow-none transition flex items-center gap-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Gửi Đề Xuất</span>
            </button>
        </div>
    </form>
</div>

<script>
// Dynamic form: hiện/ẩn fields theo loại đề xuất
function onTypeChange(type) {
    const leaveTypeField = document.getElementById('leaveTypeField');
    const dateRangeField = document.getElementById('dateRangeField');
    const amountField = document.getElementById('amountField');

    // Reset
    leaveTypeField.classList.add('hidden');
    dateRangeField.classList.add('hidden');
    amountField.classList.add('hidden');

    if (type === 'leave') {
        leaveTypeField.classList.remove('hidden');
        dateRangeField.classList.remove('hidden');
    } else if (type === 'overtime' || type === 'business_trip') {
        dateRangeField.classList.remove('hidden');
    } else if (type === 'salary_raise' || type === 'equipment') {
        amountField.classList.remove('hidden');
    }
    // 'other' -> chỉ title + description
}

// Auto-calculate total days
document.querySelector('input[name="start_date"]')?.addEventListener('change', calcDays);
document.querySelector('input[name="end_date"]')?.addEventListener('change', calcDays);

function calcDays() {
    const start = document.querySelector('input[name="start_date"]').value;
    const end = document.querySelector('input[name="end_date"]').value;
    if (start && end) {
        const d1 = new Date(start);
        const d2 = new Date(end);
        if (d2 >= d1) {
            const diff = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
            document.querySelector('input[name="total_days"]').value = diff;
        }
    }
}

// Init on page load
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="type"]:checked');
    if (checked) onTypeChange(checked.value);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
