<?php
// modules/departments/index.php - Quản lý Phòng ban và Chức vụ / Chức danh
$page_title = 'Cơ Cấu Tổ Chức & Chức Danh';

require_once __DIR__ . '/../../core/auth.php';
require_permission('departments', 'view');

// Xử lý tạo hoặc chỉnh sửa Phòng ban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    // 1. Thêm mới Phòng Ban
    if ($action_type === 'create_dept') {
        require_permission('departments', 'create');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($code) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (code, name, description) VALUES (?, ?, ?)");
                $stmt->execute([$code, $name, $description]);
                set_flash('success', "Đã thêm thành công phòng ban: {$name} ({$code})");
                redirect('modules/departments/index.php');
            } catch (PDOException $e) {
                set_flash('danger', 'Mã phòng ban đã tồn tại hoặc dữ liệu không hợp lệ.');
            }
        } else {
            set_flash('warning', 'Vui lòng điền đầy đủ Mã phòng ban và Tên phòng ban.');
        }
    }

    // 2. Chỉnh sửa Phòng Ban
    if ($action_type === 'edit_dept') {
        require_permission('departments', 'edit');
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($dept_id > 0 && !empty($code) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET code = ?, name = ?, description = ? WHERE id = ?");
                $stmt->execute([$code, $name, $description, $dept_id]);
                set_flash('success', "Đã cập nhật thông tin phòng ban: {$name}");
                redirect('modules/departments/index.php');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi cập nhật: Mã phòng ban có thể đã trùng lặp.');
            }
        }
    }

    // 3. Xóa Phòng Ban
    if ($action_type === 'delete_dept') {
        require_permission('departments', 'delete');
        $dept_id = (int)($_POST['dept_id'] ?? 0);

        if ($dept_id > 0) {
            // Kiểm tra xem phòng ban này có nhân viên không
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
            $checkStmt->execute([$dept_id]);
            $hasEmployees = $checkStmt->fetchColumn();

            if ($hasEmployees > 0) {
                set_flash('danger', "Không thể xóa phòng ban này vì đang có {$hasEmployees} nhân viên trực thuộc. Vui lòng chuyển công tác nhân viên trước.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $delStmt->execute([$dept_id]);
                set_flash('success', 'Đã xóa phòng ban thành công.');
            }
            redirect('modules/departments/index.php');
        }
    }

    // 4. Thêm mới Chức Vụ
    if ($action_type === 'create_position') {
        require_permission('departments', 'create');
        $name = trim($_POST['name'] ?? '');
        $base_salary = (float)str_replace(['.', ','], '', $_POST['base_salary'] ?? '0');

        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO positions (name, base_salary) VALUES (?, ?)");
                $stmt->execute([$name, $base_salary]);
                set_flash('success', "Đã thêm chức danh mới: {$name}");
                redirect('modules/departments/index.php?tab=positions');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi khi thêm chức danh: ' . $e->getMessage());
            }
        }
    }

    // 5. Chỉnh sửa Chức Vụ
    if ($action_type === 'edit_position') {
        require_permission('departments', 'edit');
        $pos_id = (int)($_POST['pos_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $base_salary = (float)str_replace(['.', ','], '', $_POST['base_salary'] ?? '0');

        if ($pos_id > 0 && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE positions SET name = ?, base_salary = ? WHERE id = ?");
                $stmt->execute([$name, $base_salary, $pos_id]);
                set_flash('success', "Đã cập nhật chức danh: {$name}");
                redirect('modules/departments/index.php?tab=positions');
            } catch (PDOException $e) {
                set_flash('danger', 'Lỗi khi cập nhật chức danh: ' . $e->getMessage());
            }
        }
    }

    // 6. Xóa Chức Vụ
    if ($action_type === 'delete_position') {
        require_permission('departments', 'delete');
        $pos_id = (int)($_POST['pos_id'] ?? 0);

        if ($pos_id > 0) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE position_id = ?");
            $checkStmt->execute([$pos_id]);
            $hasEmployees = $checkStmt->fetchColumn();

            if ($hasEmployees > 0) {
                set_flash('danger', "Không thể xóa chức danh này vì đang có {$hasEmployees} nhân viên đảm nhiệm.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM positions WHERE id = ?");
                $delStmt->execute([$pos_id]);
                set_flash('success', 'Đã xóa chức danh thành công.');
            }
            redirect('modules/departments/index.php?tab=positions');
        }
    }
}

// Truy vấn danh sách phòng ban kèm số lượng nhân viên thực tế
$departments = $pdo->query("
    SELECT d.*, COUNT(e.id) AS total_employees 
    FROM departments d 
    LEFT JOIN employees e ON d.id = e.department_id 
    GROUP BY d.id 
    ORDER BY d.id ASC
")->fetchAll();

// Truy vấn danh sách chức vụ kèm số lượng nhân viên
$positions = $pdo->query("
    SELECT p.*, COUNT(e.id) AS total_employees 
    FROM positions p 
    LEFT JOIN employees e ON p.id = e.position_id 
    GROUP BY p.id 
    ORDER BY p.base_salary DESC
")->fetchAll();

$active_tab = $_GET['tab'] ?? 'departments';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu Đề -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 mb-2">
                <i class="fa-solid fa-sitemap"></i> Khối Cơ Cấu Vận Hành
            </div>
            <h2 class="text-xl font-bold text-slate-800">Quản Lý Phòng Ban & Chức Danh</h2>
            <p class="text-sm text-slate-500 mt-0.5">Xây dựng cơ cấu tổ chức doanh nghiệp và định mức thang bảng lương cơ bản cho từng vị trí công tác.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (has_permission('departments', 'create')): ?>
                <?php if ($active_tab === 'departments'): ?>
                    <button onclick="openCreateDeptModal()" 
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Thêm Phòng Ban Mới</span>
                    </button>
                <?php else: ?>
                    <button onclick="openCreatePosModal()" 
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Thêm Chức Vụ Mới</span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thanh Chuyển Đổi Tab -->
    <div class="flex border-b border-slate-200">
        <a href="?tab=departments" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'departments' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-building"></i>
            <span>1. Danh Sách Phòng Ban (<?= count($departments) ?>)</span>
        </a>
        <a href="?tab=positions" 
           class="px-5 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition <?= $active_tab === 'positions' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-id-card-clip"></i>
            <span>2. Danh Mục Chức Vụ & Thang Lương (<?= count($positions) ?>)</span>
        </a>
    </div>

    <?php if ($active_tab === 'departments'): ?>
        <!-- ================= TAB 1: PHÒNG BAN ================= -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Mã & Tên Phòng Ban</th>
                            <th class="py-3.5 px-6">Mô Tả Chức Năng</th>
                            <th class="py-3.5 px-6 text-center">Quy Mô Nhân Sự</th>
                            <th class="py-3.5 px-6 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-slate-400 text-sm">Chưa có phòng ban nào trong hệ thống.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($departments as $d): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-sm border border-sky-100 flex-shrink-0">
                                            <?= e($d['code']) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm"><?= e($d['name']) ?></div>
                                            <div class="text-[11px] text-slate-400">Tạo ngày: <?= format_date($d['created_at']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-slate-600 text-xs max-w-md">
                                    <?= e($d['description'] ?: 'Không có mô tả') ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <a href="<?= base_url('modules/employees/index.php?department_id=' . $d['id']) ?>" 
                                       class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                                        <i class="fa-solid fa-users text-[10px]"></i>
                                        <span><?= $d['total_employees'] ?> thành viên</span>
                                    </a>
                                </td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <?php if (has_permission('departments', 'edit')): ?>
                                        <button onclick='openEditDeptModal(<?= json_encode($d) ?>)' 
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-500 inline-flex items-center justify-center transition"
                                                title="Chỉnh sửa">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (has_permission('departments', 'delete')): ?>
                                        <button onclick="confirmDeleteDept(<?= $d['id'] ?>, '<?= e($d['name']) ?>')" 
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
        </div>

    <?php else: ?>
        <!-- ================= TAB 2: CHỨC VỤ & THANG LƯƠNG ================= -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Tên Chức Danh / Vị Trí</th>
                            <th class="py-3.5 px-6 text-right">Định Mức Lương Cơ Bản</th>
                            <th class="py-3.5 px-6 text-center">Số Nhân Sự Đảm Nhiệm</th>
                            <th class="py-3.5 px-6 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($positions)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-slate-400 text-sm">Chưa có chức danh nào.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($positions as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm font-bold border border-purple-100">
                                            <i class="fa-solid fa-user-tie"></i>
                                        </div>
                                        <div class="font-bold text-slate-800 text-sm"><?= e($p['name']) ?></div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-right font-mono font-semibold text-emerald-600">
                                    <?= format_money($p['base_salary']) ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                        <?= $p['total_employees'] ?> nhân sự
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <?php if (has_permission('departments', 'edit')): ?>
                                        <button onclick='openEditPosModal(<?= json_encode($p) ?>)' 
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-500 inline-flex items-center justify-center transition"
                                                title="Chỉnh sửa">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (has_permission('departments', 'delete')): ?>
                                        <button onclick="confirmDeletePos(<?= $p['id'] ?>, '<?= e($p['name']) ?>')" 
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
        </div>
    <?php endif; ?>

</div>

<!-- Modal Thêm/Sửa Phòng Ban -->
<div id="deptModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="deptModalTitle" class="font-bold text-slate-800 text-base">Thêm Phòng Ban Mới</h3>
            <button onclick="closeDeptModal()" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="deptForm" action="index.php?tab=departments" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action_type" id="deptActionType" value="create_dept">
            <input type="hidden" name="dept_id" id="deptId" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mã Phòng Ban (Code) *</label>
                <input type="text" name="code" id="deptCode" required placeholder="VD: IT, HR, MKT, ACC..."
                       class="w-full uppercase px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Tên Phòng Ban *</label>
                <input type="text" name="name" id="deptName" required placeholder="VD: Phòng Công Nghệ Thông Tin..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Mô Tả Trách Nhiệm</label>
                <textarea name="description" id="deptDescription" rows="3" placeholder="Chức năng, nhiệm vụ phòng ban..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeDeptModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu Dữ Liệu
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Thêm/Sửa Chức Vụ -->
<div id="posModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="posModalTitle" class="font-bold text-slate-800 text-base">Thêm Chức Vụ Mới</h3>
            <button onclick="closePosModal()" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="posForm" action="index.php?tab=positions" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action_type" id="posActionType" value="create_position">
            <input type="hidden" name="pos_id" id="posId" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Tên Chức Vụ / Vị Trí *</label>
                <input type="text" name="name" id="posName" required placeholder="VD: Kỹ Sư Phần Mềm, Trưởng Phòng..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Định Mức Lương Cơ Bản (VNĐ) *</label>
                <input type="number" name="base_salary" id="posBaseSalary" required min="0" step="100000" placeholder="VD: 15000000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closePosModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                    Hủy
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu Dữ Liệu
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xác Nhận Xóa Chung (Không dùng confirm popup của trình duyệt) -->
<div id="deleteModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base mb-1">Xác Nhận Xóa Dữ Liệu?</h3>
        <p id="deleteModalMsg" class="text-xs text-slate-500 mb-6">Thao tác này không thể hoàn tác nếu đã thực hiện.</p>
        
        <form id="deleteForm" method="POST">
            <input type="hidden" name="action_type" id="delActionType" value="">
            <input type="hidden" name="dept_id" id="delDeptId" value="">
            <input type="hidden" name="pos_id" id="delPosId" value="">
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
// Quản lý Modal Phòng Ban
function openCreateDeptModal() {
    document.getElementById('deptModalTitle').innerText = 'Thêm Phòng Ban Mới';
    document.getElementById('deptActionType').value = 'create_dept';
    document.getElementById('deptId').value = '';
    document.getElementById('deptCode').value = '';
    document.getElementById('deptName').value = '';
    document.getElementById('deptDescription').value = '';
    document.getElementById('deptModal').classList.remove('hidden');
}

function openEditDeptModal(dept) {
    document.getElementById('deptModalTitle').innerText = 'Chỉnh Sửa Phòng Ban';
    document.getElementById('deptActionType').value = 'edit_dept';
    document.getElementById('deptId').value = dept.id;
    document.getElementById('deptCode').value = dept.code;
    document.getElementById('deptName').value = dept.name;
    document.getElementById('deptDescription').value = dept.description || '';
    document.getElementById('deptModal').classList.remove('hidden');
}

function closeDeptModal() {
    document.getElementById('deptModal').classList.add('hidden');
}

// Quản lý Modal Chức Vụ
function openCreatePosModal() {
    document.getElementById('posModalTitle').innerText = 'Thêm Chức Vụ Mới';
    document.getElementById('posActionType').value = 'create_position';
    document.getElementById('posId').value = '';
    document.getElementById('posName').value = '';
    document.getElementById('posBaseSalary').value = '';
    document.getElementById('posModal').classList.remove('hidden');
}

function openEditPosModal(pos) {
    document.getElementById('posModalTitle').innerText = 'Chỉnh Sửa Chức Vụ';
    document.getElementById('posActionType').value = 'edit_position';
    document.getElementById('posId').value = pos.id;
    document.getElementById('posName').value = pos.name;
    document.getElementById('posBaseSalary').value = pos.base_salary;
    document.getElementById('posModal').classList.remove('hidden');
}

function closePosModal() {
    document.getElementById('posModal').classList.add('hidden');
}

// Modal Xóa
function confirmDeleteDept(id, name) {
    document.getElementById('deleteForm').action = 'index.php?tab=departments';
    document.getElementById('delActionType').value = 'delete_dept';
    document.getElementById('delDeptId').value = id;
    document.getElementById('delPosId').value = '';
    document.getElementById('deleteModalMsg').innerText = `Bạn có chắc chắn muốn xóa phòng ban "${name}"?`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function confirmDeletePos(id, name) {
    document.getElementById('deleteForm').action = 'index.php?tab=positions';
    document.getElementById('delActionType').value = 'delete_position';
    document.getElementById('delDeptId').value = '';
    document.getElementById('delPosId').value = id;
    document.getElementById('deleteModalMsg').innerText = `Bạn có chắc chắn muốn xóa chức danh "${name}"?`;
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>