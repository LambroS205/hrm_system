<?php
// modules/evaluations/view.php - Chi Tiết Đánh Giá KPI & Chấm Điểm 2 Chiều
require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash('danger', 'ID phiếu đánh giá không hợp lệ.');
    redirect('modules/evaluations/index.php');
}

// Lấy đầy đủ thông tin phiếu đánh giá
$stmt = $pdo->prepare("
    SELECT e.*,
           ep.code AS period_code, ep.name AS period_name, ep.year AS period_year, ep.quarter AS period_quarter,
           ep.start_date AS period_start, ep.end_date AS period_end,
           ep.self_review_deadline, ep.manager_review_deadline, ep.status AS period_status,
           emp.employee_code, emp.fullname AS employee_name, emp.avatar AS employee_avatar,
           emp.user_id AS employee_user_id,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name,
           rev.employee_code AS reviewer_code, rev.fullname AS reviewer_name, rev.avatar AS reviewer_avatar,
           rev_pos.name AS reviewer_position, rev.user_id AS reviewer_user_id,
           t.code AS template_code, t.name AS template_name, t.description AS template_desc
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    LEFT JOIN branches b ON emp.branch_id = b.id
    LEFT JOIN departments d ON emp.department_id = d.id
    LEFT JOIN positions p ON emp.position_id = p.id
    JOIN employees rev ON e.reviewer_id = rev.id
    LEFT JOIN positions rev_pos ON rev.position_id = rev_pos.id
    JOIN kpi_templates t ON e.template_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eval = $stmt->fetch();

if (!$eval) {
    set_flash('danger', 'Không tìm thấy hồ sơ đánh giá này.');
    redirect('modules/evaluations/index.php');
}

$page_title = 'Phiếu Đánh Giá KPI: ' . $eval['evaluation_code'];

// Lấy danh sách các tiêu chí thành phần
$items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
$items_stmt->execute([$eval['template_id']]);
$template_items = $items_stmt->fetchAll();

// Giải mã JSON điểm đã lưu nếu có
$self_scores_saved = !empty($eval['self_scores_json']) ? json_decode($eval['self_scores_json'], true) : [];
$mgr_scores_saved = !empty($eval['manager_scores_json']) ? json_decode($eval['manager_scores_json'], true) : [];

$self_map = [];
if (is_array($self_scores_saved)) {
    foreach ($self_scores_saved as $s) {
        $self_map[$s['item_id']] = $s;
    }
}

$mgr_map = [];
if (is_array($mgr_scores_saved)) {
    foreach ($mgr_scores_saved as $m) {
        $mgr_map[$m['item_id']] = $m;
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

// Cấu hình xếp loại
$rating_configs = [
    'outstanding'   => ['label' => 'Xuất Sắc (A+)', 'bg' => 'from-amber-500 to-yellow-500', 'text' => 'text-amber-500', 'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300', 'icon' => 'fa-crown'],
    'excellent'     => ['label' => 'Tốt (A)', 'bg' => 'from-emerald-500 to-teal-500', 'text' => 'text-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300', 'icon' => 'fa-star'],
    'good'          => ['label' => 'Khá (B)', 'bg' => 'from-indigo-500 to-blue-500', 'text' => 'text-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300', 'icon' => 'fa-circle-check'],
    'average'       => ['label' => 'Trung Bình (C)', 'bg' => 'from-slate-500 to-zinc-500', 'text' => 'text-slate-500', 'badge' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200', 'icon' => 'fa-circle-dot'],
    'below_average' => ['label' => 'Yếu (D)', 'bg' => 'from-orange-500 to-amber-600', 'text' => 'text-orange-500', 'badge' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300', 'icon' => 'fa-triangle-exclamation'],
    'poor'          => ['label' => 'Kém (E)', 'bg' => 'from-rose-500 to-red-600', 'text' => 'text-rose-500', 'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300', 'icon' => 'fa-circle-xmark']
];

$rec_labels = [
    'none' => 'Không đề xuất',
    'bonus' => 'Khen thưởng hiệu suất',
    'salary_increase' => 'Đề xuất tăng bậc lương',
    'promotion' => 'Quy hoạch bổ nhiệm',
    'training' => 'Đào tạo & Hướng dẫn lại',
    'warning' => 'Nhắc nhở & Giám sát hiệu suất'
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Nạp Chart.js CDN phục vụ Radar Chart -->
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
                <span>Hồ Sơ Đánh Giá Hiệu Suất & KPI</span>
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
                   class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-slate-900 font-bold rounded-xl text-xs transition flex items-center gap-2 shadow-md shadow-amber-200 dark:shadow-none">
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
        
        <!-- Hàng trên: Thông tin nhân sự & Trạng thái -->
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

                        <!-- Status badge -->
                        <?php if ($eval['status'] === 'completed'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-[10px]"></i> Đã Hoàn Tất
                            </span>
                        <?php elseif ($eval['status'] === 'manager_review'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 flex items-center gap-1.5 animate-pulse">
                                <i class="fa-solid fa-user-tie text-[10px]"></i> Chờ Quản Lý Chấm
                            </span>
                        <?php elseif ($eval['status'] === 'self_review'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-pen text-[10px]"></i> Chờ Nhân Viên Tự Chấm
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-clock text-[10px]"></i> Chờ Thực Hiện
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
                        Bộ KPI: <span class="font-semibold text-indigo-600 dark:text-indigo-400"><?= e($eval['template_name']) ?></span>
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

        <!-- Hàng dưới: 4 Khối Chỉ Số Điểm Trọng Điểm -->
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

            <!-- Xếp loại & Tem chuẩn -->
            <?php 
                $cfg = $rating_configs[$eval['rating'] ?? ''] ?? null;
            ?>
            <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-900 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-amber-900 dark:text-amber-300">Xếp Loại Hiệu Suất</span>
                    <div class="text-lg font-black mt-1 <?= $cfg ? $cfg['text'] : 'text-slate-400' ?>" id="displayRatingLabel">
                        <?= $cfg ? $cfg['label'] : 'Chưa xếp loại' ?>
                    </div>
                    <div class="text-[11px] text-amber-800/80 dark:text-amber-400 mt-1 font-medium">
                        Đề xuất: <?= $rec_labels[$eval['promotion_recommendation']] ?? 'Chưa có' ?>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-500 text-white flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid <?= $cfg ? $cfg['icon'] : 'fa-certificate' ?>"></i>
                </div>
            </div>

        </div>

    </div>

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
            <span>3. Kết Quả & Phân Tích Radar</span>
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
                        <span>Bảng Tiêu Chí Tự Đánh Giá Bản Thân</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Kéo thanh slider hoặc nhập điểm từ 0 đến 100 cho từng tiêu chí, kèm minh chứng / ghi chú cụ thể.
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
                            $saved_it = $self_map[$it['id']] ?? null;
                            $init_score = $saved_it ? (float)$saved_it['score'] : 85;
                            $init_comment = $saved_it ? $saved_it['comment'] : '';
                        ?>
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 space-y-4">
                            
                            <!-- Tiêu đề tiêu chí & Trọng số -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                            <?= e($it['criteria_name']) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            <?= e($it['description']) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-auto">
                                    <?php if (!empty($it['target_value'])): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 text-[11px] font-mono text-indigo-600 dark:text-indigo-400 border border-slate-200 dark:border-slate-700">
                                            Mục tiêu: <b><?= e($it['target_value']) ?></b>
                                        </span>
                                    <?php endif; ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 text-xs font-mono font-bold">
                                        Trọng số: <?= round($it['weight'], 1) ?>%
                                    </span>
                                </div>
                            </div>

                            <!-- Interactive Slider + Input Số -->
                            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center gap-4">
                                <div class="w-full flex-1 space-y-1">
                                    <div class="flex justify-between text-[11px] text-slate-400 font-mono">
                                        <span>0 (Kém)</span>
                                        <span>50 (Trung bình)</span>
                                        <span>80 (Khá)</span>
                                        <span>100 (Xuất sắc)</span>
                                    </div>
                                    <input type="range" min="0" max="100" step="1" 
                                           id="slider_self_<?= $it['id'] ?>"
                                           value="<?= $init_score ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncSliderToInput(this, 'num_self_<?= $it['id'] ?>', 'self')"
                                           class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs font-bold text-slate-500">Điểm:</span>
                                    <input type="number" min="0" max="100" step="1"
                                           id="num_self_<?= $it['id'] ?>"
                                           name="self_scores[<?= $it['id'] ?>]"
                                           value="<?= $init_score ?>"
                                           data-item-id="<?= $it['id'] ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncInputToSlider(this, 'slider_self_<?= $it['id'] ?>', 'self')"
                                           class="self-score-input w-20 px-3 py-1.5 text-center font-mono font-black text-sm bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 rounded-xl text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500">
                                    <span class="text-xs font-bold text-slate-400">/ 100</span>
                                </div>
                            </div>

                            <!-- Ô nhập giải trình / minh chứng -->
                            <div>
                                <input type="text" id="comment_self_<?= $it['id'] ?>" value="<?= e($init_comment) ?>"
                                       placeholder="Minh chứng kết quả đạt được, tỷ lệ hoàn thành hoặc khó khăn gặp phải..."
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
                                class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-200 dark:shadow-none transition flex items-center gap-2">
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
                        <span>Bảng Quản Lý Trực Tiếp Đánh Giá & Chấm Điểm</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Xem đối chiếu điểm tự chấm của nhân viên, chấm điểm quản lý và đưa ra định hướng nhân sự.
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
                            $saved_self = $self_map[$it['id']] ?? null;
                            $saved_mgr = $mgr_map[$it['id']] ?? null;
                            $self_pt = $saved_self ? (float)$saved_self['score'] : null;
                            $init_mgr_score = $saved_mgr ? (float)$saved_mgr['score'] : ($self_pt !== null ? $self_pt : 85);
                            $init_mgr_comment = $saved_mgr ? $saved_mgr['comment'] : '';
                        ?>
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 space-y-4">
                            
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-violet-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                            <?= e($it['criteria_name']) ?>
                                        </h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            <?= e($it['description']) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-auto">
                                    <span class="px-2.5 py-1 rounded-lg bg-violet-100 dark:bg-violet-950/80 text-violet-700 dark:text-violet-300 text-xs font-mono font-bold">
                                        Trọng số: <?= round($it['weight'], 1) ?>%
                                    </span>
                                </div>
                            </div>

                            <!-- Box Đối Chiếu Điểm Tự Chấm Của Nhân Viên -->
                            <div class="bg-indigo-50/70 dark:bg-indigo-950/40 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-user-pen text-indigo-500"></i>
                                    <span class="text-slate-600 dark:text-slate-300">Nhân viên tự chấm:</span>
                                    <strong class="font-mono text-indigo-600 dark:text-indigo-400 text-sm">
                                        <?= $self_pt !== null ? $self_pt . ' điểm' : 'Chưa chấm' ?>
                                    </strong>
                                </div>
                                <div class="text-slate-500 text-[11px] truncate max-w-xs" title="<?= e($saved_self['comment'] ?? '') ?>">
                                    <?= !empty($saved_self['comment']) ? '“' . e($saved_self['comment']) . '”' : 'Không có ghi chú' ?>
                                </div>
                            </div>

                            <!-- Interactive Slider + Input Số cho Quản lý -->
                            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center gap-4">
                                <div class="w-full flex-1 space-y-1">
                                    <div class="flex justify-between text-[11px] text-slate-400 font-mono">
                                        <span>0 (Kém)</span>
                                        <span>50 (Trung bình)</span>
                                        <span>80 (Khá)</span>
                                        <span>100 (Xuất sắc)</span>
                                    </div>
                                    <input type="range" min="0" max="100" step="1" 
                                           id="slider_mgr_<?= $it['id'] ?>"
                                           value="<?= $init_mgr_score ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncSliderToInput(this, 'num_mgr_<?= $it['id'] ?>', 'manager')"
                                           class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-violet-600">
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs font-bold text-slate-500">Quản lý chấm:</span>
                                    <input type="number" min="0" max="100" step="1"
                                           id="num_mgr_<?= $it['id'] ?>"
                                           name="mgr_scores[<?= $it['id'] ?>]"
                                           value="<?= $init_mgr_score ?>"
                                           data-item-id="<?= $it['id'] ?>"
                                           data-weight="<?= $it['weight'] ?>"
                                           oninput="syncInputToSlider(this, 'slider_mgr_<?= $it['id'] ?>', 'manager')"
                                           class="mgr-score-input w-20 px-3 py-1.5 text-center font-mono font-black text-sm bg-violet-50 dark:bg-violet-950/50 border border-violet-200 dark:border-violet-800 rounded-xl text-violet-600 dark:text-violet-400 focus:ring-2 focus:ring-violet-500">
                                    <span class="text-xs font-bold text-slate-400">/ 100</span>
                                </div>
                            </div>

                            <!-- Ghi chú của quản lý cho tiêu chí này -->
                            <div>
                                <input type="text" id="comment_mgr_<?= $it['id'] ?>" value="<?= e($init_mgr_comment) ?>"
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
                            <option value="training" <?= ($eval['promotion_recommendation'] ?? '') === 'training' ? 'selected' : '' ?>>Đào tạo nâng cao chuyên môn</option>
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
                                class="px-6 py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-violet-200 dark:shadow-none transition flex items-center gap-2">
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
    <!-- TAB 3: KẾT QUẢ & PHÂN TÍCH RADAR CHART (RESULTS TAB)     -->
    <!-- ======================================================== -->
    <div id="tabContent_results" class="hidden space-y-6">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Cột trái: Radar Chart (5 cột) -->
            <div class="lg:col-span-5 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-indigo-600"></i>
                            <span>Biểu Đồ Mạng Nhện Radar KPI</span>
                        </h3>
                        <span class="text-[11px] text-slate-400 font-mono">Thang 100</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        So sánh tương quan trực quan giữa vòng Nhân viên tự đánh giá và Quản lý chấm điểm trên các trục năng lực.
                    </p>

                    <div class="relative w-full aspect-square max-h-[360px] mx-auto">
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
                        <span class="text-slate-700 dark:text-slate-300">Quản lý chấm</span>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Bảng Phân Tích Độ Lệch & Chi Tiết (7 cột) -->
            <div class="lg:col-span-7 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-scale-balanced text-indigo-600"></i>
                            <span>Phân Tích Chi Tiết Độ Lệch (Delta Gap)</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Chênh lệch giữa tự nhận thức của nhân viên và đánh giá của cấp trên
                        </p>
                    </div>

                    <?php if ($eval['rating']): ?>
                        <span class="px-3 py-1 rounded-xl text-xs font-bold <?= $cfg['badge'] ?? 'bg-slate-100' ?>">
                            <?= $cfg['label'] ?? '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Table Chi Tiết -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-bold">
                                <th class="py-2.5 pr-2">Tiêu Chí</th>
                                <th class="py-2.5 px-2 text-center">Trọng Số</th>
                                <th class="py-2.5 px-2 text-center">Tự Chấm</th>
                                <th class="py-2.5 px-2 text-center">Quản Lý</th>
                                <th class="py-2.5 px-2 text-center">Độ Lệch</th>
                                <th class="py-2.5 pl-2 text-right">Quy Đổi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach ($template_items as $it): ?>
                                <?php 
                                    $s_val = $self_map[$it['id']]['score'] ?? null;
                                    $m_val = $mgr_map[$it['id']]['score'] ?? null;
                                    $delta = ($s_val !== null && $m_val !== null) ? round($m_val - $s_val, 1) : null;
                                    $final_contrib = ($m_val !== null) ? round($m_val * $it['weight'] / 100, 2) : 0;
                                ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40">
                                    <td class="py-3 pr-2">
                                        <div class="font-bold text-slate-800 dark:text-slate-200"><?= e($it['criteria_name']) ?></div>
                                        <?php if (!empty($it['target_value'])): ?>
                                            <div class="text-[10px] text-slate-400">Mục tiêu: <?= e($it['target_value']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2 text-center font-mono font-bold text-slate-500">
                                        <?= round($it['weight'], 1) ?>%
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

                <!-- Khung Thang Điểm & Chuẩn Xếp Loại AURA HRM -->
                <div class="bg-slate-50 dark:bg-slate-900/80 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 space-y-2 text-xs">
                    <div class="font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                        <span>Khung Xếp Loại Hiệu Suất Chuẩn AURA:</span>
                        <span class="text-[11px] font-normal text-slate-400">Theo Quy chế Đánh giá & Đãi ngộ</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-[11px]">
                        <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900">
                            <b class="text-amber-700 dark:text-amber-400">≥ 95.0: Xuất Sắc (A+)</b>
                            <div class="text-[10px] text-slate-500">Thưởng lớn & Xét tăng lương</div>
                        </div>
                        <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900">
                            <b class="text-emerald-700 dark:text-emerald-400">85 - 94.9: Tốt (A)</b>
                            <div class="text-[10px] text-slate-500">Đạt thưởng KPI kỳ</div>
                        </div>
                        <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900">
                            <b class="text-indigo-700 dark:text-indigo-400">70 - 84.9: Khá (B)</b>
                            <div class="text-[10px] text-slate-500">Đạt định mức công việc</div>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                            <b class="text-slate-700 dark:text-slate-300">55 - 69.9: Trung Bình (C)</b>
                            <div class="text-[10px] text-slate-500">Cần cải thiện năng suất</div>
                        </div>
                        <div class="p-2 rounded-xl bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-900">
                            <b class="text-orange-700 dark:text-orange-400">40 - 54.9: Yếu (D)</b>
                            <div class="text-[10px] text-slate-500">Đào tạo lại & Cảnh báo</div>
                        </div>
                        <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900">
                            <b class="text-rose-700 dark:text-rose-400">< 40.0: Kém (E)</b>
                            <div class="text-[10px] text-slate-500">Xem xét chấm dứt HĐ</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>

<!-- JavaScript Logic: Tab Switching, Live Sync, Radar Chart, AJAX Submit -->
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

// Đồng bộ Slider -> Input Số
function syncSliderToInput(slider, inputId, type) {
    const input = document.getElementById(inputId);
    input.value = slider.value;
    calculateLiveScore(type);
}

// Đồng bộ Input Số -> Slider
function syncInputToSlider(input, sliderId, type) {
    const slider = document.getElementById(sliderId);
    let val = parseFloat(input.value) || 0;
    val = Math.max(0, Math.min(100, val));
    slider.value = val;
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

    TEMPLATE_ITEMS.forEach(it => {
        // Rút gọn label nếu dài quá
        const name = it.criteria_name.length > 25 ? it.criteria_name.substring(0, 25) + '...' : it.criteria_name;
        labels.push(name);

        const sInp = document.getElementById('num_self_' + it.id);
        const mInp = document.getElementById('num_mgr_' + it.id);

        selfData.push(sInp ? parseFloat(sInp.value) || 0 : (SELF_MAP[it.id] ? parseFloat(SELF_MAP[it.id].score) : 0));
        mgrData.push(mInp ? parseFloat(mInp.value) || 0 : (MGR_MAP[it.id] ? parseFloat(MGR_MAP[it.id].score) : 0));
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
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#6366f1',
                    borderWidth: 2
                },
                {
                    label: 'Quản lý chấm',
                    data: mgrData,
                    backgroundColor: 'rgba(139, 92, 246, 0.25)',
                    borderColor: '#8b5cf6',
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#8b5cf6',
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
            ? 'Bạn có chắc chắn muốn nộp kết quả tự đánh giá? Phiếu sẽ được chuyển tiếp cho Quản lý chấm điểm.'
            : 'Bạn có chắc chắn muốn phê duyệt và chốt kết quả đánh giá KPI này?';
        if (!confirm(confirmMsg)) return;
    }

    const scores = [];
    TEMPLATE_ITEMS.forEach(it => {
        const scoreInput = document.getElementById(reviewType === 'self' ? 'num_self_' + it.id : 'num_mgr_' + it.id);
        const commentInput = document.getElementById(reviewType === 'self' ? 'comment_self_' + it.id : 'comment_mgr_' + it.id);
        
        scores.push({
            item_id: it.id,
            score: parseFloat(scoreInput.value) || 0,
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
            alert(res.message);
            // Reload trang để cập nhật đầy đủ điểm và badges
            window.location.reload();
        } else {
            alert('Lỗi: ' + res.message);
        }
    } catch (e) {
        alert('Có lỗi mạng hoặc máy chủ không phản hồi: ' + e.message);
    }
}

// Khởi chạy khi tải trang
document.addEventListener('DOMContentLoaded', () => {
    calculateLiveScore('self');
    calculateLiveScore('manager');
    // Mặc định nếu đã chấm quản lý xong thì có thể xem trước kết quả
    <?php if ($eval['status'] === 'completed'): ?>
        switchTab('results');
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
