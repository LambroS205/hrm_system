<?php
// modules/evaluations/view.php - Chi Tiết Đánh Giá KPI & Chấm Điểm Định Lượng Phân Tầng (BSC & SMART Link Power)
require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash('danger', 'ID phiếu đánh giá không hợp lệ.');
    redirect('modules/evaluations/index.php');
}

// Cấu hình Cấp bậc Quản trị Nhân sự
$hierarchy_levels = [
    'executive'   => ['label' => 'Lãnh Đạo Cấp Cao (C-Level)', 'badge' => 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800', 'icon' => 'fa-crown'],
    'manager'     => ['label' => 'Quản Lý Cấp Trung (Manager)', 'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800', 'icon' => 'fa-user-tie'],
    'specialist'  => ['label' => 'Chuyên Viên / Senior', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800', 'icon' => 'fa-laptop-code'],
    'operational' => ['label' => 'Nhân Viên Tuyến Đầu (Frontline)', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800', 'icon' => 'fa-person-walking-luggage']
];

// Cấu hình Trụ cột BSC
$bsc_pillars = [
    'financial'        => ['label' => 'Tài Chính & Doanh Thu', 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/50', 'icon' => 'fa-coins'],
    'customer'         => ['label' => 'Khách Hàng & Thị Trường', 'color' => 'text-sky-600 dark:text-sky-400', 'bg' => 'bg-sky-50 dark:bg-sky-950/50', 'icon' => 'fa-users'],
    'internal_process' => ['label' => 'Quy Trình & Vận Hành', 'color' => 'text-indigo-600 dark:text-indigo-400', 'bg' => 'bg-indigo-50 dark:bg-indigo-950/50', 'icon' => 'fa-gears'],
    'learning_growth'  => ['label' => 'Đào Tạo & Phát Triển', 'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-950/50', 'icon' => 'fa-graduation-cap']
];

// Cấu hình Nhóm Mức độ Ưu tiên (Link Power)
$priority_groups = [
    'A' => ['label' => 'Nhóm A: Cốt Lõi (~50%)', 'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border-rose-200 dark:border-rose-800', 'bar' => 'bg-rose-500'],
    'B' => ['label' => 'Nhóm B: Vận Hành (~30%)', 'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800', 'bar' => 'bg-indigo-500'],
    'C' => ['label' => 'Nhóm C: Cải Tiến (~20%)', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 border-amber-200 dark:border-amber-800', 'bar' => 'bg-amber-500']
];

// Lấy đầy đủ thông tin phiếu đánh giá
$stmt = $pdo->prepare("
    SELECT e.*,
           ep.code AS period_code, ep.name AS period_name, ep.year AS period_year, ep.quarter AS period_quarter,
           ep.start_date AS period_start, ep.end_date AS period_end,
           ep.self_review_deadline, ep.manager_review_deadline, ep.status AS period_status,
           emp.employee_code, emp.fullname AS employee_name, emp.avatar AS employee_avatar,
           emp.user_id AS employee_user_id,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name, p.base_salary,
           rev.employee_code AS reviewer_code, rev.fullname AS reviewer_name, rev.avatar AS reviewer_avatar,
           rev_pos.name AS reviewer_position, rev.user_id AS reviewer_user_id,
           t.code AS template_code, t.name AS template_name, t.description AS template_desc,
           t.hierarchy_level AS template_level
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    LEFT JOIN branches b ON emp.branch_id = b.id
    LEFT JOIN departments d ON emp.department_id = d.id
    LEFT JOIN positions p ON emp.position_id = p.id
    LEFT JOIN employees rev ON e.reviewer_id = rev.id
    LEFT JOIN positions rev_pos ON rev.position_id = rev_pos.id
    LEFT JOIN kpi_templates t ON e.template_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eval = $stmt->fetch();

if (!$eval) {
    set_flash('danger', 'Không tìm thấy hồ sơ đánh giá này.');
    redirect('modules/evaluations/index.php');
}

$page_title = 'Phiếu Đánh Giá KPI: ' . ($eval['evaluation_code'] ?? 'N/A');

// Lấy danh sách các tiêu chí thành phần (ưu tiên custom_items_json đã thiết lập theo vị trí & mục tiêu đề ra)
if (!empty($eval['custom_items_json'])) {
    $parsed_custom = json_decode($eval['custom_items_json'], true);
    $template_items = is_array($parsed_custom) && !empty($parsed_custom) ? $parsed_custom : [];
} else {
    $items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
    $items_stmt->execute([$eval['template_id']]);
    $template_items = $items_stmt->fetchAll();
}

// Giải mã JSON điểm đã lưu nếu có
$self_scores_saved = !empty($eval['self_scores_json']) ? json_decode($eval['self_scores_json'], true) : [];
$mgr_scores_saved = !empty($eval['manager_scores_json']) ? json_decode($eval['manager_scores_json'], true) : [];

$self_map = [];
if (is_array($self_scores_saved)) {
    foreach ($self_scores_saved as $idx => $s) {
        if (!is_array($s)) continue;
        if (isset($s['item_id'])) {
            $self_map[$s['item_id']] = $s;
        } elseif (isset($template_items[$idx]['id'])) {
            $s['item_id'] = $template_items[$idx]['id'];
            $self_map[$s['item_id']] = $s;
        }
    }
}

$mgr_map = [];
if (is_array($mgr_scores_saved)) {
    foreach ($mgr_scores_saved as $idx => $m) {
        if (!is_array($m)) continue;
        if (isset($m['item_id'])) {
            $mgr_map[$m['item_id']] = $m;
        } elseif (isset($template_items[$idx]['id'])) {
            $m['item_id'] = $template_items[$idx]['id'];
            $mgr_map[$m['item_id']] = $m;
        }
    }
}

$user = current_user();

// Kiểm tra quyền chấm điểm
$can_edit_self = is_superadmin() || 
                 ($user['id'] == $eval['employee_user_id'] || $user['id'] == $eval['employee_id']) ||
                 has_permission('evaluations', 'edit');

$can_edit_mgr = is_superadmin() || 
                ($user['id'] == $eval['reviewer_user_id'] || $user['id'] == $eval['reviewer_id']) ||
                has_permission('evaluations', 'approve') ||
                has_permission('evaluations', 'manager_review');

// Cấu hình xếp loại & Total Rewards
$rating_configs = [
    'outstanding'   => ['label' => 'Xuất Sắc (A+)', 'multiplier' => 1.50, 'bg' => 'from-amber-500 to-yellow-500', 'text' => 'text-amber-500', 'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300', 'icon' => 'fa-crown', 'reward_desc' => 'Thưởng 150% quỹ KPI & Đề xuất thăng chức / tăng lương trước hạn'],
    'excellent'     => ['label' => 'Tốt (A)', 'multiplier' => 1.20, 'bg' => 'from-emerald-500 to-teal-500', 'text' => 'text-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300', 'icon' => 'fa-star', 'reward_desc' => 'Thưởng 120% quỹ KPI & Xét nâng bậc lương định kỳ'],
    'good'          => ['label' => 'Khá (B)', 'multiplier' => 1.00, 'bg' => 'from-indigo-500 to-blue-500', 'text' => 'text-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300', 'icon' => 'fa-circle-check', 'reward_desc' => 'Thưởng 100% quỹ KPI tiêu chuẩn (Đạt định mức)'],
    'average'       => ['label' => 'Trung Bình (C)', 'multiplier' => 0.60, 'bg' => 'from-slate-500 to-zinc-500', 'text' => 'text-slate-500', 'badge' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200', 'icon' => 'fa-circle-dot', 'reward_desc' => 'Thưởng 60% quỹ KPI & Lập kế hoạch kèm cặp cải thiện PIP 60 ngày'],
    'below_average' => ['label' => 'Yếu (D)', 'multiplier' => 0.00, 'bg' => 'from-orange-500 to-amber-600', 'text' => 'text-orange-500', 'badge' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300', 'icon' => 'fa-triangle-exclamation', 'reward_desc' => '0% thưởng hiệu suất, cảnh cáo và tái đào tạo chuyên môn'],
    'poor'          => ['label' => 'Kém (E)', 'multiplier' => 0.00, 'bg' => 'from-rose-500 to-red-600', 'text' => 'text-rose-500', 'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300', 'icon' => 'fa-circle-xmark', 'reward_desc' => '0% thưởng hiệu suất, xem xét chuyển đổi vị trí hoặc chấm dứt HĐ']
];

$rec_labels = [
    'none' => 'Không đề xuất',
    'bonus' => 'Khen thưởng hiệu suất',
    'salary_increase' => 'Đề xuất tăng bậc lương',
    'promotion' => 'Quy hoạch bổ nhiệm',
    'training' => 'Đào tạo & Hướng dẫn lại',
    'warning' => 'Nhắc nhở & Giám sát hiệu suất'
];

$level_info = $hierarchy_levels[$eval['template_level'] ?? 'specialist'] ?? $hierarchy_levels['specialist'];
$current_rating_cfg = $rating_configs[$eval['rating'] ?? ''] ?? null;
$bonus_mult = (float)($eval['bonus_multiplier'] ?? ($current_rating_cfg['multiplier'] ?? 1.00));

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Nạp Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">

    <!-- Header Navigation & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" class="hover:underline">Đánh Giá & KPI</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400">Phiếu <?= e($eval['evaluation_code']) ?></span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <span>Hồ Sơ Đánh Giá Hiệu Suất & KPI SMART</span>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="window.print()" 
                    class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-print"></i>
                <span>In Phiếu</span>
            </button>

            <?php if (in_array($eval['rating'], ['outstanding', 'excellent']) && has_permission('rewards', 'create')): ?>
                <a href="<?= base_url('modules/rewards/form.php?employee_id=' . $eval['employee_id'] . '&evaluation_id=' . $eval['id']) ?>" 
                   class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold rounded-sm text-xs transition flex items-center gap-2">
                    <i class="fa-solid fa-award"></i>
                    <span>Lập Khen Thưởng</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('evaluations', 'edit')): ?>
                <a href="<?= base_url('modules/evaluations/form.php?id=' . $eval['id']) ?>" 
                   class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Sửa Thông Tin</span>
                </a>
            <?php endif; ?>

            <a href="<?= base_url('modules/evaluations/index.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Danh Sách</span>
            </a>
        </div>
    </div>

    <!-- Banner Profile & Tổng Quan Điểm Số (Score Dashboard Card) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 sm:p-8 space-y-6">
        
        <!-- Hàng trên: Thông tin nhân sự, Cấp bậc & Trạng thái -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0">
                    <?= render_avatar($eval['employee_name'], $eval['employee_avatar'], 16) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-bold text-xs border border-indigo-200 dark:border-indigo-800">
                            <?= e($eval['evaluation_code']) ?>
                        </span>

                        <!-- Badge Cấp Bậc -->
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1.5 <?= $level_info['badge'] ?>">
                            <i class="fa-solid <?= $level_info['icon'] ?>"></i>
                            <span><?= e($level_info['label']) ?></span>
                        </span>

                        <!-- Status badge -->
                        <?php if ($eval['status'] === 'completed'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-[10px]"></i> Đã Hoàn Tất
                            </span>
                        <?php elseif ($eval['status'] === 'manager_review'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tie text-[10px]"></i> Chờ Quản Lý Chấm
                            </span>
                        <?php elseif ($eval['status'] === 'self_review'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-pen text-[10px]"></i> Chờ Nhân Viên Tự Chấm
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-clock text-[10px]"></i> Chờ Khởi Tạo
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        <?= e($eval['employee_name']) ?>
                    </h2>

                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1 flex-wrap">
                        <span><i class="fa-solid fa-id-badge mr-1 text-slate-400"></i><?= e($eval['employee_code']) ?></span>
                        <span>•</span>
                        <span><i class="fa-solid fa-briefcase mr-1 text-slate-400"></i><?= e($eval['position_name'] ?: '---') ?></span>
                        <span>•</span>
                        <span><i class="fa-solid fa-building mr-1 text-slate-400"></i><?= e($eval['department_name'] ?: '---') ?></span>
                        <span>•</span>
                        <span><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i><?= e($eval['branch_name'] ?: '---') ?></span>
                    </div>
                </div>
            </div>

            <!-- Reviewer & Period info -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 text-xs">
                <div>
                    <span class="text-slate-400 block text-[11px]">Kỳ Đánh Giá:</span>
                    <strong class="text-slate-800 dark:text-slate-200"><?= e($eval['period_name']) ?></strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">
                        Mẫu KPI: <span class="font-semibold text-indigo-600 dark:text-indigo-400"><?= e($eval['template_name']) ?></span>
                    </div>
                </div>
                <div class="sm:border-l sm:border-slate-200 sm:dark:border-slate-700 sm:pl-4">
                    <span class="text-slate-400 block text-[11px]">Quản Lý Chấm (Reviewer):</span>
                    <strong class="text-slate-800 dark:text-slate-200"><?= e($eval['reviewer_name']) ?></strong>
                    <div class="text-[11px] text-slate-500 mt-0.5">
                        <?= e($eval['reviewer_position'] ?: 'Quản lý trực tiếp') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hàng dưới: 4 Khối Chỉ Số Điểm & Total Rewards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Điểm tự đánh giá -->
            <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-indigo-900 dark:text-indigo-300">Điểm Tự Đánh Giá</span>
                    <div class="text-2xl font-black font-mono text-indigo-600 dark:text-indigo-400 mt-1">
                        <?= $eval['self_score'] !== null ? round($eval['self_score'], 1) : '---' ?>
                        <span class="text-xs font-normal text-indigo-400">/100</span>
                    </div>
                    <div class="text-[11px] text-indigo-700/80 dark:text-indigo-400 mt-1 font-medium">
                        <?= $eval['self_submitted_at'] ? 'Đã nộp: ' . format_datetime($eval['self_submitted_at']) : 'Chưa hoàn tất' ?>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-lg">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
            </div>

            <!-- Điểm quản lý chấm -->
            <div class="p-4 rounded-2xl bg-violet-50/60 dark:bg-violet-950/40 border border-violet-100 dark:border-violet-900 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-violet-900 dark:text-violet-300">Điểm Quản Lý Chấm</span>
                    <div class="text-2xl font-black font-mono text-violet-600 dark:text-violet-400 mt-1">
                        <?= $eval['manager_score'] !== null ? round($eval['manager_score'], 1) : '---' ?>
                        <span class="text-xs font-normal text-violet-400">/100</span>
                    </div>
                    <div class="text-[11px] text-violet-700/80 dark:text-violet-400 mt-1 font-medium">
                        <?= $eval['manager_submitted_at'] ? 'Đã nộp: ' . format_datetime($eval['manager_submitted_at']) : 'Chưa chấm xong' ?>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-violet-600 text-white flex items-center justify-center text-lg">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>

            <!-- Điểm tổng kết (Final Score) -->
            <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-emerald-900 dark:text-emerald-300">Điểm Tổng Kết (KPI)</span>
                    <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1" id="displayFinalScore">
                        <?= $eval['final_score'] !== null ? round($eval['final_score'], 2) : '---' ?>
                        <span class="text-xs font-normal text-emerald-400">/100</span>
                    </div>
                    <div class="text-[11px] text-emerald-700/80 dark:text-emerald-400 mt-1 font-medium">
                        Công thức: 30% NV + 70% QL
                    </div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-lg">
                    <i class="fa-solid fa-trophy"></i>
                </div>
            </div>

            <!-- Xếp loại & Total Rewards Multiplier -->
            <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-900 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-amber-900 dark:text-amber-300">Xếp Loại & Thưởng</span>
                        <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-amber-200 text-amber-800">K = x<?= number_format($bonus_mult, 2) ?></span>
                    </div>
                    <div class="text-lg font-black mt-1 <?= $current_rating_cfg ? $current_rating_cfg['text'] : 'text-slate-400' ?>" id="displayRatingLabel">
                        <?= $current_rating_cfg ? $current_rating_cfg['label'] : 'Chưa xếp loại' ?>
                    </div>
                    <div class="text-[11px] text-amber-800/80 dark:text-amber-400 mt-1 font-medium">
                        <?= $rec_labels[$eval['promotion_recommendation']] ?? 'Chưa có đề xuất' ?>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-500 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid <?= $current_rating_cfg ? $current_rating_cfg['icon'] : 'fa-certificate' ?>"></i>
                </div>
            </div>

        </div>

    </div>

    <!-- Mục Tiêu Trọng Tâm Đề Ra & Cam Kết Hiệu Suất Theo Vị Trí -->
    <?php if (!empty($eval['goals_summary']) || !empty($eval['custom_items_json'])): ?>
        <div class="bg-indigo-50 dark:bg-slate-800 p-5 sm:p-6 rounded-sm border border-indigo-200 dark:border-indigo-900 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1.5 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-600 text-white flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-bullseye"></i>
                        <span>Mục Tiêu Đề Ra Trọng Tâm Của Kỳ</span>
                    </span>
                    <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                        <i class="fa-solid fa-briefcase mr-1 text-indigo-500"></i>Tiêu chí gắn liền vị trí: <?= e($eval['position_name'] ?: 'Chuyên môn') ?>
                    </span>
                </div>
                <?php if (!empty($eval['goals_summary'])): ?>
                    <p class="text-xs font-medium text-slate-800 dark:text-slate-200 leading-relaxed bg-white/80 dark:bg-slate-900/60 p-3 rounded-2xl border border-indigo-100 dark:border-indigo-950 mt-2">
                        <?= nl2br(e($eval['goals_summary'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 text-xs flex-shrink-0">
                <div class="text-right">
                    <span class="text-[11px] text-slate-400 block">Số tiêu chí đo lường:</span>
                    <span class="font-bold text-slate-800 dark:text-white font-mono text-sm"><?= count($template_items) ?> chỉ tiêu SMART</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-flag-checkered"></i>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3 Tabs Navigation Bar -->
    <div class="border-b border-slate-200 dark:border-slate-700 flex items-center gap-2 overflow-x-auto">
        <button type="button" onclick="switchTab('self')" id="tabBtn_self"
                class="px-5 py-3 font-bold text-xs border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 flex items-center gap-2 transition whitespace-nowrap">
            <i class="fa-solid fa-user-pen"></i>
            <span>1. Tự Đánh Giá (Nhân Viên)</span>
            <?php if ($eval['self_score'] !== null): ?>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <?php endif; ?>
        </button>

        <button type="button" onclick="switchTab('manager')" id="tabBtn_manager"
                class="px-5 py-3 font-semibold text-xs border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-2 transition whitespace-nowrap">
            <i class="fa-solid fa-user-tie"></i>
            <span>2. Quản Lý Đánh Giá</span>
            <?php if ($eval['manager_score'] !== null): ?>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <?php endif; ?>
        </button>

        <button type="button" onclick="switchTab('results')" id="tabBtn_results"
                class="px-5 py-3 font-semibold text-xs border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-2 transition whitespace-nowrap">
            <i class="fa-solid fa-chart-pie"></i>
            <span>3. Kết Quả & Total Rewards</span>
        </button>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 1: NHÂN VIÊN TỰ ĐÁNH GIÁ (SELF REVIEW TAB)            -->
    <!-- ======================================================== -->
    <div id="tabContent_self" class="space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-sliders text-indigo-600"></i>
                        <span>Bảng Tiêu Chí Tự Đánh Giá Bản Thân (Đo Lường SMART)</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Nhập <strong>Số Thực Hiện Đạt Được (Actual)</strong> để hệ thống tự động tính ra % hoàn thành theo công thức khoa học.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-[11px] text-slate-400 block">Điểm tự chấm tạm tính:</span>
                        <span id="selfLiveScore" class="text-xl font-black font-mono text-indigo-600 dark:text-indigo-400">
                            <?= $eval['self_score'] !== null ? round($eval['self_score'], 1) : '0.0' ?>
                        </span>
                        <span class="text-xs text-slate-400">/100</span>
                    </div>
                </div>
            </div>

            <!-- Form Danh Sách Tiêu Chí -->
            <form id="formSelfReview" onsubmit="submitEvaluation(event, 'self', true)" class="space-y-6">
                
                <div class="space-y-5">
                    <?php foreach ($template_items as $idx => $it): ?>
                        <?php 
                            $it_id = $it['id'] ?? ($idx + 1);
                            $saved_it = $self_map[$it_id] ?? null;
                            $init_score = $saved_it ? (float)($saved_it['score'] ?? 85) : 85;
                            $init_actual = $saved_it ? ($saved_it['actual'] ?? '') : '';
                            $init_comment = $saved_it ? ($saved_it['comment'] ?? '') : '';
                            
                            $p_group = $it['priority_group'] ?? 'B';
                            $p_info = $priority_groups[$p_group] ?? $priority_groups['B'];
                            $pillar = $bsc_pillars[$it['bsc_pillar'] ?? 'internal_process'] ?? $bsc_pillars['internal_process'];
                        ?>
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 space-y-4">
                            
                            <!-- Tiêu đề tiêu chí, Badges & Trọng số -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border <?= $p_info['badge'] ?>">
                                                <?= $p_group ?>: <?= e($p_info['label']) ?>
                                            </span>
                                            <span class="text-xs <?= $pillar['color'] ?>" title="<?= e($pillar['label']) ?>">
                                                <i class="fa-solid <?= $pillar['icon'] ?> mr-1"></i><?= e($pillar['label']) ?>
                                            </span>
                                        </div>

                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1">
                                            <?= e($it['criteria_name']) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            <?= e($it['description'] ?: 'Thực hiện chỉ tiêu theo kế hoạch giao.') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-auto flex-wrap">
                                    <?php if (!empty($it['data_source'])): ?>
                                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-500 font-mono" title="Nguồn kiểm chứng">
                                            <i class="fa-solid fa-database mr-1"></i><?= e($it['data_source']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 text-xs font-mono font-bold">
                                        Trọng số: <?= round($it['weight'], 1) ?>%
                                    </span>
                                </div>
                            </div>

                            <!-- Khung SMART: Chỉ Tiêu (Target) vs Thực Hiện (Actual) vs Điểm Quy Đổi -->
                            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                
                                <!-- Cột 1: Chỉ tiêu Target (4 cột) -->
                                <div class="md:col-span-4 p-2.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-700/60">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Chỉ Tiêu Kế Hoạch (Target)</span>
                                    <div class="text-sm font-bold font-mono text-slate-800 dark:text-white mt-0.5 flex items-center justify-between">
                                        <span><?= e($it['target_value'] ?: '100%') ?></span>
                                        <span class="text-xs font-normal text-slate-400"><?= e($it['unit'] ?? '%') ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-1">
                                        Chiều đo: <?= $it['calc_direction'] === 'lower_better' ? 'Nghịch (Càng thấp càng tốt)' : 'Thuận (Càng cao càng tốt)' ?>
                                    </div>
                                </div>

                                <!-- Cột 2: Nhập số thực tế Actual (4 cột) -->
                                <div class="md:col-span-4">
                                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                                        Số Thực Hiện Đạt Được (Actual)
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="actual_self_<?= $it_id ?>"
                                               value="<?= e($init_actual) ?>"
                                               placeholder="VD: 98 hoặc 105"
                                               data-target="<?= e($it['target_value'] ?? '100%') ?>"
                                               data-direction="<?= e($it['calc_direction'] ?? 'higher_better') ?>"
                                               data-cap="<?= e($it['max_cap'] ?? 120) ?>"
                                               oninput="calcKpiFromActual(this, 'num_self_<?= $it_id ?>', 'slider_self_<?= $it_id ?>', 'self')"
                                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 font-bold"><?= e($it['unit'] ?? '%') ?></span>
                                    </div>
                                </div>

                                <!-- Cột 3: Điểm Quy Đổi & Slider (4 cột) -->
                                <div class="md:col-span-4 flex items-center gap-3">
                                    <div class="flex-1">
                                        <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                                            <span>% Hoàn Thành / Điểm</span>
                                            <span class="font-mono font-bold text-indigo-600" id="label_score_self_<?= $it_id ?>"><?= $init_score ?>đ</span>
                                        </div>
                                        <input type="range" min="0" max="120" step="1" 
                                               id="slider_self_<?= $it_id ?>"
                                               value="<?= $init_score ?>"
                                               data-weight="<?= $it['weight'] ?>"
                                               oninput="syncSliderToInput(this, 'num_self_<?= $it_id ?>', 'self')"
                                               class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                                    </div>

                                    <input type="number" min="0" max="120" step="0.5"
                                           id="num_self_<?= $it_id ?>"
                                           name="self_scores[<?= $it_id ?>]"
                                           value="<?= $init_score ?>"
                                           data-item-id="<?= $it_id ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncInputToSlider(this, 'slider_self_<?= $it_id ?>', 'self')"
                                           class="self-score-input w-16 px-2.5 py-1.5 text-center font-mono font-black text-xs bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 rounded-xl text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500">
                                </div>

                            </div>

                            <!-- Ô nhập giải trình / minh chứng -->
                            <div>
                                <input type="text" id="comment_self_<?= $it_id ?>" value="<?= e($init_comment) ?>"
                                       placeholder="Dẫn chứng số liệu, link báo cáo ERP/Git hoặc giải trình lý do đạt/chưa đạt..."
                                       class="w-full px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500">
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tự nhận xét tổng thể -->
                <div class="space-y-2 pt-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        Nhận Xét & Đánh Giá Tổng Thể Của Bản Thân
                    </label>
                    <textarea id="self_notes" rows="4" placeholder="Tự đánh giá các điểm nổi bật trong kỳ, điểm cần cải thiện, và các đề xuất cần ban lãnh đạo hỗ trợ..."
                              class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500"><?= e($eval['self_notes'] ?? '') ?></textarea>
                </div>

                <!-- Footer Buttons -->
                <?php if ($can_edit_self): ?>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" onclick="submitEvaluation(event, 'self', false)"
                                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Lưu Tạm Bản Nháp</span>
                        </button>

                        <button type="submit" 
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-sm text-xs transition flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Nộp Tự Đánh Giá (Chuyển Quản Lý)</span>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-slate-100 dark:bg-slate-700 rounded-xl text-xs text-slate-500 dark:text-slate-300 text-center">
                        <i class="fa-solid fa-lock mr-1"></i> Chế độ xem: Bạn không có quyền chỉnh sửa mục tự đánh giá này.
                    </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 2: QUẢN LÝ CHẤM ĐIỂM (MANAGER REVIEW TAB)             -->
    <!-- ======================================================== -->
    <div id="tabContent_manager" class="hidden space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 sm:p-8 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-user-tie text-violet-600"></i>
                        <span>Bảng Quản Lý Trực Tiếp Thẩm Tra & Chấm Điểm</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Đối chiếu số liệu thực tế, thẩm định mức độ hoàn thành và đưa ra xếp loại nhân sự & thưởng Total Rewards.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-[11px] text-slate-400 block">Điểm Quản Lý tạm tính:</span>
                        <span id="mgrLiveScore" class="text-xl font-black font-mono text-violet-600 dark:text-violet-400">
                            <?= $eval['manager_score'] !== null ? round($eval['manager_score'], 1) : '0.0' ?>
                        </span>
                        <span class="text-xs text-slate-400">/100</span>
                    </div>
                </div>
            </div>

            <!-- Form Quản Lý Chấm -->
            <form id="formManagerReview" onsubmit="submitEvaluation(event, 'manager', true)" class="space-y-6">
                
                <div class="space-y-5">
                    <?php foreach ($template_items as $idx => $it): ?>
                        <?php 
                            $it_id = $it['id'] ?? ($idx + 1);
                            $saved_self = $self_map[$it_id] ?? null;
                            $saved_mgr = $mgr_map[$it_id] ?? null;
                            $self_pt = $saved_self ? (float)($saved_self['score'] ?? null) : null;
                            $self_act = $saved_self ? ($saved_self['actual'] ?? '') : '';
                            $init_mgr_score = $saved_mgr ? (float)($saved_mgr['score'] ?? 85) : ($self_pt !== null ? $self_pt : 85);
                            $init_mgr_actual = $saved_mgr ? ($saved_mgr['actual'] ?? $self_act) : $self_act;
                            $init_mgr_comment = $saved_mgr ? ($saved_mgr['comment'] ?? '') : '';

                            $p_group = $it['priority_group'] ?? 'B';
                            $p_info = $priority_groups[$p_group] ?? $priority_groups['B'];
                            $pillar = $bsc_pillars[$it['bsc_pillar'] ?? 'internal_process'] ?? $bsc_pillars['internal_process'];
                        ?>
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 space-y-4">
                            
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-violet-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border <?= $p_info['badge'] ?>">
                                                <?= $p_group ?>: <?= e($p_info['label']) ?>
                                            </span>
                                            <span class="text-xs <?= $pillar['color'] ?>" title="<?= e($pillar['label']) ?>">
                                                <i class="fa-solid <?= $pillar['icon'] ?> mr-1"></i><?= e($pillar['label']) ?>
                                            </span>
                                        </div>

                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1">
                                            <?= e($it['criteria_name']) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            <?= e($it['description'] ?: 'Thực hiện chỉ tiêu theo kế hoạch giao.') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-auto flex-wrap">
                                    <span class="px-2.5 py-1 rounded-lg bg-violet-100 dark:bg-violet-950/80 text-violet-700 dark:text-violet-300 text-xs font-mono font-bold">
                                        Trọng số: <?= round($it['weight'], 1) ?>%
                                    </span>
                                </div>
                            </div>

                            <!-- Box Đối Chiếu Điểm Tự Chấm Của Nhân Viên -->
                            <div class="bg-indigo-50/70 dark:bg-indigo-950/40 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900 flex items-center justify-between text-xs flex-wrap gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-user-pen text-indigo-500"></i>
                                        <span class="text-slate-600 dark:text-slate-300">Nhân viên khai báo:</span>
                                        <strong class="font-mono text-indigo-600 dark:text-indigo-400">
                                            <?= $self_act !== '' ? e($self_act) . ' ' . e($it['unit'] ?? '') : '---' ?>
                                        </strong>
                                    </div>
                                    <span>&bull;</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-600 dark:text-slate-300">Điểm tự chấm:</span>
                                        <strong class="font-mono text-indigo-600 dark:text-indigo-400 text-sm">
                                            <?= $self_pt !== null ? $self_pt . 'đ' : 'Chưa chấm' ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="text-slate-500 text-[11px] truncate max-w-sm" title="<?= e($saved_self['comment'] ?? '') ?>">
                                    <?= !empty($saved_self['comment']) ? '“' . e($saved_self['comment']) . '”' : 'Không có ghi chú' ?>
                                </div>
                            </div>

                            <!-- Interactive Slider + Input Số cho Quản lý -->
                            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                
                                <div class="md:col-span-4">
                                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                                        Số Thực Hiện Thẩm Định (Actual)
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="actual_mgr_<?= $it_id ?>"
                                               value="<?= e($init_mgr_actual) ?>"
                                               placeholder="VD: 98 hoặc 105"
                                               data-target="<?= e($it['target_value'] ?? '100%') ?>"
                                               data-direction="<?= e($it['calc_direction'] ?? 'higher_better') ?>"
                                               data-cap="<?= e($it['max_cap'] ?? 120) ?>"
                                               oninput="calcKpiFromActual(this, 'num_mgr_<?= $it_id ?>', 'slider_mgr_<?= $it_id ?>', 'manager')"
                                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-violet-500">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 font-bold"><?= e($it['unit'] ?? '%') ?></span>
                                    </div>
                                </div>

                                <div class="md:col-span-8 flex items-center gap-3">
                                    <div class="flex-1">
                                        <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                                            <span>Quản lý thẩm định</span>
                                            <span class="font-mono font-bold text-violet-600" id="label_score_mgr_<?= $it_id ?>"><?= $init_mgr_score ?>đ</span>
                                        </div>
                                        <input type="range" min="0" max="120" step="1" 
                                               id="slider_mgr_<?= $it_id ?>"
                                               value="<?= $init_mgr_score ?>"
                                               data-weight="<?= $it['weight'] ?>"
                                               oninput="syncSliderToInput(this, 'num_mgr_<?= $it_id ?>', 'manager')"
                                               class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-violet-600">
                                    </div>

                                    <input type="number" min="0" max="120" step="0.5"
                                           id="num_mgr_<?= $it_id ?>"
                                           name="mgr_scores[<?= $it_id ?>]"
                                           value="<?= $init_mgr_score ?>"
                                           data-item-id="<?= $it_id ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncInputToSlider(this, 'slider_mgr_<?= $it_id ?>', 'manager')"
                                           class="mgr-score-input w-16 px-2.5 py-1.5 text-center font-mono font-black text-xs bg-violet-50 dark:bg-violet-950/50 border border-violet-200 dark:border-violet-800 rounded-xl text-violet-600 dark:text-violet-400 focus:ring-2 focus:ring-violet-500">
                                </div>
                            </div>

                            <!-- Ghi chú của quản lý cho tiêu chí này -->
                            <div>
                                <input type="text" id="comment_mgr_<?= $it_id ?>" value="<?= e($init_mgr_comment) ?>"
                                       placeholder="Quản lý nhận xét về chất lượng hoặc lý do điều chỉnh điểm số..."
                                       class="w-full px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-violet-500">
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Đề xuất nhân sự & Nhận xét tổng kết của Quản lý -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Đề Xuất Hành Động Nhân Sự
                        </label>
                        <select id="mgr_promotion_recommendation"
                                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white font-medium focus:ring-2 focus:ring-violet-500">
                            <option value="none" <?= ($eval['promotion_recommendation'] ?? '') === 'none' ? 'selected' : '' ?>>Không có đề xuất đặc biệt</option>
                            <option value="bonus" <?= ($eval['promotion_recommendation'] ?? '') === 'bonus' ? 'selected' : '' ?>>Khen thưởng hiệu suất xuất sắc</option>
                            <option value="salary_increase" <?= ($eval['promotion_recommendation'] ?? '') === 'salary_increase' ? 'selected' : '' ?>>Đề xuất tăng bậc lương</option>
                            <option value="promotion" <?= ($eval['promotion_recommendation'] ?? '') === 'promotion' ? 'selected' : '' ?>>Quy hoạch cán bộ nguồn / Bổ nhiệm</option>
                            <option value="training" <?= ($eval['promotion_recommendation'] ?? '') === 'training' ? 'selected' : '' ?>>Đào tạo nâng cao chuyên môn (PIP)</option>
                            <option value="warning" <?= ($eval['promotion_recommendation'] ?? '') === 'warning' ? 'selected' : '' ?>>Nhắc nhở & Kèm cặp cải thiện</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Nhận Xét Tổng Thể & Định Hướng Của Quản Lý
                        </label>
                        <textarea id="mgr_notes" rows="3" placeholder="Đánh giá thái độ, tiềm năng phát triển và mục tiêu cần đạt trong kỳ tới..."
                                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-violet-500"><?= e($eval['manager_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <?php if ($can_edit_mgr): ?>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" onclick="submitEvaluation(event, 'manager', false)"
                                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Lưu Tạm Bản Chấm</span>
                        </button>

                        <button type="submit" 
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-sm text-xs transition flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Phê Duyệt & Chốt Kết Quả (Hoàn Tất)</span>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-slate-100 dark:bg-slate-700 rounded-xl text-xs text-slate-500 dark:text-slate-300 text-center">
                        <i class="fa-solid fa-lock mr-1"></i> Chế độ xem: Bạn không có quyền quản lý để chấm điểm phiếu này.
                    </div>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 3: KẾT QUẢ & PHÂN TÍCH TOTAL REWARDS (RESULTS TAB)   -->
    <!-- ======================================================== -->
    <div id="tabContent_results" class="hidden space-y-6">
        
        <!-- Hàng 1: Radar Chart + Ma Trận Nhóm A/B/C & Total Rewards -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Cột trái: Radar Chart (5 cột) -->
            <div class="lg:col-span-5 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-indigo-600"></i>
                            <span>Biểu Đồ Radar Đa Chiều BSC</span>
                        </h3>
                        <span class="text-[11px] text-slate-400 font-mono">Thang 100</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        Phân tích tương quan hiệu suất trên 4 trụ cột Balanced Scorecard: Tài chính, Khách hàng, Quy trình và Đào tạo.
                    </p>

                    <div class="relative w-full aspect-square max-h-[340px] mx-auto">
                        <canvas id="kpiRadarChart"></canvas>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-center gap-6 text-xs font-semibold">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-indigo-500 inline-block"></span>
                        <span class="text-slate-700 dark:text-slate-300">Nhân viên tự chấm</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-violet-500 inline-block"></span>
                        <span class="text-slate-700 dark:text-slate-300">Quản lý thẩm định</span>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Ma Trận Ưu Tiên Link Power & Total Rewards (7 cột) -->
            <div class="lg:col-span-7 space-y-5">
                
                <!-- Thẻ Total Rewards Simulator -->
                <div class="bg-slate-900 text-white rounded-sm p-6 border border-slate-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-400/20 text-amber-400 flex items-center justify-center text-lg">
                                <i class="fa-solid fa-gift"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold">Chính Sách Thưởng Hiệu Suất (Total Rewards)</h3>
                                <p class="text-xs text-slate-300">Quy đổi xếp loại KPI sang hệ số đãi ngộ và khen thưởng</p>
                            </div>
                        </div>

                        <span class="px-3 py-1 rounded-xl font-mono font-black text-sm bg-amber-400 text-slate-950">
                            Hệ Số K = x<?= number_format($bonus_mult, 2) ?>
                        </span>
                    </div>

                    <div class="p-3.5 bg-white/10 backdrop-blur-md rounded-2xl border border-white/10 text-xs space-y-1.5">
                        <div class="font-semibold text-amber-300">
                            <?= $current_rating_cfg ? $current_rating_cfg['label'] : 'Đang xử lý đánh giá' ?>
                        </div>
                        <p class="text-slate-200 text-[11px] leading-relaxed">
                            <?= $current_rating_cfg ? $current_rating_cfg['reward_desc'] : 'Chưa có kết luận chính sách thưởng.' ?>
                        </p>
                    </div>

                    <?php if (!empty($eval['base_salary']) && $eval['base_salary'] > 0): ?>
                        <div class="grid grid-cols-2 gap-3 text-xs pt-1">
                            <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                <span class="text-slate-400 block text-[11px]">Lương căn bản ngạch:</span>
                                <strong class="font-mono text-white text-sm"><?= format_money($eval['base_salary']) ?></strong>
                            </div>
                            <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                <span class="text-slate-400 block text-[11px]">Dự toán thưởng KPI kỳ:</span>
                                <strong class="font-mono text-emerald-400 text-sm">
                                    <?= format_money($eval['base_salary'] * $bonus_mult * 0.20) ?>
                                </strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bảng Phân Tích Ma Trận Link Power (Nhóm A / B / C) -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-indigo-600"></i>
                        <span>Phân Tích Hiệu Suất Theo Ma Trận Link Power</span>
                    </h3>

                    <?php
                        $sum_weights = ['A' => 0, 'B' => 0, 'C' => 0];
                        $sum_scores = ['A' => 0, 'B' => 0, 'C' => 0];
                        foreach ($template_items as $idx => $it) {
                            $it_id = $it['id'] ?? ($idx + 1);
                            $pg = !empty($it['priority_group']) && in_array($it['priority_group'], ['A', 'B', 'C']) ? $it['priority_group'] : 'B';
                            $w = (float)($it['weight'] ?? 0);
                            $sc = ($it_id && isset($mgr_map[$it_id]['score'])) 
                                ? (float)$mgr_map[$it_id]['score'] 
                                : (($it_id && isset($self_map[$it_id]['score'])) ? (float)$self_map[$it_id]['score'] : 0);
                            $sum_weights[$pg] += $w;
                            $sum_scores[$pg] += ($sc * $w / 100);
                        }
                    ?>
                    <div class="space-y-3">
                        <?php foreach (['A', 'B', 'C'] as $pg): ?>
                            <?php 
                                $avg_pg = $sum_weights[$pg] > 0 ? round(($sum_scores[$pg] / $sum_weights[$pg]) * 100, 1) : 0;
                                $p_inf = $priority_groups[$pg] ?? $priority_groups['B'];
                            ?>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold border <?= $p_inf['badge'] ?>"><?= $pg ?></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200"><?= e($p_inf['label']) ?></span>
                                    </div>
                                    <div class="font-mono font-bold text-slate-700 dark:text-slate-300">
                                        <?= $avg_pg ?>% <span class="text-[10px] text-slate-400 font-normal">(Trọng số: <?= round($sum_weights[$pg], 0) ?>%)</span>
                                    </div>
                                </div>
                                <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="<?= $p_inf['bar'] ?> h-full rounded-full transition-all duration-500" style="width: <?= min(100, $avg_pg) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- Hàng 2: Bảng Phân Tích Độ Lệch & Chi Tiết -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-scale-balanced text-indigo-600"></i>
                        <span>Bảng Chi Tiết Điểm Số & Độ Lệch Thẩm Định (Delta Gap)</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        So sánh đối chiếu chi tiết từng tiêu chí giữa nhân viên và cấp quản lý
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-bold">
                            <th class="py-2.5 pr-2">Tiêu Chí (SMART)</th>
                            <th class="py-2.5 px-2 text-center">Nhóm</th>
                            <th class="py-2.5 px-2 text-center">Trọng Số</th>
                            <th class="py-2.5 px-2 text-center">Chỉ Tiêu</th>
                            <th class="py-2.5 px-2 text-center">Tự Chấm</th>
                            <th class="py-2.5 px-2 text-center">Quản Lý</th>
                            <th class="py-2.5 px-2 text-center">Độ Lệch</th>
                            <th class="py-2.5 pl-2 text-right">Đóng Góp Điểm</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($template_items as $idx => $it): ?>
                            <?php 
                                $it_id = $it['id'] ?? ($idx + 1);
                                $s_val = ($it_id && isset($self_map[$it_id]['score'])) ? (float)$self_map[$it_id]['score'] : null;
                                $m_val = ($it_id && isset($mgr_map[$it_id]['score'])) ? (float)$mgr_map[$it_id]['score'] : null;
                                $delta = ($s_val !== null && $m_val !== null) ? round($m_val - $s_val, 1) : null;
                                $final_contrib = ($m_val !== null) ? round($m_val * ($it['weight'] ?? 0) / 100, 2) : 0;
                                $pg = !empty($it['priority_group']) && in_array($it['priority_group'], ['A', 'B', 'C']) ? $it['priority_group'] : 'B';
                                $p_inf = $priority_groups[$pg] ?? $priority_groups['B'];
                            ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40">
                                <td class="py-3 pr-2">
                                    <div class="font-bold text-slate-800 dark:text-slate-200"><?= e($it['criteria_name']) ?></div>
                                    <?php if (!empty($it['data_source'])): ?>
                                        <div class="text-[10px] text-slate-400"><i class="fa-solid fa-database mr-1"></i><?= e($it['data_source']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold border <?= $p_inf['badge'] ?>"><?= $pg ?></span>
                                </td>
                                <td class="py-3 px-2 text-center font-mono font-bold text-slate-500">
                                    <?= round($it['weight'], 1) ?>%
                                </td>
                                <td class="py-3 px-2 text-center font-mono text-slate-600 dark:text-slate-400">
                                    <?= e($it['target_value'] ?: '100%') ?> <?= e($it['unit'] ?? '') ?>
                                </td>
                                <td class="py-3 px-2 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    <?= $s_val !== null ? $s_val : '---' ?>
                                </td>
                                <td class="py-3 px-2 text-center font-mono font-bold text-violet-600 dark:text-violet-400">
                                    <?= $m_val !== null ? $m_val : '---' ?>
                                </td>
                                <td class="py-3 px-2 text-center font-mono font-bold">
                                    <?php if ($delta === null): ?>
                                        <span class="text-slate-400">---</span>
                                    <?php elseif ($delta > 0): ?>
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[11px]">+<?= $delta ?></span>
                                    <?php elseif ($delta < 0): ?>
                                        <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[11px]"><?= $delta ?></span>
                                    <?php else: ?>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[11px]">0.0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pl-2 text-right font-mono font-bold text-slate-700 dark:text-slate-300">
                                    <?= $final_contrib ?> đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- JavaScript Logic: Tab Switching, Live Sync, Quantitative Actual Calc, Radar Chart, AJAX Submit -->
<script>
const EVALUATION_ID = <?= (int)$eval['id'] ?>;
const TEMPLATE_ITEMS = <?= json_encode($template_items) ?>;
const SELF_MAP = <?= json_encode($self_map) ?>;
const MGR_MAP = <?= json_encode($mgr_map) ?>;

let radarChartInstance = null;

// Chuyển Tab mượt mà
function switchTab(tabKey) {
    const tabs = ['self', 'manager', 'results'];
    tabs.forEach(t => {
        const content = document.getElementById('tabContent_' + t);
        const btn = document.getElementById('tabBtn_' + t);
        if (t === tabKey) {
            content.classList.remove('hidden');
            btn.className = 'px-5 py-3 font-bold text-xs border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 flex items-center gap-2 transition whitespace-nowrap';
        } else {
            content.classList.add('hidden');
            btn.className = 'px-5 py-3 font-semibold text-xs border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-2 transition whitespace-nowrap';
        }
    });

    if (tabKey === 'results') {
        renderRadarChart();
    }
}

// Tính toán khoa học từ Số Thực Tế (Actual) so với Kế Hoạch (Target)
function calcKpiFromActual(actualInput, scoreInputId, sliderInputId, type) {
    const actualStr = actualInput.value.trim();
    const targetStr = actualInput.getAttribute('data-target') || '100';
    const direction = actualInput.getAttribute('data-direction') || 'higher_better';
    const maxCap = parseFloat(actualInput.getAttribute('data-cap')) || 120;

    const numActual = parseFloat(actualStr);
    const match = targetStr.match(/(\d+(?:\.\d+)?)/);
    const numTarget = match ? parseFloat(match[1]) : 100;

    if (!isNaN(numActual) && numTarget > 0) {
        let completion = 0;
        if (direction === 'higher_better') {
            completion = (numActual / numTarget) * 100;
        } else if (direction === 'lower_better') {
            completion = (1 + (numTarget - numActual) / numTarget) * 100;
        } else {
            completion = numActual > 0 ? 100 : 0;
        }

        completion = Math.max(0, Math.min(maxCap, completion));
        completion = Math.round(completion * 10) / 10;

        const scoreInput = document.getElementById(scoreInputId);
        const sliderInput = document.getElementById(sliderInputId);

        if (scoreInput) scoreInput.value = completion;
        if (sliderInput) sliderInput.value = completion;

        const lbl = document.getElementById('label_score_' + type + '_' + actualInput.id.replace('actual_' + type + '_', ''));
        if (lbl) lbl.textContent = completion + 'đ';

        calculateLiveScore(type);
    }
}

// Đồng bộ Slider -> Input Số
function syncSliderToInput(slider, inputId, type) {
    const input = document.getElementById(inputId);
    input.value = slider.value;
    const itId = inputId.replace('num_' + type + '_', '');
    const lbl = document.getElementById('label_score_' + type + '_' + itId);
    if (lbl) lbl.textContent = slider.value + 'đ';
    calculateLiveScore(type);
}

// Đồng bộ Input Số -> Slider
function syncInputToSlider(input, sliderId, type) {
    const slider = document.getElementById(sliderId);
    let val = parseFloat(input.value) || 0;
    val = Math.max(0, Math.min(120, val));
    slider.value = val;
    const itId = sliderId.replace('slider_' + type + '_', '');
    const lbl = document.getElementById('label_score_' + type + '_' + itId);
    if (lbl) lbl.textContent = val + 'đ';
    calculateLiveScore(type);
}

// Tính điểm live theo trọng số %
function calculateLiveScore(type) {
    const inputs = document.querySelectorAll(type === 'self' ? '.self-score-input' : '.mgr-score-input');
    let totalWeighted = 0;
    let totalWeight = 0;

    inputs.forEach(inp => {
        const score = parseFloat(inp.value) || 0;
        const weight = parseFloat(inp.getAttribute('data-weight')) || 0;
        totalWeighted += (score * weight / 100);
        totalWeight += weight;
    });

    const finalVal = totalWeight > 0 ? (totalWeighted / totalWeight * 100) : 0;
    const rounded = Math.round(finalVal * 10) / 10;

    if (type === 'self') {
        document.getElementById('selfLiveScore').textContent = rounded.toFixed(1);
    } else {
        document.getElementById('mgrLiveScore').textContent = rounded.toFixed(1);
    }
}

// Vẽ Radar Chart với Chart.js
function renderRadarChart() {
    const ctx = document.getElementById('kpiRadarChart');
    if (!ctx) return;

    if (radarChartInstance) {
        radarChartInstance.destroy();
    }

    const labels = [];
    const selfData = [];
    const mgrData = [];

    TEMPLATE_ITEMS.forEach((it, idx) => {
        const itId = it.id || (idx + 1);
        const criteriaName = it.criteria_name || it.criteria || ('Tiêu chí ' + (idx + 1));
        const name = criteriaName.length > 22 ? criteriaName.substring(0, 22) + '...' : criteriaName;
        labels.push(name);

        const sInp = document.getElementById('num_self_' + itId);
        const mInp = document.getElementById('num_mgr_' + itId);

        const selfVal = sInp ? parseFloat(sInp.value) : ((SELF_MAP[itId] && SELF_MAP[itId].score !== undefined) ? parseFloat(SELF_MAP[itId].score) : 0);
        const mgrVal = mInp ? parseFloat(mInp.value) : ((MGR_MAP[itId] && MGR_MAP[itId].score !== undefined) ? parseFloat(MGR_MAP[itId].score) : 0);
        selfData.push(isNaN(selfVal) ? 0 : selfVal);
        mgrData.push(isNaN(mgrVal) ? 0 : mgrVal);
    });

    const isDarkMode = document.documentElement.classList.contains('dark');
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)';
    const textColor = isDarkMode ? '#cbd5e1' : '#475569';

    radarChartInstance = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Nhân viên tự chấm',
                    data: selfData,
                    backgroundColor: 'rgba(99, 102, 241, 0.25)',
                    borderColor: '#6366f1',
                    pointBackgroundColor: '#6366f1',
                    borderWidth: 2
                },
                {
                    label: 'Quản lý chấm',
                    data: mgrData,
                    backgroundColor: 'rgba(139, 92, 246, 0.25)',
                    borderColor: '#8b5cf6',
                    pointBackgroundColor: '#8b5cf6',
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    angleLines: { color: gridColor },
                    grid: { color: gridColor },
                    pointLabels: {
                        color: textColor,
                        font: { size: 10, family: 'sans-serif', weight: 'bold' }
                    },
                    suggestedMin: 0,
                    suggestedMax: 100,
                    ticks: {
                        backdropColor: 'transparent',
                        color: textColor,
                        stepSize: 20
                    }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// Gửi kết quả đánh giá qua AJAX
async function submitEvaluation(event, reviewType, isSubmitFinal) {
    if (event) event.preventDefault();

    if (isSubmitFinal) {
        const confirmMsg = reviewType === 'self' 
            ? 'Bạn có chắc chắn muốn nộp kết quả tự đánh giá? Phiếu sẽ được chuyển tiếp cho Quản lý thẩm tra.'
            : 'Bạn có chắc chắn muốn phê duyệt và chốt kết quả đánh giá KPI cùng hệ số thưởng Total Rewards?';
        const title = reviewType === 'self' ? 'Xác Nhận Nộp Tự Đánh Giá' : 'Xác Nhận Phê Duyệt KPI';
        const confirmed = await window.confirmAsync({
            title: title,
            message: confirmMsg,
            confirmText: 'Xác Nhận Nộp',
            isDanger: false
        });
        if (!confirmed) return;
    }

    const scores = [];
    TEMPLATE_ITEMS.forEach((it, idx) => {
        const itId = it.id || (idx + 1);
        const scoreInput = document.getElementById(reviewType === 'self' ? 'num_self_' + itId : 'num_mgr_' + itId);
        const actualInput = document.getElementById(reviewType === 'self' ? 'actual_self_' + itId : 'actual_mgr_' + itId);
        const commentInput = document.getElementById(reviewType === 'self' ? 'comment_self_' + itId : 'comment_mgr_' + itId);
        
        scores.push({
            item_id: itId,
            criteria: it.criteria_name || it.criteria || '',
            actual: actualInput ? actualInput.value.trim() : '',
            score: scoreInput ? (parseFloat(scoreInput.value) || 0) : 0,
            comment: commentInput ? commentInput.value : ''
        });
    });

    const notes = reviewType === 'self' 
        ? document.getElementById('self_notes').value 
        : document.getElementById('mgr_notes').value;

    const promotionRec = reviewType === 'manager' 
        ? document.getElementById('mgr_promotion_recommendation').value 
        : 'none';

    try {
        const response = await fetch('<?= base_url('modules/evaluations/ajax_evaluate.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                evaluation_id: EVALUATION_ID,
                review_type: reviewType,
                scores: scores,
                notes: notes,
                promotion_recommendation: promotionRec,
                submit_final: isSubmitFinal
            })
        });

        const res = await response.json();
        if (res.success) {
            if (typeof window.showToast === 'function') {
                window.showToast('success', res.message);
            }
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            if (typeof window.showToast === 'function') {
                window.showToast('danger', 'Lỗi: ' + res.message);
            }
        }
    } catch (e) {
        if (typeof window.showToast === 'function') {
            window.showToast('danger', 'Có lỗi mạng hoặc máy chủ không phản hồi: ' + e.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    calculateLiveScore('self');
    calculateLiveScore('manager');
    <?php if ($eval['status'] === 'completed'): ?>
        switchTab('results');
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
