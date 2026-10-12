<?php
// core/PayrollCalculator.php - Thuật toán tính lương tuân thủ chuẩn Pháp luật Lao động Việt Nam

class PayrollCalculator {

    /**
     * Tính toán số ngày công chuẩn trong tháng (các ngày từ Thứ 2 đến Thứ 6)
     * và xác định danh sách các ngày lễ rơi vào ngày làm việc trong tuần.
     *
     * @param int $year
     * @param int $month
     * @param array $holidays Mảng danh sách các ngày lễ ['YYYY-MM-DD', ...]
     * @return array ['standard_days' => float, 'holiday_weekdays' => array, 'total_weekdays' => int]
     */
    public static function calculateStandardDays($year, $month, array $holidays = []) {
        $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $end = clone $start;
        $end->modify('last day of this month');

        $totalWeekdays = 0;
        $holidayWeekdays = [];

        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('N'); // 1 = Thứ 2, ..., 5 = Thứ 6, 6 = T7, 7 = CN
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $totalWeekdays++;
                $dateStr = $current->format('Y-m-d');
                if (in_array($dateStr, $holidays)) {
                    $holidayWeekdays[] = $dateStr;
                }
            }
            $current->modify('+1 day');
        }

        return [
            'standard_days'    => (float)$totalWeekdays,
            'total_weekdays'   => $totalWeekdays,
            'holiday_weekdays' => $holidayWeekdays,
            'holiday_count'    => count($holidayWeekdays),
        ];
    }

    /**
     * Tính thuế TNCN theo biểu thuế lũy tiến từng phần 5 bậc mới (Luật Thuế TNCN 109/2025/QH15)
     *
     * Bậc 1: Đến 10 triệu                 -> 5%
     * Bậc 2: Trên 10 triệu đến 30 triệu   -> 10%
     * Bậc 3: Trên 30 triệu đến 60 triệu   -> 20%
     * Bậc 4: Trên 60 triệu đến 100 triệu  -> 30%
     * Bậc 5: Trên 100 triệu               -> 35%
     *
     * @param float $assessableIncome Thu nhập tính thuế (đã trừ các khoản giảm trừ)
     * @param array $brackets
     * @return float
     */
    public static function calculateProgressivePit($assessableIncome, array $brackets) {
        if ($assessableIncome <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $previousLimit = 0.0;

        foreach ($brackets as $bracket) {
            $limit = $bracket['threshold'];
            $rate = (float)$bracket['rate'];

            if ($limit === null) {
                // Bậc cao nhất (không giới hạn trên)
                $taxableInBracket = max(0.0, $assessableIncome - $previousLimit);
                $tax += $taxableInBracket * $rate;
                break;
            } else {
                if ($assessableIncome > $limit) {
                    $taxableInBracket = $limit - $previousLimit;
                    $tax += $taxableInBracket * $rate;
                    $previousLimit = $limit;
                } else {
                    $taxableInBracket = max(0.0, $assessableIncome - $previousLimit);
                    $tax += $taxableInBracket * $rate;
                    break;
                }
            }
        }

        return round($tax);
    }

    /**
     * Tính toán toàn diện bảng lương cho một nhân viên trong kỳ
     *
     * @param array $input Các tham số đầu vào của nhân viên
     * @param array $rules Bộ quy chuẩn pháp lý lấy từ payroll_rules_for($year, $month)
     * @return array Kết quả chi tiết breakdown
     */
    public static function calculate(array $input, array $rules) {
        $warnings = [];

        // 1. Nhận các tham số đầu vào
        $baseSalary = (float)($input['base_salary'] ?? 0);
        $contractAllowanceBase = (float)($input['contract_allowance'] ?? 0);
        $otherAllowance = (float)($input['other_allowance'] ?? 0);
        $bonus = (float)($input['bonus'] ?? 0);
        $contractType = $input['contract_type'] ?? 'fixed_term'; // probation, fixed_term, indefinite, seasonal
        $wageRegion = (int)($input['wage_region'] ?? 1);

        $standardDays = (float)($input['standard_days'] ?? 22.0);
        if ($standardDays <= 0) $standardDays = 22.0;

        $presentDays = (float)($input['present_days'] ?? 0.0);
        $paidLeaveDays = (float)($input['paid_leave_days'] ?? 0.0);
        $holidayDays = (float)($input['holiday_days'] ?? 0.0);
        $unpaidDays = (float)($input['unpaid_days'] ?? 0.0);
        $lateEarlyMinutes = (int)($input['late_early_minutes'] ?? 0);

        $otWeekdayHours = (float)($input['ot_weekday_hours'] ?? 0.0);
        $otWeekendHours = (float)($input['ot_weekend_hours'] ?? 0.0);
        $otHolidayHours = (float)($input['ot_holiday_hours'] ?? 0.0);

        $dependentsCount = max(0, (int)($input['dependents_count'] ?? 0));
        $pendingCompensation = max(0.0, (float)($input['pending_compensation'] ?? 0.0));
        $otherDeduction = max(0.0, (float)($input['other_deduction'] ?? 0.0));

        // 2. Kiểm tra cảnh báo nghiệp vụ
        $regionalMinWage = $rules['regional_min_wage'][$wageRegion] ?? $rules['regional_min_wage'][1];
        if ($baseSalary > 0 && $baseSalary < $regionalMinWage) {
            $warnings[] = sprintf(
                'Lương cơ bản (%s) thấp hơn mức lương tối thiểu Vùng %d (%s) theo NĐ 293/2025/NĐ-CP.',
                number_format($baseSalary, 0, ',', '.') . 'đ',
                $wageRegion,
                number_format($regionalMinWage, 0, ',', '.') . 'đ'
            );
        }

        $totalWorkDays = $presentDays + $paidLeaveDays + $holidayDays;
        if ($totalWorkDays <= 0 && empty($input['has_attendance_records'])) {
            $warnings[] = 'Nhân sự chưa có dữ liệu chấm công được ghi nhận trong tháng.';
        }

        $totalOtHours = $otWeekdayHours + $otWeekendHours + $otHolidayHours;
        if ($totalOtHours > ($rules['ot_monthly_limit'] ?? 40)) {
            $warnings[] = sprintf(
                'Số giờ làm thêm (%s giờ) vượt ngưỡng trần 40 giờ/tháng theo Điều 107 Bộ luật Lao động.',
                number_format($totalOtHours, 1)
            );
        }

        // 3. Đơn giá tiền lương theo ngày & giờ làm việc thực tế (Điều 55 NĐ 145/2020/NĐ-CP)
        $hoursPerDay = (float)($rules['hours_per_day'] ?? 8);
        $dailyRate = $standardDays > 0 ? ($baseSalary / $standardDays) : 0.0;
        $hourlyRate = $dailyRate > 0 ? ($dailyRate / $hoursPerDay) : 0.0;

        // Lương thời gian
        $timeSalaryRaw = round($dailyRate * $totalWorkDays);

        // Khấu trừ đi muộn / về sớm theo số phút thực tế (thay vì trừ nửa ngày công)
        $minuteRate = $hourlyRate > 0 ? ($hourlyRate / 60) : 0.0;
        $lateDeduction = round($minuteRate * $lateEarlyMinutes);
        $timeSalary = max(0.0, $timeSalaryRaw - $lateDeduction);

        // Phụ cấp theo hợp đồng (tính theo tỷ lệ ngày công thực tế)
        $contractAllowance = round(($contractAllowanceBase / $standardDays) * $totalWorkDays);

        // 4. Tiền làm thêm giờ (OT) & Phần miễn thuế TNCN (Điều 98 BLLĐ 2019 & TT 111/2013/TT-BTC)
        $otMult = $rules['ot_multipliers'];
        $otWeekdayPay = round($otWeekdayHours * $hourlyRate * ($otMult['weekday'] ?? 1.5));
        $otWeekendPay = round($otWeekendHours * $hourlyRate * ($otMult['weekend'] ?? 2.0));
        $otHolidayPay = round($otHolidayHours * $hourlyRate * ($otMult['holiday'] ?? 3.0));
        $otPay = $otWeekdayPay + $otWeekendPay + $otHolidayPay;

        // Phần tiền lương làm thêm giờ được trả cao hơn tiền lương làm việc bình thường được miễn thuế TNCN:
        // Ngày thường: 1.5 - 1.0 = 0.5; Ngày nghỉ tuần: 2.0 - 1.0 = 1.0; Ngày lễ: 3.0 - 1.0 = 2.0
        $otTaxExempt = round(
            $otWeekdayHours * $hourlyRate * 0.5 +
            $otWeekendHours * $hourlyRate * 1.0 +
            $otHolidayHours * $hourlyRate * 2.0
        );

        // 5. Tổng thu nhập GROSS
        $grossSalary = $timeSalary + $contractAllowance + $otherAllowance + $bonus + $otPay;

        // 6. Tính trích đóng Bảo hiểm bắt buộc (BHXH, BHYT, BHTN)
        // Mức lương đóng BH theo Luật BHXH 2024 = Lương cơ bản HĐ + Phụ cấp lương thỏa thuận
        $insuranceSalary = $baseSalary + $contractAllowanceBase;

        // Không đóng bảo hiểm nếu là hợp đồng thử việc hoặc nghỉ không lương từ 14 ngày làm việc trở lên (Điều 85 Luật BHXH)
        $isExemptInsurance = ($contractType === 'probation') || ($unpaidDays >= ($rules['insurance_exempt_unpaid_days'] ?? 14));

        $bhxhEmployee = 0.0;
        $bhytEmployee = 0.0;
        $bhtnEmployee = 0.0;
        $bhxhEmployer = 0.0;
        $bhytEmployer = 0.0;
        $bhtnEmployer = 0.0;
        $kpcdEmployer = 0.0;

        if (!$isExemptInsurance && $insuranceSalary > 0) {
            $baseRef = (float)$rules['base_reference_salary'];
            $capMultiplier = (int)$rules['insurance_cap_multiplier'];

            // Trần BHXH, BHYT = 20 lần mức tham chiếu/lương cơ sở
            $capBhxhBhyt = $baseRef * $capMultiplier;
            // Trần BHTN = 20 lần lương tối thiểu vùng
            $capBhtn = $regionalMinWage * $capMultiplier;

            $bhxhBase = min($insuranceSalary, $capBhxhBhyt);
            $bhytBase = min($insuranceSalary, $capBhxhBhyt);
            $bhtnBase = min($insuranceSalary, $capBhtn);

            // Người lao động đóng (8% BHXH, 1.5% BHYT, 1% BHTN)
            $empRates = $rules['employee_rates'];
            $bhxhEmployee = round($bhxhBase * $empRates['bhxh']);
            $bhytEmployee = round($bhytBase * $empRates['bhyt']);
            $bhtnEmployee = round($bhtnBase * $empRates['bhtn']);

            // Người sử dụng lao động đóng (17.5% BHXH, 3% BHYT, 1% BHTN, 2% KPCĐ)
            $emprRates = $rules['employer_rates'];
            $bhxhEmployer = round($bhxhBase * $emprRates['bhxh']);
            $bhytEmployer = round($bhytBase * $emprRates['bhyt']);
            $bhtnEmployer = round($bhtnBase * $emprRates['bhtn']);
            $kpcdEmployer = round($bhxhBase * $emprRates['kpcd']);
        }

        $totalInsuranceEmployee = $bhxhEmployee + $bhytEmployee + $bhtnEmployee;
        $employerInsurance = $bhxhEmployer + $bhytEmployer + $bhtnEmployer + $kpcdEmployer;

        // 7. Tính Thuế Thu Nhập Cá Nhân (PIT)
        // Thu nhập chịu thuế = GROSS - Phần tiền lương làm thêm giờ được miễn thuế
        $taxableIncome = max(0.0, $grossSalary - $otTaxExempt);

        $taxMethod = 'progressive';
        $familyDeduction = 0.0;
        $assessableIncome = 0.0;
        $pitAmount = 0.0;

        if ($contractType === 'probation') {
            // Hợp đồng thử việc: Khấu trừ 10% tại nguồn nếu thu nhập từ 2 triệu trở lên
            $taxMethod = 'flat10';
            $flatRule = $rules['flat_withholding'];
            if ($taxableIncome >= $flatRule['threshold']) {
                $pitAmount = round($taxableIncome * $flatRule['rate']);
            }
        } else {
            // Hợp đồng lao động chính thức / có thời hạn từ 3 tháng trở lên: Tính lũy tiến từng phần
            $taxMethod = 'progressive';
            $personalDeduction = (float)$rules['personal_deduction'];
            $singleDependentDeduction = (float)$rules['dependent_deduction'];
            $familyDeduction = $personalDeduction + ($dependentsCount * $singleDependentDeduction);

            // Thu nhập tính thuế = Thu nhập chịu thuế - Giảm trừ gia cảnh - Các khoản đóng bảo hiểm
            $assessableIncome = max(0.0, $taxableIncome - $familyDeduction - $totalInsuranceEmployee);
            $pitAmount = self::calculateProgressivePit($assessableIncome, $rules['pit_brackets']);
        }

        // 8. Lương NET trước khấu trừ bổ sung
        $netSalary = max(0.0, $grossSalary - $totalInsuranceEmployee - $pitAmount);

        // 9. Khấu trừ bồi thường thiệt hại (Điều 102 BLLĐ: không vượt quá 30% tiền lương thực lĩnh tháng)
        $maxCompensationAllowed = round($netSalary * ($rules['compensation_max_ratio'] ?? 0.30));
        $compensationDeduction = min($pendingCompensation, $maxCompensationAllowed);

        if ($pendingCompensation > $maxCompensationAllowed) {
            $warnings[] = sprintf(
                'Khoản bồi thường còn lại (%s) đã được giới hạn khấu trừ ở mức tối đa 30%% lương thực lĩnh (%s) theo Điều 102 BLLĐ.',
                number_format($pendingCompensation, 0, ',', '.') . 'đ',
                number_format($maxCompensationAllowed, 0, ',', '.') . 'đ'
            );
        }

        // 10. THỰC LĨNH CUỐI CÙNG
        $finalSalary = max(0.0, $netSalary - $compensationDeduction - $otherDeduction);

        return [
            // Thông số đầu vào chuẩn hóa
            'base_salary'             => $baseSalary,
            'contract_allowance_base' => $contractAllowanceBase,
            'contract_type'           => $contractType,
            'wage_region'             => $wageRegion,
            'standard_days'           => $standardDays,
            'present_days'            => $presentDays,
            'paid_leave_days'         => $paidLeaveDays,
            'holiday_days'            => $holidayDays,
            'unpaid_days'             => $unpaidDays,
            'total_work_days'         => $totalWorkDays,
            'late_early_minutes'      => $lateEarlyMinutes,
            'late_deduction'          => $lateDeduction,

            // Thu nhập
            'hourly_rate'             => round($hourlyRate),
            'time_salary'             => $timeSalary,
            'contract_allowance'      => $contractAllowance,
            'other_allowance'         => $otherAllowance,
            'bonus'                   => $bonus,
            'ot_weekday_hours'        => $otWeekdayHours,
            'ot_weekend_hours'        => $otWeekendHours,
            'ot_holiday_hours'        => $otHolidayHours,
            'ot_pay'                  => $otPay,
            'ot_tax_exempt'           => $otTaxExempt,
            'gross_salary'            => $grossSalary,

            // Bảo hiểm
            'insurance_salary'        => $insuranceSalary,
            'is_exempt_insurance'     => $isExemptInsurance,
            'bhxh_employee'           => $bhxhEmployee,
            'bhyt_employee'           => $bhytEmployee,
            'bhtn_employee'           => $bhtnEmployee,
            'total_insurance_emp'     => $totalInsuranceEmployee,

            'bhxh_employer'           => $bhxhEmployer,
            'bhyt_employer'           => $bhytEmployer,
            'bhtn_employer'           => $bhtnEmployer,
            'kpcd_employer'           => $kpcdEmployer,
            'employer_insurance'      => $employerInsurance,

            // Thuế TNCN
            'tax_method'              => $taxMethod,
            'taxable_income'          => $taxableIncome,
            'dependents_count'        => $dependentsCount,
            'family_deduction'        => $familyDeduction,
            'assessable_income'       => $assessableIncome,
            'pit_amount'              => $pitAmount,

            // Khấu trừ & Thực lĩnh
            'net_salary'              => $netSalary,
            'compensation_deduction'  => $compensationDeduction,
            'other_deduction'         => $otherDeduction,
            'final_salary'            => $finalSalary,

            // Cảnh báo tuân thủ pháp luật
            'warnings'                => $warnings,
        ];
    }
}
