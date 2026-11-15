<?php
// core/DemoSyncService.php - Tự động đồng bộ & khôi phục dữ liệu trình diễn (Live Demo)

class DemoSyncService
{
    /**
     * Đảm bảo dữ liệu chấm công tháng hiện tại và hợp đồng sắp hết hạn luôn mới theo ngày hệ thống.
     * Chạy tối đa 1 lần/ngày trên mỗi phiên (trừ khi $force = true).
     */
    public static function ensureLiveDemoFreshness(PDO $pdo, bool $force = false): array
    {
        $today = date('Y-m-d');
        if (
            !$force
            && PHP_SAPI !== 'cli'
            && session_status() === PHP_SESSION_ACTIVE
            && ($_SESSION['demo_synced_date'] ?? '') === $today
        ) {
            return ['skipped' => true, 'attendance_inserted' => 0, 'contracts_adjusted' => 0];
        }

        $attendanceInserted = 0;
        $contractsAdjusted = 0;

        try {
            $attendanceInserted = self::syncCurrentMonthAttendance($pdo, $today);
            $contractsAdjusted = self::ensureExpiringContracts($pdo, $today);

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['demo_synced_date'] = $today;
            }
        } catch (Throwable $e) {
            if (function_exists('log_system_error')) {
                log_system_error('DemoSyncService::ensureLiveDemoFreshness error', $e);
            }
        }

        return [
            'skipped' => false,
            'attendance_inserted' => $attendanceInserted,
            'contracts_adjusted' => $contractsAdjusted,
        ];
    }

    /**
     * Khôi phục các trạng thái trình diễn trọng yếu về điểm khởi đầu sạch sẽ:
     * - Mở khóa bảng lương tháng hiện tại để trình diễn chốt/trả lương lại từ đầu
     * - Khôi phục kỳ KPI Q4/2026 ở trạng thái active nếu bị đóng hết
     * - Khôi phục đơn đề xuất / yêu cầu điều động chờ duyệt mẫu (không đảo ngược điều động đã thực thi)
     * - Đồng bộ lại chấm công tháng hiện tại & 5 hợp đồng sắp hết hạn trong 30 ngày
     */
    public static function resetDemoState(PDO $pdo): array
    {
        $summary = [
            'payrolls_unlocked' => 0,
            'proposals_restored' => 0,
            'transfers_restored' => 0,
            'kpi_period_restored' => false,
            'attendance_inserted' => 0,
            'contracts_adjusted' => 0,
        ];

        $month = (int)date('n');
        $year = (int)date('Y');

        // 1. Mở khóa bảng lương tháng hiện tại nếu đang khóa / đã chi trả
        $payStmt = $pdo->prepare("
            UPDATE payrolls
            SET locked_at = NULL, locked_by = NULL, payment_status = 'pending'
            WHERE month = ? AND year = ? AND (locked_at IS NOT NULL OR payment_status = 'paid')
        ");
        $payStmt->execute([$month, $year]);
        $summary['payrolls_unlocked'] = $payStmt->rowCount();

        // 2. Đảm bảo luôn có kỳ đánh giá KPI active (ưu tiên PERIOD-2026-Q4)
        $activePeriods = (int)$pdo->query("SELECT COUNT(*) FROM evaluation_periods WHERE status = 'active'")->fetchColumn();
        if ($activePeriods === 0) {
            $epStmt = $pdo->prepare("UPDATE evaluation_periods SET status = 'active' WHERE code = 'PERIOD-2026-Q4'");
            $epStmt->execute();
            $summary['kpi_period_restored'] = $epStmt->rowCount() > 0;
        }

        // 3. Đảm bảo có đủ đơn đề xuất mẫu ở trạng thái chờ duyệt
        $pendingProps = (int)$pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'pending'")->fetchColumn();
        if ($pendingProps < 6) {
            $propStmt = $pdo->exec("
                UPDATE proposals
                SET status = 'pending', approved_by = NULL, approved_at = NULL, rejection_reason = NULL
                WHERE proposal_code LIKE 'DX-DEMO-%'
                   OR proposal_code IN ('DX-2026-002', 'DX-2026-005', 'DX-2026-007', 'DX-2026-008')
            ");
            $summary['proposals_restored'] = (int)$propStmt;
        }

        // 4. Đảm bảo có yêu cầu điều động chờ duyệt mẫu (chỉ khôi phục bản ghi chưa executed)
        $pendingTransfers = (int)$pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'pending'")->fetchColumn();
        if ($pendingTransfers === 0) {
            $trStmt = $pdo->exec("
                UPDATE transfers
                SET status = 'pending', approved_by = NULL, approved_at = NULL
                WHERE id IN (3, 9, 13) AND status IN ('rejected', 'cancelled')
            ");
            $summary['transfers_restored'] = (int)$trStmt;
        }

        // 5. Đồng bộ lại chấm công tháng hiện tại & hợp đồng sắp hết hạn
        $fresh = self::ensureLiveDemoFreshness($pdo, true);
        $summary['attendance_inserted'] = (int)($fresh['attendance_inserted'] ?? 0);
        $summary['contracts_adjusted'] = (int)($fresh['contracts_adjusted'] ?? 0);

        return $summary;
    }

    /**
     * Bổ sung dữ liệu chấm công cho các ngày làm việc (Thứ 2 - Thứ 6) trong tháng hiện tại
     * và ngày hôm nay (kể cả cuối tuần nếu thuyết trình vào T7/CN) cho các nhân sự đang làm việc.
     */
    private static function syncCurrentMonthAttendance(PDO $pdo, string $today): int
    {
        $empIds = $pdo->query("SELECT id FROM employees WHERE employment_status != 'resigned' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($empIds)) {
            return 0;
        }

        $totalEmps = count($empIds);
        $monthStart = date('Y-m-01', strtotime($today));
        $targetDates = [];

        $cursor = strtotime($monthStart);
        $endTs = strtotime($today);
        while ($cursor <= $endTs) {
            $d = date('Y-m-d', $cursor);
            $dow = (int)date('N', $cursor); // 1=Mon .. 7=Sun
            if ($dow <= 5 || $d === $today) {
                $targetDates[] = $d;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        if (empty($targetDates)) {
            return 0;
        }

        // Kiểm tra nhanh những ngày đã có dữ liệu (>= 50% nhân sự) để bỏ qua
        $countStmt = $pdo->prepare("
            SELECT date, COUNT(*) AS cnt
            FROM attendance
            WHERE date BETWEEN ? AND ?
            GROUP BY date
        ");
        $countStmt->execute([$monthStart, $today]);
        $existingCounts = [];
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingCounts[$row['date']] = (int)$row['cnt'];
        }

        $datesToBackfill = [];
        $threshold = max(10, (int)floor($totalEmps * 0.5));
        foreach ($targetDates as $d) {
            if (($existingCounts[$d] ?? 0) < $threshold) {
                $datesToBackfill[] = $d;
            }
        }

        if (empty($datesToBackfill)) {
            return 0;
        }

        $inserted = 0;
        $insertSql = "
            INSERT IGNORE INTO attendance
                (employee_id, date, check_in, check_out, overtime_hours, late_minutes, early_minutes, status, note)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $startedTx = !$pdo->inTransaction();
        if ($startedTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare($insertSql);
            foreach ($datesToBackfill as $d) {
                $isToday = ($d === $today);
                foreach ($empIds as $empIdRaw) {
                    $empId = (int)$empIdRaw;
                    $roll = abs(crc32($empId . '|' . $d)) % 100;

                    if ($roll < 90) {
                        // Đúng giờ (~90%)
                        $status = 'present';
                        $min = ($empId * 3 + (int)date('d', strtotime($d))) % 18;
                        $checkIn = sprintf('07:%02d:00', 40 + $min);
                        $checkOut = $isToday ? null : (($roll % 5 === 0) ? '18:30:00' : '17:15:00');
                        $ot = (!$isToday && $roll % 5 === 0) ? 1.5 : 0.0;
                        $lateMin = 0;
                        $earlyMin = 0;
                        $note = $isToday ? 'Đồng bộ ca làm việc Live Demo' : null;
                    } elseif ($roll < 96) {
                        // Đi muộn (~6%)
                        $status = 'late';
                        $lateMin = 8 + ($roll % 15);
                        $checkIn = sprintf('08:%02d:00', $lateMin);
                        $checkOut = $isToday ? null : '17:30:00';
                        $ot = 0.0;
                        $earlyMin = 0;
                        $note = 'Đến muộn ' . $lateMin . ' phút';
                    } elseif ($roll < 98) {
                        // Nghỉ phép có đơn (~2%)
                        $status = 'leave_with_permit';
                        $checkIn = null;
                        $checkOut = null;
                        $ot = 0.0;
                        $lateMin = 0;
                        $earlyMin = 0;
                        $note = 'Nghỉ phép năm có duyệt';
                    } else {
                        // Vắng (~2%)
                        $status = 'absent';
                        $checkIn = null;
                        $checkOut = null;
                        $ot = 0.0;
                        $lateMin = 0;
                        $earlyMin = 0;
                        $note = 'Vắng chưa bổ sung giải trình';
                    }

                    $stmt->execute([$empId, $d, $checkIn, $checkOut, $ot, $lateMin, $earlyMin, $status, $note]);
                    $inserted += $stmt->rowCount();
                }
            }
            if ($startedTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $inserted;
    }

    /**
     * Đảm bảo luôn có 5 hợp đồng xác định thời hạn đang hiệu lực sắp hết hạn trong 30 ngày tới
     * (ưu tiên bao phủ Khối IT, Chi nhánh Miền Bắc và Trụ sở chính) để cảnh báo Dashboard luôn sống động.
     */
    private static function ensureExpiringContracts(PDO $pdo, string $today): int
    {
        $countStmt = $pdo->query("
            SELECT COUNT(*)
            FROM employee_contracts ec
            JOIN employees e ON ec.employee_id = e.id
            WHERE ec.status = 'active'
              AND e.employment_status != 'resigned'
              AND ec.end_date IS NOT NULL
              AND ec.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $currentExpiring = (int)$countStmt->fetchColumn();
        if ($currentExpiring >= 3) {
            return 0;
        }

        $need = 5 - $currentExpiring;
        $candidates = [];

        // Ưu tiên chọn rải đều: 1 phòng IT (dept 3), 2 Chi nhánh Miền Bắc (branch 2), và phần còn lại ở các đơn vị khác
        $queries = [
            "SELECT ec.id FROM employee_contracts ec JOIN employees e ON ec.employee_id = e.id
             WHERE ec.status = 'active' AND ec.contract_type = 'fixed_term' AND e.employment_status != 'resigned'
               AND e.department_id = 3 AND (ec.end_date IS NULL OR ec.end_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))
             ORDER BY ec.id ASC LIMIT 1",
            "SELECT ec.id FROM employee_contracts ec JOIN employees e ON ec.employee_id = e.id
             WHERE ec.status = 'active' AND ec.contract_type = 'fixed_term' AND e.employment_status != 'resigned'
               AND e.branch_id = 2 AND (ec.end_date IS NULL OR ec.end_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))
             ORDER BY ec.id ASC LIMIT 2",
            "SELECT ec.id FROM employee_contracts ec JOIN employees e ON ec.employee_id = e.id
             WHERE ec.status = 'active' AND ec.contract_type = 'fixed_term' AND e.employment_status != 'resigned'
               AND e.branch_id = 1 AND e.department_id != 3 AND (ec.end_date IS NULL OR ec.end_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))
             ORDER BY ec.id ASC LIMIT 3",
            "SELECT ec.id FROM employee_contracts ec JOIN employees e ON ec.employee_id = e.id
             WHERE ec.status = 'active' AND ec.contract_type = 'fixed_term' AND e.employment_status != 'resigned'
               AND (ec.end_date IS NULL OR ec.end_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))
             ORDER BY ec.id ASC LIMIT 5",
        ];

        foreach ($queries as $sql) {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $cid = (int)$cid;
                if (!in_array($cid, $candidates, true)) {
                    $candidates[] = $cid;
                    if (count($candidates) >= $need) {
                        break 2;
                    }
                }
            }
        }

        if (empty($candidates)) {
            return 0;
        }

        $offsets = [7, 11, 15, 20, 25];
        $updated = 0;
        $updStmt = $pdo->prepare("UPDATE employee_contracts SET end_date = ? WHERE id = ?");
        foreach ($candidates as $idx => $contractId) {
            $daysAhead = $offsets[$idx % count($offsets)];
            $newEndDate = date('Y-m-d', strtotime("+{$daysAhead} days", strtotime($today)));
            $updStmt->execute([$newEndDate, $contractId]);
            $updated += $updStmt->rowCount();
        }

        return $updated;
    }
}
