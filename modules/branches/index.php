<?php
// modules/branches/index.php - Quản lý Danh mục Mạng lưới Chi nhánh của Cơ quan
$page_title = 'Mạng Lưới Chi Nhánh Cơ Quan';

require_once __DIR__ . '/../../core/auth.php';
require_permission('branches', 'view');

// Xử lý Thêm / Sửa / Xóa Chi Nhánh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    // 1. Thêm mới Chi Nhánh
    if ($action_type === 'create_branch') {
        require_permission('branches', 'create');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $target_headcount = max(1, (int)($_POST['target_headcount'] ?? 30));
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($code) && !empty($name)) {
            try {
                if ($is_headquarter) {
                    // Nếu chi nhánh này là trụ sở chính, hạ cờ các chi nhánh khác
                    $pdo->query("UPDATE branches SET is_headquarter = 0");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO branches (code, name, address, phone, email, manager_id, target_headcount, is_headquarter, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $address, $phone, $email, $manager_id, $target_headcount, $is_headquarter, $status, $notes]);

                set_flash('success', "Đã thêm thành công chi nhánh mới: {$name} ({$code})");
                redirect('modules/branches/index.php');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi: Mã chi nhánh đã tồn tại hoặc dữ liệu không hợp lệ.');
            }
        } else {
            set_flash('warning', 'Vui lòng nhập đầy đủ Mã và Tên chi nhánh.');
        }
    }

    // 2. Chỉnh sửa Chi Nhánh
    if ($action_type === 'edit_branch') {
        require_permission('branches', 'edit');
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $target_headcount = max(1, (int)($_POST['target_headcount'] ?? 30));
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $notes = trim($_POST['notes'] ?? '');

        if ($branch_id > 0 && !empty($code) && !empty($name)) {
            try {
                if ($is_headquarter) {
                    $pdo->prepare("UPDATE branches SET is_headquarter = 0 WHERE id != ?")->execute([$branch_id]);
                }

                $stmt = $pdo->prepare("
                    UPDATE branches 
                    SET code = ?, name = ?, address = ?, phone = ?, email = ?, manager_id = ?, 
                        target_headcount = ?, is_headquarter = ?, status = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $address, $phone, $email, $manager_id, $target_headcount, $is_headquarter, $status, $notes, $branch_id]);

                set_flash('success', "Đã cập nhật thông tin chi nhánh: {$name}");
                redirect('modules/branches/index.php');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi cập nhật: Mã chi nhánh có thể đã bị trùng.');
            }
        }
    }

    // 3. Xóa Chi Nhánh
    if ($action_type === 'delete_branch') {
        require_permission('branches', 'delete');
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($branch_id > 0) {
            // Kiểm tra ràng buộc nhân sự
            $checkEmp = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch_id = ?");
            $checkEmp->execute([$branch_id]);
            $empCount = (int)$checkEmp->fetchColumn();

            if ($empCount > 0) {
                set_flash('danger', "Không thể xóa chi nhánh này vì đang có {$empCount} nhân viên trực thuộc. Vui lòng thuyên chuyển nhân sự trước.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                $delStmt->execute([$branch_id]);
                set_flash('success', 'Đã xóa chi nhánh thành công.');
            }
            redirect('modules/branches/index.php');
        }
    }
}

// Truy vấn danh sách chi nhánh kèm các chỉ số thống kê thực tế
$branches = $pdo->query("
    SELECT 
        b.*,
        m.fullname AS manager_name,
        m.employee_code AS manager_code,
        COUNT(DISTINCT e.id) AS total_employees,
        COUNT(DISTINCT CASE WHEN e.employment_status = 'official' THEN e.id END) AS official_employees,
        COUNT(DISTINCT d.id) AS total_departments,
        COALESCE(SUM(p.base_salary), 0) AS total_salary_fund
    FROM branches b
    LEFT JOIN employees m ON b.manager_id = m.id
    LEFT JOIN employees e ON b.id = e.branch_id AND e.employment_status != 'resigned'
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON b.id = d.branch_id
    GROUP BY b.id
    ORDER BY b.is_headquarter DESC, b.id ASC
")->fetchAll();

// Lấy danh sách nhân viên để chọn Giám đốc/Trưởng chi nhánh
$all_employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, p.name AS position_name, b.name AS branch_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

// Thống kê tổng hợp toàn hệ thống
$total_branches = count($branches);
$system_total_emp = array_sum(array_column($branches, 'total_employees'));
$system_target_headcount = array_sum(array_column($branches, 'target_headcount'));
$system_rate = $system_target_headcount > 0 ? round(($system_total_emp / $system_target_headcount) * 100) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề & Nút Thao Tác -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 mb-2">
                <i class="fa-solid fa-building-flag"></i> Mạng Lưới Đơn Vị Trực Thuộc
            </div>
            <h2 class="text-xl font-bold text-slate-800">Quản Lý Các Chi Nhánh Của Cơ Quan</h2>
            <p class="text-sm text-slate-500 mt-0.5">Theo dõi mạng lưới trụ sở & chi nhánh vùng miền, định biên biên chế và cán bộ lãnh đạo quản lý.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="<?= base_url('modules/orgchart/index.php') ?>" 
               class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-sitemap text-indigo-600"></i>
                <span>Xem Sơ Đồ Cơ Cấu</span>
            </a>
            <a href="<?= base_url('modules/transfers/create.php') ?>" 
               class="px-4 py-2.5 bg-violet-50 hover:bg-violet-100 text-violet-700 text-xs font-semibold rounded-xl transition flex items-center gap-2 border border-violet-200">
                <i class="fa-solid fa-people-arrows"></i>
                <span>Điều Động Liên Chi Nhánh</span>
            </a>
            <?php if (has_permission('branches', 'create')): ?>
                <button onclick="openCreateBranchModal()" 
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Thêm Chi Nhánh Mới</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Khối Chỉ Số Tổng Hợp Mạng Lưới -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tổng Số Chi Nhánh</span>
                <div class="text-2xl font-bold text-slate-800 mt-1"><?= $total_branches ?></div>
                <div class="text-[11px] text-indigo-600 font-medium mt-1">1 Trụ sở chính & <?= max(0, $total_branches - 1) ?> Phân nhánh</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-building-columns"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Hiện Diện Nhân Sự</span>
                <div class="text-2xl font-bold text-slate-800 mt-1"><?= $system_total_emp ?> <span class="text-xs text-slate-400 font-normal">/ <?= $system_target_headcount ?></span></div>
                <div class="text-[11px] text-emerald-600 font-medium mt-1">Đạt <?= $system_rate ?>% định biên toàn cục</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Độ Lệch Biên Chế</span>
                <?php $headcount_diff = $system_target_headcount - $system_total_emp; ?>
                <div class="text-2xl font-bold <?= $headcount_diff > 0 ? 'text-amber-600' : 'text-slate-800' ?> mt-1">
                    <?= $headcount_diff > 0 ? "-{$headcount_diff}" : "+".abs($headcount_diff) ?>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">Cần bổ sung qua điều động</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-scale-unbalanced"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Quy Hoạch & Tối Ưu</span>
                <div class="text-sm font-bold text-slate-800 mt-1.5 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Sẵn Sàng Điều Động</span>
                </div>
                <a href="<?= base_url('modules/planning/index.php') ?>" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold block mt-1">
                    Chạy đề xuất tối ưu &rarr;
                </a>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
        </div>
    </div>

    <!-- Danh sách Thẻ Chi Nhánh Dạng Grid Cao Cấp -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-6">
        <?php foreach ($branches as $b): ?>
            <?php 
                $fill_rate = $b['target_headcount'] > 0 ? round(($b['total_employees'] / $b['target_headcount']) * 100) : 0;
                $is_shortage = $b['total_employees'] < ($b['target_headcount'] * 0.7);
            ?>
            <div class="bg-white rounded-3xl border <?= $b['is_headquarter'] ? 'border-indigo-300 ring-2 ring-indigo-50 shadow-md' : 'border-slate-200/80 shadow-sm' ?> p-6 space-y-5 hover:border-indigo-200 transition duration-200 flex flex-col justify-between">
                
                <!-- Header Thẻ -->
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl <?= $b['is_headquarter'] ? 'bg-gradient-to-tr from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-700' ?> flex items-center justify-center font-bold text-lg flex-shrink-0">
                                <?= $b['is_headquarter'] ? '<i class="fa-solid fa-crown text-amber-300"></i>' : '<i class="fa-solid fa-building"></i>' ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-slate-800 text-base leading-snug"><?= e($b['name']) ?></h3>
                                    <?php if ($b['is_headquarter']): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 uppercase">Trụ Sở Chính</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">Mã: <strong class="text-indigo-600 font-semibold"><?= e($b['code']) ?></strong></div>
                            </div>
                        </div>

                        <!-- Menu thao tác sửa / xóa -->
                        <div class="flex items-center gap-1">
                            <?php if (has_permission('branches', 'edit')): ?>
                                <button onclick='openEditBranchModal(<?= json_encode($b) ?>)' 
                                        class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-600 text-slate-500 inline-flex items-center justify-center transition"
                                        title="Chỉnh sửa chi nhánh">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                            <?php endif; ?>
                            <?php if (has_permission('branches', 'delete') && !$b['is_headquarter']): ?>
                                <button onclick="confirmDeleteBranch(<?= $b['id'] ?>, '<?= e($b['name']) ?>')" 
                                        class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-600 text-slate-500 inline-flex items-center justify-center transition"
                                        title="Xóa chi nhánh">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Thông tin cơ bản: Địa chỉ, Điện thoại, Người đứng đầu -->
                    <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs">
                        <div class="flex items-start gap-2 text-slate-600">
                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 w-3.5 text-center"></i>
                            <span class="truncate"><?= e($b['address'] ?: 'Chưa cập nhật địa chỉ') ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-slate-600">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-phone text-slate-400 w-3.5 text-center"></i>
                                <span><?= e($b['phone'] ?: '---') ?></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-envelope text-slate-400 w-3.5 text-center"></i>
                                <span class="truncate"><?= e($b['email'] ?: '---') ?></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1 text-slate-700">
                            <i class="fa-solid fa-user-tie text-indigo-500 w-3.5 text-center"></i>
                            <span>Giám đốc / Trưởng đơn vị: <strong class="text-slate-900"><?= e($b['manager_name'] ?? 'Chưa bổ nhiệm') ?></strong></span>
                        </div>
                    </div>

                    <!-- Tiến độ Định Biên & Tỷ Lệ Lấp Đầy Nhân Sự -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="flex justify-between items-center text-xs mb-1.5">
                            <span class="font-semibold text-slate-600">Định Biên Nhân Lực:</span>
                            <div class="font-mono text-slate-700">
                                <strong class="text-slate-900 text-sm"><?= $b['total_employees'] ?></strong> / <?= $b['target_headcount'] ?> cán bộ
                                <span class="font-bold ml-1 <?= $fill_rate >= 80 ? 'text-emerald-600' : ($fill_rate >= 50 ? 'text-amber-600' : 'text-rose-600') ?>">(<?= $fill_rate ?>%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all duration-500 <?= $fill_rate >= 80 ? 'bg-emerald-500' : ($fill_rate >= 50 ? 'bg-amber-500' : 'bg-rose-500') ?>" 
                                 style="width: <?= min(100, $fill_rate) ?>%"></div>
                        </div>

                        <?php if ($is_shortage): ?>
                            <div class="mt-2 text-[11px] text-rose-600 font-medium flex items-center gap-1">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Chi nhánh đang thiếu <?= $b['target_headcount'] - $b['total_employees'] ?> nhân sự so với chỉ tiêu!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Thao Tác Nhanh -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                    <div class="text-xs text-slate-500">
                        <span class="font-semibold text-slate-700"><?= $b['total_departments'] ?></span> phòng ban trực thuộc
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="<?= base_url('modules/employees/index.php?branch_id=' . $b['id']) ?>" 
                           class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition">
                            <i class="fa-solid fa-users text-indigo-600 mr-1"></i> Nhân sự
                        </a>
                        <a href="<?= base_url('modules/transfers/create.php?to_branch_id=' . $b['id']) ?>" 
                           class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition flex items-center gap-1 border border-indigo-100">
                            <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
                            <span>Điều động đến</span>
                        </a>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Modal Thêm / Chỉnh Sửa Chi Nhánh -->
<div id="branchModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 transform transition-all max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="branchModalTitle" class="font-bold text-slate-800 text-lg">Thêm Chi Nhánh Mới</h3>
            <button onclick="closeBranchModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="branchForm" action="index.php" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action_type" id="branchActionType" value="create_branch">
            <input type="hidden" name="branch_id" id="branchId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mã Chi Nhánh (Code) *</label>
                    <input type="text" name="code" id="branchCode" required placeholder="VD: HQ-HN, BR-HCM..."
                           class="w-full uppercase px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Định Biên Nhân Sự Mục Tiêu *</label>
                    <input type="number" name="target_headcount" id="branchTargetHeadcount" required min="1" value="30"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Tên Chi Nhánh / Đơn Vị *</label>
                <input type="text" name="name" id="branchName" required placeholder="VD: Chi Nhánh Miền Trung - Đà Nẵng..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Địa Chỉ Trụ Sở Chi Nhánh</label>
                <input type="text" name="address" id="branchAddress" placeholder="Số nhà, đường, quận/huyện, tỉnh/thành phố..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Điện Thoại</label>
                    <input type="text" name="phone" id="branchPhone" placeholder="024 3888 9999..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Email Chi Nhánh</label>
                    <input type="email" name="email" id="branchEmail" placeholder="chinhanh@coquan.gov.vn..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Giám Đốc / Người Đứng Đầu Chi Nhánh</label>
                <select name="manager_id" id="branchManagerId"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                    <option value="">-- Chọn Cán Bộ Lãnh Đạo --</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) - <?= e($emp['position_name']) ?> [<?= e($emp['branch_name'] ?? 'Chưa gán') ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-800 block">Thiết lập Trụ Sở Chính (HQ)</span>
                    <span class="text-[11px] text-slate-500">Đơn vị trung tâm điều phối toàn bộ cơ quan</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_headquarter" id="branchIsHq" value="1" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeBranchModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu Chi Nhánh
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xác Nhận Xóa Chi Nhánh -->
<div id="deleteModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xác Nhận Xóa Chi Nhánh?</h3>
        <p id="deleteModalMsg" class="text-xs text-slate-500 mb-6">Thao tác này chỉ thực hiện được khi chi nhánh không còn nhân sự trực thuộc.</p>
        
        <form id="deleteForm" method="POST" action="index.php">
            <input type="hidden" name="action_type" value="delete_branch">
            <input type="hidden" name="branch_id" id="delBranchId" value="">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Không, Hủy
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Đồng Ý Xóa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateBranchModal() {
    document.getElementById('branchModalTitle').innerText = 'Thêm Chi Nhánh Mới';
    document.getElementById('branchActionType').value = 'create_branch';
    document.getElementById('branchId').value = '';
    document.getElementById('branchCode').value = '';
    document.getElementById('branchName').value = '';
    document.getElementById('branchAddress').value = '';
    document.getElementById('branchPhone').value = '';
    document.getElementById('branchEmail').value = '';
    document.getElementById('branchManagerId').value = '';
    document.getElementById('branchTargetHeadcount').value = '30';
    document.getElementById('branchIsHq').checked = false;
    document.getElementById('branchModal').classList.remove('hidden');
}

function openEditBranchModal(b) {
    document.getElementById('branchModalTitle').innerText = 'Chỉnh Sửa Chi Nhánh';
    document.getElementById('branchActionType').value = 'edit_branch';
    document.getElementById('branchId').value = b.id;
    document.getElementById('branchCode').value = b.code;
    document.getElementById('branchName').value = b.name;
    document.getElementById('branchAddress').value = b.address || '';
    document.getElementById('branchPhone').value = b.phone || '';
    document.getElementById('branchEmail').value = b.email || '';
    document.getElementById('branchManagerId').value = b.manager_id || '';
    document.getElementById('branchTargetHeadcount').value = b.target_headcount || '30';
    document.getElementById('branchIsHq').checked = parseInt(b.is_headquarter) === 1;
    document.getElementById('branchModal').classList.remove('hidden');
}

function closeBranchModal() {
    document.getElementById('branchModal').classList.add('hidden');
}

function confirmDeleteBranch(id, name) {
    document.getElementById('delBranchId').value = id;
    document.getElementById('deleteModalMsg').innerText = `Bạn có chắc chắn muốn xóa chi nhánh "${name}"?`;
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
