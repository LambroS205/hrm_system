<?php
// config/payroll_rules.php - Bảng quy chuẩn và thông số tính lương theo Luật Lao động, BHXH và Thuế TNCN Việt Nam

if (!function_exists('get_raw_payroll_rule_timeline')) {
    function get_raw_payroll_rule_timeline() {
        return [
            '2026-01-01' => [
                // Mức lương cơ sở / mức tham chiếu áp dụng đầu năm 2026 (Nghị định 73/2024/NĐ-CP)
                'base_reference_salary' => 2340000,

                // Lương tối thiểu vùng theo tháng (Nghị định 293/2025/NĐ-CP áp dụng từ 01/01/2026)
                'regional_min_wage' => [
                    1 => 5310000,
                    2 => 4730000,
                    3 => 4140000,
                    4 => 3700000,
                ],

                // Giới hạn trần đóng BHXH, BHYT (tối đa 20 lần mức tham chiếu/lương cơ sở)
                // và BHTN (tối đa 20 lần mức lương tối thiểu vùng)
                'insurance_cap_multiplier' => 20,

                // Tỷ lệ trích đóng bảo hiểm đối với Người lao động (Tổng 10.5%)
                'employee_rates' => [
                    'bhxh' => 0.08,   // 8.0% Hưu trí & tử tuất
                    'bhyt' => 0.015,  // 1.5% Bảo hiểm y tế
                    'bhtn' => 0.01,   // 1.0% Bảo hiểm thất nghiệp
                ],

                // Tỷ lệ trích đóng đối với Người sử dụng lao động (Tổng 23.5% gồm 21.5% BH + 2% KPCĐ)
                'employer_rates' => [
                    'bhxh' => 0.175,  // 14% Hưu trí, 3% Ốm đau thai sản, 0.5% TNLĐ-BNN
                    'bhyt' => 0.03,   // 3.0% BHYT
                    'bhtn' => 0.01,   // 1.0% BHTN
                    'kpcd' => 0.02,   // 2.0% Kinh phí công đoàn
                ],

                // Mức giảm trừ gia cảnh thuế TNCN (Nghị quyết 110/2025/UBTVQH15 từ kỳ tính thuế 2026)
                'personal_deduction'  => 15500000, // 15.5 triệu/tháng cho bản thân
                'dependent_deduction' => 6200000,  // 6.2 triệu/tháng cho mỗi người phụ thuộc hợp lệ

                // Biểu thuế lũy tiến từng phần 5 bậc mới (Luật Thuế TNCN sửa đổi áp dụng từ 01/01/2026)
                'pit_brackets' => [
                    ['threshold' => 10000000,  'rate' => 0.05], // Đến 10 triệu: 5%
                    ['threshold' => 30000000,  'rate' => 0.10], // Trên 10 đến 30 triệu: 10%
                    ['threshold' => 60000000,  'rate' => 0.20], // Trên 30 đến 60 triệu: 20%
                    ['threshold' => 100000000, 'rate' => 0.30], // Trên 60 đến 100 triệu: 30%
                    ['threshold' => null,      'rate' => 0.35], // Trên 100 triệu: 35%
                ],

                // Khấu trừ thuế TNCN 10% vãng lai / thử việc không cam kết (Khoản 1 Điều 25 Thông tư 111/2013/TT-BTC)
                'flat_withholding' => [
                    'rate' => 0.10,
                    'threshold' => 2000000, // Thu nhập từ 2 triệu trở lên
                ],

                // Hệ số làm thêm giờ (Điều 98 Bộ luật Lao động 2019)
                'ot_multipliers' => [
                    'weekday' => 1.5, // Ngày thường: ít nhất 150%
                    'weekend' => 2.0, // Ngày nghỉ hằng tuần (T7/CN): ít nhất 200%
                    'holiday' => 3.0, // Ngày lễ, tết, ngày nghỉ có hưởng lương: ít nhất 300%
                ],

                // Số giờ làm việc tiêu chuẩn trong ngày
                'hours_per_day' => 8,

                // Trần số giờ làm thêm tối đa / tháng theo quy định (Điều 107 BLLĐ)
                'ot_monthly_limit' => 40,

                // Số ngày nghỉ không hưởng lương trong tháng dẫn đến miễn đóng BHXH (Điều 85 Luật BHXH)
                'insurance_exempt_unpaid_days' => 14,

                // Tỷ lệ khấu trừ tối đa bồi thường thiệt hại trên tiền lương thực lĩnh (Điều 102 BLLĐ)
                'compensation_max_ratio' => 0.30,
            ],

            '2026-07-01' => [
                // Điều chỉnh mức lương cơ sở / tham chiếu lên 2.530.000đ từ 01/07/2026 (Nghị định 161/2026/NĐ-CP)
                'base_reference_salary' => 2530000,
            ],
        ];
    }
}

/**
 * Lấy bộ quy tắc tính lương áp dụng cho kỳ tháng/năm cụ thể
 *
 * @param int $year
 * @param int $month
 * @return array
 */
if (!function_exists('payroll_rules_for')) {
    function payroll_rules_for($year, $month) {
        $timeline = get_raw_payroll_rule_timeline();
        $targetDate = sprintf('%04d-%02d-01', (int)$year, (int)$month);

        // Sắp xếp timeline theo thứ tự ngày tăng dần
        uksort($timeline, function($a, $b) {
            return strcmp($a, $b);
        });

        $activeRules = [];
        foreach ($timeline as $effectiveDate => $rules) {
            if (strcmp($effectiveDate, $targetDate) <= 0) {
                // Hợp nhất đè các tham số mới lên cấu hình cũ
                $activeRules = array_replace_recursive($activeRules, $rules);
                $activeRules['effective_since'] = $effectiveDate;
            }
        }

        // Dự phòng nếu targetDate trước mốc cấu hình đầu tiên
        if (empty($activeRules)) {
            $firstKey = array_key_first($timeline);
            $activeRules = $timeline[$firstKey];
            $activeRules['effective_since'] = $firstKey;
        }

        return $activeRules;
    }
}
