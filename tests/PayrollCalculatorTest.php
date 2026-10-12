<?php
// tests/PayrollCalculatorTest.php - Bộ kiểm thử tự động thuật toán tính lương theo luật Việt Nam

require_once __DIR__ . '/../config/payroll_rules.php';
require_once __DIR__ . '/../core/PayrollCalculator.php';

$passedCount = 0;
$totalCount = 0;

function assert_equals($expected, $actual, $testName) {
    global $passedCount, $totalCount;
    $totalCount++;

    $diff = abs((float)$expected - (float)$actual);
    if ($diff < 1.0) { // Sai số làm tròn cho phép < 1 đồng
        echo "  [PASS] {$testName}\n";
        $passedCount++;
    } else {
        echo "  [FAIL] {$testName}\n";
        echo "         Kỳ vọng: " . number_format($expected, 2) . ", Thực tế: " . number_format($actual, 2) . "\n";
    }
}

function assert_true($condition, $testName) {
    global $passedCount, $totalCount;
    $totalCount++;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passedCount++;
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

echo "============================================================\n";
echo "BẮT ĐẦU CHẠY BỘ KIỂM THỬ: PAYROLL CALCULATOR VN COMPLIANCE\n";
echo "============================================================\n\n";

// --- Case 1: Nhân viên chính thức, 1 người phụ thuộc, lương 30tr, đủ công T10/2026 ---
echo "1. Kiểm tra nhân viên chính thức 30tr, 1 NPT, T10/2026:\n";
$rules10_2026 = payroll_rules_for(2026, 10);
$res1 = PayrollCalculator::calculate([
    'base_salary'             => 30000000,
    'contract_allowance'      => 0,
    'contract_type'           => 'fixed_term',
    'wage_region'             => 1,
    'standard_days'           => 22,
    'present_days'            => 22,
    'dependents_count'        => 1,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(3150000, $res1['total_insurance_emp'], 'BH NLĐ = 3.150.000đ (8% BHXH + 1.5% BHYT + 1% BHTN)');
assert_equals(5150000, $res1['assessable_income'], 'Thu nhập tính thuế = 5.150.000đ (30M - 15.5M - 6.2M - 3.15M)');
assert_equals(257500, $res1['pit_amount'], 'Thuế TNCN Bậc 1 (5%) = 257.500đ');
assert_equals(26592500, $res1['final_salary'], 'Lương thực lĩnh = 26.592.500đ');

// --- Case 2: Lương cao vượt trần, 85tr, 0 NPT, Vùng I, T10/2026 (sau 01/07/2026, trần 50.6tr) ---
echo "\n2. Kiểm tra lương cao vượt trần (85tr, T10/2026 - Trần 20x 2.530.000đ = 50.6tr):\n";
$res2 = PayrollCalculator::calculate([
    'base_salary'             => 85000000,
    'contract_type'           => 'indefinite',
    'wage_region'             => 1,
    'standard_days'           => 22,
    'present_days'            => 22,
    'dependents_count'        => 0,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(4048000, $res2['bhxh_employee'], 'BHXH NLĐ kịch trần 50.6M x 8% = 4.048.000đ');
assert_equals(759000, $res2['bhyt_employee'], 'BHYT NLĐ kịch trần 50.6M x 1.5% = 759.000đ');
assert_equals(850000, $res2['bhtn_employee'], 'BHTN NLĐ đóng theo 85M x 1% = 850.000đ (dưới trần vùng 106.2M)');
assert_equals(9652900, $res2['pit_amount'], 'Thuế TNCN lũy tiến 5 bậc = 9.652.900đ');
assert_equals(69690100, $res2['final_salary'], 'Lương thực lĩnh = 69.690.100đ');

// --- Case 3: Kiểm tra mức trần trước 01/07/2026 (T06/2026, trần 20x 2.340.000đ = 46.8tr) ---
echo "\n3. Kiểm tra mức trần trước 01/07/2026 (T06/2026, trần 46.8tr):\n";
$rules06_2026 = payroll_rules_for(2026, 6);
$res3 = PayrollCalculator::calculate([
    'base_salary'             => 85000000,
    'contract_type'           => 'indefinite',
    'standard_days'           => 22,
    'present_days'            => 22,
    'has_attendance_records'  => true,
], $rules06_2026);

assert_equals(3744000, $res3['bhxh_employee'], 'BHXH NLĐ kịch trần 46.8M x 8% = 3.744.000đ');

// --- Case 4: Hợp đồng thử việc (probation 16tr) ---
echo "\n4. Kiểm tra hợp đồng thử việc (16tr, miễn BH, khấu trừ 10% tại nguồn):\n";
$res4 = PayrollCalculator::calculate([
    'base_salary'             => 16000000,
    'contract_type'           => 'probation',
    'standard_days'           => 22,
    'present_days'            => 22,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(0, $res4['total_insurance_emp'], 'Thử việc không phải đóng BHXH bắt buộc = 0đ');
assert_equals(1600000, $res4['pit_amount'], 'Khấu trừ thuế 10% tại nguồn = 1.600.000đ');
assert_equals(14400000, $res4['final_salary'], 'Lương thực lĩnh thử việc = 14.400.000đ');

// --- Case 5: Làm thêm giờ OT (Lương 22tr, công chuẩn 22, OT 4h ngày thường) ---
echo "\n5. Kiểm tra làm thêm giờ OT (Lương 22tr, 4h OT ngày thường hệ số 150%):\n";
$res5 = PayrollCalculator::calculate([
    'base_salary'             => 22000000,
    'contract_type'           => 'fixed_term',
    'standard_days'           => 22,
    'present_days'            => 22,
    'ot_weekday_hours'        => 4.0,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(125000, $res5['hourly_rate'], 'Đơn giá 1 giờ làm việc = 125.000đ');
assert_equals(750000, $res5['ot_pay'], 'Tiền làm thêm 4h ngày thường (150%) = 750.000đ');
assert_equals(250000, $res5['ot_tax_exempt'], 'Phần tiền OT được miễn thuế TNCN (0.5x) = 250.000đ');

// --- Case 6: Đi muộn 30 phút (trừ theo số phút thực tế, không trừ nửa ngày công) ---
echo "\n6. Kiểm tra khấu trừ đi muộn 30 phút:\n";
$res6 = PayrollCalculator::calculate([
    'base_salary'             => 22000000,
    'contract_type'           => 'fixed_term',
    'standard_days'           => 22,
    'present_days'            => 22,
    'late_early_minutes'      => 30,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(62500, $res6['late_deduction'], 'Trừ 30 phút đi muộn = 62.500đ');
assert_equals(21937500, $res6['time_salary'], 'Lương thời gian còn lại = 21.937.500đ');

// --- Case 7: Nghỉ không lương >= 14 ngày làm việc (miễn đóng BHXH tháng đó) ---
echo "\n7. Kiểm tra nghỉ không lương >= 14 ngày:\n";
$res7 = PayrollCalculator::calculate([
    'base_salary'             => 20000000,
    'contract_type'           => 'fixed_term',
    'standard_days'           => 22,
    'present_days'            => 7,
    'unpaid_days'             => 15,
    'has_attendance_records'  => true,
], $rules10_2026);

assert_equals(0, $res7['total_insurance_emp'], 'Miễn đóng BHXH tháng do nghỉ không hưởng lương >= 14 ngày = 0đ');

// --- Case 8: Khấu trừ bồi thường thiệt hại (không quá 30% tiền lương thực lĩnh) ---
echo "\n8. Kiểm tra giới hạn khấu trừ bồi thường thiệt hại 30% lương:\n";
$res8 = PayrollCalculator::calculate([
    'base_salary'             => 10000000,
    'contract_type'           => 'probation',
    'standard_days'           => 22,
    'present_days'            => 22,
    'pending_compensation'    => 20000000, // Nợ bồi thường 20 triệu
    'has_attendance_records'  => true,
], $rules10_2026);

// Với thử việc 10tr -> thuế 10% = 1tr -> Net = 9tr -> Trần 30% = 2.700.000đ
assert_equals(2700000, $res8['compensation_deduction'], 'Khấu trừ bồi thường tối đa 30% Net (9M x 30% = 2.700.000đ)');
assert_equals(6300000, $res8['final_salary'], 'Lương thực lĩnh sau trừ bồi thường = 6.300.000đ');

// --- Case 9: Biên các bậc thuế TNCN 5 bậc mới ---
echo "\n9. Kiểm tra biên các bậc thuế TNCN 5 bậc:\n";
$brackets = $rules10_2026['pit_brackets'];
assert_equals(500000, PayrollCalculator::calculateProgressivePit(10000000, $brackets), 'Bậc 1: 10M -> 500.000đ');
assert_equals(2500000, PayrollCalculator::calculateProgressivePit(30000000, $brackets), 'Bậc 2: 30M -> 2.500.000đ');
assert_equals(8500000, PayrollCalculator::calculateProgressivePit(60000000, $brackets), 'Bậc 3: 60M -> 8.500.000đ');
assert_equals(20500000, PayrollCalculator::calculateProgressivePit(100000000, $brackets), 'Bậc 4: 100M -> 20.500.000đ');

// --- Case 10: Tính ngày công chuẩn và ngày lễ trong tháng ---
echo "\n10. Kiểm tra ngày công chuẩn & ngày lễ:\n";
// Tháng 10/2026 có 31 ngày (01/10/2026 là Thứ 5, 31/10/2026 là Thứ 7). Số ngày Thứ 2-Thứ 6 = 22 ngày.
$std10 = PayrollCalculator::calculateStandardDays(2026, 10, []);
assert_equals(22, $std10['standard_days'], 'Số ngày công chuẩn T10/2026 = 22 ngày');

// Tháng 9/2026: Lễ 2/9 rơi vào Thứ 4 và ngày liền kề 1/9 rơi vào Thứ 3
$std09 = PayrollCalculator::calculateStandardDays(2026, 9, ['2026-09-01', '2026-09-02']);
assert_equals(22, $std09['standard_days'], 'Số ngày làm việc trong tuần T09/2026 = 22 ngày');
assert_equals(2, $std09['holiday_count'], 'Có 2 ngày lễ Quốc Khánh rơi vào ngày làm việc T2-T6');

echo "\n============================================================\n";
echo "KẾT QUẢ: {$passedCount} / {$totalCount} TESTS PASSED (" . round(($passedCount / $totalCount) * 100) . "%)\n";
echo "============================================================\n";

if ($passedCount !== $totalCount) {
    exit(1);
}
