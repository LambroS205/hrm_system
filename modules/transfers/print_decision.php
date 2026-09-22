<?php
// modules/transfers/print_decision.php - Bản In Quyết Định Điều Động & Thuyên Chuyển Công Tác Chuẩn Mẫu Văn Bản
require_once __DIR__ . '/../../core/auth.php';
require_permission('transfers', 'view');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT 
        t.*,
        e.fullname AS emp_fullname,
        e.employee_code AS emp_code,
        e.identity_card AS emp_cmnd,
        e.birth_date AS emp_birth_date,
        fb.name AS from_branch_name,
        fb.code AS from_branch_code,
        tb.name AS to_branch_name,
        tb.code AS to_branch_code,
        fd.name AS from_dept_name,
        td.name AS to_dept_name,
        fp.name AS from_pos_name,
        tp.name AS to_pos_name,
        tp.base_salary AS new_base_salary,
        u_app.fullname AS approver_name
    FROM transfers t
    JOIN employees e ON t.employee_id = e.id
    JOIN branches fb ON t.from_branch_id = fb.id
    JOIN branches tb ON t.to_branch_id = tb.id
    LEFT JOIN departments fd ON t.from_department_id = fd.id
    JOIN departments td ON t.to_department_id = td.id
    LEFT JOIN positions fp ON t.from_position_id = fp.id
    JOIN positions tp ON t.to_position_id = tp.id
    LEFT JOIN users u_app ON t.approved_by = u_app.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    die("Không tìm thấy quyết định điều động này.");
}

$effective_time = strtotime($t['effective_date']);
$day = date('d', $effective_time);
$month = date('m', $effective_time);
$year = date('Y', $effective_time);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quyết Định Điều Động - <?= e($t['decision_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; }
            .print-container { box-shadow: none !important; border: none !important; margin: 0 !important; width: 100% !important; }
        }
        body { font-family: "Times New Roman", Times, serif; }
    </style>
</head>
<body class="bg-slate-100 py-10 min-h-screen text-slate-900 leading-normal">

    <!-- Thanh Điều Khiển Đầu Trang -->
    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print px-4">
        <a href="index.php" class="px-4 py-2 bg-white rounded-xl shadow-xs text-xs font-semibold text-slate-600 hover:text-slate-900 transition flex items-center gap-1.5 font-sans">
            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
        </a>
        <button onclick="window.print()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-md text-xs font-semibold transition flex items-center gap-2 font-sans">
            <i class="fa-solid fa-print"></i> In Quyết Định (Ctrl + P)
        </button>
    </div>

    <!-- Tờ Giấy A4 Chuẩn Mẫu Văn Bản Hành Chính -->
    <div class="max-w-3xl mx-auto bg-white p-12 sm:p-16 shadow-xl border border-slate-200 print-container rounded-lg space-y-6">
        
        <!-- Header Quốc Hiệu & Cơ Quan -->
        <div class="grid grid-cols-2 gap-4 pb-6">
            <div class="text-center">
                <div class="font-bold text-xs uppercase tracking-wider">CƠ QUAN QUẢN LÝ NHÂN SỰ</div>
                <div class="font-bold text-sm uppercase mt-0.5"><?= e($t['from_branch_name']) ?></div>
                <div class="text-xs mt-1">Số: <strong><?= e($t['decision_number']) ?></strong></div>
                <div class="w-24 h-0.5 bg-slate-900 mx-auto mt-2"></div>
            </div>

            <div class="text-center">
                <div class="font-bold text-xs uppercase tracking-wider">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="font-bold text-xs underline mt-0.5">Độc lập - Tự do - Hạnh phúc</div>
                <div class="text-xs italic mt-2">Hà Nội, ngày <?= $day ?> tháng <?= $month ?> năm <?= $year ?></div>
            </div>
        </div>

        <!-- Tiêu Đề Quyết Định -->
        <div class="text-center py-4 space-y-1">
            <h1 class="text-xl font-bold uppercase tracking-tight">QUYẾT ĐỊNH</h1>
            <div class="text-sm font-bold uppercase">Về việc điều động và bổ nhiệm cán bộ, người lao động</div>
            <div class="text-xs italic">Căn cứ nhu cầu bố trí nhân sự và phát triển mạng lưới các chi nhánh của Cơ quan</div>
        </div>

        <!-- Thẩm Quyền Ban Hành -->
        <div class="text-center font-bold text-sm tracking-wide uppercase pt-2">
            THỦ TRƯỞNG CƠ QUAN / BAN TỔ CHỨC CÁN BỘ
        </div>

        <!-- Các Căn Cứ Pháp Lý -->
        <div class="text-justify text-sm space-y-1.5 leading-relaxed indent-6">
            <p>• Căn cứ Điều lệ tổ chức và Quy chế quản lý nhân sự của Cơ quan ban hành kèm theo các văn bản hiện hành;</p>
            <p>• Căn cứ Kế hoạch phát triển, kiện toàn tổ chức bộ máy và định biên nhân lực tại các đơn vị, chi nhánh;</p>
            <p>• Căn cứ phẩm chất, năng lực chuyên môn và thâm niên cống hiến của cán bộ;</p>
            <p>• Xét đề nghị của Trưởng Ban Tổ chức Cán bộ và Giám đốc <?= e($t['to_branch_name']) ?>;</p>
        </div>

        <!-- Quyết Định Ban Hành -->
        <div class="text-center font-bold text-base tracking-wider uppercase pt-4 pb-2">
            QUYẾT ĐỊNH:
        </div>

        <!-- Các Điều Khoản Cụ Thể -->
        <div class="text-justify text-sm space-y-3 leading-relaxed">
            <p>
                <strong>Điều 1.</strong> Điều động Ông/Bà: <strong><?= mb_strtoupper($t['emp_fullname'], 'UTF-8') ?></strong><br>
                - Mã số cán bộ: <span class="font-mono"><?= e($t['emp_code']) ?></span><br>
                - Đang công tác tại: <strong><?= e($t['from_dept_name']) ?></strong> thuộc <strong><?= e($t['from_branch_name']) ?></strong><br>
                - Chức danh hiện tại: <strong><?= e($t['from_pos_name'] ?: 'Cán bộ chuyên môn') ?></strong><br>
                Đến nhận nhiệm vụ công tác tại: <strong><?= e($t['to_dept_name']) ?></strong> trực thuộc <strong><?= e($t['to_branch_name']) ?></strong>.<br>
                Bổ nhiệm giữ chức vụ / vị trí: <strong><?= e($t['to_pos_name']) ?></strong>.
            </p>

            <p>
                <strong>Điều 2.</strong> Thời gian và chế độ đãi ngộ:<br>
                - Quyết định có hiệu lực thi hành kể từ ngày <strong><?= $day ?>/<?= $month ?>/<?= $year ?></strong>.<br>
                - Mức lương định ngạch chức danh mới: <strong><?= format_money($t['new_base_salary']) ?>/tháng</strong>.<br>
                <?php if ($t['allowance_support'] > 0): ?>
                    - Phụ cấp điều động chuyển vùng / hỗ trợ công tác xa: <strong><?= format_money($t['allowance_support']) ?></strong>.<br>
                <?php endif; ?>
                - Lý do điều động: <em><?= e($t['reason']) ?></em>.
            </p>

            <p>
                <strong>Điều 3.</strong> Trách nhiệm thi hành:<br>
                Ban Tổ chức Cán bộ, Ban Tài chính Kế toán, Giám đốc <?= e($t['from_branch_name']) ?>, Giám đốc <?= e($t['to_branch_name']) ?> và Ông/Bà <strong><?= e($t['emp_fullname']) ?></strong> chịu trách nhiệm thi hành Quyết định này. Ông/Bà có tên tại Điều 1 có trách nhiệm hoàn tất bàn giao công việc tại đơn vị cũ trước ngày hiệu lực và có mặt nhận nhiệm vụ đúng thời hạn./.
            </p>
        </div>

        <!-- Chữ Ký & Nơi Nhận -->
        <div class="grid grid-cols-2 gap-4 pt-8">
            <div class="text-xs space-y-1">
                <div class="font-bold">Nơi nhận:</div>
                <div>- Như Điều 3;</div>
                <div>- Ban Giám đốc cơ quan;</div>
                <div>- Lưu: VT, TCCB (<?= e($t['transfer_code']) ?>).</div>
                
                <div class="pt-6">
                    <div class="p-2.5 rounded border border-slate-200 bg-slate-50 font-sans text-[10px] space-y-0.5 max-w-[200px]">
                        <span class="font-bold text-slate-700 block"><i class="fa-solid fa-shield-check text-emerald-600"></i> HỆ THỐNG HRMS</span>
                        <span class="text-slate-500 block">Chứng thực số: <?= e($t['transfer_code']) ?></span>
                        <span class="text-slate-500 block">Ngày ký: <?= date('d/m/Y H:i') ?></span>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <div class="font-bold text-xs uppercase">TM. BAN LÃNH ĐẠO CƠ QUAN</div>
                <div class="font-bold text-sm uppercase mt-0.5">THỦ TRƯỞNG ĐƠN VỊ</div>
                <div class="text-xs italic text-slate-500 mt-0.5">(Ký, đóng dấu điện tử)</div>

                <!-- Mô Phỏng Dấu Đỏ & Chữ Ký Sang Trọng -->
                <div class="relative h-28 flex items-center justify-center">
                    <div class="w-24 h-24 rounded-full border-2 border-rose-500 text-rose-600 flex flex-col items-center justify-center text-[9px] font-bold uppercase transform -rotate-12 opacity-85 select-none pointer-events-none">
                        <span>CƠ QUAN</span>
                        <i class="fa-solid fa-star text-rose-500 text-[10px] my-0.5"></i>
                        <span>ĐÃ KÝ DUYỆT</span>
                        <span class="text-[8px] mt-0.5"><?= date('d/m/Y') ?></span>
                    </div>
                </div>

                <div class="font-bold text-sm mt-1"><?= e($t['approver_name'] ?? 'Lê Hoàng Long') ?></div>
            </div>
        </div>

    </div>

</body>
</html>
