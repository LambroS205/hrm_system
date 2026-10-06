<?php
// modules/evaluations/form.php - Khởi Tạo & Chỉnh Sửa Phiếu Đánh Giá KPI
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('evaluations', 'edit');
    $page_title = 'Chỉnh Sửa Phiếu Đánh Giá KPI';
} else {
    require_permission('evaluations', 'create');
    $page_title = 'Khởi Tạo Phiếu Đánh Giá KPI';
}

// Lấy danh sách nhân viên
$employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.avatar, e.department_id,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

// Lấy danh sách các kỳ đánh giá
$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY status = 'active' DESC, year DESC, quarter DESC")->fetchAll();

// Lấy danh sách bộ tiêu chí KPI
$templates = $pdo->query("
    SELECT t.*, d.name AS department_name,
           (SELECT COUNT(*) FROM kpi_template_items WHERE template_id = t.id) AS items_count
    FROM kpi_templates t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.status = 'active'
    ORDER BY t.name ASC
")->fetchAll();

// Khởi tạo model
$evaluation = [
    'evaluation_code' => '',
    'period_id'       => isset($_GET['period_id']) ? (int)$_GET['period_id'] : '',
    'employee_id'     => isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : '',
    'template_id'     => '',
    'reviewer_id'     => 1, // Mặc định CEO / Admin
    'status'          => 'pending',
    'promotion_recommendation' => 'none'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy phiếu đánh giá này.');
        redirect('modules/evaluations/index.php');
    }
    $evaluation = $existing;
} else {
    // Nếu chưa có period_id, lấy kỳ active đầu tiên
    if (empty($evaluation['period_id']) && !empty($periods)) {
        foreach ($periods as $p) {
            if ($p['status'] === 'active') {
                $evaluation['period_id'] = $p['id'];
                break;
            }
        }
        if (empty($evaluation['period_id'])) {
            $evaluation['period_id'] = $periods[0]['id'];
        }
    }

    // Tự sinh mã phiếu chuẩn DG-YYYY-QN-NNN
    $curr_period = null;
    foreach ($periods as $p) {
        if ($p['id'] == $evaluation['period_id']) {
            $curr_period = $p;
            break;
        }
    }

    $year = $curr_period ? $curr_period['year'] : date('Y');
    $quarter_part = ($curr_period && $curr_period['quarter']) ? '-Q' . $curr_period['quarter'] : '';
    $prefix = "DG-{$year}{$quarter_part}";

    $last_code = $pdo->query("SELECT evaluation_code FROM evaluations WHERE evaluation_code LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_seq = 1;
    if ($last_code && preg_match('/-(\d+)$/', $last_code, $matches)) {
        $next_seq = (int)$matches[1] + 1;
    }
    $evaluation['evaluation_code'] = sprintf('%s-%03d', $prefix, $next_seq);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $period_id = (int)($_POST['period_id'] ?? 0);
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $template_id = (int)($_POST['template_id'] ?? 0);
    $reviewer_id = (int)($_POST['reviewer_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $promotion_rec = $_POST['promotion_recommendation'] ?? 'none';

    $evaluation['period_id'] = $period_id;
    $evaluation['employee_id'] = $employee_id;
    $evaluation['template_id'] = $template_id;
    $evaluation['reviewer_id'] = $reviewer_id;
    $evaluation['status'] = $status;
    $evaluation['promotion_recommendation'] = $promotion_rec;

    if ($period_id <= 0) $errors[] = 'Vui lòng chọn kỳ đánh giá.';
    if ($employee_id <= 0) $errors[] = 'Vui lòng chọn nhân viên được đánh giá.';
    if ($template_id <= 0) $errors[] = 'Vui lòng chọn bộ tiêu chí KPI áp dụng.';
    if ($reviewer_id <= 0) $errors[] = 'Vui lòng chọn quản lý chấm điểm (Reviewer).';

    // Kiểm tra trùng lặp phiếu đánh giá của nhân viên trong cùng 1 kỳ
    if (empty($errors)) {
        $dup_sql = "SELECT id FROM evaluations WHERE period_id = ? AND employee_id = ?" . ($is_edit ? " AND id != ?" : "");
        $dup_params = [$period_id, $employee_id];
        if ($is_edit) $dup_params[] = $id;
        $dup_stmt = $pdo->prepare($dup_sql);
        $dup_stmt->execute($dup_params);
        if ($dup_stmt->fetch()) {
            $errors[] = 'Nhân sự này đã có phiếu đánh giá trong kỳ này rồi. Không thể tạo trùng lặp.';
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE evaluations
                    SET period_id = ?, employee_id = ?, template_id = ?, reviewer_id = ?, status = ?, promotion_recommendation = ?
                    WHERE id = ?
                ");
                $stmt->execute([$period_id, $employee_id, $template_id, $reviewer_id, $status, $promotion_rec, $id]);
                set_flash('success', "Đã cập nhật phiếu đánh giá [{$evaluation['evaluation_code']}] thành công!");
                redirect('modules/evaluations/view.php?id=' . $id);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluations (evaluation_code, period_id, employee_id, template_id, reviewer_id, status, promotion_recommendation, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$evaluation['evaluation_code'], $period_id, $employee_id, $template_id, $reviewer_id, $status, $promotion_rec]);
                $new_id = (int)$pdo->lastInsertId();
                set_flash('success', "Đã tạo mới phiếu đánh giá [{$evaluation['evaluation_code']}] thành công!");
                redirect('modules/evaluations/view.php?id=' . $new_id);
            }
        } catch (Exception $e) {
            $errors[] = 'Lỗi lưu dữ liệu: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Breadcrumbs -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" class="hover:underline">Đánh Giá & KPI</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400"><?= $is_edit ? 'Chỉnh Sửa' : 'Khởi Tạo' ?></span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid <?= $is_edit ? 'fa-pen-to-square' : 'fa-plus' ?>"></i>
                </div>
                <span><?= $is_edit ? 'Chỉnh Sửa Hồ Sơ Đánh Giá' : 'Khởi Tạo Phiếu Đánh Giá Mới' ?></span>
            </h1>
        </div>

        <a href="<?= base_url('modules/evaluations/index.php') ?>" 
           class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Quay Lại Danh Sách</span>
        </a>
    </div>

    <!-- Alert thông báo lỗi nếu có -->
    <?php if (!empty($errors)): ?>
        <div class="p-4 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-2xl text-xs text-rose-700 dark:text-rose-300 space-y-1">
            <div class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> Vui lòng sửa các lỗi sau:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-4">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Main Card Form -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <form method="POST" class="p-6 sm:p-8 space-y-6">
            <?= csrf_field() ?>

            <!-- Hàng 1: Mã phiếu & Kỳ đánh giá -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mã Phiếu Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" value="<?= e($evaluation['evaluation_code']) ?>" readonly
                           class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 cursor-not-allowed">
                    <p class="text-[11px] text-slate-400 mt-1">Mã hồ sơ tự động đồng bộ theo chuẩn AURA HRM</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Kỳ / Đợt Đánh Giá Hiệu Suất <span class="text-rose-500">*</span>
                    </label>
                    <select name="period_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $evaluation['period_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= $p['status'] === 'active' ? 'Đang mở' : 'Đã đóng' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Hàng 2: Nhân viên & Bộ tiêu chí -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Nhân Sự Được Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <select name="employee_id" id="select_employee" required onchange="handleEmployeeChange(this)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Nhân Viên --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" 
                                    data-dept-id="<?= $emp['department_id'] ?>" 
                                    <?= $evaluation['employee_id'] == $emp['id'] ? 'selected' : '' ?>>
                                <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) - <?= e($emp['department_name'] ?: 'Chưa phân bổ') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Bộ Tiêu Chí KPI Áp Dụng <span class="text-rose-500">*</span>
                    </label>
                    <select name="template_id" id="select_template" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Bộ Tiêu Chí KPI --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>" 
                                    data-dept-id="<?= $t['department_id'] ?>" 
                                    <?= $evaluation['template_id'] == $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['name']) ?> (<?= $t['items_count'] ?> tiêu chí - <?= e($t['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Hàng 3: Quản lý đánh giá (Reviewer) & Trạng thái -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Quản Lý Chấm Điểm (Reviewer) <span class="text-rose-500">*</span>
                    </label>
                    <select name="reviewer_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($employees as $rev): ?>
                            <option value="<?= $rev['id'] ?>" <?= $evaluation['reviewer_id'] == $rev['id'] ? 'selected' : '' ?>>
                                <?= e($rev['fullname']) ?> (<?= e($rev['position_name'] ?: 'Quản lý') ?> - <?= e($rev['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Cán bộ phụ trách xem xét, nhận xét và chấm điểm quản lý</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Trạng Thái Tiến Trình
                    </label>
                    <select name="status"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="pending" <?= $evaluation['status'] === 'pending' ? 'selected' : '' ?>>Chờ thực hiện (Pending)</option>
                        <option value="self_review" <?= $evaluation['status'] === 'self_review' ? 'selected' : '' ?>>Nhân viên đang tự chấm (Self-Review)</option>
                        <option value="manager_review" <?= $evaluation['status'] === 'manager_review' ? 'selected' : '' ?>>Quản lý đang chấm (Manager-Review)</option>
                        <option value="completed" <?= $evaluation['status'] === 'completed' ? 'selected' : '' ?>>Đã hoàn tất đánh giá (Completed)</option>
                    </select>
                </div>
            </div>

            <!-- Hàng 4: Đề xuất hành động nhân sự -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Định Hướng / Đề Xuất Nhân Sự (Tùy Chọn)
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                    <?php 
                    $recs = [
                        'none' => ['Chưa có', 'fa-minus', 'text-slate-500'],
                        'bonus' => ['Khen thưởng', 'fa-award', 'text-emerald-500'],
                        'salary_increase' => ['Tăng lương', 'fa-arrow-trend-up', 'text-indigo-500'],
                        'promotion' => ['Bổ nhiệm', 'fa-crown', 'text-amber-500'],
                        'training' => ['Đào tạo lại', 'fa-chalkboard-user', 'text-sky-500'],
                        'warning' => ['Nhắc nhở', 'fa-triangle-exclamation', 'text-rose-500']
                    ];
                    foreach ($recs as $key => $rInfo): 
                    ?>
                        <label class="flex flex-col items-center justify-center p-3 rounded-2xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition <?= $evaluation['promotion_recommendation'] === $key ? 'bg-indigo-50/70 dark:bg-indigo-950/50 border-indigo-500 dark:border-indigo-500' : '' ?>">
                            <input type="radio" name="promotion_recommendation" value="<?= $key ?>" <?= $evaluation['promotion_recommendation'] === $key ? 'checked' : '' ?> class="sr-only">
                            <i class="fa-solid <?= $rInfo[1] ?> text-base <?= $rInfo[2] ?> mb-1"></i>
                            <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 text-center"><?= $rInfo[0] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-between pt-6 border-t border-slate-100 dark:border-slate-700">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" 
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition">
                    Hủy Bỏ
                </a>

                <button type="submit" 
                        class="px-7 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-200 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?= $is_edit ? 'Cập Nhật Phiếu Đánh Giá' : 'Lưu & Khởi Tạo Phiếu' ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tự động gợi ý template theo department khi đổi nhân viên
function handleEmployeeChange(select) {
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption) return;
    const deptId = selectedOption.getAttribute('data-dept-id');
    const templateSelect = document.getElementById('select_template');

    if (deptId && templateSelect && templateSelect.value === '') {
        for (let i = 0; i < templateSelect.options.length; i++) {
            const opt = templateSelect.options[i];
            if (opt.getAttribute('data-dept-id') == deptId) {
                templateSelect.selectedIndex = i;
                break;
            }
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
