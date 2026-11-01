<?php
// core/LeaveService.php - Dịch vụ tính toán và quản lý quỹ phép năm tuân thủ BLLĐ 2019 (Điều 113)

class LeaveService {
    /**
     * Số ngày phép cơ bản trong năm cho điều kiện làm việc bình thường
     */
    const BASE_ANNUAL_LEAVE = 12.0;

    /**
     * Tính toán tổng số ngày phép năm được hưởng theo quy định
     * BLLĐ 2019 Điều 113 & 114:
     * - Làm đủ 12 tháng: 12 ngày phép cơ bản
     * - Cứ 05 năm làm việc tại doanh nghiệp: được cộng thêm 01 ngày phép thâm niên
     * - Làm chưa đủ 12 tháng trong năm: số ngày phép tính theo tỷ lệ số tháng làm việc
     *
     * @param array $employee Mảng thông tin nhân viên (chứa join_date hoặc start_date)
     * @param int|null $year Năm tính toán (mặc định năm hiện tại)
     * @return float
     */
    public static function getAnnualLeaveEntitlement(array $employee, ?int $year = null): float {
        $year = $year ?: (int)date('Y');
        $joinDateStr = $employee['join_date'] ?? $employee['start_date'] ?? null;

        if (!$joinDateStr) {
            return self::BASE_ANNUAL_LEAVE;
        }

        $joinDate = new DateTime($joinDateStr);
        $joinYear = (int)$joinDate->format('Y');
        $joinMonth = (int)$joinDate->format('n');
        $joinDay = (int)$joinDate->format('j');

        // Trường hợp nhân viên vào làm trong năm đang xét (chưa đủ 12 tháng)
        if ($joinYear === $year) {
            // Nếu vào sau ngày 15 của tháng thì tính từ tháng tiếp theo
            $effectiveMonths = (12 - $joinMonth + 1);
            if ($joinDay > 15 && $effectiveMonths > 0) {
                $effectiveMonths -= 1;
            }
            return max(0.0, (float)$effectiveMonths);
        }

        // Trường hợp nhân viên vào làm sau năm đang xét
        if ($joinYear > $year) {
            return 0.0;
        }

        // Tính thâm niên làm việc tính đến ngày 31/12 của năm đang xét
        $endOfYear = new DateTime("{$year}-12-31");
        $interval = $joinDate->diff($endOfYear);
        $yearsOfService = (int)$interval->y;

        // Cứ đủ 5 năm được cộng 1 ngày thâm niên
        $seniorityBonus = (float)floor($yearsOfService / 5);

        return self::BASE_ANNUAL_LEAVE + $seniorityBonus;
    }

    /**
     * Tính tổng số ngày phép năm đã sử dụng (đã duyệt) trong năm
     */
    public static function getUsedLeaveDays(PDO $pdo, int $employeeId, ?int $year = null): float {
        $year = $year ?: (int)date('Y');
        try {
            $stmt = $pdo->prepare("
                SELECT SUM(COALESCE(total_days, 1.0)) 
                FROM proposals 
                WHERE employee_id = ? 
                  AND type = 'leave' 
                  AND (leave_type = 'annual' OR leave_type IS NULL)
                  AND status = 'approved'
                  AND YEAR(COALESCE(start_date, created_at)) = ?
            ");
            $stmt->execute([$employeeId, $year]);
            return (float)($stmt->fetchColumn() ?: 0.0);
        } catch (PDOException $e) {
            return 0.0;
        }
    }

    /**
     * Tính tổng số ngày phép năm đang chờ phê duyệt
     */
    public static function getPendingLeaveDays(PDO $pdo, int $employeeId, ?int $year = null): float {
        $year = $year ?: (int)date('Y');
        try {
            $stmt = $pdo->prepare("
                SELECT SUM(COALESCE(total_days, 1.0)) 
                FROM proposals 
                WHERE employee_id = ? 
                  AND type = 'leave' 
                  AND (leave_type = 'annual' OR leave_type IS NULL)
                  AND status = 'pending'
                  AND YEAR(COALESCE(start_date, created_at)) = ?
            ");
            $stmt->execute([$employeeId, $year]);
            return (float)($stmt->fetchColumn() ?: 0.0);
        } catch (PDOException $e) {
            return 0.0;
        }
    }

    /**
     * Lấy bảng tổng hợp quỹ phép năm hoàn chỉnh của một nhân viên
     */
    public static function getLeaveSummary(PDO $pdo, int $employeeId, ?int $year = null): array {
        $year = $year ?: (int)date('Y');
        
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            return [
                'year' => $year,
                'entitled' => self::BASE_ANNUAL_LEAVE,
                'used' => 0.0,
                'pending' => 0.0,
                'remaining' => self::BASE_ANNUAL_LEAVE,
                'seniority_years' => 0
            ];
        }

        $entitled = self::getAnnualLeaveEntitlement($employee, $year);
        $used = self::getUsedLeaveDays($pdo, $employeeId, $year);
        $pending = self::getPendingLeaveDays($pdo, $employeeId, $year);
        $remaining = max(0.0, $entitled - $used);

        // Tính số năm thâm niên
        $joinDateStr = $employee['join_date'] ?? $employee['start_date'] ?? null;
        $seniorityYears = 0;
        if ($joinDateStr) {
            $joinDate = new DateTime($joinDateStr);
            $endOfYear = new DateTime("{$year}-12-31");
            $interval = $joinDate->diff($endOfYear);
            $seniorityYears = (int)$interval->y;
        }

        return [
            'year' => $year,
            'entitled' => $entitled,
            'used' => $used,
            'pending' => $pending,
            'remaining' => $remaining,
            'seniority_years' => $seniorityYears
        ];
    }
}

