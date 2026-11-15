<?php
// modules/matrix/index.php - Bảng điều khiển Ma trận phân quyền trực quan cho Super Admin
$page_title = 'Ma Trận Phân Quyền Đa Cấp';

require_once __DIR__ . '/../../core/auth.php';
require_permission('matrix', 'manage'); // Chỉ Super Admin hoặc tài khoản có quyền 'matrix:manage' mới được vào

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

// 1. Xử lý tạo Vai trò mới cho Admin con
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'create_role') {
    $role_name = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $new_scope = in_array($_POST['data_scope'] ?? 'self', ['all', 'branch', 'department', 'self'], true) ? $_POST['data_scope'] : 'self';

    if (!empty($role_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description, data_scope) VALUES (?, ?, ?)");
            $stmt->execute([$role_name, $description, $new_scope]);
            set_flash('success', "Đã tạo thành công vai trò mới: '{$role_name}'. Hãy tích chọn quyền trong ma trận bên dưới.");
            $new_role_id = $pdo->lastInsertId();
            redirect('modules/matrix/index.php?role_id=' . $new_role_id);
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi: Tên vai trò đã tồn tại hoặc không hợp lệ.');
        }
    }
}

// 2. Xử lý Lưu ma trận phân quyền (Role Permissions Matrix)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'save_matrix') {
    $target_role_id = (int)($_POST['role_id'] ?? 0);
    $selected_permissions = $_POST['perms'] ?? []; // Mảng chứa permission_id được tích chọn

    if ($target_role_id > 0) {
        try {
            $pdo->beginTransaction();

            // Xóa toàn bộ quyền cũ của vai trò này
            $delStmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delStmt->execute([$target_role_id]);

            // Thêm lại danh sách quyền mới được tích chọn từ ma trận
            if (!empty($selected_permissions)) {
                $insStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($selected_permissions as $perm_id) {
                    $insStmt->execute([$target_role_id, (int)$perm_id]);
                }
            }

            // Cập nhật Phạm vi dữ liệu (Data Scope) cho vai trò
            $data_scope = $_POST['data_scope'] ?? 'self';
            if (in_array($data_scope, ['all', 'branch', 'department', 'self'], true)) {
                $upScope = $pdo->prepare("UPDATE roles SET data_scope = ? WHERE id = ?");
                $upScope->execute([$data_scope, $target_role_id]);
            }

            $pdo->commit();

            // Cập nhật lại session nếu chính tài khoản đang đăng nhập thuộc vai trò này
            if (isset($_SESSION['user']['role_id']) && $_SESSION['user']['role_id'] == $target_role_id) {
                reload_user_permissions($pdo, $target_role_id);
            }

            set_flash('success', 'Đã cập nhật Ma Trận Phân Quyền thành công!');
            redirect('modules/matrix/index.php?role_id=' . $target_role_id);
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi khi lưu ma trận quyền: ' . $e->getMessage());
        }
    }
}

// 3. Xử lý gán Vai trò cho tài khoản người dùng (Admin con 1, 2, 3...)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'assign_user_role') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $assigned_role_id = !empty($_POST['assigned_role_id']) ? (int)$_POST['assigned_role_id'] : null;

    if ($user_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ? AND is_superadmin = 0");
            $stmt->execute([$assigned_role_id, $user_id]);
            set_flash('success', 'Đã cập nhật phân công vai trò cho tài khoản thành công.');
            redirect('modules/matrix/index.php?tab=users');
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi khi gán vai trò: ' . $e->getMessage());
        }
    }
}

// Lấy danh sách Vai trò
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Chọn vai trò đang xem ma trận (mặc định lấy vai trò đầu tiên nếu không truyền)
$current_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['id'] ?? 0);

// Lấy danh sách toàn bộ quyền trong hệ thống
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY module ASC, id ASC")->fetchAll();

// Nhóm permissions theo Module để vẽ hàng ngang
$matrix_modules = [
    'employees'   => ['name' => 'Hồ Sơ Nhân Viên', 'icon' => 'fa-address-card', 'color' => 'indigo'],
    'recruitment' => ['name' => 'Quy Trình Tuyển Dụng', 'icon' => 'fa-people-roof', 'color' => 'purple'],
    'evaluations' => ['name' => 'Đánh Giá & KPI', 'icon' => 'fa-bullseye', 'color' => 'rose'],
    'proposals'   => ['name' => 'Đề Xuất & Đơn Từ', 'icon' => 'fa-paper-plane', 'color' => 'sky'],
    'rewards'     => ['name' => 'Khen Thưởng', 'icon' => 'fa-award', 'color' => 'emerald'],
    'disciplines' => ['name' => 'Kỷ Luật & Vi Phạm', 'icon' => 'fa-scale-unbalanced', 'color' => 'rose'],
    'departments' => ['name' => 'Phòng Ban & Chức Vụ', 'icon' => 'fa-building-user', 'color' => 'sky'],
    'branches'    => ['name' => 'Mạng Lưới Chi Nhánh', 'icon' => 'fa-building-flag', 'color' => 'blue'],
    'orgchart'    => ['name' => 'Sơ Đồ Cơ Cấu Tổ Chức', 'icon' => 'fa-sitemap', 'color' => 'teal'],
    'transfers'   => ['name' => 'Thuyên Chuyển Cán Bộ', 'icon' => 'fa-people-arrows', 'color' => 'violet'],
    'planning'    => ['name' => 'Quy Hoạch & Định Biên', 'icon' => 'fa-wand-magic-sparkles', 'color' => 'amber'],
    'attendance'  => ['name' => 'Chấm Công Hàng Ngày', 'icon' => 'fa-calendar-check', 'color' => 'emerald'],
    'payroll'     => ['name' => 'Bảng Tính Lương', 'icon' => 'fa-money-bill-wave', 'color' => 'amber'],
    'matrix'      => ['name' => 'Cấu Hình Phân Quyền', 'icon' => 'fa-network-wired', 'color' => 'slate'],
];

$special_action_labels = [
    'approve'        => 'Phê Duyệt',
    'hire'           => 'Tiếp Nhận NV',
    'request'        => 'Đề Xuất ĐB',
    'self_review'    => 'Tự Chấm KPI',
    'manager_review' => 'QL Đánh Giá KPI',
];

// Lấy danh sách permission_id mà vai trò này đang sở hữu
$assigned_perm_ids = [];
if ($current_role_id > 0) {
    $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$current_role_id]);
    $assigned_perm_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Bộ lọc Tab 2 (Users)
$u_search = trim($_GET['u_search'] ?? '');
$u_role   = isset($_GET['u_role']) ? (int)$_GET['u_role'] : 0;
$u_branch = isset($_GET['u_branch']) ? (int)$_GET['u_branch'] : 0;

$u_wheres = ['1=1'];
$u_params = [];
if ($u_search !== '') {
    $u_wheres[] = "(u.fullname LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR e.employee_code LIKE ?)";
    $kw = "%{$u_search}%";
    array_push($u_params, $kw, $kw, $kw, $kw);
}
if ($u_role > 0) {
    $u_wheres[] = "u.role_id = ?";
    $u_params[] = $u_role;
}
if ($u_branch > 0) {
    $u_wheres[] = "e.branch_id = ?";
    $u_params[] = $u_branch;
}
$u_where_sql = implode(' AND ', $u_wheres);

// Lấy danh sách tài khoản kèm Hồ sơ Nhân sự, Vị trí, Phòng ban, Chi nhánh
$uStmt = $pdo->prepare("
    SELECT u.*, r.name AS role_name, r.data_scope AS role_scope,
           e.employee_code, e.employment_status,
           p.name AS position_name, d.name AS department_name, b.name AS branch_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN employees e ON e.user_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE {$u_where_sql}
    ORDER BY u.is_superadmin DESC, r.id ASC, u.id ASC
");
$uStmt->execute($u_params);
$admin_users = $uStmt->fetchAll();

$branches_list = $pdo->query("SELECT id, name FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();

// Xác định Tab hiện tại (matrix hoặc users)
$active_tab = $_GET['tab'] ?? 'matrix';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Tiêu đề giới thiệu chức năng -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 mb-2">
                <i class="fa-solid fa-shield-halved"></i> Trung Tâm Kiểm Soát Bảo Mật
            </div>
            <h2 class="text-xl font-bold text-slate-800">Ma Trận Phân Quyền Đa Cấp (Dynamic RBAC)</h2>
            <p class="text-sm text-slate-500 mt-0.5">Cho phép Super Admin tùy ý bật/tắt quyền thao tác (Xem, Thêm, Sửa, Xóa) cho từng nhóm Admin phụ trách riêng biệt.</p>
        </div>
        <div class="flex items-center gap-2">
            <!-- Nút mở Modal tạo vai trò mới -->
            <button onclick="document.getElementById('createRoleModal').classList.remove('hidden')" 
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition duration-150 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Tạo Vai Trò Mới</span>
            </button>
        </div>
    </div>

    <div class="flex border-b border-slate-200">
        <a href="?tab=matrix&role_id=<?= $current_role_id ?>" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'matrix' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-table-cells"></i>
            <span>1. Bảng Ma Trận Quyền Của Vai Trò</span>
        </a>
        <a href="?tab=users" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span>2. Phân Công Vai Trò Cho Admin Con (Users)</span>
        </a>
    </div>

    <?php if ($active_tab === 'matrix'): ?>
        <!-- ================= TAB 1: BẢNG MA TRẬN PHÂN QUYỀN ================= -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <div class="lg:col-span-1 space-y-3">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 px-2">
                        Chọn Vai Trò Để Cấu Hình
                    </h3>
                    <div class="space-y-1.5">
                        <?php foreach ($roles as $r): ?>
                            <a href="?tab=matrix&role_id=<?= $r['id'] ?>" 
                               class="w-full text-left p-3 rounded-xl block transition border <?= ($r['id'] == $current_role_id) ? 'bg-indigo-50 border-indigo-200 text-indigo-900 shadow-sm' : 'border-transparent hover:bg-slate-50 text-slate-700' ?>">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-sm"><?= e($r['name']) ?></span>
                                    <?php if ($r['id'] == $current_role_id): ?>
                                        <i class="fa-solid fa-chevron-right text-xs text-indigo-600"></i>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= e($r['description'] ?? 'Chưa có mô tả') ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Ghi chú nguyên tắc bảo mật -->
                <div class="p-4 bg-amber-50 rounded-2xl border border-amber-200/80 text-amber-900 text-xs space-y-2">
                    <div class="font-bold flex items-center gap-1.5 text-amber-800">
                        <i class="fa-solid fa-circle-info"></i> Cơ Chế Phân Quyền:
                    </div>
                    <p>• <strong>Super Admin:</strong> Mặc định có toàn quyền tối cao trên toàn hệ thống mà không cần check ma trận.</p>
                    <p>• <strong>Admin Con 1, 2, 3:</strong> Chỉ nhìn thấy các menu và chỉ thực hiện được các nút bấm đã được tích xanh trong bảng ma trận bên cạnh.</p>
                </div>
            </div>

            <div class="lg:col-span-3">
                <form action="index.php" method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="save_matrix">
                    <input type="hidden" name="role_id" value="<?= $current_role_id ?>">

                    <!-- Header của bảng ma trận -->
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
                        <div>
                            <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                                Ma Trận Quyền Cho: <span class="text-indigo-600"><?= e($roles[array_search($current_role_id, array_column($roles, 'id'))]['name'] ?? 'Chưa chọn') ?></span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Tích chọn các ô để phân bổ quyền Xem, Thêm, Sửa, Xóa tương ứng</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="toggleAllMatrix(true)" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                <i class="fa-solid fa-check-double mr-1"></i> Chọn Tất Cả
                            </button>
                            <button type="button" onclick="toggleAllMatrix(false)" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                <i class="fa-solid fa-xmark mr-1"></i> Bỏ Chọn Hết
                            </button>
                            <button type="submit" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5">
                                <i class="fa-solid fa-floppy-disk"></i> Lưu Ma Trận
                            </button>
                        </div>
                    </div>

                                        <!-- Cấu hình Phạm Vi Dữ Liệu (Data Scope) cho Vai Trò -->
                    <?php 
                    $role_idx = array_search($current_role_id, array_column($roles, 'id'));
                    $current_scope = ($role_idx !== false && isset($roles[$role_idx]['data_scope'])) ? $roles[$role_idx]['data_scope'] : 'self';
                    ?>
                    <div class="px-5 py-3.5 bg-indigo-50/70 border-b border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-indigo-600 text-sm"></i>
                            <span class="text-xs font-bold text-slate-800">Phạm Vi Dữ Liệu Cho Phép (Data Scope):</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs">
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-slate-700">
                                <input type="radio" name="data_scope" value="all" <?= $current_scope === 'all' ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                                <span>Toàn Tập Đoàn (All)</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-slate-700">
                                <input type="radio" name="data_scope" value="branch" <?= $current_scope === 'branch' ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                                <span>Theo Chi Nhánh (Branch)</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-slate-700">
                                <input type="radio" name="data_scope" value="department" <?= $current_scope === 'department' ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                                <span>Theo Khối / Phòng (Dept)</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-slate-700">
                                <input type="radio" name="data_scope" value="self" <?= $current_scope === 'self' ? 'checked' : '' ?> class="text-indigo-600 focus:ring-indigo-500">
                                <span>Cá Nhân (Self)</span>
                            </label>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/70 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                                    <th class="py-3.5 px-6">Phân Hệ / Chức Năng (Module)</th>
                                    <th class="py-3.5 px-3 text-center w-20">Xem</th>
                                    <th class="py-3.5 px-3 text-center w-20">Thêm</th>
                                    <th class="py-3.5 px-3 text-center w-20">Sửa</th>
                                    <th class="py-3.5 px-3 text-center w-20">Xóa</th>
                                    <th class="py-3.5 px-4 text-left min-w-[200px]">Quyền Đặc Thù (Approve / KPI / Tuyển / ĐB)</th>
                                    <th class="py-3.5 px-3 text-center w-24">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($matrix_modules as $mod_key => $mod_info): ?>
                                    <?php
                                    // Lấy các quyền cụ thể của module này từ cơ sở dữ liệu
                                    $mod_perms = array_filter($all_permissions, function($p) use ($mod_key) {
                                        return $p['module'] === $mod_key;
                                    });
                                    // Tạo map key action => permission_id và tách quyền đặc thù
                                    $action_map = [];
                                    $special_perms = [];
                                    foreach ($mod_perms as $p) {
                                        $action_map[$p['action']] = $p['id'];
                                        if (!in_array($p['action'], ['view', 'create', 'edit', 'manage', 'delete'], true)) {
                                            $special_perms[] = $p;
                                        }
                                    }
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition group" data-module-row="<?= $mod_key ?>">
                                        <!-- Cột Tên Phân Hệ -->
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm flex-shrink-0">
                                                    <i class="fa-solid <?= $mod_info['icon'] ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-slate-800"><?= $mod_info['name'] ?></div>
                                                    <div class="text-[11px] text-slate-400 font-mono">module: <?= $mod_key ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Cột XEM (view) -->
                                        <td class="py-4 px-4 text-center">
                                            <?php if (isset($action_map['view'])): ?>
                                                <?php $pid = $action_map['view']; ?>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="perms[]" value="<?= $pid ?>" 
                                                           class="matrix-checkbox matrix-<?= $mod_key ?> w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                                                           <?= in_array($pid, $assigned_perm_ids) ? 'checked' : '' ?>>
                                                </label>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">---</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cột THÊM (create) -->
                                        <td class="py-4 px-4 text-center">
                                            <?php if (isset($action_map['create'])): ?>
                                                <?php $pid = $action_map['create']; ?>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="perms[]" value="<?= $pid ?>" 
                                                           class="matrix-checkbox matrix-<?= $mod_key ?> w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                                                           <?= in_array($pid, $assigned_perm_ids) ? 'checked' : '' ?>>
                                                </label>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">---</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cột SỬA (edit hoặc manage) -->
                                        <td class="py-4 px-4 text-center">
                                            <?php 
                                            $edit_id = $action_map['edit'] ?? ($action_map['manage'] ?? null); 
                                            ?>
                                            <?php if ($edit_id): ?>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="perms[]" value="<?= $edit_id ?>" 
                                                           class="matrix-checkbox matrix-<?= $mod_key ?> w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                                                           <?= in_array($edit_id, $assigned_perm_ids) ? 'checked' : '' ?>>
                                                </label>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">---</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cột XÓA (delete) -->
                                        <td class="py-4 px-3 text-center">
                                            <?php if (isset($action_map['delete'])): ?>
                                                <?php $pid = $action_map['delete']; ?>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="perms[]" value="<?= $pid ?>" 
                                                           class="matrix-checkbox matrix-<?= $mod_key ?> w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                                                           <?= in_array($pid, $assigned_perm_ids) ? 'checked' : '' ?>>
                                                </label>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">---</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cột QUYỀN ĐẶC THÙ (approve, hire, request, self_review, manager_review) -->
                                        <td class="py-4 px-4">
                                            <?php if (!empty($special_perms)): ?>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <?php foreach ($special_perms as $sp): ?>
                                                        <?php $sp_label = $special_action_labels[$sp['action']] ?? ucfirst(str_replace('_', ' ', $sp['action'])); ?>
                                                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50/80 hover:bg-indigo-50/70 hover:border-indigo-200 cursor-pointer text-[11px] font-semibold text-slate-700 transition">
                                                            <input type="checkbox" name="perms[]" value="<?= $sp['id'] ?>"
                                                                   class="matrix-checkbox matrix-<?= $mod_key ?> w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-500"
                                                                   <?= in_array($sp['id'], $assigned_perm_ids) ? 'checked' : '' ?>>
                                                            <span><?= e($sp_label) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">---</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cột Chọn cả dòng -->
                                        <td class="py-4 px-3 text-center">
                                            <button type="button" onclick="toggleRow('<?= $mod_key ?>')" 
                                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold px-2 py-1 rounded hover:bg-indigo-50 transition">
                                                Cả dòng
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer lưu ma trận -->
                    <div class="p-5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                        <span class="text-xs text-slate-500">Mọi thay đổi trong ma trận sẽ có hiệu lực ngay trong phiên đăng nhập tiếp theo của Admin con.</span>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-100 transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Lưu Cấu Hình Ma Trận</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- ================= TAB 2: PHÂN CÔNG VAI TRÒ CHO TÀI KHOẢN ================= -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                        <span>Danh Sách Tài Khoản & Phân Quyền Vị Trí</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200"><?= count($admin_users) ?> tài khoản</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Đối chiếu Mã NV, Chức vụ, Phòng ban & Chi nhánh để gán đúng Vai trò và Data Scope</p>
                </div>

                <form method="GET" action="index.php" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="users">
                    <input type="text" name="u_search" value="<?= e($u_search) ?>" placeholder="Tìm tên, email, mã NV..."
                           class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48">
                    <select name="u_role" class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">-- Tất cả Vai trò --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $u_role == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="u_branch" class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">-- Tất cả Chi nhánh --</option>
                        <?php foreach ($branches_list as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= $u_branch == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i>Lọc
                    </button>
                    <?php if ($u_search !== '' || $u_role > 0 || $u_branch > 0): ?>
                        <a href="?tab=users" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">Xóa lọc</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Họ Tên & Tài Khoản</th>
                            <th class="py-3.5 px-5">Chức Vụ / Phòng Ban / Chi Nhánh</th>
                            <th class="py-3.5 px-5">Phạm Vi (Data Scope)</th>
                            <th class="py-3.5 px-5">Vai Trò Phụ Trách (Theo Ma Trận)</th>
                            <th class="py-3.5 px-6 text-right">Cập Nhật</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $scope_badges = [
                            'all'        => ['Toàn Tập Đoàn', 'bg-red-50 text-red-700 border-red-200'],
                            'branch'     => ['Theo Chi Nhánh', 'bg-amber-50 text-amber-700 border-amber-200'],
                            'department' => ['Theo Phòng Ban', 'bg-sky-50 text-sky-700 border-sky-200'],
                            'self'       => ['Cá Nhân (Self)', 'bg-slate-100 text-slate-700 border-slate-200'],
                        ];
                        ?>
                        <?php foreach ($admin_users as $u): ?>
                            <?php $sb = $u['is_superadmin'] ? ['Toàn Quyền (Super Admin)', 'bg-amber-100 text-amber-800 border-amber-200'] : ($scope_badges[$u['role_scope'] ?? 'self'] ?? $scope_badges['self']); ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs border border-indigo-200 flex-shrink-0">
                                            <?= strtoupper(mb_substr($u['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-800 flex items-center gap-2">
                                                <span><?= e($u['fullname']) ?></span>
                                                <?php if (!empty($u['employee_code'])): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono text-[10px] font-bold"><?= e($u['employee_code']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-slate-400">@<?= e($u['username']) ?> &bull; <?= e($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-5 text-xs">
                                    <?php if (!empty($u['position_name'])): ?>
                                        <div class="font-semibold text-slate-700"><?= e($u['position_name']) ?></div>
                                        <div class="text-slate-400 mt-0.5"><?= e($u['department_name'] ?? '---') ?> &bull; <?= e($u['branch_name'] ?? '---') ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Tài khoản hệ thống (không gắn hồ sơ NV)</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $sb[1] ?>">
                                        <?php if ($u['is_superadmin']): ?>
                                            <i class="fa-solid fa-crown text-[10px] mr-1.5"></i>
                                        <?php endif; ?>
                                        <?= e($sb[0]) ?>
                                    </span>
                                </td>

                                <!-- Form gán Vai trò trực tiếp cho từng user -->
                                <td class="py-4 px-5">
                                    <?php if ($u['is_superadmin']): ?>
                                        <span class="text-xs text-slate-400 italic">Không áp dụng (Super Admin tự có toàn quyền)</span>
                                    <?php else: ?>
                                        <form id="assignForm_<?= $u['id'] ?>" action="index.php" method="POST" class="flex items-center gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="assign_user_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="assigned_role_id" 
                                                    class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none w-60">
                                                <option value="">-- Chưa Gán Vai Trò --</option>
                                                <?php foreach ($roles as $r): ?>
                                                    <option value="<?= $r['id'] ?>" <?= ($u['role_id'] == $r['id']) ? 'selected' : '' ?>>
                                                        <?= e($r['name']) ?> [<?= e($r['data_scope'] ?? 'self') ?>]
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-6 text-right">
                                    <?php if (!$u['is_superadmin']): ?>
                                        <button type="submit" form="assignForm_<?= $u['id'] ?>" 
                                                class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-600 text-xs font-semibold rounded-lg border border-indigo-200 transition">
                                            Lưu Vai Trò
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

<!-- Modal Tạo Vai Trò Mới -->
<div id="createRoleModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base">Thêm Vai Trò Mới Cho Admin Con</h3>
            <button onclick="document.getElementById('createRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="index.php" method="POST" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="create_role">
            
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Tên Vai Trò Mới *</label>
                <input type="text" name="role_name" required placeholder="Ví dụ: Admin Tuyển Dụng, Admin Chấm Công..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Phạm Vi Dữ Liệu (Data Scope)</label>
                <select name="data_scope" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                    <option value="self">Cá Nhân (Self)</option>
                    <option value="department">Theo Khối / Phòng Ban (Department)</option>
                    <option value="branch">Theo Chi Nhánh (Branch)</option>
                    <option value="all">Toàn Tập Đoàn (All)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mô Tả Nhiệm Vụ</label>
                <textarea name="description" rows="3" placeholder="Ghi chú trách nhiệm của Admin giữ vai trò này..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('createRoleModal').classList.add('hidden')"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Tạo & Chuyển Đến Ma Trận
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Chọn hoặc bỏ chọn toàn bộ ma trận
function toggleAllMatrix(state) {
    document.querySelectorAll('.matrix-checkbox').forEach(cb => {
        cb.checked = state;
    });
}

// Bật/tắt tất cả các quyền của 1 dòng (1 module)
function toggleRow(moduleKey) {
    const checkboxes = document.querySelectorAll('.matrix-' + moduleKey);
    if (checkboxes.length === 0) return;
    
    // Nếu có ít nhất 1 ô chưa check thì check hết, ngược lại bỏ hết
    const hasUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
    checkboxes.forEach(cb => {
        cb.checked = hasUnchecked;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>