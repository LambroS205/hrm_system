<?php
// modules/payroll/holidays.php - Quản lý Danh mục Ngày nghỉ Lễ, Tết & Lịch nghỉ bù theo Luật Lao động
$page_title = 'Danh Mục Ngày Nghỉ Lễ & Nghỉ Bù';

require_once __DIR__ . '/../../core/auth.php';
require_permission('payroll', 'view');

$selected_year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Xử lý Thêm / Xóa ngày lễ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    $action_type = $_POST['action_type'];

    if ($action_type === 'create_holiday') {
        require_permission('payroll', 'edit');
        $h_date = trim($_POST['holiday_date'] ?? '');
        $h_name = trim($_POST['name'] ?? '');
        $h_comp = !empty($_POST['is_compensatory']) ? 1 : 0;

        if (!empty($h_date) && !empty($h_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO public_holidays (holiday_date, name, is_compensatory) VALUES (?, ?, ?)");
                $stmt->execute([$h_date, $h_name, $h_comp]);
                set_flash('success', "Đã thêm ngày lễ '{$h_name}' (" . format_date($h_date) . ")");
            } catch (PDOException $e) {
                set_flash('danger', 'Ngày này đã tồn tại trong danh mục hoặc có lỗi: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', 'Vui lòng nhập đầy đủ ngày và tên ngày nghỉ lễ.');
        }
        redirect("modules/payroll/holidays.php?year={$selected_year}");
    }

    if ($action_type === 'delete_holiday') {
        require_permission('payroll', 'delete');
        $h_id = (int)($_POST['holiday_id'] ?? 0);
        if ($h_id > 0) {
            $pdo->prepare("DELETE FROM public_holidays WHERE id = ?")->execute([$h_id]);
            set_flash('success', 'Đã xóa ngày nghỉ lễ khỏi danh mục.');
        }
        redirect("modules/payroll/holidays.php?year={$selected_year}");
    }
}

// Lấy danh sách ngày lễ trong năm đã chọn
$stmt = $pdo->prepare("
    SELECT * FROM public_holidays 
    WHERE YEAR(holiday_date) = ? 
    ORDER BY holiday_date ASC
");
$stmt->execute([$selected_year]);
$holidays = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Tiêu đề & Điều hướng -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 mb-2">
                <i class="fa-solid fa-calendar-star"></i> Quy Định Nghỉ Lễ - Điều 112 Bộ Luật Lao Động
            </div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white">Lịch Nghỉ Lễ, Tết & Nghỉ Bù Năm <?= $selected_year ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Ngày lễ rơi vào ngày làm việc (Thứ 2 - Thứ 6) được hệ thống tự động cộng vào ngày công hưởng nguyên lương của nhân sự.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="<?= base_url('modules/payroll/index.php') ?>" 
               class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Quay Lại Bảng Lương</span>
            </a>
            <?php if (has_permission('payroll', 'edit')): ?>
                <button onclick="document.getElementById('addHolidayModal').classList.remove('hidden')" 
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-100 dark:shadow-none transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Thêm Ngày Lễ</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thanh Lọc Năm -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
        <form action="holidays.php" method="GET" class="flex items-center gap-3">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Chọn Năm:</span>
            <select name="year" onchange="this.form.submit()" 
                    class="px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                <?php for ($y = 2024; $y <= 2028; $y++): ?>
                    <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <div class="text-xs text-slate-500 dark:text-slate-400">
            Tổng cộng: <strong class="text-indigo-600 dark:text-indigo-400 font-mono"><?= count($holidays) ?></strong> ngày nghỉ lễ trong năm <?= $selected_year ?>
        </div>
    </div>

    <!-- Bảng Danh Sách Ngày Lễ -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-6">Ngày Nghỉ Lễ</th>
                        <th class="py-3.5 px-6">Thứ Trong Tuần</th>
                        <th class="py-3.5 px-6">Tên Dịp Lễ / Tết</th>
                        <th class="py-3.5 px-6 text-center">Tính Chất</th>
                        <th class="py-3.5 px-6 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (empty($holidays)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400 text-sm">
                                <i class="fa-solid fa-calendar-xmark text-3xl mb-2 block text-slate-300"></i>
                                Chưa có ngày nghỉ lễ nào được khai báo cho năm <?= $selected_year ?>.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($holidays as $h): 
                        $time = strtotime($h['holiday_date']);
                        $dayOfWeekMap = [
                            1 => 'Thứ Hai',
                            2 => 'Thứ Ba',
                            3 => 'Thứ Tư',
                            4 => 'Thứ Năm',
                            5 => 'Thứ Sáu',
                            6 => 'Thứ Bảy (Cuối tuần)',
                            7 => 'Chủ Nhật (Cuối tuần)'
                        ];
                        $dow = (int)date('N', $time);
                        $isWeekend = ($dow >= 6);
                    ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition">
                            <td class="py-4 px-6 font-mono font-bold text-slate-800 dark:text-slate-100">
                                <i class="fa-regular fa-calendar text-indigo-500 mr-2"></i>
                                <?= format_date($h['holiday_date']) ?>
                            </td>
                            <td class="py-4 px-6">
                                <span class="<?= $isWeekend ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-slate-700 dark:text-slate-300' ?>">
                                    <?= $dayOfWeekMap[$dow] ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200">
                                <?= e($h['name']) ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php if ($h['is_compensatory']): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        Nghỉ bù
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Lễ chính thức
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <?php if (has_permission('payroll', 'delete')): ?>
                                    <form action="holidays.php?year=<?= $selected_year ?>" method="POST" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa ngày lễ này?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="delete_holiday">
                                        <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-100 text-rose-600 dark:text-rose-400 inline-flex items-center justify-center transition" title="Xóa ngày lễ">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Thêm Ngày Lễ -->
<div id="addHolidayModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-slate-800 dark:text-white text-base">Thêm Ngày Nghỉ Lễ / Tết</h3>
            <button onclick="document.getElementById('addHolidayModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="holidays.php?year=<?= $selected_year ?>" method="POST" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="create_holiday">

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Ngày Nghỉ Lễ (YYYY-MM-DD) *</label>
                <input type="date" name="holiday_date" required value="<?= $selected_year ?>-01-01"
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Tên Dịp Nghỉ Lễ *</label>
                <input type="text" name="name" required placeholder="VD: Ngày Quốc khánh 2/9, Tết Dương lịch..."
                       class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_compensatory" id="isComp" value="1" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                <label for="isComp" class="text-xs text-slate-600 dark:text-slate-300 font-medium">Là ngày nghỉ bù (khi lễ chính rơi vào Thứ 7 hoặc Chủ Nhật)</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="document.getElementById('addHolidayModal').classList.add('hidden')"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition">
                    Hủy Bỏ
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Lưu Ngày Lễ
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
