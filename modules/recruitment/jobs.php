<?php
// modules/recruitment/jobs.php - Quản lý Vị trí Tuyển dụng & Định biên tuyển
$page_title = 'Danh Mục Vị Trí Tuyển Dụng';

require_once __DIR__ . '/../../core/auth.php';
require_permission('recruitment', 'view');

// 1. Xử lý Thêm mới / Cập nhật Vị trí Tuyển dụng qua Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    if ($action_type === 'save_job') {
        require_permission('recruitment', 'create');

        $job_id = (int)($_POST['job_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : 1;
        $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $salary_min = (float)str_replace(['.', ',', ' '], '', $_POST['salary_range_min'] ?? '0');
        $salary_max = (float)str_replace(['.', ',', ' '], '', $_POST['salary_range_max'] ?? '0');
        $requirements = trim($_POST['requirements'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $status = $_POST['status'] ?? 'open';

        if (empty($title)) {
            set_flash('danger', 'Tiêu đề vị trí tuyển dụng không được để trống.');
        } else {
            try {
                if ($job_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE job_positions SET
                            title = :title,
                            department_id = :department_id,
                            branch_id = :branch_id,
                            position_id = :position_id,
                            quantity = :quantity,
                            salary_range_min = :salary_min,
                            salary_range_max = :salary_max,
                            requirements = :requirements,
                            description = :description,
                            deadline = :deadline,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':title'         => $title,
                        ':department_id' => $department_id,
                        ':branch_id'     => $branch_id,
                        ':position_id'   => $position_id,
                        ':quantity'      => $quantity,
                        ':salary_min'    => $salary_min,
                        ':salary_max'    => $salary_max,
                        ':requirements'  => $requirements,
                        ':description'   => $description,
                        ':deadline'      => $deadline,
                        ':status'        => $status,
                        ':id'            => $job_id
                    ]);
                    set_flash('success', "Đã cập nhật vị trí tuyển dụng '{$title}' thành công.");
                } else {
                    $year = date('Y');
                    $last_code = $pdo->query("SELECT job_code FROM job_positions WHERE job_code LIKE 'TD-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
                    $next_num = 1;
                    if ($last_code && preg_match('/TD-\d{4}-(\d+)/', $last_code, $matches)) {
                        $next_num = (int)$matches[1] + 1;
                    }
                    $job_code = sprintf('TD-%s-%03d', $year, $next_num);

                    $stmt = $pdo->prepare("
                        INSERT INTO job_positions
                            (job_code, title, department_id, branch_id, position_id, quantity, salary_range_min, salary_range_max, requirements, description, deadline, status, created_by)
                        VALUES
                            (:job_code, :title, :department_id, :branch_id, :position_id, :quantity, :salary_min, :salary_max, :requirements, :description, :deadline, :status, :created_by)
                    ");
                    $stmt->execute([
                        ':job_code'      => $job_code,
                        ':title'         => $title,
                        ':department_id' => $department_id,
                        ':branch_id'     => $branch_id,
                        ':position_id'   => $position_id,
                        ':quantity'      => $quantity,
                        ':salary_min'    => $salary_min,
                        ':salary_max'    => $salary_max,
                        ':requirements'  => $requirements,
                        ':description'   => $description,
                        ':deadline'      => $deadline,
                        ':status'        => $status,
                        ':created_by'    => current_user()['id'] ?? null
                    ]);
                    set_flash('success', "Đã tạo mới tin tuyển dụng '{$title}' ({$job_code}) thành công.");
                }
                redirect('modules/recruitment/jobs.php');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi cơ sở dữ liệu: ' . $e->getMessage());
            }
        }
    }
}

// 2. Truy vấn danh sách vị trí kèm thống kê số ứng viên ứng tuyển
$jobs = $pdo->query("
    SELECT j.*, 
           b.name AS branch_name, d.name AS department_name, p.name AS position_name,
           COUNT(c.id) AS total_candidates,
           SUM(CASE WHEN c.stage = 'hired' THEN 1 ELSE 0 END) AS hired_count
    FROM job_positions j
    LEFT JOIN branches b ON j.branch_id = b.id
    LEFT JOIN departments d ON j.department_id = d.id
    LEFT JOIN positions p ON j.position_id = p.id
    LEFT JOIN candidates c ON j.id = c.job_position_id
    GROUP BY j.id
    ORDER BY j.id DESC
")->fetchAll();

// Dữ liệu hỗ trợ dropdowns
$branches = $pdo->query("SELECT id, name, is_headquarter FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

// Thống kê tổng quan
$stats = [
    'total_open'      => (int)$pdo->query("SELECT COUNT(*) FROM job_positions WHERE status = 'open'")->fetchColumn(),
    'total_headcount' => (int)$pdo->query("SELECT SUM(quantity) FROM job_positions WHERE status = 'open'")->fetchColumn(),
    'total_applied'   => (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
    'total_hired'     => (int)$pdo->query("SELECT COUNT(*) FROM candidates WHERE stage = 'hired'")->fetchColumn(),
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header & Nút Thao Tác -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                <i class="fa-solid fa-bullhorn text-indigo-600 dark:text-indigo-400 text-2xl"></i>
                <span>Quản Lý Vị Trí Đang Tuyển Dụng</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Quản lý chỉ tiêu định biên, mô tả công việc và mức lương dự kiến cho từng vị trí
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-100 transition flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-table-columns"></i>
                <span>Xem Pipeline Ứng Viên</span>
            </a>

            <?php if (has_permission('recruitment', 'create')): ?>
                <button type="button" onclick="openJobModal()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tạo Vị Trí Mới</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số KPI Tuyển Dụng -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Vị Trí Đang Mở</span>
                <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 block"><?= number_format($stats['total_open']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đang nhận hồ sơ</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-briefcase"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Chỉ Tiêu Cần Tuyển</span>
                <span class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1 block"><?= number_format($stats['total_headcount']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Định biên nhân sự</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-sky-100 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Hồ Sơ Đã Nộp</span>
                <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 block"><?= number_format($stats['total_applied']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Ứng viên ứng tuyển</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-id-card"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Đã Tuyển Thành Công</span>
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block"><?= number_format($stats['total_hired']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đã vào làm việc</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
    </div>

    <!-- Danh Sách Vị Trí Dạng Thẻ Nâng Cao -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if (empty($jobs)): ?>
            <div class="col-span-full bg-white dark:bg-slate-800 p-12 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 text-center text-slate-400">
                <i class="fa-solid fa-bullhorn text-4xl mb-3 text-slate-300 dark:text-slate-600 block"></i>
                Chưa có tin tuyển dụng nào trong hệ thống. Nhấn "+ Tạo Vị Trí Mới" để bắt đầu đăng tuyển!
            </div>
        <?php endif; ?>

        <?php foreach ($jobs as $job): ?>
            <?php 
                $percent = $job['quantity'] > 0 ? min(100, round(($job['hired_count'] / $job['quantity']) * 100)) : 0;
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs p-6 flex flex-col justify-between card-hover space-y-4">
                
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"><?= e($job['job_code']) ?></span>
                        
                        <?php if ($job['status'] === 'open'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                Đang Tuyển
                            </span>
                        <?php elseif ($job['status'] === 'paused'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                Tạm Hoãn
                            </span>
                        <?php elseif ($job['status'] === 'filled'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                Đã Tuyển Đủ
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                                Đã Đóng
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-base font-bold text-slate-800 dark:text-white leading-tight line-clamp-1 mb-1.5">
                        <?= e($job['title']) ?>
                    </h3>

                    <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mb-3">
                        <i class="fa-solid fa-building text-[10px]"></i>
                        <span><?= e($job['branch_name'] ?? 'Toàn hệ thống') ?></span>
                        <span>•</span>
                        <span><?= e($job['department_name'] ?? 'Phòng ban') ?></span>
                    </div>

                    <!-- Mức Lương Dự Kiến -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800 mb-4">
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">Khung Thu Nhập Dự Kiến</span>
                        <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                            <?= format_money($job['salary_range_min']) ?> - <?= format_money($job['salary_range_max']) ?>
                        </div>
                    </div>

                    <!-- Tiến độ tuyển dụng -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Tiến độ tuyển:</span>
                            <span class="font-bold text-slate-800 dark:text-white">
                                <strong class="text-emerald-600"><?= $job['hired_count'] ?></strong> / <?= $job['quantity'] ?> chỉ tiêu
                                (<?= $job['total_candidates'] ?> ứng viên)
                            </span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-400 mt-3">
                        Hạn nộp: <strong><?= format_date($job['deadline']) ?></strong>
                    </div>
                </div>

                <!-- Footer thẻ: Actions -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <a href="index.php?job_position_id=<?= $job['id'] ?>" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        <span>Hồ sơ (<?= $job['total_candidates'] ?>)</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>

                    <div class="flex items-center gap-1.5">
                        <?php if (has_permission('recruitment', 'create')): ?>
                            <button type="button" onclick="editJob(<?= htmlspecialchars(json_encode($job), ENT_QUOTES) ?>)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Sửa tin">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </button>
                        <?php endif; ?>
                        <?php if (has_permission('recruitment', 'delete')): ?>
                            <form method="POST" action="delete.php" class="inline" onsubmit="return false;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                <input type="hidden" name="type" value="job">
                                <button type="button" data-confirm="Bạn có chắc chắn muốn xóa tin tuyển dụng '<?= e($job['title']) ?>' không? Các hồ sơ ứng viên thuộc tin này cũng sẽ bị xóa." class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/50 transition" title="Xóa">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- ============================================== -->
<!-- MODAL THÊM / SỬA TIN TUYỂN DỤNG -->
<!-- ============================================== -->
<div id="jobModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm hidden animate-fade-in">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 border border-slate-100 dark:border-slate-700 max-h-[90vh] overflow-y-auto animate-scale-up">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60 mb-6">
            <h3 id="jobModalTitle" class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-briefcase text-indigo-600"></i>
                <span>Tạo Vị Trí Tuyển Dụng Mới</span>
            </h3>
            <button type="button" onclick="closeJobModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_job">
            <input type="hidden" name="job_id" id="modalJobId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                    Tên Vị Trí / Chức Danh Tuyển Dụng <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="title" id="modalTitle" required placeholder="Ví dụ: Kỹ sư phần mềm Fullstack / Chuyên viên tuyển dụng" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Chi Nhánh</label>
                    <select name="branch_id" id="modalBranchId" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Phòng Ban</label>
                    <select name="department_id" id="modalDepartmentId" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Chức Danh Khung</label>
                    <select name="position_id" id="modalPositionId" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100">
                        <option value="">-- Mặc định --</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Chỉ Tiêu Tuyển</label>
                    <input type="number" name="quantity" id="modalQuantity" min="1" value="1" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-bold text-slate-800 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Lương Min (VNĐ)</label>
                    <input type="number" name="salary_range_min" id="modalSalaryMin" step="500000" min="0" value="10000000" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-mono font-bold text-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Lương Max (VNĐ)</label>
                    <input type="number" name="salary_range_max" id="modalSalaryMax" step="500000" min="0" value="20000000" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-mono font-bold text-emerald-600">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Hạn Nộp Hồ Sơ</label>
                    <input type="date" name="deadline" id="modalDeadline" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Trạng Thái Tin Tuyển</label>
                    <select name="status" id="modalStatus" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 font-bold">
                        <option value="open">Đang Mở Tuyển (Open)</option>
                        <option value="paused">Tạm Hoãn (Paused)</option>
                        <option value="filled">Đã Tuyển Đủ (Filled)</option>
                        <option value="closed">Đã Đóng (Closed)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Yêu Cầu Năng Lực</label>
                <textarea name="requirements" id="modalRequirements" rows="2" placeholder="Yêu cầu số năm kinh nghiệm, bằng cấp, kỹ năng chuyên môn..." class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Mô Tả Nhiệm Vụ</label>
                <textarea name="description" id="modalDescription" rows="2" placeholder="Mô tả công việc chi tiết hàng ngày..." class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100"></textarea>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeJobModal()" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition shadow-sm">
                    Lưu Vị Trí
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openJobModal() {
    document.getElementById('jobModalTitle').innerHTML = '<i class="fa-solid fa-briefcase text-indigo-600 mr-2"></i>Tạo Vị Trí Tuyển Dụng Mới';
    document.getElementById('modalJobId').value = 0;
    document.getElementById('modalTitle').value = '';
    document.getElementById('modalQuantity').value = 1;
    document.getElementById('modalSalaryMin').value = 10000000;
    document.getElementById('modalSalaryMax').value = 20000000;
    document.getElementById('modalRequirements').value = '';
    document.getElementById('modalDescription').value = '';
    document.getElementById('modalDeadline').value = '';
    document.getElementById('modalStatus').value = 'open';
    document.getElementById('jobModal').classList.remove('hidden');
}

function editJob(job) {
    document.getElementById('jobModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-600 mr-2"></i>Chỉnh Sửa Tin Tuyển: ' + job.job_code;
    document.getElementById('modalJobId').value = job.id;
    document.getElementById('modalTitle').value = job.title || '';
    document.getElementById('modalBranchId').value = job.branch_id || 1;
    document.getElementById('modalDepartmentId').value = job.department_id || '';
    document.getElementById('modalPositionId').value = job.position_id || '';
    document.getElementById('modalQuantity').value = job.quantity || 1;
    document.getElementById('modalSalaryMin').value = job.salary_range_min || 0;
    document.getElementById('modalSalaryMax').value = job.salary_range_max || 0;
    document.getElementById('modalRequirements').value = job.requirements || '';
    document.getElementById('modalDescription').value = job.description || '';
    document.getElementById('modalDeadline').value = job.deadline || '';
    document.getElementById('modalStatus').value = job.status || 'open';
    document.getElementById('jobModal').classList.remove('hidden');
}

function closeJobModal() {
    document.getElementById('jobModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
