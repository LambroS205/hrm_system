<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/PayrollCalculator.php';
require_once __DIR__ . '/../core/PayrollService.php';

echo "--- BẮT ĐẦU TÍNH LƯƠNG THÁNG 9/2026 ---\n";
$res9 = PayrollService::calculateMonth($pdo, 9, 2026, null, 1);
print_r($res9);

echo "\n--- BẮT ĐẦU TÍNH LƯƠNG THÁNG 10/2026 ---\n";
$res10 = PayrollService::calculateMonth($pdo, 10, 2026, null, 1);
print_r($res10);

echo "\n--- MẪU 3 NHÂN VIÊN THÁNG 9/2026 TRONG DATABASE ---\n";
$stmt = $pdo->query("SELECT p.id, e.employee_code, e.fullname, p.basic_salary, p.gross_salary, 
                            p.bhxh_employee, p.bhyt_employee, p.bhtn_employee, 
                            p.family_deduction, p.assessable_income, p.pit_amount, 
                            p.final_salary, p.employer_insurance, p.payment_status 
                     FROM payrolls p 
                     JOIN employees e ON p.employee_id = e.id 
                     WHERE p.month = 9 AND p.year = 2026 
                     ORDER BY p.id ASC LIMIT 3");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | {$row['employee_code']} - {$row['fullname']}\n";
    echo "  Lương cơ bản/HĐ: " . number_format((float)$row['basic_salary']) . "đ\n";
    echo "  Thu nhập GROSS: " . number_format((float)$row['gross_salary']) . "đ\n";
    echo "  BHXH NLĐ: " . number_format((float)$row['bhxh_employee']) . "đ | BHYT: " . number_format((float)$row['bhyt_employee']) . "đ | BHTN: " . number_format((float)$row['bhtn_employee']) . "đ\n";
    echo "  Tổng BH NLĐ (10.5%): " . number_format((float)$row['bhxh_employee'] + (float)$row['bhyt_employee'] + (float)$row['bhtn_employee']) . "đ\n";
    echo "  Giảm trừ gia cảnh: " . number_format((float)$row['family_deduction']) . "đ\n";
    echo "  Thu nhập tính thuế: " . number_format((float)$row['assessable_income']) . "đ\n";
    echo "  Thuế TNCN: " . number_format((float)$row['pit_amount']) . "đ\n";
    echo "  Thực lĩnh NET: " . number_format((float)$row['final_salary']) . "đ\n";
    echo "  DN đóng bảo hiểm (23.5%): " . number_format((float)$row['employer_insurance']) . "đ\n";
    echo "  Trạng thái: {$row['payment_status']}\n\n";
}
