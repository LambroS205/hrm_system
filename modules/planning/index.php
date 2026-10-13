<?php
// modules/planning/index.php - Trung Tâm Quy Hoạch Nhân Sự & Đề Xuất Tối Ưu Hóa Toàn Hệ Thống
$page_title = 'Kế Hoạch & Đề Xuất Tối Ưu Nhân Lực';

require_once __DIR__ . '/../../core/auth.php';
require_permission('planning', 'view');
require_once __DIR__ . '/planning_engine.php';

$user = current_user();
$active_tab = $_GET['tab'] ?? 'analytics';
if (!in_array($active_tab, ['analytics', 'simulation', 'requests', 'plans'])) {
    $active_tab = 'analytics';
}

// =========================================================================
// XỬ LÝ CÁC THAO TÁC POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    // 1. Xóa Kế Hoạch
    if ($action_type === 'delete_plan') {
        require_permission('planning', 'delete');
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $del = $pdo->prepare("DELETE FROM transfer_plans WHERE id = ?");
            $del->execute([$plan_id]);
            set_flash('success', 'Đã xóa kế hoạch quy hoạch nhân sự.');
            redirect('modules/planning/index.php?tab=plans');
        }
    }

    // 2. Áp dụng Kịch bản tối ưu hóa vào Kế hoạch (1-Click Apply)
    if ($action_type === 'apply_scenario') {
        require_permission('planning', 'create');
        $scenario_id = (int)($_POST['scenario_id'] ?? 0);
        $apply_mode = $_POST['apply_mode'] ?? 'new';
        $existing_plan_id = !empty($_POST['existing_plan_id']) ? (int)$_POST['existing_plan_id'] : null;
        $custom_title = trim($_POST['custom_title'] ?? '');

        // Lấy lại danh sách kịch bản để tìm kịch bản được chọn
        $branch_analytics = get_branch_workforce_analytics($pdo);
        $scenarios = generate_smart_scenarios($pdo, $branch_analytics);
        $selected_scenario = null;
        foreach ($scenarios as $sc) {
            if ($sc['id'] == $scenario_id) {
                $selected_scenario = $sc;
                break;
            }
        }

        if ($selected_scenario) {
            $result_plan_id = apply_scenario_to_plan($pdo, $selected_scenario, $apply_mode, $existing_plan_id, $custom_title, $user['id']);
            if ($result_plan_id) {
                set_flash('success', "Đã áp dụng thành công kịch bản [{$selected_scenario['tag']}] vào Kế hoạch quy hoạch!");
                redirect('modules/planning/view.php?id=' . $result_plan_id);
            } else {
                set_flash('danger', 'Không thể khởi tạo hoặc liên kết kế hoạch.');
            }
        } else {
            set_flash('warning', 'Không tìm thấy dữ liệu kịch bản hợp lệ.');
        }
        redirect('modules/planning/index.php?tab=simulation');
    }

    // 3. Thẩm định / Phê duyệt đề xuất từ chi nhánh
    if ($action_type === 'review_request') {
        require_permission('planning', 'approve');
        $request_id = (int)($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? 'approved';
        $review_notes = trim($_POST['review_notes'] ?? '');

        if ($request_id > 0 && in_array($decision, ['approved', 'rejected'])) {
            $upReq = $pdo->prepare("
                UPDATE branch_workforce_requests 
                SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                WHERE id = ?
            ");
            $upReq->execute([$decision, $user['id'], $review_notes, $request_id]);
            $label = ($decision === 'approved') ? 'phê duyệt' : 'từ chối';
            set_flash('success', "Đã {$label} đề xuất nhu cầu nhân lực từ chi nhánh.");
            redirect('modules/planning/index.php?tab=requests');
        }
    }

    // 4. Tích hợp đề xuất từ chi nhánh vào Kế hoạch quy hoạch tổng thể
    if ($action_type === 'incorporate_request') {
        require_permission('planning', 'create');
        $request_id = (int)($_POST['request_id'] ?? 0);
        $target_plan_id = (int)($_POST['target_plan_id'] ?? 0);
        $allowance = (float)str_replace(['.', ','], '', $_POST['allowance'] ?? '0');

        if ($request_id > 0 && $target_plan_id > 0) {
            // Lấy thông tin request
            $rstmt = $pdo->prepare("SELECT * FROM branch_workforce_requests WHERE id = ?");
            $rstmt->execute([$request_id]);
            $req = $rstmt->fetch();

            if ($req) {
                // Nếu có candidate_id, nạp trực tiếp vào transfer_plan_items
                if (!empty($req['candidate_id'])) {
                    $cand_stmt = $pdo->prepare("SELECT branch_id, department_id, position_id FROM employees WHERE id = ?");
                    $cand_stmt->execute([$req['candidate_id']]);
                    $cand_info = $cand_stmt->fetch();

                    $insItem = $pdo->prepare("
                        INSERT INTO transfer_plan_items (
                            plan_id, employee_id, from_branch_id, to_branch_id,
                            from_department_id, to_department_id, from_position_id, to_position_id,
                            priority, rationale, estimated_allowance
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insItem->execute([
                        $target_plan_id,
                        $req['candidate_id'],
                        $cand_info['branch_id'] ?? 1,
                        $req['branch_id'],
                        $cand_info['department_id'] ?? null,
                        $req['department_id'] ?? ($cand_info['department_id'] ?? 1),
                        $cand_info['position_id'] ?? null,
                        $req['position_id'] ?? ($cand_info['position_id'] ?? 1),
                        ($req['urgency'] === 'urgent') ? 'high' : 'medium',
                        "Tích hợp từ Đề xuất chi nhánh [{$req['request_code']}]: " . $req['title'],
                        $allowance > 0 ? $allowance : $req['estimated_cost']
                    ]);
                }

                // Cập nhật trạng thái đề xuất thành 'incorporated'
                $pdo->prepare("
                    UPDATE branch_workforce_requests 
                    SET status = 'incorporated', linked_plan_id = ?, reviewed_by = ?, reviewed_at = NOW(),
                        review_notes = CONCAT(COALESCE(review_notes, ''), ' [Đã tích hợp vào Kế hoạch #', ?)
                    WHERE id = ?
                ")->execute([$target_plan_id, $user['id'], $target_plan_id, $request_id]);

                set_flash('success', "Đã tích hợp thành công đề xuất {$req['request_code']} vào Kế hoạch quy hoạch!");
                redirect('modules/planning/view.php?id=' . $target_plan_id);
            }
        }
    }

    // 5. Xóa đề xuất
    if ($action_type === 'delete_request') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        if ($req_id > 0) {
            $pdo->prepare("DELETE FROM branch_workforce_requests WHERE id = ? AND status IN ('pending', 'rejected')")->execute([$req_id]);
            set_flash('success', 'Đã xóa đề xuất.');
            redirect('modules/planning/index.php?tab=requests');
        }
    }
}

// =========================================================================
// TRUY VẤN DỮ LIỆU HIỂN THỊ
// =========================================================================

// 1. Dữ liệu phân tích định biên chi nhánh & phòng ban
$branch_analytics = get_branch_workforce_analytics($pdo);
$dept_analytics = get_department_workforce_analytics($pdo);

// Tổng hợp KPIs hệ thống
$total_current_headcount = 0;
$total_target_headcount = 0;
$total_monthly_payroll = 0;
$deficit_branches_count = 0;

foreach ($branch_analytics as $ba) {
    $total_current_headcount += $ba['current_headcount'];
    $total_target_headcount += $ba['target_headcount'];
    $total_monthly_payroll += $ba['monthly_payroll'];
    if ($ba['shortage_count'] > 0) {
        $deficit_branches_count++;
    }
}
$overall_fulfillment_rate = $total_target_headcount > 0 ? round(($total_current_headcount / $total_target_headcount) * 100) : 0;

// 2. Chạy thuật toán Mô Phỏng 4 Kịch Bản Thông Minh
$scenarios = generate_smart_scenarios($pdo, $branch_analytics);

// 3. Danh sách Đề xuất từ Chi nhánh
$req_status_filter = $_GET['req_status'] ?? '';
$req_where = "";
$req_params = [];
if (!empty($req_status_filter)) {
    $req_where = "WHERE r.status = ?";
    $req_params = [$req_status_filter];
}

$branch_requests_stmt = $pdo->prepare("
    SELECT 
        r.*,
        b.name AS branch_name, b.code AS branch_code,
        d.name AS dept_name,
        p.name AS pos_name,
        e.fullname AS candidate_name, e.employee_code AS candidate_code,
        u.fullname AS creator_name,
        rev.fullname AS reviewer_name,
        tp.title AS linked_plan_title
    FROM branch_workforce_requests r
    JOIN branches b ON r.branch_id = b.id
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN positions p ON r.position_id = p.id
    LEFT JOIN employees e ON r.candidate_id = e.id
    LEFT JOIN users u ON r.created_by = u.id
    LEFT JOIN users rev ON r.reviewed_by = rev.id
    LEFT JOIN transfer_plans tp ON r.linked_plan_id = tp.id
    {$req_where}
    ORDER BY FIELD(r.urgency, 'urgent', 'high', 'normal'), r.created_at DESC
");
$branch_requests_stmt->execute($req_params);
$branch_requests = $branch_requests_stmt->fetchAll();

$pending_requests_count = (int)($pdo->query("SELECT COUNT(*) FROM branch_workforce_requests WHERE status = 'pending'")->fetchColumn() ?: 0);

// 4. Danh sách Kế Hoạch Quy Hoạch (Transfer Plans)
$plans = $pdo->query("
    SELECT 
        p.*,
        tb.name AS target_branch_name,
        tb.code AS target_branch_code,
        u.fullname AS creator_name,
        COUNT(tpi.id) AS total_candidates,
        COUNT(CASE WHEN tpi.transfer_id IS NOT NULL THEN 1 END) AS executed_candidates,
        COALESCE(SUM(tpi.estimated_allowance), 0) AS total_estimated_cost
    FROM transfer_plans p
    LEFT JOIN branches tb ON p.target_branch_id = tb.id
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN transfer_plan_items tpi ON p.id = tpi.plan_id
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll();

$active_plans_count = count(array_filter($plans, fn($p) => $p['status'] === 'active'));

// Dữ liệu cho các biểu đồ Chart.js
$chart_branch_labels = [];
$chart_branch_current = [];
$chart_branch_target = [];
foreach ($branch_analytics as $ba) {
    $chart_branch_labels[] = $ba['code'];
    $chart_branch_current[] = (int)$ba['current_headcount'];
    $chart_branch_target[] = (int)$ba['target_headcount'];
}

$chart_dept_labels = [];
$chart_dept_headcounts = [];
foreach ($dept_analytics as $da) {
    if ($da['current_headcount'] > 0) {
        $chart_dept_labels[] = $da['name'];
        $chart_dept_headcounts[] = (int)$da['current_headcount'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Thư viện Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Công Cụ Nhanh -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-sm text-xs font-semibold bg-indigo-50 dark:bg-slate-800 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 mb-2">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i> Trung Tâm Phân Tích & Điều Phối Nhân Lực Chiến Lược
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white tracking-tight">Kế Hoạch & Đề Xuất Tối Ưu Nhân Lực</h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Định biên thời gian thực, mô phỏng đa kịch bản What-If, tiếp nhận đề xuất từ chi nhánh và phê duyệt chiến dịch điều động toàn tập đoàn.
            </p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-sitemap text-indigo-600 dark:text-indigo-400"></i>
                <span>Sơ Đồ Cây</span>
            </a>
            <a href="<?= base_url('modules/planning/request_form.php') ?>" 
               class="px-3.5 py-2.5 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 text-xs font-semibold rounded-xl border border-amber-200 dark:border-amber-800/50 transition flex items-center gap-1.5">
                <i class="fa-solid fa-paper-plane text-amber-600 dark:text-amber-400"></i>
                <span>Gửi Đề Xuất Nhu Cầu</span>
            </a>
            <?php if (has_permission('planning', 'create')): ?>
                <a href="<?= base_url('modules/planning/form.php') ?>" 
                   class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tạo Kế Hoạch Mới</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hệ Thống Thẻ KPI Tóm Tắt (Summary Cards) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
        <!-- 1. Tổng Định Biên -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Tổng Định Biên</span>
            <div class="text-xl font-bold text-slate-800 dark:text-white mt-1 font-mono"><?= number_format($total_target_headcount) ?></div>
            <span class="text-[10px] text-slate-400 mt-1 block"><?= count($branch_analytics) ?> chi nhánh</span>
        </div>

        <!-- 2. Nhân Lực Hiện Có -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Nhân Sự Thực Tế</span>
            <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 font-mono"><?= number_format($total_current_headcount) ?></div>
            <span class="text-[10px] text-slate-400 mt-1 block">Đang hoạt động</span>
        </div>

        <!-- 3. Tỷ Lệ Lấp Đầy -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Tỷ Lệ Định Biên</span>
            <div class="text-xl font-bold <?= $overall_fulfillment_rate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?> mt-1 font-mono">
                <?= $overall_fulfillment_rate ?>%
            </div>
            <span class="text-[10px] text-slate-400 mt-1 block">Toàn tập đoàn</span>
        </div>

        <!-- 4. Chi Nhánh Thiếu Hụt -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Đơn Vị Cần Bù</span>
            <div class="text-xl font-bold <?= $deficit_branches_count > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600' ?> mt-1 font-mono">
                <?= $deficit_branches_count ?> Chi Nhánh
            </div>
            <span class="text-[10px] text-rose-500 mt-1 block font-medium">Cần điều phối</span>
        </div>

        <!-- 5. Quỹ Lương Hàng Tháng -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Quỹ Lương Cơ Bản</span>
            <div class="text-lg font-bold text-slate-800 dark:text-white mt-1 font-mono truncate" title="<?= format_money($total_monthly_payroll) ?>">
                <?= format_money($total_monthly_payroll) ?>
            </div>
            <span class="text-[10px] text-slate-400 mt-1 block">Ước tính/tháng</span>
        </div>

        <!-- 6. Đề Xuất Đang Chờ Duyệt -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Đề Xuất Chờ Duyệt</span>
            <div class="text-xl font-bold text-amber-500 mt-1 font-mono">
                <?= $pending_requests_count ?> Đơn
            </div>
            <a href="index.php?tab=requests&req_status=pending" class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline mt-1 block">
                Xem ngay &rarr;
            </a>
        </div>
    </div>

    <!-- Thanh Điều Hướng 4 Tabs Hiện Đại -->
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
        <a href="index.php?tab=analytics" 
           class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $active_tab === 'analytics' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>1. Ma Trận Định Biên & Quỹ Lương</span>
        </a>

        <a href="index.php?tab=simulation" 
           class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $active_tab === 'simulation' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' ?>">
            <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
            <span>2. Mô Phỏng Kịch Bản Tối Ưu (What-If)</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-400 text-slate-950 font-black">4</span>
        </a>

        <a href="index.php?tab=requests" 
           class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $active_tab === 'requests' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' ?>">
            <i class="fa-solid fa-paper-plane"></i>
            <span>3. Đề Xuất Nhân Lực Chi Nhánh</span>
            <?php if ($pending_requests_count > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-500 text-white font-bold animate-pulse"><?= $pending_requests_count ?></span>
            <?php endif; ?>
        </a>

        <a href="index.php?tab=plans" 
           class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $active_tab === 'plans' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' ?>">
            <i class="fa-solid fa-folder-tree"></i>
            <span>4. Kế Hoạch Quy Hoạch Master</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold"><?= count($plans) ?></span>
        </a>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 1: MA TRẬN ĐỊNH BIÊN & PHÂN TÍCH QUỸ LƯƠNG ĐA TẦNG                 -->
    <!-- ===================================================================== -->
    <?php if ($active_tab === 'analytics'): ?>
        
        <!-- Khối Biểu Đồ Trực Quan Hóa -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Biểu đồ cột: Định biên mục tiêu vs Thực tế từng Chi nhánh -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                            <i class="fa-solid fa-chart-column text-indigo-600"></i>
                            <span>Cân Đối Định Biên Mục Tiêu vs Thực Tế Từng Chi Nhánh</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">So sánh độ lệch nhân sự thực tế với hạn mức quy hoạch toàn hệ thống</p>
                    </div>
                </div>
                <div class="h-64 sm:h-72">
                    <canvas id="branchHeadcountChart"></canvas>
                </div>
            </div>

            <!-- Biểu đồ Donut: Phân bổ nhân sự theo Khối Phòng Ban -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-emerald-600"></i>
                        <span>Cơ Cấu Nhân Lực Theo Phòng Ban</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Tỷ trọng nhân sự phân bổ chức năng chuyên môn</p>
                </div>
                <div class="h-60 sm:h-64 relative flex items-center justify-center">
                    <canvas id="deptDistributionChart"></canvas>
                </div>
                <div class="text-[11px] text-center text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                    Dữ liệu thời gian thực cập nhật từ bảng hồ sơ nhân viên
                </div>
            </div>

        </div>

        <!-- Bảng Phân Tích Ma Trận Chi Nhánh & Phòng Ban Drill-down -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-table-cells-large text-indigo-600"></i>
                        <span>Ma Trận Định Biên Chi Tiết Cấp Chi Nhánh & Đơn Vị</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Click vào chi nhánh để mở rộng xem định biên từng phòng ban trực thuộc</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Mức độ hoàn thành:</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">&lt; 60% Báo Động</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">60 - 85% Cảnh Báo</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">&ge; 85% Đạt Chuẩn</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                            <th class="py-3 px-4">Mã & Tên Chi Nhánh</th>
                            <th class="py-3 px-4 text-center">Thực Tế / Định Biên</th>
                            <th class="py-3 px-4 text-center">Độ Lệch</th>
                            <th class="py-3 px-4 text-center">Tỷ Lệ Đạt</th>
                            <th class="py-3 px-4 text-right">Quỹ Lương Tháng</th>
                            <th class="py-3 px-4 text-right">Lương Bình Quân</th>
                            <th class="py-3 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($branch_analytics as $ba): ?>
                            <?php 
                                $is_critical = $ba['fulfillment_rate'] < 60;
                                $is_healthy = $ba['fulfillment_rate'] >= 85;
                            ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="toggleBranchDepts(<?= $ba['id'] ?>)" class="text-slate-400 hover:text-indigo-600 transition">
                                            <i id="chevron-branch-<?= $ba['id'] ?>" class="fa-solid fa-chevron-right text-[10px]"></i>
                                        </button>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                                                <span><?= e($ba['name']) ?></span>
                                                <?php if ($ba['is_headquarter']): ?>
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">HQ</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="font-mono text-[10px] text-slate-400"><?= e($ba['code']) ?> • Trưởng đơn vị: <?= e($ba['manager_name'] ?? 'Chưa phân công') ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                    <?= $ba['current_headcount'] ?> / <?= $ba['target_headcount'] ?> NV
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($ba['shortage_count'] > 0): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                            <i class="fa-solid fa-arrow-down mr-0.5"></i> Thiếu <?= $ba['shortage_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                            Đủ định biên
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-1.5 rounded-full <?= $is_critical ? 'bg-rose-500' : ($is_healthy ? 'bg-emerald-500' : 'bg-amber-500') ?>" 
                                                 style="width: <?= min(100, $ba['fulfillment_rate']) ?>%"></div>
                                        </div>
                                        <span class="font-mono font-bold text-[11px] <?= $is_critical ? 'text-rose-600' : ($is_healthy ? 'text-emerald-600' : 'text-amber-600') ?>">
                                            <?= $ba['fulfillment_rate'] ?>%
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    <?= format_money($ba['monthly_payroll']) ?>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono text-slate-500 dark:text-slate-400">
                                    <?= format_money($ba['avg_salary']) ?>
                                </td>

                                <td class="py-3.5 px-4 text-center space-x-1">
                                    <a href="request_form.php?branch_id=<?= $ba['id'] ?>" 
                                       class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-[11px] font-semibold rounded-lg transition"
                                       title="Gửi đề xuất điều động cho chi nhánh này">
                                        Đề Xuất
                                    </a>
                                    <a href="<?= base_url('modules/transfers/create.php?to_branch_id=' . $ba['id']) ?>" 
                                       class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold rounded-lg transition"
                                       title="Điều động nhân sự đến chi nhánh này">
                                        Điều Động &rarr;
                                    </a>
                                </td>
                            </tr>

                            <!-- Dòng con chi tiết phòng ban (Ẩn mặc định, toggle khi bấm) -->
                            <tr id="branch-depts-<?= $ba['id'] ?>" class="hidden bg-slate-50/50 dark:bg-slate-800/20">
                                <td colspan="7" class="p-3 pl-10">
                                    <div class="border-l-2 border-indigo-300 dark:border-indigo-700 pl-4 space-y-2">
                                        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                                            Các phòng ban trực thuộc <?= e($ba['name']) ?>:
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <?php 
                                                $branch_depts = array_filter($dept_analytics, fn($d) => $d['branch_id'] == $ba['id']);
                                            ?>
                                            <?php if (empty($branch_depts)): ?>
                                                <div class="text-xs text-slate-400 italic">Chưa có phòng ban riêng tại chi nhánh này.</div>
                                            <?php endif; ?>
                                            <?php foreach ($branch_depts as $bd): ?>
                                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <strong class="text-slate-800 dark:text-white"><?= e($bd['name']) ?></strong>
                                                        <span class="font-mono text-[10px] text-slate-400"><?= e($bd['code']) ?></span>
                                                    </div>
                                                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                                                        <span>Thực tế / Định biên:</span>
                                                        <strong class="font-mono text-slate-700 dark:text-slate-300"><?= $bd['current_headcount'] ?> / <?= $bd['target_headcount'] ?></strong>
                                                    </div>
                                                    <div class="flex items-center justify-between text-[10px]">
                                                        <span>Tỷ lệ: <strong class="<?= $bd['fulfillment_rate'] < 60 ? 'text-rose-500' : 'text-emerald-500' ?>"><?= $bd['fulfillment_rate'] ?>%</strong></span>
                                                        <?php if ($bd['shortage_count'] > 0): ?>
                                                            <span class="text-rose-600 font-bold">Thiếu <?= $bd['shortage_count'] ?> NV</span>
                                                        <?php else: ?>
                                                            <span class="text-emerald-600 font-medium">Đủ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 2: TRỢ LÝ MÔ PHỎNG & ĐỀ XUẤT 4 KỊCH BẢN THÔNG MINH (WHAT-IF)       -->
    <!-- ===================================================================== -->
    <?php if ($active_tab === 'simulation'): ?>

        <div class="bg-slate-900 rounded-sm p-6 sm:p-8 text-white border border-slate-800 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-sm text-xs font-semibold bg-slate-800 text-amber-300 border border-slate-700 mb-2">
                        <i class="fa-solid fa-sparkles"></i> Aura AI What-If Simulation Engine
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-white">4 Kịch Bản Tối Ưu Hóa & Luân Chuyển Khuyên Dùng</h3>
                    <p class="text-xs sm:text-sm text-indigo-200 mt-1 max-w-3xl">
                        Thuật toán tự động quét dữ liệu định biên, hồ sơ thâm niên, điểm đánh giá KPI và quỹ lương để mô phỏng 4 phương án chiến lược, không trùng lắp đơn vị và cho phép áp dụng hàng loạt chỉ với 1 click.
                    </p>
                </div>
                
                <button onclick="location.reload()" 
                        class="px-4 py-2.5 rounded-sm bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs border border-slate-700 transition flex items-center gap-1.5 flex-shrink-0">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Tái Tính Toán Thuật Toán</span>
                </button>
            </div>

            <!-- Grid 4 Kịch Bản -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($scenarios as $sol): ?>
                    <div class="bg-slate-800/80 rounded-sm p-6 border border-slate-700 space-y-5 hover:border-slate-600 transition flex flex-col justify-between">
                        
                        <div class="space-y-4">
                            <!-- Header Kịch Bản -->
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-3 py-1 rounded-sm text-[11px] font-bold uppercase border <?= $sol['tag_class'] ?>">
                                    <i class="fa-solid <?= $sol['icon'] ?> mr-1"></i>
                                    <?= $sol['tag'] ?>
                                </span>
                                <div class="flex items-center gap-1.5 text-emerald-400 text-xs font-mono font-bold bg-emerald-950/40 px-2.5 py-1 rounded-sm border border-emerald-500/30">
                                    <i class="fa-solid fa-chart-line text-[11px]"></i>
                                    <span>Hiệu Quả: <?= $sol['impact_score'] ?>/100</span>
                                </div>
                            </div>

                            <h4 class="font-bold text-white text-base leading-snug"><?= e($sol['title']) ?></h4>
                            <p class="text-xs text-indigo-100 leading-relaxed"><?= e($sol['desc']) ?></p>

                            <!-- Danh Sách Cán Bộ Đề Cử -->
                            <?php foreach ($sol['candidates'] as $cand): ?>
                                <div class="p-4 bg-black/30 rounded-sm border border-white/10 space-y-2.5 text-xs">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-sm bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">
                                                <?= mb_substr($cand['candidate_name'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <strong class="text-white text-sm block"><?= e($cand['candidate_name']) ?></strong>
                                                <span class="font-mono text-[10px] text-amber-300"><?= e($cand['candidate_code']) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                                                Match: <?= $cand['match_score'] ?>%
                                            </span>
                                            <div class="text-[10px] text-emerald-300 font-mono mt-0.5">KPI: <?= $cand['kpi_score'] ?> (<?= $cand['kpi_grade'] ?>)</div>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-white/10 grid grid-cols-2 gap-2 text-[11px]">
                                        <div>
                                            <span class="text-indigo-300 block text-[10px]">Đơn vị xuất phát:</span>
                                            <strong class="text-slate-300"><?= e($cand['from_branch_name']) ?></strong>
                                            <div class="text-[10px] text-slate-400"><?= e($cand['from_pos_name']) ?></div>
                                        </div>
                                        <div>
                                            <span class="text-amber-300 block text-[10px]">Đơn vị tiếp nhận:</span>
                                            <strong class="text-amber-200"><?= e($cand['to_branch_name']) ?></strong>
                                            <div class="text-[10px] text-amber-300/80"><?= e($cand['to_pos_name']) ?></div>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-white/10 flex items-center justify-between text-[11px]">
                                        <span class="text-indigo-300">Dự toán phụ cấp chuyển vùng:</span>
                                        <span class="text-emerald-300 font-mono font-bold"><?= format_money($cand['allowance']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Thước Đo Dự Báo Tác Động Trước & Sau (Before vs After) -->
                            <div class="p-3 bg-white/5 rounded-xl border border-white/10 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-300">Dự báo lấp đầy định biên tại <strong class="text-amber-300"><?= e($sol['target_branch_code']) ?></strong>:</span>
                                    <span class="font-mono font-bold text-white"><?= $sol['before_after']['current_rate'] ?>% &rarr; <span class="text-emerald-400"><?= $sol['before_after']['projected_rate'] ?>%</span></span>
                                </div>
                                <div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full bg-emerald-400 transition-all duration-500" style="width: <?= min(100, $sol['before_after']['projected_rate']) ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Nút Thao Tác Áp Dụng 1-Click -->
                        <div class="pt-3 border-t border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <span class="text-[11px] text-emerald-300 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-[10px]"></i>
                                <span><?= e($sol['impact_label']) ?></span>
                            </span>

                            <button onclick="openApplyScenarioModal(<?= htmlspecialchars(json_encode($sol), ENT_QUOTES, 'UTF-8') ?>)" 
                                    class="px-5 py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-bold text-xs rounded-sm transition flex items-center justify-center gap-2 flex-shrink-0">
                                <i class="fa-solid fa-bolt text-xs"></i>
                                <span>Áp Dụng Phương Án Này</span>
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 3: ĐỀ XUẤT NHÂN LỰC & TỐI ƯU TỪ CHI NHÁNH                         -->
    <!-- ===================================================================== -->
    <?php if ($active_tab === 'requests'): ?>

        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-amber-500"></i>
                        <span>Danh Sách Đề Xuất Nhu Cầu & Tối Ưu Nhân Lực Từ Các Chi Nhánh</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Tiếp nhận kiến nghị từ các Giám đốc Chi nhánh, thẩm định và tích hợp vào Kế hoạch tổng thể</p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Bộ lọc trạng thái -->
                    <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs">
                        <a href="index.php?tab=requests" class="px-2.5 py-1 rounded-lg font-semibold transition <?= empty($req_status_filter) ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs' : 'text-slate-500' ?>">Tất Cả</a>
                        <a href="index.php?tab=requests&req_status=pending" class="px-2.5 py-1 rounded-lg font-semibold transition <?= $req_status_filter === 'pending' ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-slate-500' ?>">Chờ Duyệt</a>
                        <a href="index.php?tab=requests&req_status=approved" class="px-2.5 py-1 rounded-lg font-semibold transition <?= $req_status_filter === 'approved' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500' ?>">Đã Duyệt</a>
                        <a href="index.php?tab=requests&req_status=incorporated" class="px-2.5 py-1 rounded-lg font-semibold transition <?= $req_status_filter === 'incorporated' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500' ?>">Đã Tích Hợp</a>
                    </div>

                    <a href="request_form.php" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Gửi Đề Xuất Mới</span>
                    </a>
                </div>
            </div>

            <!-- Bảng Đề Xuất -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                            <th class="py-3 px-4">Mã & Tiêu Đề Đề Xuất</th>
                            <th class="py-3 px-4">Đơn Vị & Khối Ngành</th>
                            <th class="py-3 px-4 text-center">Loại Hình & Số Lượng</th>
                            <th class="py-3 px-4 text-center">Cấp Bách</th>
                            <th class="py-3 px-4 text-right">Dự Toán</th>
                            <th class="py-3 px-4 text-center">Trạng Thái</th>
                            <th class="py-3 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($branch_requests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-slate-400">
                                    Không có đề xuất nào phù hợp với bộ lọc hiện tại.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($branch_requests as $req): ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800 dark:text-white"><?= e($req['title']) ?></div>
                                    <div class="font-mono text-[10px] text-slate-400 mt-0.5">
                                        Mã: <?= e($req['request_code']) ?> • Người gửi: <?= e($req['creator_name'] ?? 'Admin') ?> (<?= format_date($req['created_at']) ?>)
                                    </div>
                                    <?php if (!empty($req['candidate_name'])): ?>
                                        <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-0.5 font-medium">
                                            <i class="fa-solid fa-user-check text-[9px]"></i> Cán bộ tiến cử: <?= e($req['candidate_name']) ?> (<?= e($req['candidate_code']) ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200"><?= e($req['branch_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= e($req['dept_name'] ?? 'Toàn Chi Nhánh') ?> • <?= e($req['pos_name'] ?? 'Vị trí mở') ?></div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        <?= [
                                            'request_headcount' => 'Xin Bổ Sung',
                                            'surplus_relocation' => 'Điều Chuyển Ra',
                                            'management_appointment' => 'Tiếp Nhận Quản Lý',
                                            'internal_restructure' => 'Tái Cơ Cấu'
                                        ][$req['request_type']] ?? 'Khác' ?>
                                    </span>
                                    <div class="text-[11px] font-mono font-bold text-slate-800 dark:text-slate-200 mt-1">
                                        <?= $req['quantity'] ?> Nhân Sự
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($req['urgency'] === 'urgent'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                            Khẩn Cấp
                                        </span>
                                    <?php elseif ($req['urgency'] === 'high'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                            Cao
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                            Bình Thường
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    <?= format_money($req['estimated_cost']) ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                            Chờ Thẩm Định
                                        </span>
                                    <?php elseif ($req['status'] === 'approved'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                            Đã Phê Duyệt
                                        </span>
                                    <?php elseif ($req['status'] === 'incorporated'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                            Đã Tích Hợp Kế Hoạch
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                            Bị Từ Chối
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                    <!-- Nút Xem chi tiết & Thẩm định -->
                                    <button onclick="openReviewModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>)" 
                                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-[11px] font-semibold rounded-lg transition">
                                        Chi Tiết
                                    </button>

                                    <!-- Nút Tích hợp vào Kế hoạch quy hoạch -->
                                    <?php if ($req['status'] !== 'incorporated' && has_permission('planning', 'create')): ?>
                                        <button onclick="openIncorporateModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>)" 
                                                class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-bold rounded-lg transition"
                                                title="Tích hợp đề xuất này vào Kế hoạch điều động">
                                            Tích Hợp Kế Hoạch
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 4: DANH SÁCH KẾ HOẠCH QUY HOẠCH MASTER                           -->
    <!-- ===================================================================== -->
    <?php if ($active_tab === 'plans'): ?>

        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-4">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-folder-tree text-indigo-600"></i>
                        <span>Danh Sách Kế Hoạch Quy Hoạch Điều Động Nhân Sự Đã Lập</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Theo dõi các chiến dịch điều động theo quý, năm hoặc mở rộng chi nhánh mới</p>
                </div>
                
                <a href="form.php" 
                   class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Tạo Kế Hoạch Mới</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                            <th class="py-3 px-4">Mã & Tên Kế Hoạch</th>
                            <th class="py-3 px-4">Loại Hình & Chi Nhánh Trọng Điểm</th>
                            <th class="py-3 px-4">Khung Thời Gian</th>
                            <th class="py-3 px-4 text-center">Tiến Độ Thực Thi</th>
                            <th class="py-3 px-4 text-center">Trạng Thái</th>
                            <th class="py-3 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($plans)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-400">
                                    Chưa có kế hoạch quy hoạch nào được lập. Bấm "Tạo Kế Hoạch Mới" để bắt đầu.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($plans as $p): ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4">
                                    <a href="view.php?id=<?= $p['id'] ?>" class="font-bold text-slate-800 dark:text-white hover:text-indigo-600 transition text-xs block">
                                        <?= e($p['title']) ?>
                                    </a>
                                    <span class="font-mono text-[10px] text-slate-400">Mã: <?= e($p['plan_code']) ?> • Người lập: <?= e($p['creator_name'] ?? 'Admin') ?></span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        <?= [
                                            'quarterly' => 'Quy hoạch theo Quý',
                                            'annual' => 'Kế hoạch định kỳ Năm',
                                            'branch_expansion' => 'Mở rộng chi nhánh mới',
                                            'emergency_rebalance' => 'Cân bằng khẩn cấp'
                                        ][$p['plan_type']] ?? 'Kế hoạch chung' ?>
                                    </span>
                                    <?php if (!empty($p['target_branch_name'])): ?>
                                        <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mt-1">
                                            <i class="fa-solid fa-location-dot text-[9px]"></i> Trọng điểm: <?= e($p['target_branch_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400">
                                    <?= format_date($p['start_date']) ?> &rarr; <?= format_date($p['end_date']) ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="font-mono font-bold text-slate-800 dark:text-slate-200 text-xs">
                                        <?= $p['executed_candidates'] ?> / <?= $p['total_candidates'] ?> Cán Bộ
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        Dự toán: <?= format_money($p['total_estimated_cost']) ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">Đang Triển Khai</span>
                                    <?php elseif ($p['status'] === 'completed'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-800">Đã Hoàn Thành</span>
                                    <?php elseif ($p['status'] === 'draft'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">Bản Thảo (Nháp)</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Đã Hủy</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                    <a href="view.php?id=<?= $p['id'] ?>" 
                                       class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-semibold rounded-lg transition"
                                       title="Xem chi tiết kế hoạch">
                                        Xem & Điều Phối
                                    </a>
                                    <?php if (has_permission('planning', 'delete')): ?>
                                        <button onclick="confirmDeletePlan(<?= $p['id'] ?>, '<?= e($p['title']) ?>')" 
                                                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-900/40 text-slate-500 inline-flex items-center justify-center transition"
                                                title="Xóa kế hoạch">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    <?php endif; ?>

</div>

<!-- ========================================================================= -->
<!-- CÁC MODAL TƯƠNG TÁC (APPLY SCENARIO, REVIEW REQUEST, INCORPORATE, DELETE) -->
<!-- ========================================================================= -->

<!-- Modal 1: 1-Click Áp Dụng Kịch Bản Vào Kế Hoạch -->
<div id="applyScenarioModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider block">1-Click Apply Simulation</span>
                <h3 class="font-bold text-slate-800 dark:text-white text-base">Áp Dụng Kịch Bản Vào Kế Hoạch</h3>
            </div>
            <button onclick="document.getElementById('applyScenarioModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="apply_scenario">
            <input type="hidden" name="scenario_id" id="modalScenarioId" value="">

            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-1">
                <div class="text-xs text-slate-500 dark:text-slate-400">Kịch bản chọn: <strong id="modalScenarioTitle" class="text-slate-800 dark:text-white"></strong></div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium" id="modalScenarioImpact"></div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Hình Thức Áp Dụng *</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 cursor-pointer">
                        <input type="radio" name="apply_mode" value="new" checked onchange="toggleApplyMode('new')" class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-800 dark:text-white">Tạo Kế Hoạch Mới</span>
                    </label>
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 cursor-pointer">
                        <input type="radio" name="apply_mode" value="existing" onchange="toggleApplyMode('existing')" class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-800 dark:text-white">Thêm Vào Kế Hoạch Có Sẵn</span>
                    </label>
                </div>
            </div>

            <!-- Trường Tạo Mới -->
            <div id="newPlanSection">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Tên Kế Hoạch Quy Hoạch Mới</label>
                <input type="text" name="custom_title" id="modalNewPlanTitle" value=""
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Trường Chọn Kế Hoạch Có Sẵn -->
            <div id="existingPlanSection" class="hidden">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Chọn Kế Hoạch Tiếp Nhận</label>
                <select name="existing_plan_id"
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($plans as $p): ?>
                        <?php if ($p['status'] === 'active'): ?>
                            <option value="<?= $p['id'] ?>">
                                [<?= e($p['plan_code']) ?>] <?= e($p['title']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
                <button type="button" onclick="document.getElementById('applyScenarioModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-bolt text-xs"></i>
                    <span>Xác Nhận Áp Dụng Ngay</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Chi Tiết & Thẩm Định Đề Xuất Chi Nhánh -->
<div id="reviewRequestModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider block" id="modalReqCode"></span>
                <h3 class="font-bold text-slate-800 dark:text-white text-base">Thẩm Định Đề Xuất Nhân Lực</h3>
            </div>
            <button onclick="document.getElementById('reviewRequestModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="space-y-3 text-xs">
            <div>
                <span class="text-slate-400 block text-[11px]">Tiêu đề đề xuất:</span>
                <strong id="modalReqTitle" class="text-slate-800 dark:text-white text-sm"></strong>
            </div>
            <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                <div>
                    <span class="text-slate-400 text-[10px]">Đơn vị đề xuất:</span>
                    <div id="modalReqBranch" class="font-semibold text-slate-700 dark:text-slate-300"></div>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px]">Khối & Chức danh:</span>
                    <div id="modalReqDeptPos" class="font-semibold text-slate-700 dark:text-slate-300"></div>
                </div>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Căn cứ & Giải trình:</span>
                <div id="modalReqRationale" class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl text-slate-700 dark:text-slate-300 leading-relaxed max-h-32 overflow-y-auto"></div>
            </div>
        </div>

        <?php if (has_permission('planning', 'approve')): ?>
            <form method="POST" action="index.php" class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="review_request">
                <input type="hidden" name="request_id" id="modalReviewReqId" value="">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">Ý Kiến Phản Hồi / Chỉ Đạo Của Tập Đoàn</label>
                    <textarea name="review_notes" rows="2" placeholder="Ghi chú ý kiến thẩm định của Ban Nhân Sự..."
                              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <button type="submit" name="decision" value="rejected"
                            class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition">
                        Từ Chối Đề Xuất
                    </button>
                    <button type="submit" name="decision" value="approved"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition">
                        Phê Duyệt Đề Xuất
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 3: Tích Hợp Đề Xuất Vào Kế Hoạch Quy Hoạch -->
<div id="incorporateModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider block">Incorporate to Master Plan</span>
                <h3 class="font-bold text-slate-800 dark:text-white text-base">Tích Hợp Vào Kế Hoạch</h3>
            </div>
            <button onclick="document.getElementById('incorporateModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="incorporate_request">
            <input type="hidden" name="request_id" id="modalIncorpReqId" value="">

            <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-xl border border-indigo-100 dark:border-indigo-800 text-xs space-y-1">
                <div class="text-slate-500 dark:text-slate-400">Đề xuất: <strong id="modalIncorpReqTitle" class="text-indigo-700 dark:text-indigo-300"></strong></div>
                <div class="text-[11px] text-slate-500">Cán bộ liên quan: <strong id="modalIncorpCandidate" class="text-slate-700 dark:text-slate-300"></strong></div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Chọn Kế Hoạch Quy Hoạch Cần Đưa Vào *</label>
                <select name="target_plan_id" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($plans as $p): ?>
                        <?php if ($p['status'] === 'active'): ?>
                            <option value="<?= $p['id'] ?>">
                                [<?= e($p['plan_code']) ?>] <?= e($p['title']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">Dự Toán Phụ Cấp Bổ Sung (VNĐ)</label>
                <input type="text" name="allowance" id="modalIncorpAllowance" value=""
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono font-semibold text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
                <button type="button" onclick="document.getElementById('incorporateModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tích Hợp Vào Kế Hoạch</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Xóa Kế Hoạch -->
<div id="deletePlanModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-white text-base mb-1">Xóa Kế Hoạch Quy Hoạch?</h3>
        <p id="delPlanMsg" class="text-xs text-slate-500 mb-5"></p>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_plan">
            <input type="hidden" name="plan_id" id="delPlanId" value="">
            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="document.getElementById('deletePlanModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition">
                    Xóa Kế Hoạch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT XỬ LÝ BIỂU ĐỒ & MODAL TƯƠNG TÁC                               -->
<!-- ========================================================================= -->
<script>
// Toggle mở rộng danh sách phòng ban trực thuộc chi nhánh
function toggleBranchDepts(branchId) {
    const row = document.getElementById('branch-depts-' + branchId);
    const chevron = document.getElementById('chevron-branch-' + branchId);
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        chevron.classList.remove('fa-chevron-right');
        chevron.classList.add('fa-chevron-down');
    } else {
        row.classList.add('hidden');
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-right');
    }
}

// Xử lý Modal Apply Scenario
function openApplyScenarioModal(scenario) {
    document.getElementById('modalScenarioId').value = scenario.id;
    document.getElementById('modalScenarioTitle').innerText = scenario.title;
    document.getElementById('modalScenarioImpact').innerText = scenario.impact_label;
    document.getElementById('modalNewPlanTitle').value = 'Kế Hoạch Tối Ưu: ' + scenario.title;
    toggleApplyMode('new');
    document.getElementById('applyScenarioModal').classList.remove('hidden');
}

function toggleApplyMode(mode) {
    if (mode === 'new') {
        document.getElementById('newPlanSection').classList.remove('hidden');
        document.getElementById('existingPlanSection').classList.add('hidden');
    } else {
        document.getElementById('newPlanSection').classList.add('hidden');
        document.getElementById('existingPlanSection').classList.remove('hidden');
    }
}

// Xử lý Modal Review Request
function openReviewModal(req) {
    document.getElementById('modalReqCode').innerText = req.request_code;
    document.getElementById('modalReqTitle').innerText = req.title;
    document.getElementById('modalReqBranch').innerText = req.branch_name;
    document.getElementById('modalReqDeptPos').innerText = (req.dept_name || 'Toàn chi nhánh') + ' • ' + (req.pos_name || 'Vị trí mở');
    document.getElementById('modalReqRationale').innerText = req.rationale;
    document.getElementById('modalReviewReqId').value = req.id;
    document.getElementById('reviewRequestModal').classList.remove('hidden');
}

// Xử lý Modal Incorporate Request
function openIncorporateModal(req) {
    document.getElementById('modalIncorpReqId').value = req.id;
    document.getElementById('modalIncorpReqTitle').innerText = req.title;
    document.getElementById('modalIncorpCandidate').innerText = req.candidate_name ? (req.candidate_name + ' (' + req.candidate_code + ')') : 'Chưa chỉ định';
    document.getElementById('modalIncorpAllowance').value = req.estimated_cost;
    document.getElementById('incorporateModal').classList.remove('hidden');
}

// Xử lý Modal Delete Plan
function confirmDeletePlan(id, title) {
    document.getElementById('delPlanId').value = id;
    document.getElementById('delPlanMsg').innerText = `Kế hoạch: "${title}" sẽ bị xóa cùng danh sách các đề xuất điều động trực thuộc.`;
    document.getElementById('deletePlanModal').classList.remove('hidden');
}

// Khởi tạo Biểu đồ Chart.js khi ở Tab Analytics
<?php if ($active_tab === 'analytics'): ?>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Biểu đồ Cột So sánh Định biên từng Chi nhánh
    const ctxBranch = document.getElementById('branchHeadcountChart')?.getContext('2d');
    if (ctxBranch) {
        new Chart(ctxBranch, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_branch_labels) ?>,
                datasets: [
                    {
                        label: 'Thực Tế',
                        data: <?= json_encode($chart_branch_current) ?>,
                        backgroundColor: '#6366f1',
                        borderRadius: 0
                    },
                    {
                        label: 'Mục Tiêu',
                        data: <?= json_encode($chart_branch_target) ?>,
                        backgroundColor: '#e2e8f0',
                        borderRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. Biểu đồ Donut Cơ cấu Nhân lực Phòng ban
    const ctxDept = document.getElementById('deptDistributionChart')?.getContext('2d');
    if (ctxDept) {
        new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_slice($chart_dept_labels, 0, 8)) ?>,
                datasets: [{
                    data: <?= json_encode(array_slice($chart_dept_headcounts, 0, 8)) ?>,
                    backgroundColor: [
                        '#6366f1', '#3b82f6', '#10b981', '#f59e0b',
                        '#ec4899', '#8b5cf6', '#14b8a6', '#f97316'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
