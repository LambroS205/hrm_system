<?php
// tests/KpiCalculatorTest.php - Bộ kiểm thử tự động thuật toán tính KPI, Link Power & Guardrails
require_once __DIR__ . '/../config/kpi_rules.php';
require_once __DIR__ . '/../core/KpiCalculator.php';

$passedCount = 0;
$totalCount = 0;

function assert_equals($expected, $actual, $testName, $tolerance = 0.01) {
    global $passedCount, $totalCount;
    $totalCount++;

    $diff = abs((float)$expected - (float)$actual);
    if ($diff <= $tolerance) {
        echo "  [PASS] {$testName}\n";
        $passedCount++;
    } else {
        echo "  [FAIL] {$testName}\n";
        echo "         Kỳ vọng: " . var_export($expected, true) . ", Thực tế: " . var_export($actual, true) . "\n";
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
echo "BẮT ĐẦU CHẠY BỘ KIỂM THỬ: KPI CALCULATOR & LINK POWER ENGINE\n";
echo "============================================================\n\n";

// -------------------------------------------------------------
// 1. Kiểm tra parseTarget
// -------------------------------------------------------------
echo "1. Kiểm tra trích xuất Target (parseTarget):\n";
assert_equals(95.0, KpiCalculator::parseTarget('≥ 95%'), 'Bóc tách ≥ 95% = 95.0');
assert_equals(0.5, KpiCalculator::parseTarget('< 0,5%'), 'Bóc tách < 0,5% (phẩy VN) = 0.5');
assert_equals(100.0, KpiCalculator::parseTarget('100 Triệu'), 'Bóc tách 100 Triệu = 100.0');
assert_equals(0.0, KpiCalculator::parseTarget('0 lỗi nghiêm trọng'), 'Bóc tách 0 lỗi = 0.0');
assert_true(KpiCalculator::parseTarget(null) === null, 'Chuỗi rỗng/null trả về null');

// -------------------------------------------------------------
// 2. Kiểm tra computeItemCompletion (Thuận, Nghịch, Boolean, Ngưỡng Liệt, Trần max_cap)
// -------------------------------------------------------------
echo "\n2. Kiểm tra tính điểm từng tiêu chí (computeItemCompletion):\n";

// 2.1 Thuận (higher_better), bình thường
$c1 = [
    'weight' => 20,
    'calc_direction' => 'higher_better',
    'target_value' => '100',
    'min_threshold' => 0,
    'max_cap' => 120
];
$res1 = KpiCalculator::computeItemCompletion($c1, '110');
assert_equals(110.0, $res1['effective_score'], 'Thuận 110/100 = 110%');
assert_true(!$res1['threshold_failed'], 'Không bị điểm liệt');

// 2.2 Vượt trần max_cap
$res1_cap = KpiCalculator::computeItemCompletion($c1, '150');
assert_equals(120.0, $res1_cap['effective_score'], 'Vượt trần 150/100 bị chặn ở max_cap 120%');

// 2.3 Ngưỡng liệt (Fail Threshold) - Điểm dưới ngưỡng rơi về 0
$c_thresh = [
    'weight' => 25,
    'calc_direction' => 'higher_better',
    'target_value' => '100',
    'min_threshold' => 60.0,
    'max_cap' => 120
];
$res_thresh_fail = KpiCalculator::computeItemCompletion($c_thresh, '55');
assert_equals(0.0, $res_thresh_fail['effective_score'], 'Dưới ngưỡng liệt 60% (đạt 55%) -> Rơi về 0.0 điểm');
assert_true($res_thresh_fail['threshold_failed'], 'Cờ threshold_failed bật true');

$res_thresh_pass = KpiCalculator::computeItemCompletion($c_thresh, '65');
assert_equals(65.0, $res_thresh_pass['effective_score'], 'Đạt 65% (trên ngưỡng liệt 60%) -> Giữ nguyên 65%');
assert_true(!$res_thresh_pass['threshold_failed'], 'Cờ threshold_failed bật false khi đạt trên ngưỡng');

// 2.4 Chiều nghịch (lower_better) - VD: Tỷ lệ hao hụt, bug tồn đọng
$c_lower = [
    'weight' => 20,
    'calc_direction' => 'lower_better',
    'target_value' => '2',
    'min_threshold' => 0,
    'max_cap' => 120
];
$res_lower_good = KpiCalculator::computeItemCompletion($c_lower, '1');
assert_equals(120.0, $res_lower_good['effective_score'], 'Nghịch: Target 2, Thực tế 1 -> Tốt hơn, bị trần ở 120%');

$res_lower_bad = KpiCalculator::computeItemCompletion($c_lower, '3');
assert_equals(50.0, $res_lower_bad['effective_score'], 'Nghịch: Target 2, Thực tế 3 -> 50%');

// 2.5 Tiêu chí mục tiêu 0 lỗi (Zero Target Penalty)
$c_zero = [
    'weight' => 15,
    'calc_direction' => 'lower_better',
    'target_value' => '0 lỗi',
    'min_threshold' => 0,
    'max_cap' => 120
];
$res_zero_0 = KpiCalculator::computeItemCompletion($c_zero, '0');
assert_equals(100.0, $res_zero_0['effective_score'], 'Zero-target: 0 lỗi -> Đạt 100%');

$res_zero_2 = KpiCalculator::computeItemCompletion($c_zero, '2');
// 2 sự cố vi phạm * 20đ trừ = 100 - 40 = 60
assert_equals(60.0, $res_zero_2['effective_score'], 'Zero-target: 2 vi phạm trừ 40đ -> Còn 60%');

// 2.6 Tiêu chí Boolean (Đạt / Không đạt)
$c_bool = [
    'weight' => 10,
    'calc_direction' => 'boolean',
    'target_value' => 'Đạt',
    'min_threshold' => 0,
    'max_cap' => 120
];
$res_bool_pass = KpiCalculator::computeItemCompletion($c_bool, 'Đạt');
assert_equals(100.0, $res_bool_pass['effective_score'], 'Boolean Đạt = 100%');

$res_bool_fail = KpiCalculator::computeItemCompletion($c_bool, 'Không đạt');
assert_equals(0.0, $res_bool_fail['effective_score'], 'Boolean Không đạt = 0%');

// -------------------------------------------------------------
// 3. Kiểm tra tính toàn bảng điểm & Chống lạm phát điểm khi bỏ trống (Score Sheet)
// -------------------------------------------------------------
echo "\n3. Kiểm tra calculateScoreSheet & Chống lạm phát điểm khi bỏ trống tiêu chí:\n";
$templateItems = [
    [
        'id' => 1,
        'criteria_name' => 'Doanh số bán lẻ WinMart',
        'priority_group' => 'A',
        'bsc_pillar' => 'financial',
        'weight' => 50.0,
        'calc_direction' => 'higher_better',
        'target_value' => '100',
        'min_threshold' => 50.0,
        'max_cap' => 120.0
    ],
    [
        'id' => 2,
        'criteria_name' => 'Chỉ số hài lòng khách hàng NPS',
        'priority_group' => 'B',
        'bsc_pillar' => 'customer',
        'weight' => 30.0,
        'calc_direction' => 'higher_better',
        'target_value' => '90',
        'min_threshold' => 0,
        'max_cap' => 120.0
    ],
    [
        'id' => 3,
        'criteria_name' => 'Sáng kiến cải tiến vận hành',
        'priority_group' => 'C',
        'bsc_pillar' => 'internal_process',
        'weight' => 20.0,
        'calc_direction' => 'higher_better',
        'target_value' => '2',
        'min_threshold' => 0,
        'max_cap' => 120.0
    ]
];

// Ca A: Chỉ nhập tiêu chí 1 (100% điểm), bỏ trống tiêu chí 2 và 3
// Điểm kỳ vọng: (50% * 100) / 100 = 50.0 điểm.
// Thuật toán cũ bị lạm phát chia cho 50 -> 100.0 điểm. KpiCalculator mới PHẢI ra 50.0 điểm!
$inputs_partial = [
    ['item_id' => 1, 'actual_value' => '100']
];
$sheet_partial = KpiCalculator::calculateScoreSheet($templateItems, $inputs_partial);
assert_equals(50.0, $sheet_partial['score'], 'Chống lạm phát điểm: Chỉ làm tiêu chí 50% weight -> Điểm tổng chỉ đạt 50.0 (không bị thổi lên 100)');
assert_equals(2, count($sheet_partial['missing_items']), 'Báo cáo 2 tiêu chí bị bỏ trống');

// Ca B: Nhập đầy đủ cả 3 tiêu chí
$inputs_full = [
    ['item_id' => 1, 'actual_value' => '100'], // 100% * 50 = 50
    ['item_id' => 2, 'actual_value' => '90'],  // 100% * 30 = 30
    ['item_id' => 3, 'actual_value' => '2']   // 100% * 20 = 20
];
$sheet_full = KpiCalculator::calculateScoreSheet($templateItems, $inputs_full);
assert_equals(100.0, $sheet_full['score'], 'Nhập đầy đủ cả 3 tiêu chí đạt 100% -> Tổng điểm = 100.0');
assert_equals(100.0, $sheet_full['priority_avg']['A'], 'Nhóm A trung bình 100%');
assert_equals(100.0, $sheet_full['priority_avg']['B'], 'Nhóm B trung bình 100%');
assert_equals(100.0, $sheet_full['priority_avg']['C'], 'Nhóm C trung bình 100%');

// -------------------------------------------------------------
// 4. Kiểm tra calculateFinalScore (30% NV + 70% Quản lý)
// -------------------------------------------------------------
echo "\n4. Kiểm tra calculateFinalScore:\n";
// Self = 90, Manager = 80 -> 0.3*90 + 0.7*80 = 27 + 56 = 83.0
$final1 = KpiCalculator::calculateFinalScore(90.0, 80.0);
assert_equals(83.0, $final1, '30% của 90đ + 70% của 80đ = 83.0đ');

// Self = null (NV chưa tự chấm) -> 100% điểm quản lý = 85.0
$final2 = KpiCalculator::calculateFinalScore(null, 85.0);
assert_equals(85.0, $final2, 'NV chưa tự chấm (null) -> Lấy 100% điểm quản lý = 85.0đ');

// -------------------------------------------------------------
// 5. Kiểm tra classifyRating & Link Power Group A Gate
// -------------------------------------------------------------
echo "\n5. Kiểm tra classifyRating & Link Power Group A Gate:\n";

// 5.1 Các mốc điểm chuẩn
$r_out = KpiCalculator::classifyRating(96.0, ['A' => 100.0]);
assert_equals('outstanding', $r_out['rating'], '96.0đ xếp loại outstanding');
assert_equals('A+', $r_out['rating_grade'], '96.0đ grade A+');
assert_equals(1.50, $r_out['multiplier'], '96.0đ hệ số thưởng 1.50');

$r_exc = KpiCalculator::classifyRating(88.0, ['A' => 85.0]);
assert_equals('excellent', $r_exc['rating'], '88.0đ xếp loại excellent');
assert_equals('A', $r_exc['rating_grade'], '88.0đ grade A');
assert_equals(1.20, $r_exc['multiplier'], '88.0đ hệ số thưởng 1.20');

$r_good = KpiCalculator::classifyRating(75.0, ['A' => 80.0]);
assert_equals('good', $r_good['rating'], '75.0đ xếp loại good (B)');
assert_equals(1.00, $r_good['multiplier'], '75.0đ hệ số thưởng 1.00');

$r_poor = KpiCalculator::classifyRating(35.0, ['A' => 30.0]);
assert_equals('poor', $r_poor['rating'], '35.0đ xếp loại poor (E)');
assert_equals(0.0, $r_poor['multiplier'], '35.0đ hệ số thưởng 0.00');

// 5.2 Khống chế Link Power Group A Gate
// Tổng điểm 92.0đ (thông thường đạt hạng A - excellent, multiplier 1.20)
// Nhưng Nhóm A chỉ đạt 50% (< 60% min_completion yêu cầu)
// -> Bị giáng xuống tối đa 'average' (hạng C, multiplier 0.60)
$r_gated = KpiCalculator::classifyRating(92.0, ['A' => 50.0]);
assert_equals('average', $r_gated['rating'], 'Group A Gate: Nhóm A < 60% bị hạ trần xuống average');
assert_equals('C', $r_gated['rating_grade'], 'Group A Gate: Grade bị hạ xuống C');
assert_equals(0.60, $r_gated['multiplier'], 'Group A Gate: Thưởng hạ về 0.60');
assert_true($r_gated['gate_applied'], 'Group A Gate: Cờ gate_applied bật true');

echo "\n============================================================\n";
echo "KẾT QUẢ KIỂM THỬ: {$passedCount} / {$totalCount} TESTS PASSED\n";
echo "============================================================\n";

if ($passedCount === $totalCount) {
    echo ">>> TẤT CẢ CÁC BÀI KIỂM THỬ ĐỀU ĐẠT CHUẨN 100%! <<<\n";
    exit(0);
} else {
    echo ">>> CÓ BÀI KIỂM THỬ THẤT BẠI! <<<\n";
    exit(1);
}
