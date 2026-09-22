<?php
// modules/disciplines/index.php - Quản lý Kỷ Luật & Bảng Kanban Kéo - Thả
$page_title = 'Quản Lý Kỷ Luật & Xử Lý Vi Phạm';

require_once __DIR__ . '/../../core/auth.php';
require_permission('disciplines', 'view');

// 1. Thu thập bộ lọc từ GET
$search      = trim($_GET['search'] ?? '');
$filter_type = trim($_GET['type'] ?? '');
$filter_stat = trim($_GET['status'] ?? '');
$filter_dept = (int)($_GET['department_id'] ?? 0);
$current_view = trim($_GET['view'] ?? 'kanban');

// 2. Thống kê tổng quan
$stats = [
    'total_count'    => (int)$pdo->query("SELECT COUNT(*) FROM disciplines")->fetchColumn(),
    'total_penalty'  => (float)$pdo->query("SELECT SUM(penalty_amount) FROM disciplines WHERE status IN ('approved', 'executed')")->fetchColumn(),
    'pending_count'  => (int)$pdo->query("SELECT COUNT(*) FROM disciplines WHERE status = 'pending'")->fetchColumn(),
    'executed_count' => (int)$pdo->query("SELECT COUNT(*) FROM disciplines WHERE status = 'executed'")->fetchColumn(),
];

// 3. Xây dựng truy vấn lọc
$where_clauses = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(d.discipline_code LIKE :search OR d.title LIKE :search OR e.fullname LIKE :search OR e.employee_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($filter_type)) {
    $where_clauses[] = "d.discipline_type = :type";
    $params[':type'] = $filter_type;
}

if (!empty($filter_stat)) {
    $where_clauses[] = "d.status = :status";
    $params[':status'] = $filter_stat;
}

if ($filter_dept > 0) {
    $where_clauses[] = "e.department_id = :dept_id";
    $params[':dept_id'] = $filter_dept;
}

$where_sql = implode(' AND ', $where_clauses);

$query_sql = "
    SELECT d.*, 
           e.fullname AS employee_name, e.employee_code, e.avatar AS employee_avatar,
           b.name AS branch_name, dept.name AS department_name, p.name AS position_name,
           u_cre.fullname AS creator_name,
           u_app.fullname AS approver_name
    FROM disciplines d
    JOIN employees e ON d.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments dept ON e.department_id = dept.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN users u_cre ON d.created_by = u_cre.id
    LEFT JOIN users u_app ON d.approved_by = u_app.id
    WHERE {$where_sql}
    ORDER BY d.discipline_date DESC, d.id DESC
";

$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$disciplines = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Phân loại cột Kanban
$kanban_columns = [
    'pending' => [
        'title' => 'Chờ Xử Lý & Thẩm Tra',
        'color' => 'amber',
        'badge_bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        'items' => []
    ],
    'approved' => [
        'title' => 'Đã Phê Duyệt Quyết Định',
        'color' => 'sky',
        'badge_bg' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300',
        'items' => []
    ],
    'executed' => [
        'title' => 'Đã Thi Hành Kỷ Luật',
        'color' => 'emerald',
        'badge_bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        'items' => []
    ]
];

foreach ($disciplines as $d_item) {
    $st = $d_item['status'];
    if (isset($kanban_columns[$st])) {
        $kanban_columns[$st]['items'][] = $d_item;
    } else {
        $kanban_columns['pending']['items'][] = $d_item;
    }
}

// Cấu hình nhãn và icon các hình thức kỷ luật
$type_meta = [
    'warning' => [
        'label' => 'Nhắc Nhở / Phê Bình',
        'icon'  => 'fa-triangle-exclamation',
        'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
    ],
    'reprimand' => [
        'label' => 'Khiển Trách',
        'icon'  => 'fa-clipboard-question',
        'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300'
    ],
    'salary_cut' => [
        'label' => 'Khấu Trừ / Hạ Lương',
        'icon'  => 'fa-circle-dollar-to-slot',
        'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
    ],
    'demotion' => [
        'label' => 'Giáng Chức',
        'icon'  => 'fa-arrow-down-wide-short',
        'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300'
    ],
    'termination' => [
        'label' => 'Sa Thải / Buộc Thôi Việc',
        'icon'  => 'fa-ban',
        'class' => 'bg-red-200 text-red-900 dark:bg-red-950 dark:text-red-300'
    ]
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- 1. Thanh Tiêu Đề & Chỉ Số KPI -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                <i class="fa-solid fa-scale-unbalanced text-rose-600 dark:text-rose-400 text-2xl"></i>
                <span>Xử Lý Kỷ Luật & Vi Phạm Nội Bộ</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Lập biên bản, xét duyệt mức độ kỷ luật và khấu trừ tiền phạt công minh, chuẩn mực
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <div class="inline-flex rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-1 shadow-2xs">
                <a href="?view=kanban<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_type) ? '&type=' . urlencode($filter_type) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 <?= $current_view === 'kanban' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>"
                   title="Chế độ Bảng Kéo - Thả trực quan">
                    <i class="fa-solid fa-table-columns"></i>
                    <span>Kéo - Thả (Kanban)</span>
                </a>
                <a href="?view=table<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_type) ? '&type=' . urlencode($filter_type) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 <?= $current_view === 'table' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>"
                   title="Chế độ Bảng danh sách chi tiết">
                    <i class="fa-solid fa-list-ul"></i>
                    <span>Dạng Bảng</span>
                </a>
            </div>

            <?php if (has_permission('disciplines', 'create')): ?>
                <a href="form.php" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-rose-200 dark:shadow-none">
                    <i class="fa-solid fa-plus"></i>
                    <span>Lập Biên Bản Mới</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số KPI -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Tổng Vụ Vi Phạm</span>
                <span class="text-2xl font-extrabold text-slate-800 dark:text-white mt-1 block"><?= number_format($stats['total_count']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Biên bản ghi nhận</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-scale-unbalanced"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Tổng Khấu Trừ Phạt</span>
                <span class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 font-mono mt-1 block"><?= format_money($stats['total_penalty']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đã duyệt & thi hành</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Chờ Xét Xử / Duyệt</span>
                <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 block"><?= number_format($stats['pending_count']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Chưa có kết luận</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-hourglass-start"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Đã Thi Hành</span>
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block"><?= number_format($stats['executed_count']) ?></span>
                <span class="text-[11px] text-slate-400 mt-0.5 block">Đã trừ lương / hoàn tất</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
        </div>
    </div>

    <!-- 2. Bộ Lọc Tìm Kiếm -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="hidden" name="view" value="<?= e($current_view) ?>">

            <div class="lg:col-span-2 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= e($search) ?>" 
                       placeholder="Tìm theo mã BB, nội dung vi phạm hoặc cán bộ..." 
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition">
            </div>

            <div>
                <select name="type" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 transition">
                    <option value="">-- Tất cả mức độ --</option>
                    <option value="warning" <?= $filter_type === 'warning' ? 'selected' : '' ?>>Nhắc Nhở / Phê Bình</option>
                    <option value="reprimand" <?= $filter_type === 'reprimand' ? 'selected' : '' ?>>Khiển Trách</option>
                    <option value="salary_cut" <?= $filter_type === 'salary_cut' ? 'selected' : '' ?>>Khấu Trừ / Hạ Lương</option>
                    <option value="demotion" <?= $filter_type === 'demotion' ? 'selected' : '' ?>>Giáng Chức</option>
                    <option value="termination" <?= $filter_type === 'termination' ? 'selected' : '' ?>>Sa Thải / Thôi Việc</option>
                </select>
            </div>

            <div>
                <select name="department_id" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 transition">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter_dept == $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition">
                    <i class="fa-solid fa-filter mr-1"></i> Áp Dụng
                </button>
                <?php if (!empty($search) || !empty($filter_type) || !empty($filter_stat) || $filter_dept > 0): ?>
                    <a href="?view=<?= e($current_view) ?>" class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 text-xs font-semibold transition" title="Xóa bộ lọc">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- 3. GIAO DIỆN CHÍNH: KANBAN BOARD HOẶC BẢNG DANH SÁCH -->

    <?php if ($current_view === 'kanban'): ?>
        <!-- ============================================== -->
        <!-- CHẾ ĐỘ 1: KANBAN BOARD KÉO - THẢ TRỰC QUAN -->
        <!-- ============================================== -->
        
        <div class="kanban-board grid grid-cols-1 md:grid-cols-3 gap-5 items-start">
            <?php foreach ($kanban_columns as $col_key => $col): ?>
                <div class="kanban-column bg-slate-100/80 dark:bg-slate-800/60 rounded-3xl p-4 border-2 border-transparent transition-all duration-200 min-h-[550px] flex flex-col" 
                     data-status="<?= $col_key ?>">
                    
                    <div class="flex items-center justify-between pb-3.5 mb-3 border-b border-slate-200 dark:border-slate-700/80 px-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full <?= $col_key === 'pending' ? 'bg-amber-500 animate-pulse' : ($col_key === 'approved' ? 'bg-sky-500' : 'bg-emerald-500') ?>"></span>
                            <h3 class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                <?= $col['title'] ?>
                            </h3>
                        </div>
                        <span class="kanban-count-badge px-2 py-0.5 rounded-full text-[11px] font-extrabold <?= $col['badge_bg'] ?>">
                            <?= count($col['items']) ?>
                        </span>
                    </div>

                    <div class="kanban-cards-container flex-1 space-y-3.5 min-h-[300px]">
                        <?php if (empty($col['items'])): ?>
                            <div class="empty-placeholder text-center py-12 text-slate-400 text-xs border-2 border-dashed border-slate-200 dark:border-slate-700/60 rounded-2xl">
                                <i class="fa-solid fa-inbox text-2xl text-slate-300 dark:text-slate-600 mb-1.5 block"></i>
                                Kéo thẻ kỷ luật thả vào đây
                            </div>
                        <?php endif; ?>

                        <?php foreach ($col['items'] as $item): ?>
                            <?php 
                                $meta = $type_meta[$item['discipline_type']] ?? $type_meta['warning'];
                            ?>
                            <div class="kanban-card bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs card-hover transition-all group"
                                 data-id="<?= $item['id'] ?>"
                                 data-code="<?= e($item['discipline_code']) ?>">

                                <div class="flex items-center justify-between gap-2 mb-2.5">
                                    <span class="font-mono text-xs font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                                        <i class="fa-solid fa-grip-vertical text-slate-300 dark:text-slate-600 text-[10px] group-hover:text-rose-500"></i>
                                        <span><?= e($item['discipline_code']) ?></span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $meta['class'] ?> flex items-center gap-1">
                                        <i class="fa-solid <?= $meta['icon'] ?> text-[9px]"></i>
                                        <span><?= $meta['label'] ?></span>
                                    </span>
                                </div>

                                <h4 class="text-xs font-bold text-slate-800 dark:text-white line-clamp-2 leading-relaxed mb-3">
                                    <?= e($item['title']) ?>
                                </h4>

                                <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 mb-3">
                                    <?= render_avatar($item['employee_name'], $item['employee_avatar'], 8) ?>
                                    <div class="truncate">
                                        <a href="<?= base_url('modules/employees/view.php?id=' . $item['employee_id']) ?>" class="text-xs font-bold text-slate-800 dark:text-white hover:text-rose-600 truncate block">
                                            <?= e($item['employee_name']) ?>
                                        </a>
                                        <p class="text-[10px] text-slate-400 truncate">
                                            <?= e($item['branch_name'] ?? '---') ?> • <?= e($item['department_name'] ?? '---') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100 dark:border-slate-700/60">
                                    <div>
                                        <span class="text-[10px] text-slate-400 block">Khấu trừ phạt:</span>
                                        <span class="font-mono font-bold text-rose-600 dark:text-rose-400 text-xs">
                                            <?= $item['penalty_amount'] > 0 ? format_money($item['penalty_amount']) : 'Không phạt tiền' ?>
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] text-slate-400 block">Ngày lập:</span>
                                        <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300">
                                            <?= format_date($item['discipline_date']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mt-3 pt-2 text-[11px] border-t border-slate-100 dark:border-slate-700/40">
                                    <div>
                                        <?php if (!empty($item['attachment'])): ?>
                                            <a href="<?= base_url('assets/uploads/attachments/' . e($item['attachment'])) ?>" target="_blank" class="text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1 font-semibold" title="Xem biên bản scan">
                                                <i class="fa-solid fa-paperclip"></i>
                                                <span>File scan</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[10px] italic">Không đính kèm</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <?php if (has_permission('disciplines', 'create')): ?>
                                            <a href="form.php?id=<?= $item['id'] ?>" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Chỉnh sửa">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('disciplines', 'delete')): ?>
                                            <form method="POST" action="delete.php" class="inline" onsubmit="return false;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="button" data-confirm="Bạn có chắc chắn muốn xóa biên bản kỷ luật '<?= e($item['discipline_code']) ?>' không?" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Xóa">
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

    <?php else: ?>
        <!-- ============================================== -->
        <!-- CHẾ ĐỘ 2: BẢNG DANH SÁCH CHI TIẾT (TABLE VIEW) -->
        <!-- ============================================== -->

        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs overflow-hidden">
            <div class="table-responsive">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/75 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-bold">
                            <th class="py-3.5 px-4">Mã BB</th>
                            <th class="py-3.5 px-4">Cán Bộ Bị Xử Lý</th>
                            <th class="py-3.5 px-4">Hình Thức & Hành Vi Vi Phạm</th>
                            <th class="py-3.5 px-4 text-right">Khấu Trừ Phạt</th>
                            <th class="py-3.5 px-4">Số Biên Bản / Ngày Lập</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-center">Đính Kèm</th>
                            <th class="py-3.5 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php if (empty($disciplines)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-slate-400">
                                    <i class="fa-solid fa-scale-unbalanced text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                                    Không tìm thấy biên bản kỷ luật nào phù hợp.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($disciplines as $d): ?>
                            <?php $meta = $type_meta[$d['discipline_type']] ?? $type_meta['warning']; ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-800 dark:text-slate-100">
                                    <?= e($d['discipline_code']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <?= render_avatar($d['employee_name'], $d['employee_avatar'], 8) ?>
                                        <div>
                                            <a href="<?= base_url('modules/employees/view.php?id=' . $d['employee_id']) ?>" class="font-bold text-slate-800 dark:text-white hover:text-rose-600">
                                                <?= e($d['employee_name']) ?>
                                            </a>
                                            <div class="text-[11px] text-slate-400">
                                                <?= e($d['employee_code']) ?> • <?= e($d['branch_name'] ?? 'Chi nhánh') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold <?= $meta['class'] ?> mb-1">
                                        <i class="fa-solid <?= $meta['icon'] ?> text-[9px]"></i>
                                        <span><?= $meta['label'] ?></span>
                                    </span>
                                    <div class="font-semibold text-slate-800 dark:text-slate-200 line-clamp-1">
                                        <?= e($d['title']) ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-rose-600 dark:text-rose-400">
                                    <?= $d['penalty_amount'] > 0 ? format_money($d['penalty_amount']) : '---' ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-700 dark:text-slate-300"><?= e($d['decision_number'] ?: '---') ?></div>
                                    <div class="text-[11px] text-slate-400"><?= format_date($d['discipline_date']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($d['status'] === 'executed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Đã Thi Hành
                                        </span>
                                    <?php elseif ($d['status'] === 'approved'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                            Đã Phê Duyệt
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Chờ Xử Lý
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($d['attachment'])): ?>
                                        <a href="<?= base_url('assets/uploads/attachments/' . e($d['attachment'])) ?>" target="_blank" class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 flex items-center justify-center mx-auto transition" title="Xem biên bản scan">
                                            <i class="fa-solid fa-file-lines"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300 dark:text-slate-600">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if (has_permission('disciplines', 'create')): ?>
                                            <a href="form.php?id=<?= $d['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-rose-600 transition" title="Chỉnh sửa">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('disciplines', 'delete')): ?>
                                            <form method="POST" action="delete.php" class="inline" onsubmit="return false;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="button" data-confirm="Bạn có chắc chắn muốn xóa biên bản kỷ luật '<?= e($d['discipline_code']) ?>' không?" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-rose-600 transition" title="Xóa">
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

<!-- Script Kích Hoạt Kéo - Thả Kanban Cho Phân Hệ Kỷ Luật -->
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
                formData.append('status', params.newStatus);
                formData.append('_csrf_token', '<?= csrf_token() ?>');

                fetch('<?= base_url("modules/disciplines/ajax_status.php") ?>', {
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
                    } else {
                        if (window.showToast) {
                            window.showToast('danger', data.message || 'Cập nhật thất bại.');
                        }
                        params.revert();
                    }
                })
                .catch(err => {
                    console.error('Kanban discipline update error:', err);
                    if (window.showToast) {
                        window.showToast('danger', 'Lỗi kết nối máy chủ khi chuyển trạng thái.');
                    }
                    params.revert();
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
