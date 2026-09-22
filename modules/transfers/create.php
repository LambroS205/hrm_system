<?php
// modules/transfers/create.php - Lập Đề Xuất / Quyết Định Thuyên Chuyển Công Tác Mới
$page_title = 'Lập Phiếu Thuyên Chuyển Công Tác';

require_once __DIR__ . '/../../core/auth.php';
require_permission('transfers', 'create');

$user = current_user();

// Nhận tham số truyền vào từ URL nếu có (từ OrgChart hoặc Danh sách Nhân sự)
$preset_employee_id = (int)($_GET['employee_id'] ?? 0);
$preset_to_branch_id = (int)($_GET['to_branch_id'] ?? 0);
$preset_to_dept_id = (int)($_GET['to_department_id'] ?? 0);

// Xử lý Lưu Phiếu Thuyên Chuyển
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $from_branch_id = (int)($_POST['from_branch_id'] ?? 0);
    $from_department_id = !empty($_POST['from_department_id']) ? (int)$_POST['from_department_id'] : null;
    $from_position_id = !empty($_POST['from_position_id']) ? (int)$_POST['from_position_id'] : null;

    $to_branch_id = (int)($_POST['to_branch_id'] ?? 0);
    $to_department_id = (int)($_POST['to_department_id'] ?? 0);
    $to_position_id = (int)($_POST['to_position_id'] ?? 0);

    $transfer_type = $_POST['transfer_type'] ?? 'branch_transfer';
    $effective_date = !empty($_POST['effective_date']) ? $_POST['effective_date'] : date('Y-m-d');
    $decision_number = trim($_POST['decision_number'] ?? '');
    $allowance_support = (float)str_replace(['.', ','], '', $_POST['allowance_support'] ?? '0');
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $execute_immediately = isset($_POST['execute_immediately']) && has_permission('transfers', 'approve');

    if ($employee_id > 0 && $to_branch_id > 0 && $to_department_id > 0 && $to_position_id > 0 && !empty($reason)) {
        try {
            $pdo->beginTransaction();

            // Sinh mã phiếu thuyên chuyển tự động (VD: TC-2026-002...)
            $last_id = (int)($pdo->query("SELECT MAX(id) FROM transfers")->fetchColumn() ?: 0);
            $transfer_code = 'TC-' . date('Y') . '-' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);

            if (empty($decision_number)) {
                $decision_number = 'QĐ-' . str_pad($last_id + 1, 2, '0', STR_PAD_LEFT) . '/QĐ-ĐĐ-' . date('Y');
            }

            $status = $execute_immediately ? 'executed' : 'pending';
            $approved_by = $execute_immediately ? $user['id'] : null;
            $approved_at = $execute_immediately ? date('Y-m-d H:i:s') : null;
            $executed_at = $execute_immediately ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("
                INSERT INTO transfers (
                    transfer_code, employee_id, from_branch_id, to_branch_id, 
                    from_department_id, to_department_id, from_position_id, to_position_id, 
                    transfer_type, reason, effective_date, decision_number, 
                    allowance_support, status, requested_by, approved_by, 
                    approved_at, executed_at, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transfer_code, $employee_id, $from_branch_id, $to_branch_id,
                $from_department_id, $to_department_id, $from_position_id, $to_position_id,
                $transfer_type, $reason, $effective_date, $decision_number,
                $allowance_support, $status, $user['id'], $approved_by,
                $approved_at, $executed_at, $notes
            ]);

            // Nếu chọn thực thi ngay, cập nhật thông tin nhân sự
            if ($execute_immediately) {
                $upEmp = $pdo->prepare("
                    UPDATE employees 
                    SET branch_id = ?, department_id = ?, position_id = ? 
                    WHERE id = ?
                ");
                $upEmp->execute([$to_branch_id, $to_department_id, $to_position_id, $employee_id]);
            }

            $pdo->commit();
            set_flash('success', $execute_immediately 
                ? "Đã tạo và thực thi điều động cán bộ thành công! Quyết định số: {$decision_number}" 
                : "Đã gửi đề xuất thuyên chuyển thành công. Chờ lãnh đạo phê duyệt!");
            
            redirect('modules/transfers/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi lưu dữ liệu: ' . $e->getMessage());
        }
    } else {
        set_flash('warning', 'Vui lòng điền đầy đủ các thông tin bắt buộc (Cán bộ, Chi nhánh đích, Phòng ban, Chức vụ và Lý do).');
    }
}

// 1. Lấy danh sách toàn bộ cán bộ nhân viên kèm thông tin chi nhánh & phòng ban hiện tại
$employees = $pdo->query("
    SELECT e.id, e.employee_code, e.fullname, e.branch_id, e.department_id, e.position_id,
           b.name AS branch_name, b.code AS branch_code,
           d.name AS department_name,
           p.name AS position_name, p.base_salary
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.employment_status != 'resigned'
    ORDER BY e.fullname ASC
")->fetchAll();

// 2. Lấy danh sách toàn bộ chi nhánh
$branches = $pdo->query("SELECT id, name, code, is_headquarter FROM branches WHERE status = 'active' ORDER BY is_headquarter DESC, name ASC")->fetchAll();

// 3. Lấy toàn bộ phòng ban kèm branch_id
$departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY branch_id ASC, name ASC")->fetchAll();

// 4. Lấy toàn bộ chức vụ
$positions = $pdo->query("SELECT id, name, base_salary FROM positions ORDER BY base_salary DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Tiêu Đề -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 mb-2">
                <i class="fa-solid fa-file-signature"></i> Quy Trình Điều Động Cán Bộ
            </div>
            <h2 class="text-xl font-bold text-slate-800">Lập Phiếu Đề Xuất Thuyên Chuyển Công Tác</h2>
            <p class="text-sm text-slate-500 mt-0.5">Thực hiện điều động nhân sự giữa các chi nhánh hoặc luân chuyển nội bộ phòng ban trong cơ quan.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 flex-shrink-0">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Danh Sách Lệnh</span>
        </a>
    </div>

    <!-- Form Đề Xuất Thuyên Chuyển -->
    <form action="create.php" method="POST" id="transferForm" class="space-y-6">

        <!-- Khối 1: Chọn Cán Bộ & Thông Tin Hiện Tại -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs">1</span>
                <span>Thông Tin Cán Bộ Được Đề Xuất Điều Động</span>
            </h3>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Chọn Cán Bộ / Nhân Viên *</label>
                <select name="employee_id" id="employeeSelect" required onchange="onSelectEmployee(this.value)"
                        class="w-full px-3.5 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tìm và chọn cán bộ công chức / nhân viên --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $preset_employee_id == $emp['id'] ? 'selected' : '' ?>>
                            <?= e($emp['fullname']) ?> (<?= e($emp['employee_code']) ?>) — [<?= e($emp['branch_code'] ?? 'Chưa gán') ?>] <?= e($emp['department_name'] ?? 'Chưa có phòng') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Khối hiển thị thông tin hiện tại tự động nạp từ JS -->
            <div id="currentInfoBox" class="p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100 hidden">
                <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 block mb-2">Vị Trí Công Tác Hiện Tại</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px]">Chi Nhánh Đang Công Tác:</span>
                        <strong id="curBranchName" class="text-slate-800 font-semibold block text-sm">---</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Phòng Ban Quản Lý:</span>
                        <strong id="curDeptName" class="text-slate-800 font-semibold block text-sm">---</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Chức Danh & Định Ngạch:</span>
                        <strong id="curPosName" class="text-indigo-600 font-semibold block text-sm">---</strong>
                    </div>
                </div>

                <!-- Hidden inputs lưu thông tin gốc -->
                <input type="hidden" name="from_branch_id" id="fromBranchId" value="">
                <input type="hidden" name="from_department_id" id="fromDeptId" value="">
                <input type="hidden" name="from_position_id" id="fromPosId" value="">
            </div>
        </div>

        <!-- Khối 2: Đơn Vị Tiếp Nhận Mới (Điểm Đến) -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs">2</span>
                <span>Đơn Vị Tiếp Nhận & Vị Trí Công Tác Mới</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chi Nhánh Tiếp Nhận *</label>
                    <select name="to_branch_id" id="toBranchSelect" required onchange="filterDepartmentsByBranch(this.value)"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chi Nhánh Tiếp Nhận --</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= $preset_to_branch_id == $br['id'] ? 'selected' : '' ?>>
                                <?= $br['is_headquarter'] ? '★ ' : '• ' ?><?= e($br['name']) ?> (<?= e($br['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Phòng Ban Mới *</label>
                    <select name="to_department_id" id="toDeptSelect" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Phòng Ban --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" data-branch-id="<?= $d['branch_id'] ?>" <?= $preset_to_dept_id == $d['id'] ? 'selected' : '' ?>>
                                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Chức Danh / Vị Trí Mới *</label>
                    <select name="to_position_id" id="toPosSelect" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Chọn Chức Danh Công Tác Mới --</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['name']) ?> (Lương cơ bản: <?= format_money($p['base_salary']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Hình Thức Thuyên Chuyển *</label>
                    <select name="transfer_type" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="branch_transfer">🏢 Điều Động Liên Chi Nhánh</option>
                        <option value="promotion">⭐ Thăng Chức & Bổ Nhiệm</option>
                        <option value="rotation">🔄 Luân Chuyển Cán Bộ Định Kỳ</option>
                        <option value="special_assignment">⚡ Biệt Phái Công Tác Có Thời Hạn</option>
                        <option value="demotion">Giáng Chức / Điều Chuyển Khác</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Khối 3: Quyết Định, Phụ Cấp & Chế Độ Đãi Ngộ -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-5">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center text-xs">3</span>
                <span>Văn Bản Căn Cứ & Chế Độ Đãi Ngộ</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Ngày Có Hiệu Lực *</label>
                    <input type="date" name="effective_date" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Số Quyết Định Điều Động</label>
                    <input type="text" name="decision_number" placeholder="Tự sinh nếu để trống..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Phụ Cấp Chuyển Vùng (VNĐ)</label>
                    <input type="number" name="allowance_support" min="0" step="100000" placeholder="VD: 5000000"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Lý Do Điều Động / Quyết Định Của Tổ Chức *</label>
                <textarea name="reason" rows="3" required placeholder="Nêu rõ lý do (Tăng cường nhân sự chi nhánh mới, đáp ứng nhu cầu sản xuất kinh doanh, luân chuyển nguồn...)"
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Ghi Chú & Bàn Giao Công Việc</label>
                <textarea name="notes" rows="2" placeholder="Ghi chú về hồ sơ bàn giao, hỗ trợ nhà ở công vụ..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <?php if (has_permission('transfers', 'approve')): ?>
                <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-emerald-900 block">Phê duyệt & Thực thi chuyển hồ sơ ngay</span>
                        <span class="text-[11px] text-emerald-700">Tự động cập nhật cán bộ sang chi nhánh mới mà không cần chờ duyệt</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="execute_immediately" value="1" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <!-- Nút Gửi Form -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="index.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Hủy Bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Xác Nhận & Lưu Quyết Định</span>
            </button>
        </div>

    </form>

</div>

<script>
const employeesData = <?= json_encode($employees) ?>;

function onSelectEmployee(empId) {
    empId = parseInt(empId);
    const box = document.getElementById('currentInfoBox');
    
    if (!empId) {
        box.classList.add('hidden');
        return;
    }

    const emp = employeesData.find(e => parseInt(e.id) === empId);
    if (emp) {
        document.getElementById('curBranchName').innerText = emp.branch_name || 'Chưa gán chi nhánh';
        document.getElementById('curDeptName').innerText = emp.department_name || 'Chưa gán phòng ban';
        document.getElementById('curPosName').innerText = emp.position_name || 'Chưa có chức danh';

        document.getElementById('fromBranchId').value = emp.branch_id || '';
        document.getElementById('fromDeptId').value = emp.department_id || '';
        document.getElementById('fromPosId').value = emp.position_id || '';

        box.classList.remove('hidden');
    }
}

function filterDepartmentsByBranch(branchId) {
    branchId = parseInt(branchId);
    const deptSelect = document.getElementById('toDeptSelect');
    const options = deptSelect.querySelectorAll('option');

    options.forEach(opt => {
        if (!opt.value) return; // giữ option placeholder
        const optBranchId = parseInt(opt.getAttribute('data-branch-id'));
        if (!branchId || optBranchId === branchId) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });

    // Reset lựa chọn nếu phòng ban hiện tại không thuộc chi nhánh mới
    const currentOpt = deptSelect.selectedOptions[0];
    if (currentOpt && currentOpt.value && parseInt(currentOpt.getAttribute('data-branch-id')) !== branchId) {
        deptSelect.value = '';
    }
}

// Khởi tạo nếu có preset
document.addEventListener('DOMContentLoaded', () => {
    const initialEmpId = document.getElementById('employeeSelect').value;
    if (initialEmpId) {
        onSelectEmployee(initialEmpId);
    }
    const initialBranchId = document.getElementById('toBranchSelect').value;
    if (initialBranchId) {
        filterDepartmentsByBranch(initialBranchId);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
