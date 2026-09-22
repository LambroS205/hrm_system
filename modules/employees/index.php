<?php
// modules/employees/index.php - Quản lý Danh sách hồ sơ nhân viên
$page_title = 'Hồ Sơ Nhân Viên';

require_once __DIR__ . '/../../core/auth.php';
require_permission('employees', 'view');

// Xử lý Xóa nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'delete_employee') {
    verify_csrf();
    require_permission('employees', 'delete');
    $emp_id = (int)($_POST['employee_id'] ?? 0);

    if ($emp_id > 0) {
        // Lấy thông tin avatar để xóa file ảnh vật lý nếu có
        $stmt = $pdo->prepare("SELECT avatar, fullname FROM employees WHERE id = ?");
        $stmt->execute([$emp_id]);
        $emp = $stmt->fetch();

        if ($emp) {
            if (!empty($emp['avatar'])) {
                $avatar_path = __DIR__ . '/../../assets/uploads/' . $emp['avatar'];
                if (file_exists($avatar_path)) {
                    unlink($avatar_path);
                }
            }
            // Xóa bản ghi nhân viên (ràng buộc khóa ngoại sẽ tự xử lý hoặc ngắt liên kết)
            $delStmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $delStmt->execute([$emp_id]);
            set_flash('success', "Đã xóa hồ sơ nhân viên: {$emp['fullname']}");
        }
    }
    redirect('modules/employees/index.php');
}

// Nhận các tham số tìm kiếm & lọc
$keyword = trim($_GET['search'] ?? '');
$branch_filter = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$dept_filter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$status_filter = trim($_GET['status'] ?? '');

// Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Xây dựng câu SQL linh hoạt
$where_clauses = ['1=1'];
$params = [];

if (!empty($keyword)) {
    $where_clauses[] = "(e.fullname LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR e.phone LIKE ?)";
    $search_param = "%{$keyword}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($branch_filter > 0) {
    $where_clauses[] = "e.branch_id = ?";
    $params[] = $branch_filter;
}

if ($dept_filter > 0) {
    $where_clauses[] = "e.department_id = ?";
    $params[] = $dept_filter;
}

if (!empty($status_filter)) {
    $where_clauses[] = "e.employment_status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Đếm tổng số bản ghi
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE {$where_sql}");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy danh sách nhân viên kèm thông tin chi nhánh, phòng ban & chức vụ
$query_sql = "
    SELECT e.*, 
           b.name AS branch_name, b.code AS branch_code,
           d.name AS department_name, d.code AS department_code, 
           p.name AS position_name 
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN positions p ON e.position_id = p.id 
    WHERE {$where_sql} 
    ORDER BY e.id DESC 
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Lấy danh sách chi nhánh & phòng ban để làm dropdown bộ lọc
$branches = $pdo->query("SELECT id, name, code FROM branches ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();

// Thống kê nhanh cho các tab số liệu
$total_all = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn() ?: 0;
$total_official = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'official'")->fetchColumn() ?: 0;
$total_probation = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'probation'")->fetchColumn() ?: 0;
$total_resigned = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'resigned'")->fetchColumn() ?: 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Topbar Hồ Sơ & Nút Thêm Mới -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 mb-2">
                <i class="fa-solid fa-address-card"></i> Cơ Sở Dữ Liệu Nhân Sự
            </div>
            <h2 class="text-xl font-bold text-slate-800">Danh Sách Hồ Sơ Nhân Viên</h2>
            <p class="text-sm text-slate-500 mt-0.5">Tìm kiếm, lọc danh sách, theo dõi hợp đồng và quản lý thông tin lý lịch cán bộ nhân viên.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (has_permission('employees', 'create')): ?>
                <a href="<?= base_url('modules/employees/form.php') ?>" 
                   class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-100 transition flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Thêm Nhân Viên Mới</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Thẻ Đếm Thống Kê Nhanh -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="index.php" class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-indigo-200 transition flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tất Cả Nhân Sự</span>
                <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $total_all ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-users text-base"></i>
            </div>
        </a>
        <a href="index.php?status=official" class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-emerald-200 transition flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Chính Thức</span>
                <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $total_official ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i class="fa-solid fa-circle-check text-base"></i>
            </div>
        </a>
        <a href="index.php?status=probation" class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-amber-200 transition flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Đang Thử Việc</span>
                <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $total_probation ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="fa-solid fa-clock-rotate-left text-base"></i>
            </div>
        </a>
        <a href="index.php?status=resigned" class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-rose-200 transition flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-500">Đã Nghỉ Việc</span>
                <div class="text-xl font-bold text-slate-800 mt-0.5"><?= $total_resigned ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                <i class="fa-solid fa-user-xmark text-base"></i>
            </div>
        </a>
    </div>

    <!-- Khung Bộ Lọc & Tìm Kiếm -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-1">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= e($keyword) ?>" 
                           placeholder="Họ tên, mã NV, email..." 
                           class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>
            </div>

            <div>
                <select name="branch_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white font-medium text-slate-700">
                    <option value="">-- Tất cả chi nhánh --</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= ($branch_filter == $br['id']) ? 'selected' : '' ?>>
                            <?= e($br['name']) ?> (<?= e($br['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="department_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($dept_filter == $dept['id']) ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="official" <?= ($status_filter === 'official') ? 'selected' : '' ?>>Chính thức</option>
                    <option value="probation" <?= ($status_filter === 'probation') ? 'selected' : '' ?>>Thử việc</option>
                    <option value="resigned" <?= ($status_filter === 'resigned') ? 'selected' : '' ?>>Đã nghỉ việc</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-filter text-xs"></i> Lọc
                </button>
                <a href="index.php" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition flex items-center justify-center">
                    Đặt lại
                </a>
            </div>
        </form>
    </div>

    <!-- Bảng Danh Sách Nhân Viên -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-6">Nhân Viên</th>
                        <th class="py-3.5 px-6">Chi Nhánh & Phòng Ban</th>
                        <th class="py-3.5 px-6">Chức Danh / Vị Trí</th>
                        <th class="py-3.5 px-6 text-center">Trạng Thái</th>
                        <th class="py-3.5 px-6 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400 text-sm">
                                <i class="fa-regular fa-folder-open text-3xl mb-2 block text-slate-300"></i>
                                Không tìm thấy nhân viên nào phù hợp với điều kiện tìm kiếm.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Cột Avatar & Họ Tên -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $emp['avatar'])): ?>
                                        <img src="<?= base_url('assets/uploads/' . e($emp['avatar'])) ?>" 
                                             class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-xs" 
                                             alt="<?= e($emp['fullname']) ?>">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-violet-500 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                            <?= strtoupper(mb_substr($emp['fullname'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="view.php?id=<?= $emp['id'] ?>" class="font-bold text-slate-800 text-sm hover:text-indigo-600 transition">
                                            <?= e($emp['fullname']) ?>
                                        </a>
                                        <div class="text-[11px] text-slate-400 font-mono">
                                            Mã: <span class="font-semibold text-indigo-600"><?= e($emp['employee_code']) ?></span> • <?= e($emp['phone'] ?: 'Chưa có SĐT') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Cột Chi Nhánh & Phòng Ban -->
                            <td class="py-4 px-6 text-xs">
                                <div class="font-bold text-indigo-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-building-flag text-[10px] text-indigo-500"></i>
                                    <span><?= e($emp['branch_name'] ?? 'Chưa phân chi nhánh') ?></span>
                                </div>
                                <div class="text-slate-600 mt-0.5">
                                    <?= e($emp['department_name'] ?? 'Chưa gán phòng ban') ?>
                                </div>
                            </td>

                            <!-- Cột Chức Danh / Vị Trí -->
                            <td class="py-4 px-6 text-xs">
                                <div class="font-semibold text-slate-800">
                                    <?= e($emp['position_name'] ?? 'Chưa bổ nhiệm') ?>
                                </div>
                                <div class="text-slate-400 text-[10px] mt-0.5">
                                    Vào làm: <?= format_date($emp['hire_date']) ?>
                                </div>
                            </td>

                            <!-- Cột Trạng thái hợp đồng -->
                            <td class="py-4 px-6 text-center">
                                <?php if ($emp['employment_status'] === 'official'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Chính Thức
                                    </span>
                                <?php elseif ($emp['employment_status'] === 'probation'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Thử Việc
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Đã Nghỉ
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Cột Thao tác -->
                            <td class="py-4 px-6 text-right space-x-1 whitespace-nowrap">
                                <a href="view.php?id=<?= $emp['id'] ?>" 
                                   class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-500 inline-flex items-center justify-center transition"
                                   title="Xem hồ sơ & Lịch sử công tác">
                                    <i class="fa-regular fa-eye text-xs"></i>
                                </a>

                                <?php if (has_permission('transfers', 'create')): ?>
                                    <a href="<?= base_url('modules/transfers/create.php?employee_id=' . $emp['id']) ?>" 
                                       class="w-8 h-8 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 inline-flex items-center justify-center transition"
                                       title="Thuyên chuyển công tác">
                                        <i class="fa-solid fa-people-arrows text-xs"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (has_permission('employees', 'edit')): ?>
                                    <a href="form.php?id=<?= $emp['id'] ?>" 
                                       class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-500 inline-flex items-center justify-center transition"
                                       title="Sửa hồ sơ">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (has_permission('employees', 'delete')): ?>
                                    <button onclick="confirmDeleteEmployee(<?= $emp['id'] ?>, '<?= e($emp['fullname']) ?>')" 
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-500 inline-flex items-center justify-center transition"
                                            title="Xóa">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân Trang -->
        <?php if ($total_pages > 1): ?>
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <div>
                    Hiển thị <?= count($employees) ?> / <?= $total_records ?> nhân sự
                </div>
                <div class="flex items-center gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($keyword) ?>&department_id=<?= $dept_filter ?>&status=<?= urlencode($status_filter) ?>" 
                           class="w-8 h-8 flex items-center justify-center rounded-lg font-semibold transition <?= ($i == $page) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Xác Nhận Xóa Nhân Viên -->
<div id="deleteEmpModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-user-xmark"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xác Nhận Xóa Nhân Viên?</h3>
        <p id="deleteEmpMsg" class="text-xs text-slate-500 mb-6">Thao tác này sẽ xóa hồ sơ và ảnh đại diện của nhân viên khỏi hệ thống.</p>
        
        <form action="index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_employee">
            <input type="hidden" name="employee_id" id="deleteEmpId" value="">
            <div class="flex items-center justify-center gap-3">
                <button type="button" onclick="document.getElementById('deleteEmpModal').classList.add('hidden')" 
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
function confirmDeleteEmployee(id, name) {
    document.getElementById('deleteEmpId').value = id;
    document.getElementById('deleteEmpMsg').innerText = `Bạn có chắc chắn muốn xóa nhân viên "${name}"?`;
    document.getElementById('deleteEmpModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>