<?php
// modules/recruitment/index.php - Pipeline Tuyển Dụng & Bảng Kanban Kéo - Thả 6 Giai Đoạn
$page_title = 'Pipeline Tuyển Dụng & Ứng Viên';

require_once __DIR__ . '/../../core/auth.php';
require_permission('recruitment', 'view');

// 1. Thu thập bộ lọc từ GET
$search         = trim($_GET['search'] ?? '');
$job_filter     = (int)($_GET['job_position_id'] ?? 0);
$branch_filter  = (int)($_GET['branch_id'] ?? 0);
$source_filter  = trim($_GET['source'] ?? '');
$current_view   = trim($_GET['view'] ?? 'kanban'); // Mặc định là Kanban Kéo - Thả

// 2. Thống kê tổng quan KPI Tuyển dụng
$stats = [
    'total_jobs'        => (int)$pdo->query("SELECT COUNT(*) FROM job_positions WHERE status = 'open'")->fetchColumn(),
    'total_candidates'  => (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
    'interviewing_count'=> (int)$pdo->query("SELECT COUNT(*) FROM candidates WHERE stage = 'interview'")->fetchColumn(),
    'hired_count'       => (int)$pdo->query("SELECT COUNT(*) FROM candidates WHERE stage = 'hired'")->fetchColumn(),
];

// 3. Xây dựng truy vấn danh sách ứng viên có lọc
$where_clauses = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(c.fullname LIKE :search OR c.candidate_code LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($job_filter > 0) {
    $where_clauses[] = "c.job_position_id = :job_id";
    $params[':job_id'] = $job_filter;
}

if ($branch_filter > 0) {
    $where_clauses[] = "j.branch_id = :branch_id";
    $params[':branch_id'] = $branch_filter;
}

if (!empty($source_filter)) {
    $where_clauses[] = "c.source = :source";
    $params[':source'] = $source_filter;
}

$where_sql = implode(' AND ', $where_clauses);

$query_sql = "
    SELECT c.*, 
           j.job_code, j.title AS job_title, j.salary_range_min, j.salary_range_max,
           b.name AS branch_name, d.name AS department_name,
           e.employee_code AS hired_emp_code
    FROM candidates c
    JOIN job_positions j ON c.job_position_id = j.id
    LEFT JOIN branches b ON j.branch_id = b.id
    LEFT JOIN departments d ON j.department_id = d.id
    LEFT JOIN employees e ON c.hired_employee_id = e.id
    WHERE {$where_sql}
    ORDER BY c.created_at DESC, c.id DESC
";

$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Lấy danh sách vị trí & chi nhánh cho bộ lọc
$job_positions = $pdo->query("SELECT id, title, job_code FROM job_positions ORDER BY id DESC")->fetchAll();
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();

// Cấu trúc 6 cột Kanban Pipeline
$pipeline_columns = [
    'applied' => [
        'title' => 'Tiếp Nhận Hồ Sơ',
        'icon' => 'fa-file-lines',
        'color' => 'indigo',
        'badge_bg' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300',
        'items' => []
    ],
    'screening' => [
        'title' => 'Sàng Lọc Hồ Sơ',
        'icon' => 'fa-filter',
        'color' => 'amber',
        'badge_bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        'items' => []
    ],
    'interview' => [
        'title' => 'Phỏng Vấn',
        'icon' => 'fa-comments',
        'color' => 'purple',
        'badge_bg' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
        'items' => []
    ],
    'offer' => [
        'title' => 'Gửi Offer',
        'icon' => 'fa-file-signature',
        'color' => 'sky',
        'badge_bg' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300',
        'items' => []
    ],
    'hired' => [
        'title' => 'Trúng Tuyển',
        'icon' => 'fa-trophy',
        'color' => 'emerald',
        'badge_bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        'items' => []
    ],
    'rejected' => [
        'title' => 'Không Phù Hợp',
        'icon' => 'fa-ban',
        'color' => 'rose',
        'badge_bg' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
        'items' => []
    ]
];

foreach ($candidates as $cand) {
    $st = $cand['stage'];
    if (isset($pipeline_columns[$st])) {
        $pipeline_columns[$st]['items'][] = $cand;
    } else {
        $pipeline_columns['applied']['items'][] = $cand;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header & Nút Điều Hướng -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                <i class="fa-solid fa-people-roof text-indigo-600 dark:text-indigo-400 text-2xl"></i>
                <span>Quy Trình & Pipeline Tuyển Dụng Ứng Viên</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Kéo - Thả ứng viên qua 6 giai đoạn tuyển dụng và tự động tạo hồ sơ nhân sự trúng tuyển
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Chuyển đổi View -->
            <div class="inline-flex rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-1 shadow-2xs">
                <a href="?view=kanban<?= $job_filter ? '&job_position_id=' . $job_filter : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 <?= $current_view === 'kanban' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Kanban Kéo - Thả</span>
                </a>
                <a href="?view=table<?= $job_filter ? '&job_position_id=' . $job_filter : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 <?= $current_view === 'table' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                    <i class="fa-solid fa-list-ul"></i>
                    <span>Dạng Bảng</span>
                </a>
            </div>

            <a href="jobs.php" class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 transition flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-briefcase"></i>
                <span>Vị Trí Đang Tuyển (<?= $stats['total_jobs'] ?>)</span>
            </a>

            <?php if (has_permission('recruitment', 'create')): ?>
                <a href="candidate_form.php" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Tiếp Nhận Ứng Viên</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Chỉ Số KPI Tổng Quan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Vị Trí Đang Mở</span>
                <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 block"><?= number_format($stats['total_jobs']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đang nhận hồ sơ</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-briefcase"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Tổng Ứng Viên</span>
                <span class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1 block"><?= number_format($stats['total_candidates']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đã nộp hồ sơ</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Đang Phỏng Vấn</span>
                <span class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 mt-1 block"><?= number_format($stats['interviewing_count']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đang thẩm định</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-purple-100 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-comments"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Tuyển Dụng Thành Công</span>
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block"><?= number_format($stats['hired_count']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đã trúng tuyển</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
    </div>

    <!-- Thanh Bộ Lọc -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="hidden" name="view" value="<?= e($current_view) ?>">

            <div class="lg:col-span-2 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= e($search) ?>" 
                       placeholder="Tìm theo họ tên, mã UV, email, số điện thoại..." 
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
            </div>

            <div>
                <select name="job_position_id" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition">
                    <option value="">-- Tất cả vị trí tuyển --</option>
                    <?php foreach ($job_positions as $jp): ?>
                        <option value="<?= $jp['id'] ?>" <?= $job_filter == $jp['id'] ? 'selected' : '' ?>><?= e($jp['title']) ?> (<?= e($jp['job_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="branch_id" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition">
                    <option value="">-- Tất cả chi nhánh --</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branch_filter == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition">
                    <i class="fa-solid fa-filter mr-1"></i> Lọc
                </button>
                <?php if (!empty($search) || $job_filter > 0 || $branch_filter > 0): ?>
                    <a href="?view=<?= e($current_view) ?>" class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 text-xs font-semibold transition" title="Xóa bộ lọc">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- GIAO DIỆN CHÍNH: KANBAN PIPELINE HOẶC TABLE VIEW -->

    <?php if ($current_view === 'kanban'): ?>
        <!-- ============================================== -->
        <!-- CHẾ ĐỘ 1: KANBAN PIPELINE 6 GIAI ĐOẠN KÉO THẢ -->
        <!-- ============================================== -->

        <div class="kanban-board overflow-x-auto pb-4">
            <div class="flex gap-4 min-w-[1300px] items-start">
                <?php foreach ($pipeline_columns as $col_key => $col): ?>
                    <div class="kanban-column w-72 flex-shrink-0 bg-slate-100/80 dark:bg-slate-800/60 rounded-3xl p-3.5 border-2 border-transparent transition-all duration-200 min-h-[580px] flex flex-col"
                         data-status="<?= $col_key ?>">

                        <!-- Tiêu đề Cột -->
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200 dark:border-slate-700/80 px-1">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid <?= $col['icon'] ?> text-xs text-slate-500 dark:text-slate-400"></i>
                                <h3 class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                    <?= $col['title'] ?>
                                </h3>
                            </div>
                            <span class="kanban-count-badge px-2 py-0.5 rounded-full text-[11px] font-extrabold <?= $col['badge_bg'] ?>">
                                <?= count($col['items']) ?>
                            </span>
                        </div>

                        <!-- Danh sách thẻ ứng viên -->
                        <div class="kanban-cards-container flex-1 space-y-3 min-h-[350px]">
                            <?php if (empty($col['items'])): ?>
                                <div class="empty-placeholder text-center py-10 text-slate-400 text-xs border-2 border-dashed border-slate-200 dark:border-slate-700/60 rounded-2xl">
                                    Kéo thả ứng viên vào đây
                                </div>
                            <?php endif; ?>

                            <?php foreach ($col['items'] as $cand): ?>
                                <div class="kanban-card bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover transition-all group"
                                     data-id="<?= $cand['id'] ?>"
                                     data-code="<?= e($cand['candidate_code']) ?>">

                                    <!-- Header thẻ: Mã hồ sơ & Rating sao -->
                                    <div class="flex items-center justify-between gap-1 mb-2">
                                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                            <i class="fa-solid fa-grip-vertical text-slate-300 dark:text-slate-600 text-[10px] group-hover:text-indigo-500"></i>
                                            <span><?= e($cand['candidate_code']) ?></span>
                                        </span>
                                        <span class="text-[11px] text-amber-500 font-bold">
                                            <?= str_repeat('★', (int)$cand['rating']) ?>
                                        </span>
                                    </div>

                                    <!-- Tên ứng viên -->
                                    <a href="candidate_view.php?id=<?= $cand['id'] ?>" class="text-xs font-bold text-slate-800 dark:text-white hover:text-indigo-600 block line-clamp-1 mb-1">
                                        <?= e($cand['fullname']) ?>
                                    </a>

                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 mb-2.5">
                                        <i class="fa-solid fa-briefcase text-[10px] mr-1 text-slate-400"></i>
                                        <span><?= e($cand['job_title']) ?></span>
                                    </div>

                                    <!-- Chi tiết kinh nghiệm & học vấn -->
                                    <div class="p-2.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800 text-[11px] space-y-1 mb-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400">Kinh nghiệm:</span>
                                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= (int)$cand['experience_years'] ?> năm</span>
                                        </div>
                                        <div class="truncate text-slate-500">
                                            <?= e($cand['education'] ?: 'Chưa ghi học vấn') ?>
                                        </div>
                                    </div>

                                    <!-- Nút Tuyển Dụng nếu ở cột Hired -->
                                    <?php if ($col_key === 'hired'): ?>
                                        <div class="mb-3">
                                            <?php if (!empty($cand['hired_employee_id'])): ?>
                                                <a href="<?= base_url('modules/employees/view.php?id=' . $cand['hired_employee_id']) ?>" class="w-full py-1.5 px-2 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 text-emerald-800 dark:text-emerald-300 text-[11px] font-bold rounded-xl flex items-center justify-center gap-1 hover:bg-emerald-100 transition">
                                                    <i class="fa-solid fa-id-card"></i>
                                                    <span>Đã tạo NV: <?= e($cand['hired_emp_code']) ?></span>
                                                </a>
                                            <?php else: ?>
                                                <?php if (has_permission('recruitment', 'hire')): ?>
                                                    <a href="hire.php?id=<?= $cand['id'] ?>" class="w-full py-1.5 px-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-xl flex items-center justify-center gap-1 shadow-xs transition animate-pulse">
                                                        <i class="fa-solid fa-user-plus"></i>
                                                        <span>Tuyển Dụng ➜ Tạo Mã NV</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Footer thẻ -->
                                    <div class="flex items-center justify-between text-[11px] pt-2 border-t border-slate-100 dark:border-slate-700/50">
                                        <div>
                                            <?php if (!empty($cand['cv_file'])): ?>
                                                <a href="<?= base_url('assets/uploads/cvs/' . e($cand['cv_file'])) ?>" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-semibold" title="Xem CV">
                                                    <i class="fa-solid fa-file-pdf"></i> CV
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[10px] italic">Chưa có CV</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <a href="candidate_view.php?id=<?= $cand['id'] ?>" class="p-1 text-slate-400 hover:text-indigo-600 rounded transition" title="Xem hồ sơ">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                            <?php if (has_permission('recruitment', 'edit')): ?>
                                                <a href="candidate_form.php?id=<?= $cand['id'] ?>" class="p-1 text-slate-400 hover:text-indigo-600 rounded transition" title="Chỉnh sửa">
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (has_permission('recruitment', 'delete')): ?>
                                                <form method="POST" action="delete.php" class="inline" onsubmit="return false;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $cand['id'] ?>">
                                                    <input type="hidden" name="type" value="candidate">
                                                    <button type="button" data-confirm="Bạn có chắc muốn xóa ứng viên '<?= e($cand['fullname']) ?>' không?" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Xóa">
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
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- ============================================== -->
        <!-- CHẾ ĐỘ 2: BẢNG DANH SÁCH CHI TIẾT (TABLE VIEW) -->
        <!-- ============================================== -->

        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs overflow-hidden">
            <div class="table-responsive">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/75 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-bold">
                            <th class="py-3.5 px-4">Mã UV</th>
                            <th class="py-3.5 px-4">Họ & Tên Ứng Viên</th>
                            <th class="py-3.5 px-4">Vị Trí Ứng Tuyển</th>
                            <th class="py-3.5 px-4">Kinh Nghiệm</th>
                            <th class="py-3.5 px-4 text-center">Đánh Giá</th>
                            <th class="py-3.5 px-4 text-center">Giai Đoạn</th>
                            <th class="py-3.5 px-4 text-center">Tệp CV</th>
                            <th class="py-3.5 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-slate-400">
                                    <i class="fa-solid fa-users text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                                    Không tìm thấy hồ sơ ứng viên nào phù hợp.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($candidates as $c): ?>
                            <?php $meta = $pipeline_columns[$c['stage']] ?? $pipeline_columns['applied']; ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-800 dark:text-slate-100">
                                    <?= e($c['candidate_code']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                                            <?= strtoupper(mb_substr($c['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                        <div>
                                            <a href="candidate_view.php?id=<?= $c['id'] ?>" class="font-bold text-slate-800 dark:text-white hover:text-indigo-600">
                                                <?= e($c['fullname']) ?>
                                            </a>
                                            <div class="text-[11px] text-slate-400">
                                                <?= e($c['email']) ?> • <?= e($c['phone']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200"><?= e($c['job_title']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= e($c['branch_name'] ?? 'Chi nhánh') ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-700 dark:text-slate-300"><?= (int)$c['experience_years'] ?> năm kn</div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-xs"><?= e($c['education'] ?: '---') ?></div>
                                </td>
                                <td class="py-3 px-4 text-center text-amber-500 font-bold">
                                    <?= str_repeat('★', (int)$c['rating']) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $meta['badge_bg'] ?>">
                                        <?= $meta['title'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($c['cv_file'])): ?>
                                        <a href="<?= base_url('assets/uploads/cvs/' . e($c['cv_file'])) ?>" target="_blank" class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 flex items-center justify-center mx-auto transition" title="Xem CV">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300 dark:text-slate-600">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if ($c['stage'] === 'hired' && empty($c['hired_employee_id']) && has_permission('recruitment', 'hire')): ?>
                                            <a href="hire.php?id=<?= $c['id'] ?>" class="px-2 py-1 rounded-lg bg-emerald-600 text-white text-[11px] font-bold hover:bg-emerald-700 transition" title="Chuyển thành nhân viên">
                                                <i class="fa-solid fa-user-plus mr-1"></i> Tạo NV
                                            </a>
                                        <?php endif; ?>
                                        <a href="candidate_view.php?id=<?= $c['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 transition" title="Chi tiết">
                                            <i class="fa-regular fa-eye"></i>
                                        </a>
                                        <?php if (has_permission('recruitment', 'edit')): ?>
                                            <a href="candidate_form.php?id=<?= $c['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 transition" title="Chỉnh sửa">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('recruitment', 'delete')): ?>
                                            <form method="POST" action="delete.php" class="inline" onsubmit="return false;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="type" value="candidate">
                                                <button type="button" data-confirm="Bạn có chắc muốn xóa ứng viên '<?= e($c['fullname']) ?>' không?" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-rose-600 transition" title="Xóa">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Script Kích Hoạt Kéo Thả Kanban Pipeline 6 Cột -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.kanban-board')) {
        HRMSDragDrop.initKanban({
            boardSelector: '.kanban-board',
            columnSelector: '.kanban-column',
            cardSelector: '.kanban-card',
            onStatusChange: function(params) {
                const formData = new FormData();
                formData.append('id', params.cardId);
                formData.append('stage', params.newStatus);
                formData.append('_csrf_token', '<?= csrf_token() ?>');

                fetch('<?= base_url("modules/recruitment/ajax_stage.php") ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (window.showToast) {
                            window.showToast('success', data.message);
                        }
                        // Nếu chuyển sang giai đoạn trúng tuyển (hired) và chưa có mã nhân viên, nhắc nhở tạo nhân sự
                        if (data.is_hired && !data.has_employee) {
                            setTimeout(() => {
                                if (window.showToast) {
                                    window.showToast('info', `Ứng viên [${data.candidate_name}] đã trúng tuyển! Hãy bấm vào thẻ để tạo mã nhân viên mới.`);
                                }
                            }, 1200);
                        }
                    } else {
                        if (window.showToast) {
                            window.showToast('danger', data.message || 'Cập nhật giai đoạn thất bại.');
                        }
                        params.revert();
                    }
                })
                .catch(err => {
                    console.error('Kanban pipeline error:', err);
                    if (window.showToast) {
                        window.showToast('danger', 'Lỗi kết nối máy chủ khi chuyển giai đoạn.');
                    }
                    params.revert();
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
