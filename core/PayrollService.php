<?php
// core/PayrollService.php - Dịch vụ tổng hợp dữ liệu và xử lý quy trình tính lương toàn doanh nghiệp

require_once __DIR__ . '/../config/payroll_rules.php';
require_once __DIR__ . '/PayrollCalculator.php';

class PayrollService {

    /**
     * Kiểm tra xem bảng lương của tháng/năm đã bị khóa (chốt số liệu) hay chưa
     */
    public static function isMonthLocked(PDO $pdo, $month, $year) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total_count,
                   COUNT(CASE WHEN locked_at IS NOT NULL THEN 1 END) AS locked_count
            FROM payrolls 
            WHERE month = ? AND year = ?
        ");
        $stmt->execute([(int)$month, (int)$year]);
        $row = $stmt->fetch();

        if (!$row || (int)$row['total_count'] === 0) {
            return false;
        }

        // Bảng lương được coi là đã khóa nếu tất cả hoặc có bản ghi đã chốt/thanh toán
        return (int)$row['locked_count'] > 0;
    }

    /**
     * Khóa toàn bộ bảng tính lương của tháng
     */
    public static function lockMonth(PDO $pdo, $month, $year, $userId = null) {
        $stmt = $pdo->prepare("
            UPDATE payrolls 
            SET locked_at = NOW(), locked_by = ? 
            WHERE month = ? AND year = ? AND locked_at IS NULL
        ");
        return $stmt->execute([$userId, (int)$month, (int)$year]);
    }

    /**
     * Mở khóa bảng tính lương (chỉ mở được nếu chưa có bản ghi nào đã thanh toán)
     */
    public static function unlockMonth(PDO $pdo, $month, $year) {
        // Kiểm tra xem đã có khoản lương nào được thanh toán hay chưa
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM payrolls WHERE month = ? AND year = ? AND payment_status = 'paid'");
        $checkStmt->execute([(int)$month, (int)$year]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            throw new Exception("Không thể mở khóa bảng lương vì đã có nhân viên nhận thanh toán lương.");
        }

        $stmt = $pdo->prepare("
            UPDATE payrolls 
            SET locked_at = NULL, locked_by = NULL 
            WHERE month = ? AND year = ?
        ");
        return $stmt->execute([(int)$month, (int)$year]);
    }

    /**
     * Tính toán hoặc cập nhật bảng lương tự động cho toàn bộ nhân sự trong tháng
     */
    public static function calculateMonth(PDO $pdo, $month, $year, $onlyEmployeeIds = null, $operatorUserId = null) {
        $month = (int)$month;
        $year  = (int)$year;

        // 1. Lấy bộ quy tắc pháp lý áp dụng cho kỳ
        $rules = payroll_rules_for($year, $month);

        // 2. Xác định khoảng thời gian của tháng
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        // 3. Lấy danh sách ngày lễ trong tháng
        $holStmt = $pdo->prepare("SELECT holiday_date FROM public_holidays WHERE holiday_date BETWEEN ? AND ?");
        $holStmt->execute([$startDate, $endDate]);
        $holidays = $holStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 4. Tính toán số ngày công chuẩn và ngày lễ trong tuần
        $stdInfo = PayrollCalculator::calculateStandardDays($year, $month, $holidays);
        $standardDays = (float)$stdInfo['standard_days'];
        $holidayWeekdays = $stdInfo['holiday_weekdays'];
        $holidayDaysCount = (float)count($holidayWeekdays);

        // 5. Lấy danh sách nhân viên đủ điều kiện tính lương
        // Điều kiện: Ngày vào làm <= ngày cuối tháng VÀ (Chưa thôi việc HOẶC có dữ liệu chấm công trong tháng)
        $empSql = "
            SELECT e.id, e.employee_code, e.fullname, e.hire_date, e.employment_status,
                   b.wage_region,
                   p.base_salary AS position_base_salary
            FROM employees e
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.hire_date <= ?
              AND (
                  e.employment_status != 'resigned'
                  OR EXISTS (
                      SELECT 1 FROM attendance a 
                      WHERE a.employee_id = e.id AND a.date BETWEEN ? AND ?
                  )
              )
        ";
        $params = [$endDate, $startDate, $endDate];
        if (!empty($onlyEmployeeIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($onlyEmployeeIds), '?'));
            $empSql .= " AND e.id IN ($inPlaceholders)";
            $params = array_merge($params, $onlyEmployeeIds);
        }

        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute($params);
        $employees = $empStmt->fetchAll();

        // 6. Tải trước các dữ liệu liên quan theo lô (Batch fetching)

        // a. Hợp đồng lao động hiệu lực mới nhất của từng nhân sự
        $contractsMap = [];
        $ctrStmt = $pdo->prepare("
            SELECT ec.*
            FROM employee_contracts ec
            INNER JOIN (
                SELECT employee_id, MAX(start_date) AS max_start
                FROM employee_contracts
                WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?)
                GROUP BY employee_id
            ) latest ON ec.employee_id = latest.employee_id AND ec.start_date = latest.max_start
        ");
        $ctrStmt->execute([$endDate, $startDate]);
        foreach ($ctrStmt->fetchAll() as $c) {
            $contractsMap[$c['employee_id']] = $c;
        }

        // b. Tổng hợp dữ liệu chấm công từ bảng attendance
        $attendanceMap = [];
        $attStmt = $pdo->prepare("
            SELECT 
                employee_id,
                COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_count,
                COUNT(CASE WHEN status = 'leave_with_permit' AND (leave_type IS NULL OR leave_type IN ('annual', 'personal')) THEN 1 END) AS paid_leave_count,
                COUNT(CASE WHEN status = 'leave_with_permit' AND leave_type = 'unpaid' THEN 1 END) AS unpaid_leave_count,
                COUNT(CASE WHEN status = 'leave_with_permit' AND leave_type IN ('sick', 'maternity') THEN 1 END) AS social_leave_count,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_count,
                SUM(COALESCE(late_minutes, 0) + COALESCE(early_minutes, 0)) AS total_late_early_minutes,
                COUNT(*) AS total_records
            FROM attendance
            WHERE date BETWEEN ? AND ?
            GROUP BY employee_id
        ");
        $attStmt->execute([$startDate, $endDate]);
        foreach ($attStmt->fetchAll() as $a) {
            $attendanceMap[$a['employee_id']] = $a;
        }

        // c. Tổng hợp làm thêm giờ OT từ attendance kèm kiểm tra đề xuất OT được duyệt
        $otMap = [];
        $otStmt = $pdo->prepare("
            SELECT 
                a.employee_id,
                a.date,
                a.overtime_hours,
                CASE 
                    WHEN p.id IS NOT NULL THEN 1 
                    ELSE 0 
                END AS is_approved
            FROM attendance a
            LEFT JOIN proposals p ON p.employee_id = a.employee_id 
                                 AND p.type = 'overtime' 
                                 AND p.status = 'approved'
                                 AND a.date BETWEEN p.start_date AND p.end_date
            WHERE a.date BETWEEN ? AND ? AND a.overtime_hours > 0
        ");
        $otStmt->execute([$startDate, $endDate]);
        foreach ($otStmt->fetchAll() as $otRow) {
            $empId = (int)$otRow['employee_id'];
            if (!isset($otMap[$empId])) {
                $otMap[$empId] = ['weekday' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'unapproved' => 0.0];
            }
            $otHours = (float)$otRow['overtime_hours'];
            $dateStr = $otRow['date'];
            $dayOfWeek = (int)date('N', strtotime($dateStr)); // 1-5 weekday, 6 T7, 7 CN

            if (in_array($dateStr, $holidays)) {
                $otMap[$empId]['holiday'] += $otHours;
            } elseif ($dayOfWeek >= 6) {
                $otMap[$empId]['weekend'] += $otHours;
            } else {
                $otMap[$empId]['weekday'] += $otHours;
            }

            if (!$otRow['is_approved']) {
                $otMap[$empId]['unapproved'] += $otHours;
            }
        }

        // d. Số người phụ thuộc giảm trừ gia cảnh
        $depStmt = $pdo->query("
            SELECT employee_id, COUNT(*) AS dep_count 
            FROM employee_dependents 
            WHERE tax_deductible = 1 
            GROUP BY employee_id
        ");
        $dependentsMap = $depStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // e. Tiền thưởng khen thưởng trong tháng
        $rewStmt = $pdo->prepare("
            SELECT employee_id, SUM(amount) AS total_bonus
            FROM rewards
            WHERE status IN ('approved', 'executed')
              AND reward_date BETWEEN ? AND ?
            GROUP BY employee_id
        ");
        $rewStmt->execute([$startDate, $endDate]);
        $bonusMap = $rewStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // f. Khoản bồi thường kỷ luật còn phải nợ
        $discStmt = $pdo->prepare("
            SELECT employee_id, SUM(penalty_amount) AS total_penalty
            FROM disciplines
            WHERE status IN ('approved', 'executed')
              AND penalty_amount > 0
              AND discipline_date <= ?
            GROUP BY employee_id
        ");
        $discStmt->execute([$endDate]);
        $disciplineMap = $discStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // g. Khoản bồi thường đã khấu trừ ở các kỳ trước
        $prevCompStmt = $pdo->prepare("
            SELECT employee_id, SUM(compensation_deduction) AS total_already_deducted
            FROM payrolls
            WHERE (year < ? OR (year = ? AND month < ?))
            GROUP BY employee_id
        ");
        $prevCompStmt->execute([$year, $year, $month]);
        $alreadyDeductedMap = $prevCompStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // h. Bản ghi payrolls hiện có của tháng (để giữ lại các khoản phụ cấp/giảm trừ sửa tay và không ghi đè nếu đã chốt)
        $existingPayrollStmt = $pdo->prepare("SELECT * FROM payrolls WHERE month = ? AND year = ?");
        $existingPayrollStmt->execute([$month, $year]);
        $existingPayrolls = [];
        foreach ($existingPayrollStmt->fetchAll() as $row) {
            $existingPayrolls[$row['employee_id']] = $row;
        }

        // 7. Câu lệnh UPSERT vào bảng payrolls
        $upsertStmt = $pdo->prepare("
            INSERT INTO payrolls (
                employee_id, month, year, contract_type,
                basic_salary, time_salary, work_days, standard_days,
                paid_leave_days, holiday_days, unpaid_days, late_early_minutes,
                contract_allowance, allowance, bonus,
                ot_weekday_hours, ot_weekend_hours, ot_holiday_hours, ot_pay, ot_tax_exempt,
                gross_salary, insurance_salary,
                bhxh_employee, bhyt_employee, bhtn_employee,
                dependents_count, family_deduction, assessable_income,
                tax_method, pit_amount,
                deduction, compensation_deduction,
                final_salary, employer_insurance, calc_warnings,
                payment_status, locked_at, locked_by
            ) VALUES (
                :emp_id, :month, :year, :contract_type,
                :basic_salary, :time_salary, :work_days, :standard_days,
                :paid_leave_days, :holiday_days, :unpaid_days, :late_early_minutes,
                :contract_allowance, :allowance, :bonus,
                :ot_weekday_hours, :ot_weekend_hours, :ot_holiday_hours, :ot_pay, :ot_tax_exempt,
                :gross_salary, :insurance_salary,
                :bhxh_employee, :bhyt_employee, :bhtn_employee,
                :dependents_count, :family_deduction, :assessable_income,
                :tax_method, :pit_amount,
                :deduction, :compensation_deduction,
                :final_salary, :employer_insurance, :calc_warnings,
                :payment_status, :locked_at, :locked_by
            )
            ON DUPLICATE KEY UPDATE
                contract_type         = VALUES(contract_type),
                basic_salary          = VALUES(basic_salary),
                time_salary           = VALUES(time_salary),
                work_days             = VALUES(work_days),
                standard_days         = VALUES(standard_days),
                paid_leave_days       = VALUES(paid_leave_days),
                holiday_days          = VALUES(holiday_days),
                unpaid_days           = VALUES(unpaid_days),
                late_early_minutes    = VALUES(late_early_minutes),
                contract_allowance    = VALUES(contract_allowance),
                allowance             = VALUES(allowance),
                bonus                 = VALUES(bonus),
                ot_weekday_hours      = VALUES(ot_weekday_hours),
                ot_weekend_hours      = VALUES(ot_weekend_hours),
                ot_holiday_hours      = VALUES(ot_holiday_hours),
                ot_pay                = VALUES(ot_pay),
                ot_tax_exempt         = VALUES(ot_tax_exempt),
                gross_salary          = VALUES(gross_salary),
                insurance_salary      = VALUES(insurance_salary),
                bhxh_employee         = VALUES(bhxh_employee),
                bhyt_employee         = VALUES(bhyt_employee),
                bhtn_employee         = VALUES(bhtn_employee),
                dependents_count      = VALUES(dependents_count),
                family_deduction      = VALUES(family_deduction),
                assessable_income     = VALUES(assessable_income),
                tax_method            = VALUES(tax_method),
                pit_amount            = VALUES(pit_amount),
                deduction             = VALUES(deduction),
                compensation_deduction= VALUES(compensation_deduction),
                final_salary          = VALUES(final_salary),
                employer_insurance    = VALUES(employer_insurance),
                calc_warnings         = VALUES(calc_warnings)
        ");

        $calculatedCount = 0;
        $skippedLockedCount = 0;

        foreach ($employees as $emp) {
            $empId = (int)$emp['id'];

            // Nếu bản ghi của nhân sự đã bị khóa hoặc đã thanh toán -> BỎ QUA không ghi đè
            if (isset($existingPayrolls[$empId])) {
                $ex = $existingPayrolls[$empId];
                if (!empty($ex['locked_at']) || $ex['payment_status'] === 'paid') {
                    $skippedLockedCount++;
                    continue;
                }
            }

            // a. Xác định thông số lương & hợp đồng
            $contract = $contractsMap[$empId] ?? null;
            if ($contract) {
                $baseSalary = (float)$contract['base_salary'];
                $contractAllowance = (float)$contract['allowance'];
                $contractType = $contract['contract_type'];
            } else {
                // Fallback nếu chưa có HĐ: lấy theo chức danh
                $baseSalary = (float)($emp['position_base_salary'] ?: 0);
                $contractAllowance = 0.0;
                $contractType = ($emp['employment_status'] === 'probation') ? 'probation' : 'fixed_term';
            }

            // b. Dữ liệu công thực tế
            $hasAttendance = isset($attendanceMap[$empId]);
            $att = $attendanceMap[$empId] ?? null;

            $presentDays = $att ? (float)$att['present_count'] : 0.0;
            $paidLeaveDays = $att ? (float)$att['paid_leave_count'] : 0.0;
            $unpaidDays = $att ? (float)($att['unpaid_leave_count'] + $att['social_leave_count'] + $att['absent_count']) : 0.0;
            $lateMinutes = $att ? (int)$att['total_late_early_minutes'] : 0;

            // Ngày lễ trong tuần được tự động cộng vào ngày công hưởng lương (Điều 112 BLLĐ)
            // nếu nhân sự làm việc trong thời gian đó (hire_date <= ngày lễ)
            $empHolidayDays = 0.0;
            foreach ($holidayWeekdays as $hwDate) {
                if ($emp['hire_date'] <= $hwDate) {
                    $empHolidayDays += 1.0;
                }
            }

            // c. Dữ liệu OT
            $ot = $otMap[$empId] ?? ['weekday' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'unapproved' => 0.0];

            // d. Các khoản phụ cấp / khấu trừ đã sửa tay ở bảng lương
            $otherAllowance = isset($existingPayrolls[$empId]) ? (float)$existingPayrolls[$empId]['allowance'] : 0.0;
            $otherDeduction = isset($existingPayrolls[$empId]) ? (float)$existingPayrolls[$empId]['deduction'] : 0.0;
            $paymentStatus  = isset($existingPayrolls[$empId]) ? $existingPayrolls[$empId]['payment_status'] : 'pending';

            // Tránh trừ đúp: Trong dữ liệu cũ, cột deduction được nạp bằng đúng 10.5% lương cứng để mô phỏng BHXH
            // Nay hệ thống đã tính tự động BHXH, BHYT, BHTN theo đúng luật và trần, nên khử trừ đúp khoản này.
            if ($otherDeduction > 0 && abs($otherDeduction - round($baseSalary * 0.105)) < 10000) {
                $otherDeduction = 0.0;
            }

            // e. Thưởng
            $bonus = (float)($bonusMap[$empId] ?? 0.0);

            // f. Nợ bồi thường
            $totalPenalty = (float)($disciplineMap[$empId] ?? 0.0);
            $alreadyDeducted = (float)($alreadyDeductedMap[$empId] ?? 0.0);
            $pendingCompensation = max(0.0, $totalPenalty - $alreadyDeducted);

            // g. Gọi Calculator
            $calcInput = [
                'base_salary'             => $baseSalary,
                'contract_allowance'      => $contractAllowance,
                'other_allowance'         => $otherAllowance,
                'bonus'                   => $bonus,
                'contract_type'           => $contractType,
                'wage_region'             => (int)($emp['wage_region'] ?: 1),
                'standard_days'           => $standardDays,
                'present_days'            => $presentDays,
                'paid_leave_days'         => $paidLeaveDays,
                'holiday_days'            => $empHolidayDays,
                'unpaid_days'             => $unpaidDays,
                'late_early_minutes'      => $lateMinutes,
                'ot_weekday_hours'        => $ot['weekday'],
                'ot_weekend_hours'        => $ot['weekend'],
                'ot_holiday_hours'        => $ot['holiday'],
                'dependents_count'        => (int)($dependentsMap[$empId] ?? 0),
                'pending_compensation'    => $pendingCompensation,
                'other_deduction'         => $otherDeduction,
                'has_attendance_records'  => $hasAttendance,
            ];

            $calcRes = PayrollCalculator::calculate($calcInput, $rules);

            if (!$contract) {
                $calcRes['warnings'][] = 'Nhân sự chưa có Hợp đồng lao động hiệu lực (đang tính tạm theo mức lương chức vụ).';
            }
            if ($ot['unapproved'] > 0) {
                $calcRes['warnings'][] = sprintf('Có %s giờ OT chưa có đề xuất tăng ca được phê duyệt.', number_format($ot['unapproved'], 1));
            }

            // h. Lưu vào Database
            $upsertStmt->execute([
                ':emp_id'                => $empId,
                ':month'                 => $month,
                ':year'                  => $year,
                ':contract_type'         => $calcRes['contract_type'],
                ':basic_salary'          => $calcRes['base_salary'],
                ':time_salary'           => $calcRes['time_salary'],
                ':work_days'             => $calcRes['total_work_days'],
                ':standard_days'         => $calcRes['standard_days'],
                ':paid_leave_days'       => $calcRes['paid_leave_days'],
                ':holiday_days'          => $calcRes['holiday_days'],
                ':unpaid_days'           => $calcRes['unpaid_days'],
                ':late_early_minutes'    => $calcRes['late_early_minutes'],
                ':contract_allowance'    => $calcRes['contract_allowance'],
                ':allowance'             => $calcRes['other_allowance'],
                ':bonus'                 => $calcRes['bonus'],
                ':ot_weekday_hours'      => $calcRes['ot_weekday_hours'],
                ':ot_weekend_hours'      => $calcRes['ot_weekend_hours'],
                ':ot_holiday_hours'      => $calcRes['ot_holiday_hours'],
                ':ot_pay'                => $calcRes['ot_pay'],
                ':ot_tax_exempt'         => $calcRes['ot_tax_exempt'],
                ':gross_salary'          => $calcRes['gross_salary'],
                ':insurance_salary'      => $calcRes['insurance_salary'],
                ':bhxh_employee'         => $calcRes['bhxh_employee'],
                ':bhyt_employee'         => $calcRes['bhyt_employee'],
                ':bhtn_employee'         => $calcRes['bhtn_employee'],
                ':dependents_count'      => $calcRes['dependents_count'],
                ':family_deduction'      => $calcRes['family_deduction'],
                ':assessable_income'     => $calcRes['assessable_income'],
                ':tax_method'            => $calcRes['tax_method'],
                ':pit_amount'            => $calcRes['pit_amount'],
                ':deduction'             => $calcRes['other_deduction'],
                ':compensation_deduction'=> $calcRes['compensation_deduction'],
                ':final_salary'          => $calcRes['final_salary'],
                ':employer_insurance'    => $calcRes['employer_insurance'],
                ':calc_warnings'         => !empty($calcRes['warnings']) ? json_encode($calcRes['warnings'], JSON_UNESCAPED_UNICODE) : null,
                ':payment_status'        => $paymentStatus,
                ':locked_at'             => null,
                ':locked_by'             => null,
            ]);

            $calculatedCount++;
        }

        return [
            'calculated_count'     => $calculatedCount,
            'skipped_locked_count' => $skippedLockedCount,
            'standard_days'        => $standardDays,
            'holiday_count'        => $holidayDaysCount,
        ];
    }

    /**
     * Tính toán lại một bản ghi lương duy nhất (sau khi HR điều chỉnh ngày công hoặc phụ cấp/khấu trừ tay)
     */
    public static function recalculateOne(PDO $pdo, $payrollId, array $overrides = []) {
        $stmt = $pdo->prepare("
            SELECT pr.*, e.hire_date, b.wage_region 
            FROM payrolls pr
            JOIN employees e ON pr.employee_id = e.id
            LEFT JOIN branches b ON e.branch_id = b.id
            WHERE pr.id = ?
        ");
        $stmt->execute([(int)$payrollId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Không tìm thấy bản ghi lương #{$payrollId}");
        }

        if (!empty($row['locked_at']) || $row['payment_status'] === 'paid') {
            throw new Exception("Bản ghi lương này đã được chốt số liệu hoặc đã thanh toán, không thể điều chỉnh.");
        }

        $rules = payroll_rules_for($row['year'], $row['month']);

        $workDays = isset($overrides['work_days']) ? (float)$overrides['work_days'] : (float)$row['work_days'];
        $otherAllowance = isset($overrides['allowance']) ? (float)$overrides['allowance'] : (float)$row['allowance'];
        $otherDeduction = isset($overrides['deduction']) ? (float)$overrides['deduction'] : (float)$row['deduction'];

        $calcInput = [
            'base_salary'             => (float)$row['basic_salary'],
            'contract_allowance'      => (float)$row['contract_allowance'],
            'other_allowance'         => $otherAllowance,
            'bonus'                   => (float)$row['bonus'],
            'contract_type'           => $row['contract_type'] ?: 'fixed_term',
            'wage_region'             => (int)($row['wage_region'] ?: 1),
            'standard_days'           => (float)($row['standard_days'] ?: 22.0),
            'present_days'            => $workDays,
            'paid_leave_days'         => 0.0,
            'holiday_days'            => 0.0,
            'unpaid_days'             => (float)$row['unpaid_days'],
            'late_early_minutes'      => (int)$row['late_early_minutes'],
            'ot_weekday_hours'        => (float)$row['ot_weekday_hours'],
            'ot_weekend_hours'        => (float)$row['ot_weekend_hours'],
            'ot_holiday_hours'        => (float)$row['ot_holiday_hours'],
            'dependents_count'        => (int)$row['dependents_count'],
            'pending_compensation'    => (float)$row['compensation_deduction'],
            'other_deduction'         => $otherDeduction,
            'has_attendance_records'  => true,
        ];

        $res = PayrollCalculator::calculate($calcInput, $rules);

        $upStmt = $pdo->prepare("
            UPDATE payrolls SET
                work_days              = ?,
                allowance              = ?,
                deduction              = ?,
                time_salary            = ?,
                gross_salary           = ?,
                insurance_salary       = ?,
                bhxh_employee          = ?,
                bhyt_employee          = ?,
                bhtn_employee          = ?,
                family_deduction       = ?,
                assessable_income      = ?,
                pit_amount             = ?,
                final_salary           = ?,
                employer_insurance     = ?,
                calc_warnings          = ?
            WHERE id = ?
        ");

        $upStmt->execute([
            $res['total_work_days'],
            $res['other_allowance'],
            $res['other_deduction'],
            $res['time_salary'],
            $res['gross_salary'],
            $res['insurance_salary'],
            $res['bhxh_employee'],
            $res['bhyt_employee'],
            $res['bhtn_employee'],
            $res['family_deduction'],
            $res['assessable_income'],
            $res['pit_amount'],
            $res['final_salary'],
            $res['employer_insurance'],
            !empty($res['warnings']) ? json_encode($res['warnings'], JSON_UNESCAPED_UNICODE) : null,
            $payrollId
        ]);

        return $res;
    }
}
