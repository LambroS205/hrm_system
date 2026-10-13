<?php
// modules/evaluations/periods.php - Quản lý Các Kỳ / Đợt Đánh Giá Hiệu Suất & KPI
$page_title = 'Quản Lý Các Đợt Đánh Giá KPI';

require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'view');

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$templates = $pdo->query("SELECT id, code, name, department_id FROM kpi_templates WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Xử lý POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. LƯU ĐỢT ĐÁNH GIÁ (TẠO MỚI HOẶC SỬA)
    if ($action_type === 'save_period') {
        require_permission('evaluations', 'create');
        $period_id = (int)($_POST['period_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['quarterly', 'annual', 'probation', 'project']) ? $_POST['type'] : 'quarterly';
        $year = (int)($_POST['year'] ?? date('Y'));
        $quarter = !empty($_POST['quarter']) ? (int)$_POST['quarter'] : null;
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $self_review_deadline = !empty($_POST['self_review_deadline']) ? $_POST['self_review_deadline'] : null;
        $manager_review_deadline = !empty($_POST['manager_review_deadline']) ? $_POST['manager_review_deadline'] : null;
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'active', 'closed']) ? $_POST['status'] : 'active';
        $description = trim($_POST['description'] ?? '');

        if (empty($code) || empty($name) || empty($start_date) || empty($end_date)) {
            set_flash('danger', 'Vui lòng điền đầy đủ các thông tin bắt buộc.');
            redirect('modules/evaluations/periods.php');
        }

        try {
            if ($period_id > 0) {
                // Kiểm tra trùng code
                $chk = $pdo->prepare("SELECT id FROM evaluation_periods WHERE code = ? AND id != ?");
                $chk->execute([$code, $period_id]);
                if ($chk->fetch()) {
                    throw new Exception("Mã kỳ đánh giá [{$code}] đã tồn tại.");
                }

                $stmt = $pdo->prepare("
                    UPDATE evaluation_periods
                    SET code = ?, name = ?, type = ?, year = ?, quarter = ?, start_date = ?, end_date = ?,
                        self_review_deadline = ?, manager_review_deadline = ?, status = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $type, $year, $quarter, $start_date, $end_date, $self_review_deadline, $manager_review_deadline, $status, $description, $period_id]);
                set_flash('success', "Đã cập nhật đợt đánh giá [{$code}] thành công!");
            } else {
                $chk = $pdo->prepare("SELECT id FROM evaluation_periods WHERE code = ?");
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    throw new Exception("Mã kỳ đánh giá [{$code}] đã tồn tại.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_periods (code, name, type, year, quarter, start_date, end_date, self_review_deadline, manager_review_deadline, status, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $type, $year, $quarter, $start_date, $end_date, $self_review_deadline, $manager_review_deadline, $status, $description]);
                set_flash('success', "Đã tạo đợt đánh giá mới [{$code}] thành công!");
            }
        } catch (Exception $e) {
            set_flash('danger', 'Lỗi: ' . $e->getMessage());
        }
        redirect('modules/evaluations/periods.php');
    }

    // 2. CHUYỂN ĐỔI TRẠNG THÁI (ĐÓNG / MỞ KỲ)
    if ($action_type === 'toggle_status') {
        require_permission('evaluations', 'edit');
        $period_id = (int)($_POST['period_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'active';

        if ($period_id > 0 && in_array($new_status, ['upcoming', 'active', 'closed'])) {
            $stmt = $pdo->prepare("UPDATE evaluation_periods SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $period_id]);
            set_flash('success', 'Đã chuyển trạng thái kỳ đánh giá thành công.');
        }
        redirect('modules/evaluations/periods.php');
    }

    // 3. TỰ ĐỘNG PHÂN BỔ PHIẾU ĐÁNH GIÁ HÀNG LOẠT (AUTO-GENERATE EVALUATIONS)
    if ($action_type === 'auto_generate') {
        require_permission('evaluations', 'create');
        $period_id = (int)($_POST['period_id'] ?? 0);
        $target_dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $override_template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $default_reviewer_id = (int)($_POST['reviewer_id'] ?? 1); // Mặc định CEO/Admin

        $period_stmt = $pdo->prepare("SELECT * FROM evaluation_periods WHERE id = ?");
        $period_stmt->execute([$period_id]);
        $period = $period_stmt->fetch();

        if (!$period) {
            set_flash('danger', 'Kỳ đánh giá không tồn tại.');
            redirect('modules/evaluations/periods.php');
        }

        // Lấy danh sách nhân viên cần phân bổ
        $emp_query = "
            SELECT e.id, e.employee_code, e.fullname, e.department_id, e.position_id
            FROM employees e
            WHERE e.employment_status != 'resigned'
        ";
        $emp_params = [];
        if ($target_dept_id > 0) {
            $emp_query .= " AND e.department_id = ?";
            $emp_params[] = $target_dept_id;
        }
        $emp_stmt = $pdo->prepare($emp_query);
        $emp_stmt->execute($emp_params);
        $employees_to_assign = $emp_stmt->fetchAll();

        // Chuẩn bị template mapping theo department
        $dept_template_map = [];
        $general_template_id = 0;
        foreach ($templates as $t) {
            if (!empty($t['department_id'])) {
                $dept_template_map[$t['department_id']] = $t['id'];
            } else if (!$general_template_id) {
                $general_template_id = $t['id'];
            }
        }
        if (!$general_template_id && !empty($templates)) {
            $general_template_id = $templates[0]['id'];
        }

        // Đếm mã phiếu hiện tại của kỳ
        $code_prefix = 'DG-' . $period['year'] . ($period['quarter'] ? '-Q' . $period['quarter'] : '');
        $last_code = $pdo->query("SELECT evaluation_code FROM evaluations WHERE evaluation_code LIKE '{$code_prefix}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $curr_seq = 0;
        if ($last_code && preg_match('/-(\d+)$/', $last_code, $matches)) {
            $curr_seq = (int)$matches[1];
        }

        $created_count = 0;
        $skipped_count = 0;

        $check_existing = $pdo->prepare("SELECT id FROM evaluations WHERE period_id = ? AND employee_id = ?");
        $insert_eval = $pdo->prepare("
            INSERT INTO evaluations (evaluation_code, period_id, employee_id, template_id, reviewer_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $pdo->beginTransaction();
        try {
            foreach ($employees_to_assign as $emp) {
                // Kiểm tra đã có phiếu trong kỳ này chưa
                $check_existing->execute([$period_id, $emp['id']]);
                if ($check_existing->fetch()) {
                    $skipped_count++;
                    continue;
                }

                // Xác định template
                $tpl_id = $override_template_id ?: ($dept_template_map[$emp['department_id']] ?? $general_template_id);
                if (!$tpl_id) continue;

                // Xác định reviewer: Người có user_id = 1 hoặc chính reviewer được chỉ định, tránh reviewer trùng employee
                $rev_id = ($default_reviewer_id == $emp['id']) ? 1 : $default_reviewer_id;
                if ($rev_id == $emp['id']) {
                    // Nếu là admin thì reviewer là chính mình hoặc ID 2
                    $rev_id = ($emp['id'] == 1) ? 2 : 1;
                }

                $curr_seq++;
                $eval_code = sprintf('%s-%03d', $code_prefix, $curr_seq);

                $insert_eval->execute([
                    $eval_code,
                    $period_id,
                    $emp['id'],
                    $tpl_id,
                    $rev_id
                ]);
                $created_count++;
            }

            $pdo->commit();
            set_flash('success', "Đã tạo thành công {$created_count} phiếu đánh giá mới (Bỏ qua {$skipped_count} nhân sự đã có phiếu).");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi khi phân bổ phiếu: ' . $e->getMessage());
        }

        redirect('modules/evaluations/periods.php');
    }

    // 4. XÓA ĐỢT ĐÁNH GIÁ
    if ($action_type === 'delete_period') {
        require_permission('evaluations', 'delete');
        $del_id = (int)($_POST['period_id'] ?? 0);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE period_id = ?");
        $cnt->execute([$del_id]);
        $has_evals = (int)$cnt->fetchColumn();

        if ($has_evals > 0) {
            set_flash('danger', "Không thể xóa kỳ này vì đã có {$has_evals} phiếu đánh giá liên quan.");
        } else {
            $pdo->prepare("DELETE FROM evaluation_periods WHERE id = ?")->execute([$del_id]);
            set_flash('success', 'Đã xóa kỳ đánh giá thành công.');
        }
        redirect('modules/evaluations/periods.php');
    }
}

// Lấy danh sách kỳ đánh giá kèm thống kê tiến độ
$periods = $pdo->query("
    SELECT p.*,
           COUNT(e.id) AS total_evaluations,
           COUNT(CASE WHEN e.status = 'completed' THEN 1 END) AS completed_evaluations,
           COUNT(CASE WHEN e.status = 'manager_review' THEN 1 END) AS manager_review_evaluations,
           COUNT(CASE WHEN e.status = 'self_review' THEN 1 END) AS self_review_evaluations,
           COUNT(CASE WHEN e.status = 'pending' THEN 1 END) AS pending_evaluations,
           AVG(CASE WHEN e.status = 'completed' THEN e.final_score END) AS avg_final_score
    FROM evaluation_periods p
    LEFT JOIN evaluations e ON p.id = e.period_id
    GROUP BY p.id
    ORDER BY p.year DESC, p.quarter DESC, p.id DESC
")->fetchAll();

// Lấy danh sách quản lý / reviewer tiềm năng (Positions Lead / Director / Admin)
$reviewers = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, p.name AS position_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-1">
                <a href="<?= base_url('modules/evaluations/index.php') ?>" class="hover:underline">Đánh Giá & KPI</a>
                <span>/</span>
                <span class="text-slate-500 dark:text-slate-400">Kỳ Đánh Giá Hiệu Suất</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-200 dark:shadow-none">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <span>Quản Lý Các Kỳ & Đợt Đánh Giá</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Lên lịch các đợt đánh giá định kỳ (Quý/Năm), thiết lập hạn chót và phân bổ phiếu KPI hàng loạt cho nhân sự.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= base_url('modules/evaluations/kpi_templates.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-layer-group text-indigo-500"></i>
                <span>Bộ Tiêu Chí KPI</span>
            </a>
            <a href="<?= base_url('modules/evaluations/index.php') ?>" 
               class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-list-check text-indigo-500"></i>
                <span>Danh Sách Đánh Giá</span>
            </a>
            <?php if (has_permission('evaluations', 'create')): ?>
                <button type="button" onclick="openPeriodModal()" 
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-sm text-xs transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tạo Đợt Đánh Giá Mới</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Danh Sách Các Đợt Đánh Giá Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php if (empty($periods)): ?>
            <div class="col-span-full bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-slate-200 dark:border-slate-700">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-500 flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800 dark:text-white">Chưa Có Đợt Đánh Giá Nào</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    Hãy khởi tạo đợt đánh giá đầu tiên (Ví dụ: Đánh Giá Hiệu Suất Quý 3/2026).
                </p>
                <?php if (has_permission('evaluations', 'create')): ?>
                    <button type="button" onclick="openPeriodModal()" class="mt-4 px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-xl text-xs hover:bg-indigo-700 transition">
                        <i class="fa-solid fa-plus mr-1.5"></i> Tạo Ngay
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($periods as $p): ?>
            <?php
                $pct_completed = $p['total_evaluations'] > 0 ? round(($p['completed_evaluations'] / $p['total_evaluations']) * 100) : 0;
                $today_time = strtotime(date('Y-m-d'));
                $self_dl_time = !empty($p['self_review_deadline']) ? strtotime($p['self_review_deadline']) : 0;
                $mgr_dl_time = !empty($p['manager_review_deadline']) ? strtotime($p['manager_review_deadline']) : 0;
                $is_self_overdue = ($self_dl_time > 0 && $today_time > $self_dl_time && $p['status'] === 'active');
                $is_mgr_overdue = ($mgr_dl_time > 0 && $today_time > $mgr_dl_time && $p['status'] === 'active');
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div class="p-6 space-y-5">
                    
                    <!-- Top header: Code + Status Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-bold text-xs border border-indigo-200 dark:border-indigo-800">
                                    <?= e($p['code']) ?>
                                </span>
                                <?php if ($p['status'] === 'active'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 animate-pulse">
                                        <i class="fa-solid fa-circle text-[6px]"></i> Đang Diễn Ra
                                    </span>
                                <?php elseif ($p['status'] === 'upcoming'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">
                                        <i class="fa-solid fa-clock text-[9px]"></i> Sắp Diễn Ra
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        <i class="fa-solid fa-lock text-[9px]"></i> Đã Đóng Kỳ
                                    </span>
                                <?php endif; ?>

                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                    <?= $p['type'] === 'quarterly' ? "Quý {$p['quarter']}/{$p['year']}" : ($p['type'] === 'annual' ? "Năm {$p['year']}" : 'Chuyên đề') ?>
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-2 leading-snug">
                                <?= e($p['name']) ?>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                                <?= e($p['description'] ?: 'Không có mô tả chi tiết.') ?>
                            </p>
                        </div>

                        <?php if ($p['avg_final_score']): ?>
                            <div class="text-right flex-shrink-0 bg-indigo-50 dark:bg-indigo-950/50 p-3 rounded-2xl border border-indigo-100 dark:border-indigo-900 text-center min-w-[70px]">
                                <span class="text-[10px] uppercase font-bold text-indigo-500 block">Điểm TB</span>
                                <span class="text-lg font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                                    <?= round($p['avg_final_score'], 1) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline Deadlines Box -->
                    <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span class="flex items-center gap-2">
                                <i class="fa-regular fa-calendar text-indigo-500"></i> Khoảng thời gian:
                            </span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">
                                <?= format_date($p['start_date']) ?> — <?= format_date($p['end_date']) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-user-pen text-sky-500"></i> Hạn NV tự đánh giá:
                            </span>
                            <span class="font-medium <?= $is_self_overdue ? 'text-rose-600 font-bold' : 'text-slate-800 dark:text-slate-200' ?>">
                                <?= format_date($p['self_review_deadline']) ?>
                                <?php if ($is_self_overdue): ?>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 ml-1">Đã hết hạn</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-user-tie text-violet-500"></i> Hạn Quản lý chấm:
                            </span>
                            <span class="font-medium <?= $is_mgr_overdue ? 'text-rose-600 font-bold' : 'text-slate-800 dark:text-slate-200' ?>">
                                <?= format_date($p['manager_review_deadline']) ?>
                                <?php if ($is_mgr_overdue): ?>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 ml-1">Đã hết hạn</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Progress bar & Counts -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="text-slate-600 dark:text-slate-400">
                                Tiến độ hoàn thành: <strong class="text-indigo-600 dark:text-indigo-400"><?= $p['completed_evaluations'] ?>/<?= $p['total_evaluations'] ?></strong> phiếu
                            </span>
                            <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold"><?= $pct_completed ?>%</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                            <div class="bg-emerald-600 h-full transition-all duration-500" style="width: <?= $pct_completed ?>%"></div>
                        </div>

                        <!-- Mini Badges for statuses -->
                        <div class="flex items-center gap-2 pt-1 text-[11px] flex-wrap">
                            <span class="text-slate-500">Chờ thực hiện: <b class="text-slate-700 dark:text-slate-300"><?= $p['pending_evaluations'] ?></b></span>
                            <span>•</span>
                            <span class="text-sky-600">NV đang tự chấm: <b><?= $p['self_review_evaluations'] ?></b></span>
                            <span>•</span>
                            <span class="text-amber-600">Quản lý đang chấm: <b><?= $p['manager_review_evaluations'] ?></b></span>
                        </div>
                    </div>

                </div>

                <!-- Footer Card Actions -->
                <div class="px-6 py-3.5 bg-slate-50/80 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between flex-wrap gap-2">
                    
                    <a href="<?= base_url('modules/evaluations/index.php?period_id=' . $p['id']) ?>" 
                       class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-right"></i> Xem <?= $p['total_evaluations'] ?> Phiếu Đánh Giá
                    </a>

                    <div class="flex items-center gap-2">
                        <?php if (has_permission('evaluations', 'create') && $p['status'] !== 'closed'): ?>
                            <!-- Nút Phân bổ hàng loạt -->
                            <button type="button" onclick="openAutoGenerateModal(<?= $p['id'] ?>, '<?= e($p['name']) ?>')"
                                    class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5" title="Phân bổ phiếu KPI tự động cho nhân viên">
                                <i class="fa-solid fa-users-gear"></i>
                                <span class="hidden sm:inline">Phân Bổ Phiếu</span>
                            </button>
                        <?php endif; ?>

                        <?php if (has_permission('evaluations', 'edit')): ?>
                            <!-- Nút Toggle Active/Closed -->
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action_type" value="toggle_status">
                                <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $p['status'] === 'active' ? 'closed' : 'active' ?>">
                                <button type="submit" class="p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-white dark:hover:bg-slate-700 text-xs transition" title="<?= $p['status'] === 'active' ? 'Đóng kỳ đánh giá' : 'Mở lại kỳ đánh giá' ?>">
                                    <i class="fa-solid <?= $p['status'] === 'active' ? 'fa-lock text-amber-500' : 'fa-lock-open text-emerald-500' ?>"></i>
                                </button>
                            </form>

                            <!-- Nút Sửa -->
                            <button type="button" onclick='editPeriod(<?= json_encode($p) ?>)'
                                    class="p-2 rounded-xl text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950 text-xs transition" title="Chỉnh sửa kỳ đánh giá">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        <?php endif; ?>

                        <?php if (has_permission('evaluations', 'delete') && $p['total_evaluations'] == 0): ?>
                            <!-- Nút Xóa -->
                            <form method="POST"
                                  data-confirm="Bạn có chắc muốn xóa đợt đánh giá [<?= e($p['code']) ?>]?"
                                  data-confirm-title="Xác Nhận Xóa Đợt Đánh Giá"
                                  data-confirm-danger="true">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action_type" value="delete_period">
                                <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="p-2 rounded-xl text-rose-500 hover:text-rose-700 hover:bg-rose-50 text-xs transition" title="Xóa kỳ đánh giá">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: TẠO / SỬA KỲ ĐÁNH GIÁ (PERIOD MODAL)               -->
<!-- ======================================================== -->
<div id="periodModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-2xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>
                <div>
                    <h3 id="periodModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Khởi Tạo Đợt Đánh Giá Mới</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Lập kế hoạch và thiết lập thời hạn đánh giá hiệu suất</p>
                </div>
            </div>
            <button type="button" onclick="closePeriodModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" id="periodForm" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_period">
            <input type="hidden" name="period_id" id="p_form_id" value="0">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mã Kỳ Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="code" id="p_form_code" required placeholder="VD: PERIOD-2026-Q4"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white uppercase focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Loại Kỳ Đánh Giá
                    </label>
                    <select name="type" id="p_form_type" onchange="toggleQuarterSelect()"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <option value="quarterly">Theo Quý (Quarterly)</option>
                        <option value="annual">Theo Năm (Annual)</option>
                        <option value="probation">Đánh giá Thử việc (Probation)</option>
                        <option value="project">Theo Dự án / Đột xuất</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Tên Đợt Đánh Giá <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="p_form_name" required placeholder="VD: Đánh Giá Hiệu Suất & KPI Quý 4/2026"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Năm Đánh Giá
                    </label>
                    <input type="number" name="year" id="p_form_year" value="<?= date('Y') ?>" required min="2020" max="2050"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-800 dark:text-white">
                </div>

                <div id="quarterWrap">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Quý
                    </label>
                    <select name="quarter" id="p_form_quarter"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="1">Quý 1</option>
                        <option value="2">Quý 2</option>
                        <option value="3">Quý 3</option>
                        <option value="4" selected>Quý 4</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Ngày Bắt Đầu <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="start_date" id="p_form_start" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Ngày Kết Thúc <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="end_date" id="p_form_end" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Hạn Chót Nhân Viên Tự Chấm
                    </label>
                    <input type="date" name="self_review_deadline" id="p_form_self_deadline"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Hạn Chót Quản Lý Hoàn Tất Chấm
                    </label>
                    <input type="date" name="manager_review_deadline" id="p_form_mgr_deadline"
                           class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Trạng Thái
                    </label>
                    <select name="status" id="p_form_status"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="active">Đang diễn ra (Active)</option>
                        <option value="upcoming">Sắp diễn ra (Upcoming)</option>
                        <option value="closed">Đã đóng kỳ (Closed)</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Mô Tả / Hướng Dẫn Kèm Theo
                    </label>
                    <textarea name="description" id="p_form_desc" rows="3" placeholder="Mục đích kỳ đánh giá, hướng dẫn nhân viên..."
                              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closePeriodModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu Đợt Đánh Giá</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: PHÂN BỔ PHIẾU ĐÁNH GIÁ TỰ ĐỘNG (AUTO GENERATE)     -->
<!-- ======================================================== -->
<div id="autoGenerateModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Phân Bổ Phiếu Tự Động</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400" id="ag_period_name_text"></p>
                </div>
            </div>
            <button type="button" onclick="closeAutoGenerateModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="auto_generate">
            <input type="hidden" name="period_id" id="ag_period_id" value="0">

            <div class="p-3.5 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-2xl border border-indigo-100 dark:border-indigo-900 text-xs text-indigo-800 dark:text-indigo-300 leading-relaxed">
                <i class="fa-solid fa-circle-info mr-1.5 text-indigo-500"></i>
                Hệ thống sẽ tự động quét danh sách nhân viên đang làm việc, tạo phiếu đánh giá với mã phiếu chuẩn hóa và gán bộ tiêu chí KPI tương ứng theo từng phòng ban.
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Phạm Vi Nhân Sự Nhận Phiếu
                </label>
                <select name="department_id" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="0">-- Tất Cả Nhân Viên (Toàn Tập Đoàn) --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>">Chỉ khối: <?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Ghi Đè Bộ Tiêu Chí (Tùy Chọn)
                </label>
                <select name="template_id" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="0">Tự động chọn theo phòng ban của nhân viên (Khuyên dùng)</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Quản Lý / Người Chấm Mặc Định
                </label>
                <select name="reviewer_id" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($reviewers as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['id'] == 1 ? 'selected' : '' ?>>
                            <?= e($r['fullname']) ?> (<?= e($r['position_name'] ?: 'Quản lý') ?> - <?= e($r['employee_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeAutoGenerateModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                    Đóng
                </button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-play"></i>
                    <span>Bắt Đầu Khởi Tạo</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPeriodModal() {
    document.getElementById('p_form_id').value = '0';
    document.getElementById('periodModalTitle').textContent = 'Khởi Tạo Đợt Đánh Giá Mới';
    document.getElementById('p_form_code').value = 'PERIOD-' + new Date().getFullYear() + '-Q' + Math.ceil((new Date().getMonth() + 1) / 3);
    document.getElementById('p_form_type').value = 'quarterly';
    document.getElementById('p_form_name').value = '';
    document.getElementById('p_form_year').value = new Date().getFullYear();
    document.getElementById('p_form_quarter').value = Math.ceil((new Date().getMonth() + 1) / 3);
    document.getElementById('p_form_start').value = '';
    document.getElementById('p_form_end').value = '';
    document.getElementById('p_form_self_deadline').value = '';
    document.getElementById('p_form_mgr_deadline').value = '';
    document.getElementById('p_form_status').value = 'active';
    document.getElementById('p_form_desc').value = '';
    toggleQuarterSelect();
    document.getElementById('periodModal').classList.remove('hidden');
}

function closePeriodModal() {
    document.getElementById('periodModal').classList.add('hidden');
}

function editPeriod(period) {
    document.getElementById('p_form_id').value = period.id;
    document.getElementById('periodModalTitle').textContent = 'Chỉnh Sửa Đợt Đánh Giá: ' + period.code;
    document.getElementById('p_form_code').value = period.code;
    document.getElementById('p_form_type').value = period.type;
    document.getElementById('p_form_name').value = period.name;
    document.getElementById('p_form_year').value = period.year;
    document.getElementById('p_form_quarter').value = period.quarter || 1;
    document.getElementById('p_form_start').value = period.start_date;
    document.getElementById('p_form_end').value = period.end_date;
    document.getElementById('p_form_self_deadline').value = period.self_review_deadline || '';
    document.getElementById('p_form_mgr_deadline').value = period.manager_review_deadline || '';
    document.getElementById('p_form_status').value = period.status;
    document.getElementById('p_form_desc').value = period.description || '';
    toggleQuarterSelect();
    document.getElementById('periodModal').classList.remove('hidden');
}

function toggleQuarterSelect() {
    const type = document.getElementById('p_form_type').value;
    const wrap = document.getElementById('quarterWrap');
    wrap.style.display = (type === 'quarterly') ? 'block' : 'none';
}

function openAutoGenerateModal(periodId, periodName) {
    document.getElementById('ag_period_id').value = periodId;
    document.getElementById('ag_period_name_text').textContent = 'Đợt: ' + periodName;
    document.getElementById('autoGenerateModal').classList.remove('hidden');
}

function closeAutoGenerateModal() {
    document.getElementById('autoGenerateModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
