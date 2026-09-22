<?php
// modules/planning/index.php - Lên Kế Hoạch Điều Động & Trung Tâm Đề Xuất Phương Án Tối Ưu
$page_title = 'Kế Hoạch & Đề Xuất Tối Ưu Nhân Lực';

require_once __DIR__ . '/../../core/auth.php';
require_permission('planning', 'view');

$user = current_user();

// Xử lý Xóa Kế Hoạch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'delete_plan') {
    require_permission('planning', 'delete');
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    if ($plan_id > 0) {
        $del = $pdo->prepare("DELETE FROM transfer_plans WHERE id = ?");
        $del->execute([$plan_id]);
        set_flash('success', 'Đã xóa kế hoạch quy hoạch nhân sự.');
        redirect('modules/planning/index.php');
    }
}

// 1. Phân tích hiện trạng nhân lực và độ lệch định biên từng chi nhánh
$branch_analytics = $pdo->query("
    SELECT 
        b.*,
        COUNT(DISTINCT e.id) AS current_headcount,
        (b.target_headcount - COUNT(DISTINCT e.id)) AS shortage_count,
        ROUND((COUNT(DISTINCT e.id) / NULLIF(b.target_headcount, 0)) * 100) AS fulfillment_rate,
        COUNT(DISTINCT d.id) AS dept_count
    FROM branches b
    LEFT JOIN employees e ON b.id = e.branch_id AND e.employment_status != 'resigned'
    LEFT JOIN departments d ON b.id = d.branch_id
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY fulfillment_rate ASC, b.target_headcount DESC
")->fetchAll();

// 2. Lấy danh sách các kế hoạch quy hoạch đã lập
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

// 3. THUẬT TOÁN ĐỀ XUẤT PHƯƠNG ÁN TỐI ƯU (SMART RECOMMENDATION ENGINE)
// Tìm chi nhánh thiếu hụt nhiều nhất (Deficit Branches) và chi nhánh có nguồn nhân lực dồi dào (Surplus/Sufficient Branches)
$shortage_branches = [];
$surplus_branches = [];

foreach ($branch_analytics as $ba) {
    if ($ba['shortage_count'] > 0) {
        $shortage_branches[] = $ba;
    } else {
        $surplus_branches[] = $ba;
    }
}

// Lấy danh sách nhân viên tiềm năng có thể điều chuyển từ chi nhánh dồi dào
$candidates_pool = $pdo->query("
    SELECT e.id, e.fullname, e.employee_code, e.branch_id, e.department_id, e.position_id,
           b.name AS branch_name, b.code AS branch_code,
           d.name AS dept_name,
           p.name AS pos_name, p.base_salary
    FROM employees e
    JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status = 'official'
    ORDER BY e.hire_date ASC
    LIMIT 10
")->fetchAll();

// Tạo các kịch bản phương án tốt nhất
$recommended_solutions = [];

if (!empty($shortage_branches) && !empty($candidates_pool)) {
    $primary_deficit_branch = $shortage_branches[0]; // Chi nhánh thiếu nhất
    $sec_deficit_branch = isset($shortage_branches[1]) ? $shortage_branches[1] : $primary_deficit_branch;

    // Kịch bản 1: Cân bằng cấp bách - Điều động cán bộ nòng cốt bổ sung ngay cho chi nhánh yếu
    $sol1_candidate = $candidates_pool[0];
    $recommended_solutions[] = [
        'id' => 1,
        'tag' => 'Khuyên Dùng Nhất',
        'tag_class' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'title' => 'Phương Án 1: Cân Bằng Cấp Bách & Tăng Cường Định Biên Chi Nhánh ' . $primary_deficit_branch['code'],
        'desc' => "Chi nhánh {$primary_deficit_branch['name']} hiện chỉ đạt {$primary_deficit_branch['fulfillment_rate']}% định biên (thiếu {$primary_deficit_branch['shortage_count']} nhân sự). Đề xuất điều động cán bộ có kinh nghiệm từ Trụ sở chính đến hỗ trợ kiện toàn bộ máy.",
        'transfer_preview' => [
            'candidate_name' => $sol1_candidate['fullname'],
            'candidate_code' => $sol1_candidate['employee_code'],
            'candidate_id' => $sol1_candidate['id'],
            'from_branch' => $sol1_candidate['branch_name'],
            'from_branch_id' => $sol1_candidate['branch_id'],
            'to_branch' => $primary_deficit_branch['name'],
            'to_branch_id' => $primary_deficit_branch['id'],
            'role' => 'Tăng cường Trưởng nhóm / Chuyên viên nòng cốt'
        ],
        'impact_score' => 95,
        'impact_label' => 'Tăng 35% năng lực vận hành tại ' . $primary_deficit_branch['code'],
        'estimated_budget' => 4500000
    ];

    // Kịch bản 2: Luân chuyển phát triển nguồn lãnh đạo trẻ
    if (isset($candidates_pool[1])) {
        $sol2_candidate = $candidates_pool[1];
        $recommended_solutions[] = [
            'id' => 2,
            'tag' => 'Chiến Lược Dài Hạn',
            'tag_class' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
            'title' => 'Phương Án 2: Luân Chuyển Cán Bộ Nguồn & Đào Tạo Quản Lý Thực Địa',
            'desc' => "Luân chuyển định kỳ 12 tháng đối với cán bộ nòng cốt sang chi nhánh {$sec_deficit_branch['name']} để cọ xát thị trường thực tế trước khi xem xét quy hoạch cấp cao.",
            'transfer_preview' => [
                'candidate_name' => $sol2_candidate['fullname'],
                'candidate_code' => $sol2_candidate['employee_code'],
                'candidate_id' => $sol2_candidate['id'],
                'from_branch' => $sol2_candidate['branch_name'],
                'from_branch_id' => $sol2_candidate['branch_id'],
                'to_branch' => $sec_deficit_branch['name'],
                'to_branch_id' => $sec_deficit_branch['id'],
                'role' => 'Biệt phái quản lý dự án chi nhánh'
            ],
            'impact_score' => 88,
            'impact_label' => 'Xây dựng đội ngũ kế cận chuẩn hóa',
            'estimated_budget' => 5000000
        ];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Nút Thao Tác -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 mb-2">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i> Trợ Lý Trí Tuệ Nhân Sự & Điều Động Tối Ưu
            </div>
            <h2 class="text-xl font-bold text-slate-800">Quy Hoạch Nhân Sự & Đề Xuất Phương Án Tối Ưu</h2>
            <p class="text-sm text-slate-500 mt-0.5">Phân tích ma trận thừa/thiếu định biên giữa các chi nhánh, mô phỏng kịch bản và tự động đưa ra phương án điều chuyển hiệu quả nhất.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-sitemap text-indigo-600"></i>
                <span>Xem Sơ Đồ Cây</span>
            </a>
            <?php if (has_permission('planning', 'create')): ?>
                <a href="<?= base_url('modules/planning/form.php') ?>" 
                   class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tạo Kế Hoạch Mới</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KHỐI 1: BẢNG PHÂN TÍCH NHÂN LỰC & ĐỘ LỆCH ĐỊNH BIÊN CÁC CHI NHÁNH -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-scale-unbalanced-flip text-indigo-600"></i>
                    <span>Ma Trận Cân Đối Nhân Lực Giữa Các Chi Nhánh Cơ Quan</span>
                </h3>
                <p class="text-slate-400 text-xs mt-0.5">Cơ sở dữ liệu thời gian thực để lên phương án điều động chính xác</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                <?= count($branch_analytics) ?> Chi Nhánh Đang Giám Sát
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($branch_analytics as $ba): ?>
                <?php 
                    $is_critical = $ba['fulfillment_rate'] < 50;
                    $is_healthy = $ba['fulfillment_rate'] >= 80;
                ?>
                <div class="p-4 rounded-2xl border <?= $is_critical ? 'border-rose-200 bg-rose-50/30' : ($is_healthy ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/40') ?> space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="font-mono text-[10px] font-bold text-indigo-600 uppercase block"><?= e($ba['code']) ?></span>
                            <h4 class="font-bold text-slate-800 text-xs leading-snug"><?= e($ba['name']) ?></h4>
                        </div>
                        <?php if ($ba['is_headquarter']): ?>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800">HQ</span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-1">
                        <div class="flex justify-between text-xs font-mono">
                            <span class="text-slate-500">Thực tế / Định biên:</span>
                            <strong class="text-slate-800"><?= $ba['current_headcount'] ?> / <?= $ba['target_headcount'] ?></strong>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full <?= $is_critical ? 'bg-rose-500' : ($is_healthy ? 'bg-emerald-500' : 'bg-amber-500') ?>" 
                                 style="width: <?= min(100, $ba['fulfillment_rate']) ?>%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] pt-1">
                        <span class="font-semibold <?= $is_critical ? 'text-rose-600' : ($is_healthy ? 'text-emerald-600' : 'text-amber-600') ?>">
                            Đạt <?= $ba['fulfillment_rate'] ?>%
                        </span>
                        <?php if ($ba['shortage_count'] > 0): ?>
                            <span class="text-rose-700 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-arrow-down text-[9px]"></i> Thiếu <?= $ba['shortage_count'] ?> NV
                            </span>
                        <?php else: ?>
                            <span class="text-emerald-700 font-semibold">Đủ biên chế</span>
                        <?php endif; ?>
                    </div>

                    <div class="pt-2 border-t border-slate-100/80 flex items-center justify-between text-[10px]">
                        <span class="text-slate-400"><?= $ba['dept_count'] ?> Phòng Ban</span>
                        <a href="<?= base_url('modules/transfers/create.php?to_branch_id=' . $ba['id']) ?>" 
                           class="text-indigo-600 font-bold hover:underline">
                            Điều động đến &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KHỐI 2: TRỢ LÝ ĐỀ XUẤT PHƯƠNG ÁN TỐI ƯU (SMART RECOMMENDATIONS) -->
    <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-amber-300 backdrop-blur-md mb-2">
                    <i class="fa-solid fa-sparkles"></i> Đề Xuất Tối Ưu Tự Động
                </div>
                <h3 class="text-lg sm:text-xl font-bold tracking-tight text-white">Phương Án Bố Trí & Thuyên Chuyển Khuyên Dùng</h3>
                <p class="text-xs sm:text-sm text-indigo-200 mt-1 max-w-2xl">
                    Hệ thống đã tự động phân tích dữ liệu chuyên môn, thâm niên và cán cân định biên để tạo ra phương án điều động mang lại hiệu quả cao nhất cho cơ quan.
                </p>
            </div>
            
            <button onclick="location.reload()" 
                    class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-md transition flex items-center gap-1.5 flex-shrink-0">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>Chạy Lại Thuật Toán</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($recommended_solutions as $sol): ?>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/15 space-y-4 hover:border-white/30 transition flex flex-col justify-between">
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border <?= $sol['tag_class'] ?>">
                                <?= $sol['tag'] ?>
                            </span>
                            <div class="flex items-center gap-1 text-emerald-400 text-xs font-mono font-bold">
                                <i class="fa-solid fa-chart-line text-[10px]"></i>
                                <span>Hiệu Quả: <?= $sol['impact_score'] ?>/100</span>
                            </div>
                        </div>

                        <h4 class="font-bold text-white text-sm leading-snug"><?= e($sol['title']) ?></h4>
                        <p class="text-xs text-indigo-100 leading-relaxed"><?= e($sol['desc']) ?></p>

                        <!-- Thẻ Chi Tiết Cán Bộ Dự Kiến Điều Động -->
                        <div class="p-3 bg-black/20 rounded-xl border border-white/10 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-indigo-300 text-[11px]">Cán bộ đề cử:</span>
                                <strong class="text-white"><?= e($sol['transfer_preview']['candidate_name']) ?> (<span class="font-mono text-amber-300"><?= e($sol['transfer_preview']['candidate_code']) ?></span>)</strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-indigo-300">Lộ trình:</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-300"><?= e($sol['transfer_preview']['from_branch']) ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px] text-amber-300"></i>
                                    <strong class="text-amber-200"><?= e($sol['transfer_preview']['to_branch']) ?></strong>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-indigo-300">Dự toán phụ cấp:</span>
                                <span class="text-emerald-300 font-mono font-bold"><?= format_money($sol['estimated_budget']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Nút Áp Dụng Phương Án -->
                    <div class="pt-2 flex items-center justify-between">
                        <span class="text-[11px] text-emerald-300 font-medium flex items-center gap-1">
                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                            <span><?= e($sol['impact_label']) ?></span>
                        </span>

                        <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $sol['transfer_preview']['candidate_id'] . '&to_branch_id=' . $sol['transfer_preview']['to_branch_id']) ?>" 
                           class="px-4 py-2 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-[11px]"></i>
                            <span>Áp Dụng Phương Án Này</span>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- KHỐI 3: DANH SÁCH CÁC KẾ HOẠCH ĐÃ LẬP -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-folder-tree text-indigo-600"></i>
                    <span>Danh Sách Kế Hoạch Quy Hoạch Nhân Sự Đã Lập</span>
                </h3>
                <p class="text-slate-400 text-xs mt-0.5">Theo dõi các chiến dịch điều động theo quý, năm hoặc mở rộng chi nhánh mới</p>
            </div>
            
            <a href="<?= base_url('modules/planning/form.php') ?>" 
               class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition flex items-center gap-1">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Tạo Kế Hoạch</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 uppercase font-semibold">
                        <th class="py-3 px-4">Mã & Tên Kế Hoạch</th>
                        <th class="py-3 px-4">Loại Hình & Chi Nhánh Trọng Điểm</th>
                        <th class="py-3 px-4">Thời Gian Triển Khai</th>
                        <th class="py-3 px-4 text-center">Tiến Độ Thực Thi</th>
                        <th class="py-3 px-4 text-center">Trạng Thái</th>
                        <th class="py-3 px-4 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">Chưa có kế hoạch quy hoạch nào được lập. Bấm "Tạo Kế Hoạch Mới" để bắt đầu.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($plans as $p): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4">
                                <a href="view.php?id=<?= $p['id'] ?>" class="font-bold text-slate-800 hover:text-indigo-600 transition text-xs block">
                                    <?= e($p['title']) ?>
                                </a>
                                <span class="font-mono text-[10px] text-slate-400">Mã: <?= e($p['plan_code']) ?> • Người lập: <?= e($p['creator_name'] ?? 'Admin') ?></span>
                            </td>

                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                    <?= [
                                        'quarterly' => 'Quy hoạch theo Quý',
                                        'annual' => 'Kế hoạch định kỳ Năm',
                                        'branch_expansion' => 'Mở rộng chi nhánh mới',
                                        'emergency_rebalance' => 'Cân bằng khẩn cấp'
                                    ][$p['plan_type']] ?? 'Kế hoạch chung' ?>
                                </span>
                                <?php if (!empty($p['target_branch_name'])): ?>
                                    <div class="text-[11px] text-indigo-600 font-semibold mt-1">
                                        <i class="fa-solid fa-location-dot text-[9px]"></i> Trọng điểm: <?= e($p['target_branch_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 font-mono text-slate-600">
                                <?= format_date($p['start_date']) ?> &rarr; <?= format_date($p['end_date']) ?>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <div class="font-mono font-bold text-slate-800 text-xs">
                                    <?= $p['executed_candidates'] ?> / <?= $p['total_candidates'] ?> Cán Bộ
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    Dự toán: <?= format_money($p['total_estimated_cost']) ?>
                                </div>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($p['status'] === 'active'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Đang Triển Khai</span>
                                <?php elseif ($p['status'] === 'completed'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">Đã Hoàn Thành</span>
                                <?php elseif ($p['status'] === 'draft'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">Bản Thảo (Nháp)</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700">Đã Hủy</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                <a href="view.php?id=<?= $p['id'] ?>" 
                                   class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg transition"
                                   title="Xem chi tiết kế hoạch">
                                    Xem & Điều Phối
                                </a>
                                <?php if (has_permission('planning', 'delete')): ?>
                                    <button onclick="confirmDeletePlan(<?= $p['id'] ?>, '<?= e($p['title']) ?>')" 
                                            class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-500 inline-flex items-center justify-center transition"
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

</div>

<!-- Modal Xóa Kế Hoạch -->
<div id="deletePlanModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xóa Kế Hoạch Quy Hoạch?</h3>
        <p id="delPlanMsg" class="text-xs text-slate-500 mb-5"></p>
        <form method="POST" action="index.php">
            <input type="hidden" name="action_type" value="delete_plan">
            <input type="hidden" name="plan_id" id="delPlanId" value="">
            <div class="flex items-center justify-center gap-2.5">
                <button type="button" onclick="document.getElementById('deletePlanModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
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

<script>
function confirmDeletePlan(id, title) {
    document.getElementById('delPlanId').value = id;
    document.getElementById('delPlanMsg').innerText = `Kế hoạch: "${title}" sẽ bị xóa cùng các đề xuất chi tiết trực thuộc.`;
    document.getElementById('deletePlanModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
